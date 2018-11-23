<?php

use PumaStudios\DocManager\Document;
use PumaStudios\DocManager\DocumentDownload;
use PumaStudios\DocManager\SCDownloadURL;

/**
 * Class TestSCDownloadURL
 *
 * Test shortcode displaying modified date
 *
 * @package PumaStudios-DocManager
 * @group shortcode
 */
class SCDownloadURLTest extends WP_UnitTestCase {

	/**
	 * Setup for entire class
	 *
	 * @param Factory $factory Factory class used to create objects
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
		$this->assertTrue( shortcode_exists( 'docmgr-download-url' ), 'Shortcode docmgr-download-url exists' );
	}

	/**
	 * Test shortcode with empty input
	 */
	function test_sc_download_url_null() {
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( [] ), "Null input returns empty string" );
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
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( $attrs ), "Test invalid input" );
	}

	/**
	 * Wrong post type
	 */
	function test_not_document() {
		$post_id = $this->factory->post->create();

		$attrs['id'] = $post_id;
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( $attrs ), "Wrong post type" );
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
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( $attrs ), "Wrong post status" );
	}

	/**
	 * Test shortcode with valid id
	 *
	 * @param string $content Content for the link
	 * @testWith [ "Link" ]
	 * 		     [ "" ]
	 */
	function test_sc_download_url( $content ) {
		$doc_attrs	 = [
			'target_file' => __DIR__ . '/samples/small.pdf'
		];
		$post_id	 = $this->factory->document->create( $doc_attrs );

		$document	 = Document::get_instance( $post_id );
		$url		 = DocumentDownload::get_download_url( $document );


		$attrs		 = [ 'id' => $post_id ];
		$expected	 = sprintf( '<a href="%s">%s</a>', $url, $content );
		$this->assertEquals( $expected, SCDownloadURL::sc_docmgr_download_url( $attrs, $content ), "HTMLized download URL for document" );
	}

}
