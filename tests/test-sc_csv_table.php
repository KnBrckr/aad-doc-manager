<?php

/**
 * Class SCCSVTableTest
 *
 * @package Aad_Doc_Manager
 */
use PumaStudios\DocManager\Document;
use PumaStudios\DocManager\SCCSVTable;

/**
 * Test shortcode to display a CSV table
 *
 * @group shortcode
 */
class TestSCCSVTable extends WP_UnitTestCase {

	/**
	 * Setup for entire class
	 *
	 * @param Factor $factory Factory class used to create objects
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		/**
		 * Setup factory for Documents
		 */
		$factory->document = new \WP_UnitTest_Factory_For_Document( $factory );
	}

	function tearDown() {
		/**
		 * Remove document attachments before rolling back the DB
		 */
		$this->factory->document->destroy();

		/**
		 * Rollback WP environment
		 */
		parent::tearDown();
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
		$handle		 = fopen( $file, "r" );
		$expected	 = '';
		while ( ( $line		 = fgets( $handle )) !== false ) {
			$expected .= trim( $line );
		}
		fclose( $handle );

		$this->assertStringMatchesFormat( $expected, $result, $msg );
	}

	/**
	 * Test shortcodes are setup
	 *
	 * @param string $sc Shortcode name
	 *
	 * @testWith [ "csvview" ]
	 *           [ "docmgr-csv-table" ]
	 */
	function test_shortcodes_exist( $sc ) {
		$this->assertTrue( shortcode_exists( $sc ), "Shortcode $sc exists" );
	}

	/**
	 * Test actions exist
	 *
	 * @param string $action action name
	 * @param array $func function
	 *
	 * @testWith [ "wp_enqueue_scripts", [ "PumaStudios\\DocManager\\SCCSVTable", "action_enqueue_datatables" ] ]
	 *           [ "wp_footer", [ "PumaStudios\\DocManager\\SCCSVTable", "action_init_datatables" ] ]
	 */
	function test_actions_exist( $action, $func ) {
		$this->assertEquals( 10, has_action( $action, $func ), "Hooked to action $action" );
	}

	/**
	 * Test enqueuing of datables JS script
	 */
	function test_action_enqueue_datatables() {
		SCCSVTable::action_enqueue_datatables();

		$this->assertTrue( wp_script_is( "aad-doc-manager-datatable-js", 'registered' ), "script aad-doc-manager-mark-js is registered" );
		$this->assertTrue( wp_script_is( "aad-doc-manager-mark-js", 'enqueued' ), "script aad-doc-manager-mark-js is enqueued" );
		$this->assertTrue( wp_script_is( "aad-doc-manager-datatable-mark-js", 'enqueued' ), "script aad-doc-manager-datatable-mark-js is enqueued" );
		$this->assertTrue( wp_style_is( "aad-doc-manager-datatable-css", 'enqueued' ), "style aad-doc-manager-datatable-css is enqueued" );
	}

	/**
	 * Test init of datatables
	 */
	function test_action_init_datatables() {
		$this->expectOutputRegex( '/^\s*\<script\X*script\>\s*$/' );
		SCCSVTable::action_init_datatables();
	}

	/**
	 * Test shortcode with invalid id
	 *
	 * @param array $attrs Attributes to test
	 * @testWith [ { "id" : "888888" } ]
	 *           [ { "id" : "Some weird stuff" } ]
	 *           [ { } ]
	 */
	function test_bad_input( $attrs ) {
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Test invalid input" );
	}

	/**
	 * Wrong post type
	 */
	function test_not_document() {
		$post_id = $this->factory->post->create();

		$attrs['id'] = $post_id;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong post type" );
	}

	/**
	 * Document exists, but wrong post status
	 */
	function test_not_published() {
		// Use post factory - document factory not able to create post_status 'inherit'
		$doc_attrs	 = [
			'post_type'		 => Document::POST_TYPE,
			'post_mime_type' => 'text/csv',
			'post_status'	 => 'inherit'
		];
		$post_id	 = $this->factory->post->create( $doc_attrs );

		$attrs['id'] = $post_id;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong post status" );
	}

	/**
	 * Test wrong mime-type. Only CSV is supported
	 */
	public function test_wrong_mime_type() {
		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/small.pdf'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$attrs['id'] = $post_id;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong mime type" );
	}

	function test_sc_csv_table_header() {
		$expected_files = [
			__DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '_0.html',
			__DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '_1.html',
			__DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '_2.html'
		];

		$post_ids = [
			$this->factory->document->create( [ 'target_file' => __DIR__ . '/samples/simple.csv' ] ),
			$this->factory->document->create( [ 'target_file' => __DIR__ . '/samples/td-list.csv' ] ),
			$this->factory->document->create( [ 'target_file' => __DIR__ . '/samples/english_monarchs.csv' ] )
		];

		foreach ( $post_ids as $index => $post_id ) {
			$attrs = [ 'id' => $post_id ];
			$result = SCCSVTable::sc_docmgr_csv_table( $attrs );
			$this->_assertStringMatchesFormatFile( $expected_files[$index], $result, "Test table headers #$index");
		}
	}

	/**
	 * Test SCCSVTable shortcode with simple csv file
	 */
	function test_sc_csv_table_simple() {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/simple.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		/**
		 * Test Valid Table
		 */
		$attrs	 = [ 'id' => $post_id ];
		$result	 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$this->_assertStringMatchesFormatFile( $expected_file, $result, "Simple 3-line CSV file, no extra options" );
	}

	/**
	 * Test SCCSVTable shortcode with multi-line entry in a column
	 */
	function test_sc_csv_table_td_list() {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/td-list.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		/**
		 * Test Valid Table
		 */
		$attrs	 = [ 'id' => $post_id ];
		$result	 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$this->_assertStringMatchesFormatFile( $expected_file, $result, "Column with new-lines" );
	}

	/**
	 * Test CSV storage format 1
	 */
	function test_sc_csv_table_format_1() {
		self::markTestIncomplete( 'Test csv table format 1' );
	}

	/**
	 * Test CSV storage format 2
	 */
	function test_sc_csv_table_format_2() {
		self::markTestIncomplete( 'Test csv table format 2' );
	}

	/**
	 * Test CSV storage format 3
	 */
	function test_sc_csv_table_format_3() {
		self::markTestIncomplete( 'Test csv table format 3' );
	}

	/**
	 * Display of date field in caption enabled
	 *
	 * @param string $date
	 * @testWith [ 1 ]
	 *           [ "1" ]
	 *           [ "yes" ]
	 *           [ "true" ]
	 *           [ "On" ]
	 */
	function test_sc_csv_table_date_enabled( $date ) {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		$doc_attrs	 = [
			'post_date'		 => '2018-06-13 05:37:30',
			'post_date_gmt'	 => '2018-06-13 12:37:30',
			'target_file'	 => __DIR__ . '/samples/simple.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$attrs	 = [ 'id' => $post_id, 'date' => $date ];
		$result	 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$this->_assertStringMatchesFormatFile( $expected_file, $result, "Date Caption Enabled" );
	}

	/**
	 * Display of date field in caption disabled
	 *
	 * @param string $date
	 * @testWith [ 0 ]
	 *           [ "0" ]
	 *           [ "n" ]
	 *           [ "No" ]
	 *           [ "false" ]
	 * 			 [ "abc" ]
	 *           [ "off" ]
	 */
	function test_sc_csv_table_date_disabled( $date ) {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/simple.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$attrs		 = [ 'id' => $post_id, 'date' => $date ];
		$result		 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$expected	 = '';
		$this->_assertStringMatchesFormatFile( $expected_file, $result, "Date caption disabled" );
	}

	/**
	 * Test row-colors option in csv_table
	 */
	function test_sc_csv_table_row_colors() {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		self::markTestIncomplete( 'Row Coloring Enabled' );
	}

	/**
	 * Test enabled row-number option
	 *
	 * @param string $number_rows Input values to test
	 * @testWith [ 1 ]
	 *           [ "1" ]
	 *           [ "yes" ]
	 * 			 [ "YES" ]
	 *           [ "true" ]
	 *           [ "On" ]
	 */
	function test_sc_csv_table_row_number_enabled( string $number_rows ) {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/simple.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		/**
		 * Test Valid Table
		 */
		$attrs	 = [ 'id' => $post_id, 'row-number' => $number_rows ];
		$result	 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$this->_assertStringMatchesFormatFile( $expected_file, $result, "Simple 3-line CSV file with row numbers" );
	}

	/**
	 * Test disabled row-number option
	 *
	 * @param string $number_rows Input values to test
	 * @testWith [ 0 ]
	 *           [ "0" ]
	 *           [ "No" ]
	 *           [ "false" ]
	 * 			 [ "abc" ]
	 *           [ "off" ]
	 */
	function test_sc_csv_table_row_number_disabled( string $number_rows ) {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/simple.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$attrs	 = [ 'id' => $post_id, 'row-number' => $number_rows ];
		$result	 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$this->_assertStringMatchesFormatFile( $expected_file, $result, "Simple 3-line CSV file without row numbers" );
	}

	/**
	 * Test page-length option in csv_table
	 *
	 * @param int $length
	 *
	 * @testWith [1]
	 *           [10]
	 *           [100]
	 */
	function test_sc_csv_table_valid_page_length( $length ) {
		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/simple.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$attrs		 = [ 'id' => $post_id, 'page-length' => $length ];
		$result		 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$expected	 = '<div%S><table %s data-page-length="' . $length . '"><caption>%s</caption>%a</tr></table></div>';
		$this->assertStringMatchesFormat( $expected, $result, "Valid modified page-length" );
	}

	/**
	 * Test page-length option in csv_table
	 *
	 * @param int $length
	 *
	 * @testWith ["abc"]
	 *           [-1]
	 *           [0]
	 * 			 [""]
	 */
	function test_sc_csv_table_invalid_page_length( $length ) {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/simple.csv'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$attrs		 = [ 'id' => $post_id, 'page-length' => $length ];
		$result		 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$expected	 = '<div%S><table %s data-page-length="10"><caption>%s</caption>%a</tr></table></div>';
		$this->assertStringMatchesFormat( $expected, $result, "Invalid modified page-length" );
	}

	/**
	 * Test rows option in csv_table
	 */
	function test_sc_csv_table_rows() {
		$expected_file = __DIR__ . '/data/' . __CLASS__ . '/' . __FUNCTION__ . '.html';

		self::markTestIncomplete( 'Test limited set of rows to include' );
	}

}
