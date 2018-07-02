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

	function test_prepare_items() {
		self::markTestIncomplete();
	}

}
