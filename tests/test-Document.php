<?php

/**
 * Class DocumentTest
 *
 * @package Aad_Doc_Manager
 */

namespace PumaStudios\DocManager;

/**
 * Override PHP is_uploaded_file() for testing purposes
 *
 * @param string $path path to file
 * @return boolean
 */
function is_uploaded_file( string $path ) {
	return file_exists( $path );
}

/**
 * Override WordPress media_handle_upload to override PHP handling that only works in browser mode
 *
 * @param type $file_id
 * @param type $post_id
 * @param type $post_data
 * @param type $_overrides
 * @return mixed
 */
function media_handle_upload( $file_id, $post_id, $post_data = array(), $_overrides = array( 'test_form' => false ) ) {
	$overrides = array_merge( $_overrides, [ 'action' => 'copy_file' ] );
	return \media_handle_upload( $file_id, $post_id, $post_data, $overrides );
}

/**
 * Sample test case.
 */
class TestDocument extends \WP_UnitTestCase {

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
		/**
		 * Setup factory for Documents
		 */
		$factory->document = new \WP_UnitTest_Factory_For_Document( $factory );

		self::$normal_post_id = $factory->post->create();

		/**
		 * A valid document
		 */
		$doc_attrs					 = [
			'post_date'		 => self::DATESTAMP,
			'post_date_gmt'	 => self::DATESTAMP_GMT,
			'target_file'	 => __DIR__ . '/samples/cat-breeds.csv'
		];
		self::$document_csv_post_id	 = $factory->document->create( $doc_attrs );

		/**
		 * A PDF document
		 */
		$doc_attrs['target_file']	 = __DIR__ . '/samples/small.pdf';
		self::$document_pdf_post_id	 = $factory->document->create( $doc_attrs );

