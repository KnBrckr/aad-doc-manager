<?php

/**
 * Class SampleTest
 *
 * @package Aad_Doc_Manager
 */
use PumaStudios\DocManager\SCModified;

/**
 * Sample test case.
 */
class TestSCModified extends WP_UnitTestCase {

	/**
	 * @var int PostID for a normal post
	 */
	protected static $normal_post_id;

	/**
	 * Setup for entire class
	 *
	 * @param Factor $factory Factory class used to create objects
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$normal_post_id = $factory->post->create();
	}

	/**
	 * Test invalid post arguments
	 */
	function test_invalid_post() {

		/**
		 * Null Input
		 */
		$attrs = [];
		$this->assertEquals( "", SCModified::sc_docmgr_modified( $attrs ), "Null input returns empty string" );

		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs['id'] = "Some weird stuff";
		$this->assertEquals( "", SCModified::sc_docmgr_modified( $attrs ), "Text input returns empty string" );

		/**
		 * Post-id that does not exist
		 */
		$attrs['id'] = "8999999";
		$this->assertEquals( "", SCModified::sc_docmgr_modified( $attrs ), "Post does not exist" );

		/**
		 * Wrong post type
		 */
		$attrs['id'] = self::$normal_post_id;
		$this->assertEquals( "", SCModified::sc_docmgr_modified( $attrs ), "Wrong post type" );

//		/**
//		 * Document exists, but wrong post status
//		 */
//		$attrs['id'] = 'FIXME';
//		$this->assertEquals( "FIXME", SCModified::sc_docmgr_modified( $attrs ), "Wrong post status" );
	}

	/**
	 * Good post
	 */
//	function test_good_document() {
//
//		$attrs = [ 'id' => 'FIXME' ];
//		$this->assertEquals( 'FIXME', SCModified::sc_docmgr_modified( $attrs ), "Valid post" );
//	}

}
