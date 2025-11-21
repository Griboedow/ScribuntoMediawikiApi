<?php

namespace MediaWiki\Extension\ScribuntoMediawikiApi;

class HooksHandler
{

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ScribuntoExternalLibraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ): bool
	{
		if ( $engine == 'lua' ) {
			$extraLibraries['mw.api'] = ScribuntoMediawikiApiLibrary::class;
		}

		return true;
	}
}
