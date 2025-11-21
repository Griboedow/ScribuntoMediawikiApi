local api = {}
local php

function api.actionApiCall( params )
	local result = php.actionApiCall( params )
	if result == nil then return nil end
	return result
end

function api.setupInterface()
	-- Interface setup
	api.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register library within the "mw.api" namespace
	mw = mw or {}
	mw.api = api

	package.loaded['mw.api'] = api
end

return api
