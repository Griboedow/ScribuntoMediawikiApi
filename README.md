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
| `path` | string | Path relative to the REST root, e.g. `'/v1/page/Main_Page/summary'` |
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

