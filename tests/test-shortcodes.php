<?php

/**
 * Class SCCSVTableTest
 *
 * @package Aad_Doc_Manager
 */
use PumaStudios\DocManager\Document;
use PumaStudios\DocManager\SCCSVTable;
use PumaStudios\DocManager\SCCreated;
use PumaStudios\DocManager\SCDownloadURL;
use PumaStudios\DocManager\SCModified;

/**
 * Sample test case.
 */
class TestShortcodes extends WP_UnitTestCase {

	const DATESTAMP		 = '2018-06-13 05:37:30';
	const DATESTAMP_GMT	 = '2018-06-13 12:37:30';

	/**
	 * @var int PostID for a normal post
	 */
	protected static $normal_post_id;

	/**
	 * @var int  PostID for a published CSV document
	 */
	protected static $document_csv_post_id;

	/**
	 * @var int PostID for a published PDF document
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
		$doc_attrs					 = [
			'post_type'		 => Document::POST_TYPE,
			'post_date'		 => self::DATESTAMP,
			'post_date_gmt'	 => self::DATESTAMP_GMT,
			'post_mime_type' => 'text/csv'
		];
		self::$document_csv_post_id	 = $factory->post->create( $doc_attrs );

		/**
		 * PDF mime-type
		 */
		$doc_attrs['post_mime_type'] = 'application/pdf';
		self::$document_pdf_post_id	 = $factory->post->create( $doc_attrs );

		/**
		 * An older revision
		 */
		$doc_attrs['post_status']		 = 'inherit';
		self::$document_revision_post_id = $factory->post->create( $doc_attrs );
	}

	/**
	 * Test SCCSVTable shortcode with invalid document
	 */
	public function test_sc_csv_table_empty() {
		/**
		 * Null input returns empty string
		 */
		$attrs = [];
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Null input returns empty string" );

		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs['id'] = "Some weird stuff";
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Text input returns empty string" );

		/**
		 * Post ID does not exist
		 */
		$attrs['id'] = "8888888";
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Post does not exist" );

		/**
		 * Wrong post type
		 */
		$attrs['id'] = self::$normal_post_id;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong post type" );

		/**
		 * Wrong post status
		 */
		$attrs['id'] = self::$document_revision_post_id;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong post status" );

		/**
		 * Wrong mime type
		 */
		$attrs['id'] = self::$document_pdf_post_id;
		$this->assertEquals( "", SCCSVTable::sc_docmgr_csv_table( $attrs ), "Wrong mime type" );
	}

	/**
	 * Test SCCSVTable shortcode with invalid document
	 */
	function test_sc_csv_table_simple() {
		self::markTestIncomplete();

		/**
		 * Test Valid Table
		 */
		$attrs = [ 'id' => self::$document_csv_post_id ];
		$result	 = SCCSVTable::sc_docmgr_csv_table( $attrs );
		$expected = '<div class="aad-doc-manager"><table class="aad-doc-manager-csv searchHighlight responsive no-wrap" width="100%"><caption>%a</caption>%a</table></div>';
		$this->assertStringMatchesFormat( $expected, $result, "Valid document post type" );
	}

	/**
	 * Test enqueuing of datables JS script
	 */
	function test_action_enqueue_datatables() {
		self::markTestIncomplete();
	}

	/**
	 * Test init of datatables
	 */
	function test_action_init_datatables() {
		self::markTestIncomplete();
	}

	/**
	 * Test CSV storage format 1
	 */
	function test_sc_csv_table_format_1() {
		self::markTestIncomplete();
	}

	/**
	 * Test CSV storage format 2
	 */
	function test_sc_csv_table_format_2() {
		self::markTestIncomplete();
	}

	/**
	 * Test CSV storage format 3
	 */
	function test_sc_csv_table_format_3() {
		self::markTestIncomplete();
	}

	/**
	 * Test date option in csv_table
	 */
	function test_sc_csv_table_date() {
		self::markTestIncomplete();
	}

	/**
	 * Test row-colors option in csv_table
	 */
	function test_sc_csv_table_row_colors() {
		self::markTestIncomplete();
	}

	/**
	 * Test row-number option in csv_table
	 */
	function test_sc_csv_table_row_number() {
		self::markTestIncomplete();
	}

	/**
	 * Test page-length option in csv_table
	 */
	function test_sc_csv_table_page_length() {
		self::markTestIncomplete();
	}

	/**
	 * Test rows option in csv_table
	 */
	function test_sc_csv_table_rows() {
		self::markTestIncomplete();
	}

	/**
	 * Test SCCreated shortcode with invalid id
	 */
	function test_sc_created_empty() {

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
		$attrs['id'] = "8999999";
		$this->assertEquals( "", SCCreated::sc_docmgr_created( $attrs ), "Post does not exist" );

		/**
		 * Wrong post type
		 */
		$attrs['id'] = self::$normal_post_id;
		$this->assertEquals( "", SCCreated::sc_docmgr_created( $attrs ), "Wrong post type" );

		/**
		 * Document exists, but wrong post status
		 */
		$attrs['id'] = self::$document_revision_post_id;
		$this->assertEquals( "", SCCreated::sc_docmgr_created( $attrs ), "Wrong post status" );
	}

	/**
	 * Test SCCreated shortcode with invalid id
	 */
	function test_sc_created() {
		/**
		 * Valid Document
		 */
		$attrs['id'] = self::$document_csv_post_id;
		$this->assertEquals( 'June 13, 2018', SCCreated::sc_docmgr_created( $attrs ), "Valid post" );
	}

	/**
	 * Test SCModified shortcode with invalid document
	 */
	function test_sc_modified_empty() {

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

		/**
		 * Document exists, but wrong post status
		 */
		$attrs['id'] = self::$document_revision_post_id;
		$this->assertEquals( '', SCModified::sc_docmgr_modified( $attrs ), "Wrong post status" );
	}

	/**
	 * Test SCModified shortcode with valid document
	 */
	function test_sc_modified() {
		/**
		 * Valid document
		 */
		$attrs = [ 'id' => self::$document_csv_post_id ];
		$this->assertEquals( "June 13, 2018", SCModified::sc_docmgr_modified( $attrs ), "Valid post" );
	}

	/**
	 * Test SCModified shortcode with invalid ID
	 */
	function test_sc_download_url_empty() {

		/**
		 * Null Input
		 */
		$attrs = [];
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( $attrs ), "Null input returns empty string" );

		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs['id'] = "Some weird stuff";
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( $attrs ), "Text input returns empty string" );

		/**
		 * Post-id that does not exist
		 */
		$attrs['id'] = "8999999";
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( $attrs ), "Post does not exist" );

		/**
		 * Wrong post type
		 */
		$attrs['id'] = self::$normal_post_id;
		$this->assertEquals( "", SCDownloadURL::sc_docmgr_download_url( $attrs ), "Wrong post type" );

		/**
		 * Document exists, but wrong post status
		 */
		$attrs['id'] = self::$document_revision_post_id;
		$this->assertEquals( '', SCDownloadURL::sc_docmgr_download_url( $attrs ), "Wrong post status" );
	}

	/**
	 * Test SCModified shortcode with valid ID
	 */
	function test_sc_download_url() {
		self::markTestIncomplete();

		/**
		 * Valid document
		 */
		$attrs['id'] = self::$document_pdf_post_id;
		$expected = 'a url';
		$this->assertEquals( $expected, SCDownloadURL::sc_docmgr_download_url( $attrs ), "Valid download URL" );
	}

}
