# ScribuntoMediawikiApi Extension
[ScribuntoMediawikiApi](https://www.mediawiki.org/wiki/Extension:ScribuntoMediawikiApi) extension provides Lua modules with the ability to interact with both the [MediaWiki Action API](https://www.mediawiki.org/wiki/API:Action_API) and the [MediaWiki REST API](https://www.mediawiki.org/wiki/API:REST_API). This allows Lua scripts to query MediaWiki data programmatically — entirely via internal PHP routing, with no HTTP round-trips.

# Prerequisites
- MediaWiki 1.39 +
- Scribunto extension installed

# Installation
1. Download and install the ScribuntoMediawikiApi extension.
2. Add the following line to your `LocalSettings.php`:
   ```php
   wfLoadExtension( 'ScribuntoMediawikiApi' );
   ```
3. Ensure the Scribunto extension is installed and configured properly.

# Usage
This extension exposes Lua functions that allow interaction with the MediaWiki Action API. These functions can be used within Lua modules to perform various tasks, such as querying pages, fetching user information, or performing other API-supported actions.

## Example
1. Create a new Lua module on your MediaWiki with a content like:
   ```lua
   local p = {}
   
   function p.test()
     return mw.api.actionApiCall{
         action = 'query',
         meta = 'userinfo'
     }
   end
   
   return p
   ```
2. Try to call the main method in debug console:
   ```lua
   mw.logObject(p.test())
   ```
   ```lua
   table#1 {
       ["batchcomplete"] = true,
       ["query"] = table#2 {
           ["userinfo"] = table#3 {
               ["id"] = 1,
               ["name"] = "Niko",
           },
       },
   }
   ```

## REST API

`mw.api.restApiCall(path, queryParams)` dispatches a **read-only GET** request through MediaWiki's internal REST router (no network stack involved).

| Parameter | Type | Description |
|---|---|---|
| `path` | string | Path relative to the local REST root, e.g. `'/v1/search/title'` |
| `queryParams` | table (optional) | Key/value query-string parameters |

Returns the decoded JSON response as a Lua table, or `{ error = "..." }` on failure.

### Example
1. Create a Lua module:
   ```lua
   local p = {}

   function p.test()
       local r = mw.api.restApiCall('/v1/search/title', { q = 'a', limit = 5 })
       if r.error then return 'ERROR: ' .. r.error end
       return r
   end

   return p
   ```

2. Call it in the debug console:
   ```lua
   mw.logObject(p.test())
   ```
   ```lua
   table#1 {
       ["pages"] = table#2 {
           table#3 {
               ["excerpt"] = "Asdf",
               ["id"] = 243,
               ["key"] = "Asdf",
               ["title"] = "Asdf",
           },
       },
   }
   ```

# Useful recipes: a small wiki dashboard

Create **Module:WikiDashboard**, paste the complete Lua block below, then use the
invocations in the table. These examples query the **current wiki**; they do not
call Wikipedia or another remote site.

| Use case | Put this on a wiki page | What it does |
|---|---|---|
| Project reading list | `{{#invoke:WikiDashboard\|category\|category=Category:Tutorials}}` | Lists up to 100 pages, subcategories and files in a category, sorted by category sort key. Useful for a handbook or onboarding portal. |
| Impact check before a rewrite | `{{#invoke:WikiDashboard\|backlinks\|title=Installation}}` | Lists up to 100 pages linking directly to a page. Useful for finding documentation that may need updating. This is not a list of template transclusions or links through redirects. |
| Writing backlog for a guide | `{{#invoke:WikiDashboard\|redlinks\|title=Getting started}}` | Finds missing main-namespace pages among up to 100 distinct outgoing link targets. Useful for turning a long guide into a concrete writing checklist. |
| Find related documentation | `{{#invoke:WikiDashboard\|search\|query=Install}}` | Uses the REST title search route to return up to ten matching titles. This searches titles, not full page text. |

For `backlinks` and `redlinks`, omit `title` to inspect the page containing the
invocation. For `category`, include the category namespace prefix. Empty results,
API errors and truncated results produce a visible message. The category recipe
does not add the containing page to a category, and file results are links rather
than embedded images.

```lua
local p = {}

local function argument(frame, name)
    return mw.text.trim(frame.args[name] or '')
end

local function target(frame)
    local title = argument(frame, 'title')
    if title == '' then title = mw.title.getCurrentTitle().prefixedText end
    return title
end

local function message(text)
    return tostring(mw.html.create('p'):wikitext(mw.text.nowiki(text)))
end

-- These titles come from the wiki API. Escape labels and prefix targets
-- with a colon so category/file links do not categorize/embed content.
local function render(titles, incomplete, emptyMessage)
    local box = mw.html.create('div')
    if #titles == 0 then
        box:tag('p'):wikitext(mw.text.nowiki(emptyMessage))
    else
        local list = box:tag('ul')
        for _, title in ipairs(titles) do
            list:tag('li'):wikitext('[[:' .. title .. '|' .. mw.text.nowiki(title) .. ']]')
        end
    end
    if incomplete then
        box:tag('p'):wikitext('Partial results: the query limit was reached.')
    end
    return tostring(box)
end

-- At most two requests of 50 items each. Carry ALL continuation fields,
-- including the generic "continue" field, into the next request.
-- Action API page maps are traversed with pairs: numeric page IDs are not
-- reliable array indexes in this extension's PHP-to-Lua conversion.
local function collect(params, field, accept)
    local titles, seen = {}, {}
    for batch = 1, 2 do
        local response = mw.api.actionApiCall(params)
        if type(response) ~= 'table' or response.error then
            return nil, false, 'The wiki API could not complete this query.'
        end
        if response.warnings then
            return nil, false, 'The wiki API returned a warning; check the query parameters.'
        end
        local query = response.query or {}
        local iterate = field == 'pages' and pairs or ipairs
        for _, item in iterate(query[field] or {}) do
            if accept(item) and not seen[item.title] then
                seen[item.title] = true
                titles[#titles + 1] = item.title
            end
        end
        if not response.continue then return titles, false end
        if batch == 2 then return titles, true end
        for key, value in pairs(response.continue) do params[key] = value end
    end
end

local function hasTitle(item)
    return type(item) == 'table' and type(item.title) == 'string'
end

function p.category(frame)
    local category = argument(frame, 'category')
    local title = mw.title.new(category)
    if not title or title.namespace ~= 14 then
        return message('Provide a category title, for example category=Category:Tutorials.')
    end
    local titles, incomplete, err = collect({
        action = 'query', list = 'categorymembers',
        cmtitle = title.prefixedText, cmprop = 'title',
        cmsort = 'sortkey', cmdir = 'asc', cmlimit = 50
    }, 'categorymembers', hasTitle)
    if err then return message(err) end
    return render(titles, incomplete, 'No members found in this category.')
end

function p.backlinks(frame)
    local titles, incomplete, err = collect({
        action = 'query', list = 'backlinks', bltitle = target(frame),
        blfilterredir = 'nonredirects', bllimit = 50
    }, 'backlinks', hasTitle)
    if err then return message(err) end
    table.sort(titles)
    return render(titles, incomplete, 'No direct backlinks found.')
end

function p.redlinks(frame)
    local titles, incomplete, err = collect({
        action = 'query', generator = 'links', titles = target(frame),
        gplnamespace = 0, gpllimit = 50, prop = 'info'
    }, 'pages', function(item)
        -- Default Action API output can represent a missing flag as "".
        return hasTitle(item) and item.missing ~= nil
    end)
    if err then return message(err) end
    table.sort(titles)
    return render(titles, incomplete, 'No missing targets found in the examined links.')
end

function p.search(frame)
    local query = argument(frame, 'query')
    if query == '' then return message('Provide query= followed by a title search.') end
    local response = mw.api.restApiCall('/v1/search/title', { q = query, limit = 10 })
    if type(response) ~= 'table' or response.error then
        return message('Title search is unavailable on this wiki.')
    end
    local titles = {}
    for _, page in ipairs(response.pages or {}) do
        if hasTitle(page) then titles[#titles + 1] = page.title end
    end
    return render(titles, #titles == 10, 'No matching titles found.')
end

return p
```

## Limits and freshness

- These are read-only display recipes. API calls run with the current request's
  user context. Do not put user-specific or private API results into public page
  output: the extension does not make parser caches vary by user. Use the debug
  console for experiments such as the `userinfo` example above.
- Category membership, backlinks, outgoing links and search results can change
  without invalidating the page containing this module. Normal parser caching
  still applies; purge the dashboard when you need a fresh result. This is not a
  live monitoring feed or a substitute for an exhaustive maintenance report.
- Each Action API recipe makes at most two calls and examines at most 100 items;
  the REST recipe makes one call. The redlink limit counts **all examined link
  targets**, including existing pages. A partial result can therefore contain
  fewer than 100 missing pages, or none. Search reports a possible truncation
  when it fills its ten-result limit.
- The examples deliberately avoid unlimited continuation loops. Keep the number
  of invocations per page small, especially on busy wikis.
- REST routes depend on the installed MediaWiki version and extensions. The
  examples use the local core `/v1/search/title` route; RESTBase routes such as
  `/page/summary` are not automatically available through this extension.

## Reproducing the recipe checks

`tests/readme-recipes.php` extracts the Lua module directly from this README and
runs it through Scribunto and the real local APIs. It creates about 110 fixture
pages per run, so use a **disposable wiki only**, with an existing `Admin` user
able to save pages, and both Scribunto and this extension enabled:

```sh
MW_INSTALL_PATH=/path/to/disposable/wiki php tests/readme-recipes.php --disposable-wiki
```

The checks cover multi-batch queries, result limits, empty results, escaped
labels, exclusion of existing pages from the writing backlog, the default page
title and REST title search. The recipes were checked on MediaWiki 1.43.1 with
LuaStandalone; this is not a compatibility certification for every supported
MediaWiki release.