		/**
		 * An older revision
		 */
		$doc_attrs['post_status']		 = 'inherit';
		self::$document_revision_post_id = $factory->document->create( $doc_attrs );
	}

	/**
	 * Test run method does needed setup
	 *
	 * Document::run() will be done as a part of starting the plugin for test.
	 */
	function test_run() {
		$register_post_type_prio = has_action( 'init', [ Document::class, 'register_post_type' ] );
		$register_taxonomy_prio	 = has_action( 'init', [ Document::class, 'register_taxonomy' ] );
		$this->assertEquals( 10, $register_post_type_prio, 'Document::register_post_type() must run during WP init action' );
		$this->assertEquals( 11, $register_taxonomy_prio, 'Document::register_taxonomy() must run during WP init action after post type registered' );
	}

	/**
	 * Test registration of taxonomy
	 */
	function test_register_taxonomy() {
		$this->assertTrue( taxonomy_exists( Document::TERM_GUID ), "Taxonomy for Document GUIDs must exist" );
	}

	/**
	 * Test registration of post type
	 */
	function test_register_post_type() {
		$this->assertTrue( post_type_exists( Document::POST_TYPE ), "Document Post Type must exist" );
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
	 * Test retrieval of document revision
	 */
	function test_get_revision() {
		self::markTestIncomplete('Must implement ability to manage document revisions');
		$this->assertTrue( Document::get_instance( self::$document_revision_post_id, 'inherit' ) instanceof Document, 'retrieve a document revision' );
	}

	/**
	 *
	 * @return Document
	 */
	function test_get_csv_instance() {
		/**
		 * Retrieve a CSV document
		 */
		$document = Document::get_instance( self::$document_csv_post_id );
		$this->assertTrue( $document instanceof Document, 'find published document by ID' );

		return $document;
	}

	/**
	 *
	 * @depends test_get_csv_instance
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
	 * Negative test document access by GUID
	 */
	function test_get_document_by_guid_negative() {
		/**
		 * Invalid guid
		 */
		$this->assertNull( Document::get_document_by_guid( 'bad-guid' ), "malformed GUID" );

		/**
		 * Valid guid, no document
		 */
		$this->assertNull( Document::get_document_by_guid( self::GUID1 ), "no such GUID" );
	}

	/**
	 * Positive test for document access by GUID
	 */
	function test_get_document_by_guid_positive() {

		/**
		 * Valid guid, matching document
		 */
		self::markTestIncomplete( 'find document by valid GUID must be implemented' );
		$document = Document::get_document_by_guid( self::GUID2 );
		$this->assertTrue( $document instanceof Document, "find document by valid GUID" );
	}

	/**
	 * Test retrieval of real path to an attachment
	 */
	function test_get_attachment_real_path() {
		$document	 = Document::get_instance( self::$document_pdf_post_id );
		$path		 = $document->get_attachment_realpath();

		$this->assertNotNull( $path, 'Expect download path to exist' );
		$this->assertFileExists( $path, 'Download File must exist' );
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

	/**
	 * negative tests for Document::create_document()
	 */
	function test_create_document_negative() {
		$this->assertWPError( Document::create_document( [], 'bad' ) );

		$_FILES = [
			'document' => [
				'error' => UPLOAD_ERR_NO_TMP_DIR
			]
		];

		$this->assertWPError( Document::create_document( [], 'bad' ) );
		$this->assertWPError( Document::create_document( [], 'document' ) );

		/*
		 * Test an unknown error code
		 */
		$_FILES = [
			'document' => [
				'error' => 999999
			]
		];
		$this->assertWPError( Document::create_document( [], 'document' ) );
	}

	/**
	 * positive test for Document::create_document()
	 */
	function test_create_csv_document() {

		$tmpfile = tempnam( sys_get_temp_dir(), 'phpunit-uploadtest' );
		copy( __DIR__ . '/samples/cat-breeds.csv', $tmpfile );

		$_FILES = [
			'document' => [
				'error'		 => UPLOAD_ERR_OK,
				'name'		 => 'cat-breeds.csv',
				'type'		 => 'text/csv',
				'size'		 => '3484',
				'tmp_name'	 => $tmpfile
			]
		];

		$document = Document::create_document( [], 'document' );
		if ( is_wp_error( $document ) ) {
			$msg = $document->get_error_message();
		} else {
			$msg = "Create csv document postive test";
		}

		/**
		 * Cleanup before assert
		 */
		@unlink( $tmpfile );

		$this->assertTrue( $document instanceof Document, $msg );

		$path		 = $document->get_attachment_realpath();
		$upload_dir	 = wp_upload_dir();

		$this->assertRegExp( '|^' . $upload_dir['path'] . '|', $path, 'Expect a path to a file' );

		return $document;
	}

	/**
	 * Confirm valid CSV document object
	 *
	 * @param Document $document Document Object under test
	 * @depends test_create_csv_document
	 */
	function test_valid_csv_document( Document $document ) {
		$this->assertEquals( 'cat-breeds.csv', $document->post_title, "Expect title to match upload file name" );
		$this->assertTrue( $document->is_csv(), "Expect document to be CSV mime type" );
	}

	/**
	 * Create a valid PDF document
	 */
	function test_create_pdf_document() {
		$tmpfile = tempnam( sys_get_temp_dir(), 'phpunit-uploadtest' );
		copy( __DIR__ . '/samples/small.pdf', $tmpfile );

		$_FILES = [
			'document' => [
				'error'		 => UPLOAD_ERR_OK,
				'name'		 => 'small.pdf',
				'type'		 => 'text/pdf',
				'size'		 => '3484',
				'tmp_name'	 => $tmpfile
			]
		];

		$document = Document::create_document( [], 'document' );
		if ( is_wp_error( $document ) ) {
			$msg = $document->get_error_message();
		} else {
			$msg = "Create pdf document postive test";
		}

		/**
		 * Cleanup before assert
		 */
		@unlink( $tmpfile );

		$this->assertTrue( $document instanceof Document, $msg, "Expect create_document to return Document object" );

		$path		 = $document->get_attachment_realpath();
		$upload_dir	 = wp_upload_dir();

		$this->assertRegExp( '|^' . $upload_dir['path'] . '|', $path, 'Expect a path to a file' );

		return $document;
	}

	/**
	 * Confirm valid PDF document object
	 *
	 * @param Document $document Document Object under test
	 * @depends test_create_pdf_document
	 */
	function test_valid_pdf_document( Document $document ) {
		$this->assertEquals( 'small.pdf', $document->post_title, "Expect title to match upload file name" );
		$this->assertFalse( $document->is_csv(), "Expect document is not a CSV file" );
	}

}
