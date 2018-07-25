<?php
/**
 * Copyright (C) 2018 Kenneth J. Brucker <ken@pumastudios.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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
 * @param string $file_id
 * @param int $post_id
 * @param array $post_data
 * @param array $_overrides
 * @return mixed
 */
function media_handle_upload( $file_id, $post_id, $post_data = array(), $_overrides = array( 'test_form' => false ) ) {
	$overrides = array_merge( $_overrides, [ 'action' => 'copy_file' ] );
	return \media_handle_upload( $file_id, $post_id, $post_data, $overrides );
}


class DocumentTest extends \WP_UnitTestCase {
	/**
	 * Setup for entire class
	 *
	 * @param object $factory Factory class used to create objects
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		/**
		 * Setup factory for Documents
		 */
		if ( ! isset( $factory->document ) ) {
			$factory->document = new \WP_UnitTest_Factory_For_Document( $factory );
		}
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
	 * Test retrieval of real path to an attachment
	 *
	 * @covers \PumaStudios\DocManager\Document::get_attachment_realpath()
	 */
	function testGet_attachment_real_path() {
		$target_file = __DIR__ . '/samples/small.pdf';
		$post_id     = $this->factory->document->create( [ 'target_file' => $target_file ] );
		$document    = Document::get_instance( $post_id );
		$path        = $document->get_attachment_realpath();

		$this->assertNotNull( $path, 'Expect download path to exist' );

		/**
		 * Path should be in upload directory
		 */
		$upload_dir = wp_upload_dir();
		$this->assertRegExp( '|^' . $upload_dir['path'] . '|', $path, 'Expect a path to a file' );

		/**
		 * Contents should match original file
		 */
		$this->assertFileEquals( $target_file, $path, 'Expect equal file contents' );
	}

	public function testIs_mime_type_supported() {
		self::markTestIncomplete('Implement is_mime_type_supported');

	}

	/**
	 * Negative test for document of CSV mime type
	 *
	 * @covers \PumaStudios\DocManager\Document::is_csv()
	 */
	function testIs_csv_false() {
		$target_file = __DIR__ . '/samples/small.pdf';
		$post_id	 = $this->factory->document->create( [ 'target_file' => $target_file ] );

		$document = Document::get_instance( $post_id );
		$this->assertFalse( $document->is_csv(), "Document is not a CSV file" );
	}

	/**
	 * Positive test for document of CSV mime type
	 *
	 * @covers \PumaStudios\DocManager\Document::is_csv()
	 */
	function testIs_csv_true() {
		$target_file = __DIR__ . '/samples/cat-breeds.csv';
		$post_id	 = $this->factory->document->create( [ 'target_file' => $target_file ] );

		$document = Document::get_instance( $post_id );
		$this->assertTrue( $document->is_csv(), "Document is a CSV file" );
	}



	/**
	 * Negative test document access by GUID
	 *
	 * @covers \PumaStudios\DocManager\Document::get_document_by_guid()
	 */
	function testGet_document_by_guid_negative() {
		/**
		 * Invalid guid
		 */
		$this->assertNull( Document::get_document_by_guid( 'bad-guid' ), "malformed GUID" );

		/**
		 * Valid guid, no document
		 */
		$this->assertNull( Document::get_document_by_guid( '378ff88b-7eef-4156-836c-85e1eefc1e65' ), "no such GUID" );
	}

	/**
	 * Positive test for document access by GUID
	 *
	 * @covers \PumaStudios\DocManager\Document::get_document_by_guid()
	 */
	function testGet_document_by_guid_positive() {
		$guid = '3809d4e8-16ff-4a58-9a24-2bc79cf0d425';

		/**
		 * Valid guid, matching document
		 */
		self::markTestIncomplete( 'find document by valid GUID must be implemented' );
		$document = Document::get_document_by_guid( $guid );
		$this->assertTrue( $document instanceof Document, "find document by valid GUID" );
	}

