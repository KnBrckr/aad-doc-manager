<?php

/**
 * Class TestDocManagerTable
 *
 * @package PumaStudios-DocManager
 */
use PumaStudios\DocManager\DocManagerTable;

/**
 * Test support for list of Documents on admin page
 *
 * @group admin
 */
class TestDocManagerTable extends WP_UnitTestCase {

	function test_current_action() {
		self::markTestIncomplete();
	}

	function test_get_columns() {
		self::markTestIncomplete();

		$doc_table = new DocManagerTable( [] );

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
			'columns'
		];

		$columns = $doc_table->get_columns();

		$this->assertEqualSets( $expected_keys, array_keys( $columns ), 'Document table columns' );
	}

	function test_prepare_items() {
		self::markTestIncomplete();
	}

}
