<?php

/**
 * Class TestDocManagerTable
 *
 * @package PumaStudios-DocManager
 */
use PumaStudios\DocManager\DocManagerTable;
use PumaStudios\DocManager\DocumentAdmin;

/**
 * Test support for list of Documents on admin page
 *
 * @group admin
 */
class TestDocManagerTable extends WP_UnitTestCase {

	/**
	 * Setup for entire class of tests
	 *
	 * @param Factor $factory Factory class used to create objects
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		/**
		 * Setup factory for Documents
		 */
		$factory->document = new \WP_UnitTest_Factory_For_Document( $factory );
	}

	/**
	 * Per test setup
	 */
	function setUp() {
		/**
		 * Run as admin role
		 */
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		parent::setUp();
	}

	/**
	 * Per test teardown
	 */
	function tearDown() {
		/**
		 * Remove document attachments before rolling back the DB
		 */
		$this->factory->document->destroy();

		/**
		 * Turn off admin mode
		 */
		set_current_screen( 'front' );

		/**
		 * Rollback WP environment
		 */
		parent::tearDown();
	}

	function get_table() {
		$screen	 = get_plugin_page_hookname( DocumentAdmin::DOCUMENT_MENU_SLUG, '' );
		$table	 = new DocManagerTable( [
			'singular'	 => 'singular name',
			'plural'	 => 'plural name', // Plural label
			'ajax'		 => false, // Will not support AJAX on this table
			'upload_url' => 'upload_url',
			'table_url'	 => 'table_url',
			'screen'	 => $screen
			] );

		return $table;
	}

	/**
	 * get file contents
	 *
	 * @param string $file
	 */
	private function get_file_contents( string $file ) {
		$handle		 = fopen( $file, "r" );
		$expected	 = '';
		while ( ( $line		 = fgets( $handle )) !== false ) {
			$expected .= trim( $line );
		}
		fclose( $handle );

		return $expected;
	}

	/**
	 * Wrapper for assertStringMatchesFormat to use file as input
	 *
	 * Removes leading and trailing whitespace and collapses input file to a single line to match style of expected output
	 *
	 * @param string $file File containing expected HTML in easy to read format
	 * @param string $result Result to compare against
	 * @param string $msg Assert message
	 */
	private function _assertStringMatchesFormatFile( string $file, string $result, string $msg ) {
		$expected = $this->get_file_contents( $file );
		$this->assertStringMatchesFormat( $expected, $result, $msg );
	}

	private function _expectOutputRegexFile( string $file, string $msg ) {
		$expected = $this->get_file_contents( $file );
		$this->expectOutputRegex( $expected, $msg );
	}

	function test_instantiate() {
		$table = $this->get_table();
		$this->assertTrue( $table instanceof DocManagerTable );
	}

	/**
	 * Two ways to request delete all
	 */
	function test_current_action_delete_all() {
		$_REQUEST['delete_all'] = 1;

		$table = $this->get_table();
		$this->assertEquals( 'delete_all', $table->current_action(), 'Delete all requested' );

		unset( $_REQUEST['delete_all'] );

		$_REQUEST['delete_all2'] = 1;

		$table = $this->get_table();
		$this->assertEquals( 'delete_all', $table->current_action(), 'Delete all requested' );

		unset( $_REQUEST['delete_all2'] );
	}

	function test_current_action() {
		$table = $this->get_table();

		$this->assertEquals( '', $table->current_action(), 'No action requested' );

		$_REQUEST['action'] = 'edit';
		$action = $table->current_action();
		unset( $_REQUEST['action']);

		$this->assertEquals( 'edit', $action, 'Edit action requested' );
	}

	/**
	 * Confirm expected columns will be available
	 */
	function test_get_columns() {
		$doc_table = $this->get_table();

		$expected_keys = [
			'cb',
			'doc_id',
			'title',
			'download_cnt',
			'shortcode',
			'download_shortcode',
			'download_url',
			'date_modified',
			'type',
			'rows',
			'columns',
			'csv_storage_format', // Debug is set
			'doc_uuid' // Debug is set
		];


		// In Debug mode there will be additional columns
		$columns = $doc_table->get_columns();
		$this->assertEqualSets( $expected_keys, array_keys( $columns ), 'Document table columns' );
	}

	/**
	 * No documents available
	 */
	function test_prepare_items_empty() {
		$table = $this->get_table();

		$table->prepare_items();

		$this->assertEquals( 0, count( $table->items ), "No documents available for list" );
		$this->assertEquals( 1, $table->get_pagenum(), "Current Page" );
		$this->assertEquals( 0, $table->get_pagination_arg( 'total_pages' ), 'No documents => 0 pages' );
		$this->assertEquals( 0, $table->get_pagination_arg( 'total_items' ), 'No documents, total_items == 0' );
		$this->assertEquals( 20, $table->get_pagination_arg( 'per_page' ), 'Default of 20 items per page' );
	}

	/**
	 * Test prepare works correctly with a valid document list
	 */
	function test_prepare_items() {
		$ids = $this->factory->document->create_many( 5, [ 'target_file' => __DIR__ . '/samples/simple.csv' ] );

		$table = $this->get_table();

		$table->prepare_items();

		$this->assertEquals( 5, count( $table->items ), 'Have documents to list' );

		$this->assertEquals( 1, $table->get_pagenum(), "Current Page" );
		$this->assertEquals( 1, $table->get_pagination_arg( 'total_pages' ), '5 documents => 1 page' );
		$this->assertEquals( 5, $table->get_pagination_arg( 'total_items' ), 'Documents on list, total_items == 5' );
		$this->assertEquals( 20, $table->get_pagination_arg( 'per_page' ), 'Default of 20 items per page' );
	}

	function test_display_row() {
		$expected_file	 = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';
		$ids			 = $this->factory->document->create_many( 5, [ 'target_file' => __DIR__ . '/samples/simple.csv' ] );

		$table = $this->get_table();

		$table->prepare_items();

		$item = $table->items[0];

		$this->_expectOutputRegexFile( $expected_file, 'Row Data in expected format' );
		$table->single_row( $item );
	}

	function test_display_items() {
		$ids = $this->factory->document->create_many( 5, [ 'target_file' => __DIR__ . '/samples/simple.csv' ] );

		$table = $this->get_table();

		$table->prepare_items();

		self::markTestIncomplete();
		$table->display();
	}
}
