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

	const DATESTAMP		 = '2018-06-13 05:37:30';
	const DATESTAMP_GMT	 = '2018-06-13 12:37:30';
	const GUID1			 = '378ff88b-7eef-4156-836c-85e1eefc1e65';
	const GUID2			 = '3809d4e8-16ff-4a58-9a24-2bc79cf0d425';

	/**
	 * @var int PostID for a normal post
	 */
	protected static $normal_post_id;

	/**
	 * @var int  PostID for a CSV document
	 */
	protected static $document_csv_post_id;

	/**
	 * @var int PostID for a PDF document
	 */
	protected static $document_pdf_post_id;

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
			'post_type'		 => Document::POST_TYPE,
			'post_date'		 => self::DATESTAMP,
			'post_date_gmt'	 => self::DATESTAMP_GMT,
			'post_mime_type' => 'text/csv'
		];
		self::$document_csv_post_id	 = $factory->post->create( $doc_attrs );

		/**
		 * A PDF document
		 */
		$doc_attrs['post_mime_type'] = 'application/pdf';
		self::$document_pdf_post_id = $factory->post->create( $doc_attrs );

		/**
		 * An older revision
		 */
		$doc_attrs['post_status']		 = 'inherit';
		self::$document_revision_post_id = $factory->post->create( $doc_attrs );

		Document::register_taxonomy();
	}

	/**
	 * Test registration of taxonomy
	 */
	function test_register_taxonomy() {
		self::markTestIncomplete();
	}

	/**
	 * Test registration of post type
	 */
	function test_register_post_type() {
		self::markTestIncomplete();
	}

	/**
	 * Test document retrieval
	 */
	function test_emtpy_get_document() {
		/**
		 * Invalid document
		 */
		$this->assertNull( Document::get_instance( '999999' ) );

		/**
		 * Wrong post type
		 */
		$this->assertNull( Document::get_instance( self::$normal_post_id ) );

		/**
		 * not published
		 */
		$this->assertNull( Document::get_instance( self::$document_revision_post_id ) );
	}

	/**
	 *
	 * @return Document
	 */
	function test_get_instance() {
		$this->assertTrue( Document::get_instance( self::$document_revision_post_id, 'inherit' ) instanceof Document, 'find document revision' );

		/**
		 * Normal document
		 */
		$document = Document::get_instance( self::$document_csv_post_id );
		$this->assertTrue( $document instanceof Document, 'find published document by ID' );

		return $document;
	}

	/**
	 *
	 * @depends test_get_instance
	 * @param Document $document
	 */
	function test_document_contents( Document $document ) {
		$this->assertEquals( self::$document_csv_post_id, $document->ID );
		$this->assertEquals( self::DATESTAMP, $document->post_modified );
		$this->assertEquals( self::DATESTAMP_GMT, $document->post_modified_gmt );
		$this->assertEquals( self::DATESTAMP_GMT, $document->post_date_gmt );
		$this->assertEquals( 'text/csv', $document->post_mime_type );
	}

	/**
	 * Test document access by GUID
	 */
	function test_get_document_by_guid() {
		/**
		 * Invalid guid
		 */
		$this->assertNull( Document::get_document_by_guid( 'bad-guid' ), "malformed GUID" );

		/**
		 * Valid guid, no document
		 */
		$this->assertNull( Document::get_document_by_guid( self::GUID1 ), "no such GUID" );

		/**
		 * Valid guid, matching document
		 */
		self::markTestIncomplete( 'find document by valid GUID must be implemented' );
		$document = Document::get_document_by_guid( self::GUID2 );
		$this->assertTrue( $document instanceof Document, "find document by valid GUID" );
	}

	/**
	 * Test retrieval of attachment for a document
	 *
	 * @depends test_get_instance
	 * @param Document $document A document post
	 */
	function test_get_attachment( Document $document ) {
		self::markTestIncomplete();
	}

	/**
	 * Test retrieval of real path to an attachment
	 *
	 * @depends test_get_instance
	 * @param Document $document A document post
	 */
	function test_get_attachment_real_path( Document $document ) {
		self::markTestIncomplete();
	}

	/**
	 * Test supported mime types
	 */
	function test_supported_mime_types() {
		$expected_mime_types = [
			'text/csv', 'application/pdf'
		];

		$this->assertEqualSets( $expected_mime_types, Document::get_supported_mime_types(), 'Supported Mime Types' );

		foreach ( $expected_mime_types as $mime_type ) {
			$this->assertTrue( Document::is_mime_type_supported( $mime_type ) );
		}

		$this->assertFalse( Document::is_mime_type_supported( 'bad-mime-type' ) );
	}

	/**
	 * Negative test for document of CSV mime type
	 */
	function test_is_csv_false() {
		$document = Document::get_instance( self::$document_pdf_post_id );
		$this->assertFalse( $document->is_csv() );
	}

	/**
	 * Positive test for document of CSV mime type
	 */
	function test_is_csv_true() {
		$document = Document::get_instance( self::$document_csv_post_id );
		$this->assertTrue( $document->is_csv() );
	}

}
