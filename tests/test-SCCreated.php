<?php

/**
 * Class SampleTest
 *
 * @package Aad_Doc_Manager
 */
use PumaStudios\DocManager\SCCreated;

/**
 * Sample test case.
 */
class TestSCCreated extends WP_UnitTestCase {

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
	 * Test invalid post arguments
	 */
	function test_invalid_post() {

		/**
		 * Null Input
		 */
		$attrs = [];
		$this->assertEquals( "", SCCreated::sc_docmgr_created( $attrs ), "Null input returns empty string" );

		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs['id'] = "Some weird stuff";
		$this->assertEquals( "", SCCreated::sc_docmgr_created( $attrs ), "Text input returns empty string" );

		/**
		 * Post-id that does not exist
		 */
		$attrs['id'] = "8999999" ;
		$this->assertEquals( "", SCCreated::sc_docmgr_created( $attrs ), "Post does not exist" );

		/**
		 * Wrong post type
		 */
		$attrs['id'] = self::$normal_post_id ;
		$this->assertEquals( "", SCCreated::sc_docmgr_created( $attrs ), "Wrong post type" );

		/**
		 * Document exists, but wrong post status
		 */
//		$attrs['id'] = 'FIXME';
//		$this->assertEquals( "FIXME", SCCreated::sc_docmgr_created( $attrs ), "Wrong post status" );
	}

	/**
	 * Good post
	 */
//	function test_good_document() {
//
//		$attrs = [ 'id' => 'FIXME' ];
//		$this->assertEquals( 'FIXME', SCCreated::sc_docmgr_created( $attrs ), "Valid post" );
//	}

}
