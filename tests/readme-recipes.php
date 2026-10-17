<?php
/** Run ONLY on a disposable wiki: creates about 110 fixture pages. */
require_once ( getenv( 'MW_INSTALL_PATH' ) ?: dirname( __DIR__, 3 ) ) . '/maintenance/Maintenance.php';

class ReadmeRecipesTest extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addOption( 'disposable-wiki', 'Acknowledge that this creates test pages', false, false );
    }

    private function check( $condition, $label ) {
        if ( !$condition ) {
            throw new RuntimeException( 'FAIL: ' . $label );
        }
        $this->output( "PASS: $label\n" );
    }

    public function execute() {
        if ( !$this->hasOption( 'disposable-wiki' ) ) {
            $this->fatalError( 'Use --disposable-wiki on a disposable installation only.' );
        }
        $services = MediaWiki\MediaWikiServices::getInstance();
        $user = $services->getUserFactory()->newFromName( 'Admin' );
        RequestContext::getMain()->setUser( $user );
        $prefix = 'RecipeTest' . bin2hex( random_bytes( 4 ) );
        $save = function ( $name, $text, $model = CONTENT_MODEL_WIKITEXT ) use ( $services, $user ) {
            $title = MediaWiki\Title\Title::newFromText( $name );
            $page = $services->getWikiPageFactory()->newFromTitle( $title );
            $status = $page->doUserEditContent(
                ContentHandler::makeContent( $text, $title, $model ), $user, 'README recipe fixture'
            );
            if ( !$status->isOK() ) {
                throw new RuntimeException( 'Could not save fixture: ' . $name );
            }
            DeferredUpdates::doUpdates();
        };
        $readme = file_get_contents( __DIR__ . '/../README.md' );
        preg_match( '/^```lua\R(.*?)^```/ms', $readme, $match );
        $this->check( isset( $match[1] ), 'extract exact README module' );
        $module = $prefix . 'Dashboard';
        $save( 'Module:' . $module, $match[1], 'Scribunto' );
        $save( "$prefix Target", 'Existing target' );
        for ( $i = 1; $i <= 105; $i++ ) {
            $save( sprintf( '%s Member %03d', $prefix, $i ),
                "[[$prefix Target]][[Category:$prefix Category]]" );
        }
        $links = "[[$prefix Target]]";
        for ( $i = 1; $i <= 105; $i++ ) {
            $links .= sprintf( '[[%s Missing %03d]]', $prefix, $i );
        }
        $save( "$prefix Guide", $links );
        $save( "$prefix Short guide", "[[$prefix Target]][[$prefix Missing 001]]" );
        $save( "$prefix A & B", "[[Category:$prefix Small]]" );
        $save( "Category:$prefix Subcategory", "[[Category:$prefix Special]]" );
        $save( "File:$prefix Example.png", "[[Category:$prefix Special]]" );
        $parse = function ( $function, $args = '', $pageName = null ) use ( $services, $module, $prefix ) {
            $parser = $services->getParserFactory()->create();
            $result = $parser->parse(
                '{{#invoke:' . $module . '|' . $function . $args . '}}',
                MediaWiki\Title\Title::newFromText( $pageName ?: "$prefix Dashboard" ),
                ParserOptions::newFromAnon()
            )->getText();
            if ( strpos( $result, 'scribunto-error' ) !== false ) {
                throw new RuntimeException( $result );
            }
            return $result;
        };
        foreach ( [ 'category' => "|category=Category:$prefix Category",
            'backlinks' => "|title=$prefix Target", 'redlinks' => "|title=$prefix Guide" ] as $fn => $args ) {
            $html = $parse( $fn, $args );
            $this->check( substr_count( $html, '<li>' ) === 100, "$fn: two batches / 100 results" );
            $this->check( strpos( $html, 'Partial results:' ) !== false, "$fn: truncation disclosed" );
            if ( $fn === 'category' ) {
                $this->check( strpos( $html, 'Member 001</a>' ) < strpos( $html, 'Member 100</a>' ),
                    'category order preserved across batches' );
            }
            if ( $fn === 'redlinks' ) {
                $this->check( strpos( $html, "$prefix Target</a>" ) === false, 'existing targets excluded' );
            }
        }
        $html = $parse( 'category', "|category=Category:$prefix Small" );
        $this->check( substr_count( $html, '<li>' ) === 1 && strpos( $html, 'A &amp; B' ) !== false,
            'small category and escaped title' );
        $this->check( strpos( $html, 'Partial results:' ) === false, 'complete result has no truncation notice' );
        $html = $parse( 'redlinks', "|title=$prefix Short guide" );
        $this->check( substr_count( $html, '<li>' ) === 1 && strpos( $html, 'Missing 001</a>' ) !== false,
            'mixed existing and missing targets' );
        $html = $parse( 'category', "|category=Category:$prefix Special" );
        $this->check( substr_count( $html, '<li>' ) === 2 && strpos( $html, '<img' ) === false &&
            strpos( $html, "Category:$prefix Subcategory</a>" ) !== false,
            'category and file results are ordinary links' );
        $this->check( strpos( $parse( 'backlinks', '|title=[' ), 'could not complete this query' ) !== false,
            'API errors render a message' );
        $this->check( strpos( $parse( 'category' ), 'Provide a category title' ) !== false, 'category validation' );
        $this->check( strpos( $parse( 'category', "|category=Category:$prefix Empty" ), 'No members found' ) !== false,
            'empty category' );
        $this->check( strpos( $parse( 'backlinks', "|title=$prefix Absent" ), 'No direct backlinks found' ) !== false,
            'empty backlinks' );
        $this->check( strpos( $parse( 'redlinks', "|title=$prefix Target" ), 'No missing targets found' ) !== false,
            'no missing targets' );
        $this->check( substr_count( $parse( 'redlinks', '', "$prefix Guide" ), '<li>' ) === 100,
            'default title is containing page' );
        $this->check( strpos( $parse( 'search' ), 'followed by a title search' ) !== false, 'search validation' );
        $html = $parse( 'search', "|query=$prefix" );
        $this->check( substr_count( $html, '<li>' ) === 10, 'real REST title search' );
        $this->check( strpos( $html, 'Partial results:' ) !== false, 'search result cap disclosed' );
        $this->check( strpos( $parse( 'search', "|query={$prefix}DoesNotExist" ), 'No matching titles' ) !== false,
            'empty REST result' );
    }
}
$maintClass = ReadmeRecipesTest::class;
require_once RUN_MAINTENANCE_IF_MAIN;
