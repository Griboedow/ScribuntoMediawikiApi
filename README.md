# ScribuntoMediawikiApi Extension
[ScribuntoMediawikiApi](https://www.mediawiki.org/wiki/Extension:ScribuntoMediawikiApi) extension provides Lua modules with the ability to interact with the [MediaWiki Action API](https://www.mediawiki.org/wiki/API:Action_API). This allows Lua scripts to query and manipulate MediaWiki data programmatically.

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
