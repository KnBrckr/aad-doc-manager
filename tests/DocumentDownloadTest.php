<?php

use PumaStudios\DocManager\DocumentDownload;

/**
 * Class DocumumentDownloadTest
 *
 * @package PumaStudios-DocManager
 */
class DocumentDownloadTest extends WP_UnitTestCase {

	/**
	 * Test class initialization when WooCommerce is not present
	 */
	function test_run() {
		DocumentDownload::run();
		$this->assertEquals( 10, has_action( 'template_redirect', array( DocumentDownload::class, 'action_try_endpoint' ) ), 'Hooked to action template_redirect' );
		$this->assertEquals( false, has_action( 'woocommerce_downloadable_file_exists', array( DocumentDownload::class, 'filter_woo_downloadable_file_exists' ) ), 'WooCommerce hooks not present' );
	}

	/**
	 * Test class initialization with WooCommerce present
	 */
	function test_run_with_woo() {
		self::markTestIncomplete();
	}

	/**
	 * Test download endpoint
	 */
	function test_action_try_endpoint() {
		self::markTestIncomplete();
	}

	/**
	 * Test woocommerce filter
	 */
	function test_filter_woo_downloadable_file_exists() {
		self::markTestIncomplete();
	}

	/**
	 * Test retrieval of download URL
	 */
	function test_get_download_url() {
		self::markTestIncomplete();
	}

}
