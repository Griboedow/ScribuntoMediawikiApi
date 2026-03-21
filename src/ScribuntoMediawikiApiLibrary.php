<?php
namespace MediaWiki\Extension\ScribuntoMediawikiApi;

use GuzzleHttp\Psr7\Uri;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\MainConfigSchema;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\CorsUtils;
use MediaWiki\Rest\EntryPoint;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseFactory;
use Scribunto_LuaLibraryBase;


class ScribuntoMediawikiApiLibrary extends Scribunto_LuaLibraryBase {


	const API_ERROR_FIELD = 'error';

	public function register() {

		$lib = [
			'actionApiCall' => [ $this, 'actionApiCall' ],
			'restApiCall'   => [ $this, 'restApiCall' ],
		];

		$this->getEngine()->registerInterface( __DIR__ . '/' . 'mw.api.lua', $lib, [] );
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

	/**
	 * Make a read-only REST API call using MediaWiki's internal routing
	 * (no HTTP round-trip).
	 *
	 * @param string|null $path  REST path relative to the REST root, e.g. "/v1/page/Main_Page"
	 * @param array|null  $queryParams  Optional associative array of query-string parameters
	 * @return array Single-element array containing the decoded response as a Lua table,
	 *               or ['error' => '...'] on failure.
	 */
	public function restApiCall( $path = null, $queryParams = null ) {
		if ( !is_string( $path ) || $path === '' ) {
			return [ $this->convertArrayToLuaTable(
				[ self::API_ERROR_FIELD => 'restApiCall: path must be a non-empty string' ]
			) ];
		}

		// Normalise query params: Scribunto passes Lua tables as 1-indexed PHP arrays,
		// so an associative Lua table arrives as a plain PHP associative array — that's fine.
		if ( !is_array( $queryParams ) ) {
			$queryParams = [];
		}

		$context = \RequestContext::getMain();
		$services = MediaWikiServices::getInstance();
		$conf     = $services->getMainConfig();

		// Build the full URI path that the Router expects (it strips the REST prefix internally).
		$scriptPath = $conf->get( MainConfigNames::ScriptPath );
		$restPrefix = MainConfigSchema::getDefaultRestPath( $scriptPath );
		$uriString  = $restPrefix . $path;
		if ( $queryParams ) {
			$uriString .= '?' . http_build_query( $queryParams );
		}

		$requestData = new RequestData( [
			'method'      => 'GET',
			'uri'         => new Uri( $uriString ),
			'queryParams' => $queryParams,
		] );

		// Build the same helpers that EntryPoint::doSetup() would build.
		$code          = $services->getContentLanguageCode()->toString();
		$langs         = array_unique( [ $code, 'en' ] );
		$textFormatters = [];
		$msgFactory    = $services->getMessageFormatterFactory();
		foreach ( $langs as $lang ) {
			$textFormatters[] = $msgFactory->getTextFormatter( $lang );
		}

		$responseFactory = new ResponseFactory( $textFormatters );

		$cors = new CorsUtils(
			new ServiceOptions( CorsUtils::CONSTRUCTOR_OPTIONS, $conf ),
			$responseFactory,
			$context->getUser()
		);

		try {
			$router   = EntryPoint::createRouter( $services, $context, $requestData, $responseFactory, $cors );
			$response = $router->execute( $requestData );

			$statusCode = $response->getStatusCode();
			$body       = $response->getBody();
			$body->rewind();
			$decoded = json_decode( $body->getContents(), true );

			if ( $decoded === null ) {
				return [ $this->convertArrayToLuaTable(
					[ self::API_ERROR_FIELD => 'restApiCall: failed to decode response body' ]
				) ];
			}

			// Surface HTTP error status through the conventional 'error' key.
			if ( $statusCode >= 400 && !isset( $decoded[self::API_ERROR_FIELD] ) ) {
				$decoded[self::API_ERROR_FIELD] = $decoded['message'] ?? ( 'HTTP ' . $statusCode );
			}

			return [ $this->convertArrayToLuaTable( $decoded ) ];
		} catch ( \Exception $e ) {
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

