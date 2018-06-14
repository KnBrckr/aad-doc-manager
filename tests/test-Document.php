<?php

/**
 * Class DocumentTest
 *
 * @package Aad_Doc_Manager
 */
use PumaStudios\DocManager\Document;

/**
 * Sample test case.
 */
class TestDocument extends WP_UnitTestCase {

	const DATESTAMP				 = '2018-06-13 05:37:30';
	const DATESTAMP_GMT			 = '2018-06-13 12:37:30';

	/**
	 * @var int PostID for a normal post
	 */
	protected static $normal_post_id;

	/**
	 * @var int  PostID for a normal document
	 */
	protected static $document_post_id;

	/**
	 * @var int PostID for a document revision
	 */
	protected static $document_revision_post_id;

	/**
	 * Setup for entire class
	 *
	 * @param Factor $factory Factory class used to create objects
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$normal_post_id = $factory->post->create();

		/**
		 * A valid document
		 */
		$doc_attrs				 = [
			'post_type'			 => Document::POST_TYPE,
			'post_date'			 => self::DATESTAMP,
			'post_date_gmt'		 => self::DATESTAMP_GMT,
			'post_mime_type'	 => 'text/csv'
		];
		self::$document_post_id	 = $factory->post->create( $doc_attrs );

		/**
		 * An older revision
		 */
		$doc_attrs['post_status']		 = 'inherit';
		self::$document_revision_post_id = $factory->post->create( $doc_attrs );

		Document::register_taxonomy();
	}

	/**
	 * Test document retrieval
	 */
	function test_get_document() {
		/**
		 * Invalid document
		 */
		$this->assertNull( Document::get_document( '999999' ) );

		/**
		 * Wrong post type
		 */
		$this->assertNull( Document::get_document( self::$normal_post_id ) );

		/**
		 * not published
		 */
		$this->assertNull( Document::get_document( self::$document_revision_post_id ) );
		$this->assertTrue( Document::get_document( self::$document_revision_post_id, 'inherit' ) instanceof Document );

		/**
		 * Normal document
		 */
		$document = Document::get_document( self::$document_post_id );

		$this->assertTrue( $document instanceof Document );
		if ( $document instanceof Document ) {
			$this->assertEquals( self::DATESTAMP, $document->post_modified );
			$this->assertEquals( self::DATESTAMP_GMT, $document->post_modified_gmt );
			$this->assertEquals( self::DATESTAMP_GMT, $document->post_date_gmt );
			$this->assertEquals( 'text/csv', $document->post_mime_type );
		}

	}

}