	/**
	 * negative tests for Document::create_document()
	 *
	 * @covers \PumaStudios\DocManager\Document::create_document()
	 */
	public function testCreate_document_negative() {
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
	 *
	 * @covers \PumaStudios\DocManager\Document::create_document()
	 */
	public function testCreate_document_csv() {

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
	}

	/**
	 * Create a valid PDF document
	 *
	 * @covers \PumaStudios\DocManager\Document::create_document()
	 */
	function testCreate_document_pdf() {
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
			$msg = "Expect create_document to return Document object";
		}

		/**
		 * Cleanup before assert
		 */
		@unlink( $tmpfile );

		$this->assertTrue( $document instanceof Document, $msg );
	}

	public function test__construct() {
		self::markTestIncomplete('Test constuctor method');
	}

	public function test__get() {
		self::markTestIncomplete('Test property retrieval');
	}

	/**
	 * Test supported mime types
	 */
	public function testGet_supported_mime_types() {
		$expected_mime_types = [
			'text/csv',
			'application/pdf'
		];

		$this->assertEqualSets( $expected_mime_types, Document::get_supported_mime_types() );

		foreach ( $expected_mime_types as $mime_type ) {
			$this->assertTrue( Document::is_mime_type_supported( $mime_type ) );
		}

		$this->assertFalse( Document::is_mime_type_supported( 'bad-mime-type' ) );
	}

	/**
	 * Test registration of post type
	 *
	 * @covers \PumaStudios\DocManager\Document::register_post_type()
	 */
	public function testRegister_post_type() {
		$this->assertTrue( post_type_exists( Document::POST_TYPE ), "Document Post Type must exist" );
	}

	/**
	 * Test run method performs the needed setup
	 *
	 * Document::run() will be done as a part of starting the plugin for test.
	 *
	 * @covers \PumaStudios\DocManager\Document::run()
	 */
	public function testRun() {
		$register_post_type_prio = has_action( 'init', [ Document::class, 'register_post_type' ] );
		$register_taxonomy_prio  = has_action( 'init', [ Document::class, 'register_taxonomy' ] );
		$this->assertEquals( 10, $register_post_type_prio, 'Document::register_post_type() must run during WP init action' );
		$this->assertEquals( 11, $register_taxonomy_prio, 'Document::register_taxonomy() must run during WP init action after post type registered' );
	}

	/**
	 * No matching document exists
	 *
	 * @covers \PumaStudios\DocManager\Document::get_instance()
	 */
	public function test_no_post() {
		/**
		 * Invalid document
		 */
		$this->assertNull( Document::get_instance( '999999' ) );
	}

	/**
	 * Wrong post type
	 *
	 * @covers \PumaStudios\DocManager\Document::get_instance()
	 */
	public function test_not_a_document() {
		$post_id = $this->factory->post->create();
		$this->assertNull( Document::get_instance( $post_id ) );
	}

	/**
	 * Document is not published
	 *
	 * @covers \PumaStudios\DocManager\Document::get_instance()
	 */
	public function test_document_not_published() {
		self::markTestIncomplete( 'Must implement ability to manage document revisions' );
		/**
		 * An older revision
		 */
		$doc_attrs = [
			'target_file' => __DIR__ . '/samples/small.pdf',
			'post_status' => 'inherit'
		];
		$post_id   = $this->factory->document->create( $doc_attrs );

		$this->assertNull( Document::get_instance( $post_id ) );
	}

	/**
	 * Retrieve an available document
	 *
	 * @covers \PumaStudios\DocManager\Document::get_instance()
	 */
	public function testGet_Instance() {
		self::markTestIncomplete( "Need test for normal get_instance use case" );
	}

	/**
	 * Test document property interface
	 *
	 * @covers \PumaStudios\DocManager\Document::get_instance()
	 */
	function test_document_properties() {
		$datestamp     = '2018-06-13 05:37:30';
		$datestamp_gmt = '2018-06-13 12:37:30';

		/**
		 * Create valid CSV document
		 */
		$doc_attrs = [
			'post_date'      => $datestamp,
			'post_date_gmt'  => $datestamp_gmt,
			'target_file'    => __DIR__ . '/samples/cat-breeds.csv',
			'post_mime_type' => 'text/csv'
		];
		$post_id   = $this->factory->document->create( $doc_attrs );

		/**
		 * Retrieve the CSV document
		 */
		$document = Document::get_instance( $post_id );
		$this->assertTrue( $document instanceof Document, 'find published document by ID' );
		$this->assertEquals( $post_id, $document->ID );
		$this->assertEquals( $datestamp, $document->post_modified );
		$this->assertEquals( $datestamp_gmt, $document->post_modified_gmt );
		$this->assertEquals( $datestamp_gmt, $document->post_date_gmt );
		$this->assertEquals( 'text/csv', $document->post_mime_type );
	}

