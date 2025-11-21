<?php
namespace MediaWiki\Extension\MwApiForLua;

use Scribunto_LuaLibraryBase;
use RequestContext;


class LuaMwApiLibrary extends Scribunto_LuaLibraryBase {


	const API_ERROR_FIELD = 'error';

	public function register() {

		$lib = [
			'actionApiCall'  => [ $this, 'actionApiCall' ]
		];

		$this->getEngine()->registerInterface( __DIR__ . '/' . 'mw.actionapi.lua', $lib, [] );
	}

	public function actionApiCall( $arguments = null ) {
        // Initialize ApiMain for making Action API calls
        $context = \RequestContext::getMain();
        $session = $context->getRequest()->getSession();

        $fauxRequest = new \FauxRequest( $arguments, true, $session );
        $fauxRequest->setSessionId( $session->getSessionId() );

        $fauxContext = new \RequestContext();
        $fauxContext->setRequest( $fauxRequest );
        $fauxContext->setUser( $context->getUser() );
        $fauxContext->setLanguage( $context->getLanguage() );

        $apiMain = new \ApiMain( $fauxContext, true );

        try {
            $apiMain->execute();
            $result = $apiMain->getResult()->getResultData( null, [ 'Strip' => 'all' ] );

            // Convert the result to a Lua-compatible table
            return [ $this->convertArrayToLuaTable( $result ) ];
        } catch ( \Exception $e ) {
            // Handle exceptions and return an error message
            return [ $this->convertArrayToLuaTable( [ self::API_ERROR_FIELD => $e->getMessage() ] ) ];
        }
    }

	private function convertArrayToLuaTable( $ar ) {

		if ( is_array( $ar) ) {
			foreach ( $ar as $key => $value ) {
				$ar[$key] = $this->convertArrayToLuaTable( $value );
			}
			array_unshift( $ar, '' );
			unset( $ar[0] );
		}
		return $ar;
	}
}

