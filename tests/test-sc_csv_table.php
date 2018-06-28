<?php

/**
 * Class SCCSVTableTest
 *
 * @package Aad_Doc_Manager
 */
use PumaStudios\DocManager\Document;
use PumaStudios\DocManager\SCCSVTable;

/**
 * Sample test case.
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

	/**
	 * Test SCCSVTable shortcode with invalid document
	 */
	function test_sc_csv_table_simple() {
		self::markTestIncomplete();

		/**
		 * Test Valid Table
		 */
		$attrs		 = [ 'id' => self::$document_csv_post_id ];
		$result		 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$expected	 = '<div class="aad-doc-manager"><table class="aad-doc-manager-csv searchHighlight responsive no-wrap" width="100%"><caption>%a</caption>%a</table></div>';
		$this->assertStringMatchesFormat( $expected, $result, "Valid document post type" );
	}

	/**
	 * Test CSV storage format 1
	 */
	function test_sc_csv_table_format_1() {
		self::markTestIncomplete();
	}

	/**
	 * Test CSV storage format 2
	 */
	function test_sc_csv_table_format_2() {
		self::markTestIncomplete();
	}

	/**
	 * Test CSV storage format 3
	 */
	function test_sc_csv_table_format_3() {
		self::markTestIncomplete();
	}

	/**
	 * Test date option in csv_table
	 */
	function test_sc_csv_table_date() {
		self::markTestIncomplete();
	}

	/**
	 * Test row-colors option in csv_table
	 */
	function test_sc_csv_table_row_colors() {
		self::markTestIncomplete();
	}

	/**
	 * Test row-number option in csv_table
	 */
	function test_sc_csv_table_row_number() {
		self::markTestIncomplete();
	}

	/**
	 * Test page-length option in csv_table
	 */
	function test_sc_csv_table_page_length() {
		self::markTestIncomplete();
	}

	/**
	 * Test rows option in csv_table
	 */
	function test_sc_csv_table_rows() {
		self::markTestIncomplete();
	}

}