	/**
	 * Test retrieval of document revision
	 *
	 * See test_document_not_published for setup
	 *
	 * @covers \PumaStudios\DocManager\Document::get_instance()
	 */
	public function testGet_instance_revision() {
		self::markTestIncomplete( 'Must implement ability to manage document revisions' );
		$post_id = 0;
		$this->assertTrue( Document::get_instance( $post_id, 'inherit' ) instanceof Document, 'retrieve a document revision' );
	}

	/**
	 * Test registration of taxonomy
	 *
	 * @covers \PumaStudios\DocManager\Document::register_taxonomy()
	 */
	public function testRegister_taxonomy() {
		$this->assertTrue( taxonomy_exists( Document::TERM_GUID ), "Taxonomy for Document GUIDs must exist" );
	}

	/**
	 * Test retrieval of csv data for non-csv document
	 */
	function testGet_csv_records_not_csv() {
		$target_file = __DIR__ . '/samples/small.pdf';
		$post_id	 = $this->factory->document->create( [ 'target_file' => $target_file ] );

		$document = Document::get_instance( $post_id );
		$this->assertEmpty( $document->get_csv_records() );
		$this->assertEmpty( $document->get_csv_record( 1 ) );
		$this->assertEmpty( $document->get_csv_header() );
	}

	/**
	 * Retrieve a single record from simple CSV file
	 *
	 * @param int $index Index into CSV file
	 * @param array $expected Expected matching record data
	 *
	 * @testWith [ 0, { "First Name": "Jane", "Last Name": "Doe", "email": null } ]
	 *           [ 1, { "First Name": "John", "Last Name": "Smith", "email": "jsmith@example.com"} ]
	 * 	         [ 2, { "First Name": "Jack", "Last Name": "Rogers", "email": "ro@example.com"} ]
	 */
	function testGet_csv_record( $index, $expected ) {
		$target_file = __DIR__ . '/samples/simple.csv';
		$post_id	 = $this->factory->document->create( [ 'target_file' => $target_file ] );

		$document = Document::get_instance( $post_id );

		$record = $document->get_csv_record( $index );
		$this->assertEqualSetsWithIndex( $expected, $record );
	}

	/**
	 * Retrieve a single record from simple CSV file
	 *
	 * @param int $index Index into CSV file
	 * @testWith [ 5 ]
	 *           [ 99 ]
	 */
	function testGet_csv_record_out_of_range( $index ) {
		$target_file = __DIR__ . '/samples/simple.csv';
		$post_id	 = $this->factory->document->create( [ 'target_file' => $target_file ] );

		$document = Document::get_instance( $post_id );

		$this->assertEmpty( $document->get_csv_record( $index ) );
	}

	/**
	 * Retrieve array of records for simple CSV file
	 */
	function testGet_csv_records() {
		$target_file = __DIR__ . '/samples/simple.csv';
		$post_id	 = $this->factory->document->create( [ 'target_file' => $target_file ] );

		$document = Document::get_instance( $post_id );

		$records = $document->get_csv_records();

		$this->assertNotNull( $records );
		$this->assertEquals( 3, count( $records ) );
	}

	/**
	 * Test retrieval of header from simple CSV file
	 */
	function testGet_csv_header() {
		$target_file = __DIR__ . '/samples/simple.csv';
		$post_id	 = $this->factory->document->create( [ 'target_file' => $target_file ] );

		$document = Document::get_instance( $post_id );

		$header = $document->get_csv_header();
		$this->assertEquals( [ 'First Name', 'Last Name', 'email' ], $header, 'Retrieve header from CSV document' );
	}


}
