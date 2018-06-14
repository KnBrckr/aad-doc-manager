<?php

/**
 * Class SCCSVTableTest
 *
 * @package Aad_Doc_Manager
 */
use PumaStudios\DocManager\SCCSVTable;

/**
 * Sample test case.
 */
class TestSCCSVTable extends WP_UnitTestCase {

	/**
	 * @var int PostID for a normal post
	 */
	protected static $normal_post_id;

	/**
	 * Setup for entire class
	 *
	 * @param Factor $factory Factory class used to create objects
	 */
	public static function wpSetUpBeforeClass($factory) {
		self::$normal_post_id = $factory->post->create();
	}

	/**
	 * Test invalid post ids
	 */
	public function test_invalid_post() {
		/**
		 * Null input returns empty string
		 */
		$attrs = [];
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Null input returns empty string" );

		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs['id'] = "Some weird stuff" ;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Text input returns empty string" );

		/**
		 * Post ID does not exist
		 */
		$attrs['id'] = "8888888";
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Post does not exist" );

		/**
		 * Wrong post type
		 */
		$attrs['id'] = self::$normal_post_id ;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong post type" );

		/**
		 * Wrong post status
		 */
//		$attrs['id'] = 2;
//		$this->assertEquals( "FIXME", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong post status" );
	}

	/**
	 * Test Valid Table
	 */
//	function test_good_csv_table() {
//		$attrs	 = [ 'id' => 1 ];
//		$result	 = SCCSVTable::sc_docmgr_csv_table( $attrs );
//		$result	 = 'FIXME';
//		$this->assertStringMatchesFormat( '<div class="aad-doc-manager"><table class="aad-doc-manager-csv searchHighlight responsive no-wrap" width="100%"><caption>%a</caption>%a</table></div>', $result, "Valid document post type" );
//	}

}
