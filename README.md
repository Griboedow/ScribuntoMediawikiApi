# ScribuntoMediawikiApi Extension
The ScribuntoMediawikiApi extension provides Lua modules with the ability to interact with the MediaWiki Action API. This allows Lua scripts to query and manipulate MediaWiki data programmatically.

# Prerequisites
- MediaWiki 1.39 +
- Scribunto extension must be installed and enabled

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
1. Create a new Lua module in your MediaWiki instance.
2. Use the `mw.actionapi` library to interact with the Action API. For example:
   ```lua
   local result = mw.api.actionApiCall({
       action = 'query',
       list = 'allpages',
       aplimit = 10
   })

   return result
   ```
3. Save the module and use it

