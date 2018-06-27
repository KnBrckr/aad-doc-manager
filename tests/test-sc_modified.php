<?php

/**
 * Class SCModified Tests
 *
 * @package Aad_Doc_Manager
 */

use PumaStudios\DocManager\Document;
use PumaStudios\DocManager\SCModified;

/**
 * Test shortcode displaying modified date
 *
 * @group shortcode
 */
class TestSCModified extends WP_UnitTestCase {

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
	 * Test class initialized
	 */
	function test_run() {
		$this->assertTrue( shortcode_exists( 'docmgr-modified' ), 'Shortcode docmgr-modified exists' );
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
		$this->assertEquals( "", SCModified::sc_docmgr_modified( $attrs ), "Test invalid input" );
	}


	/**
	 * Wrong post type
	 */
	function test_not_document() {
		$post_id = $this->factory->post->create();

		$attrs['id'] = $post_id;
		$this->assertEquals( "", SCModified::sc_docmgr_modified( $attrs ), "Wrong post type" );
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
		$this->assertEquals( "", SCModified::sc_docmgr_modified( $attrs ), "Wrong post status" );
	}

	/**
	 * Test SCModified shortcode with valid id
	 */
	function test_sc_modified() {
		$doc_attrs	 = [
			'post_type'		 => Document::POST_TYPE,
			'post_date'		 => '2018-06-13 05:37:30',
			'post_date_gmt'	 => '2018-06-13 12:37:30',
			'target_file' => __DIR__ . '/samples/small.pdf'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$attrs['id'] = $post_id;
		$this->assertEquals( 'June 13, 2018', SCModified::sc_docmgr_modified( $attrs ), "Valid post" );
	}

}
