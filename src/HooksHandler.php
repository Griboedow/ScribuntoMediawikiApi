<?php

namespace MediaWiki\Extension\MwApiForLua;

class HooksHandler
{

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ScribuntoExternalLibraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ): bool
	{
		if ( $engine == 'lua' ) {
			$extraLibraries['mw.api'] = LuaMwApiLibrary::class;
		}

		return true;
	}
}
