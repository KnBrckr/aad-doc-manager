<?php

/**
 * Class TestDocumentAdmin
 *
 * @package PumaStudios-DocManager
 */
use PumaStudios\DocManager\DocumentAdmin;

/**
 * Test Admin portion of Document support
 *
 * @group admin
 * @group document
 */
class TestDocumentAdmin extends WP_UnitTestCase {

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

	/**
	 * Per test setup
	 */
	function setUp() {
		/**
		 * Run as admin role
		 */
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

//		/**
//		 * Set current screen to edit screen
//		 */
//
//		Needed?
//
//		set_current_screen( 'edit.php' );

		parent::setUp();
	}

	/**
	 * Per test teardown
	 */
	function tearDown() {
		/**
		 * Remove document attachments before rolling back the DB
		 */
		$this->factory->document->destroy();

		/**
		 * Turn off admin mode
		 */
		set_current_screen( 'front' );

		/**
		 * Rollback WP environment
		 */
		parent::tearDown();
	}

	/**
	 * Confirm initial setup hooks admin_menu
	 */
	function test_run() {
		DocumentAdmin::run();
		$this->assertEquals( 10, has_action( 'admin_menu', 'PumaStudios\\DocManager\\DocumentAdmin::action_create_menu' ), 'Hooked to action action_create_menu' );
	}

	/**
	 * Test Creation of admin menu item and related sub-pages
	 */
	function test_action_create_menu() {
		DocumentAdmin::action_create_menu();

		/**
		 * Main page exists?
		 */
		$this->assertStringEndsWith( '?page=' . DocumentAdmin::DOCUMENT_MENU_SLUG, menu_page_url( DocumentAdmin::DOCUMENT_MENU_SLUG, false ) );

		/**
		 * Actions for main page
		 */
		$hook_suffix_main = get_plugin_page_hookname( DocumentAdmin::DOCUMENT_MENU_SLUG, '' );
		$this->assertEquals( 10, has_action( 'load-' . $hook_suffix_main, 'PumaStudios\\DocManager\\DocumentAdmin::action_prerender_table_page' ), 'Main admin screen load action' );
		$this->assertEquals( 10, has_action( 'admin_print_styles-' . $hook_suffix_main, 'PumaStudios\\DocManager\\DocumentAdmin::action_enqueue_admin_styles' ), 'Main admin print style' );

		/**
		 * Upload page exists?
		 */
		$this->assertStringEndsWith( 'admin.php?page=' . DocumentAdmin::UPLOAD_PAGE_SLUG, menu_page_url( DocumentAdmin::UPLOAD_PAGE_SLUG, false ) );

		/**
		 * Actions for upload sub-page
		 */
		$hook_suffix_upload = get_plugin_page_hookname( DocumentAdmin::UPLOAD_PAGE_SLUG, DocumentAdmin::DOCUMENT_MENU_SLUG );
		$this->assertEquals( 10, has_action( 'load-' . $hook_suffix_upload, 'PumaStudios\\DocManager\\DocumentAdmin::action_process_upload_form' ), 'Doc Upload screen load action' );
		$this->assertEquals( 10, has_action( 'admin_print_styles-' . $hook_suffix_upload, 'PumaStudios\\DocManager\\DocumentAdmin::action_enqueue_admin_styles' ), 'Doc Upload print style' );
	}

	function test_action_prerender_table_page_no_edit() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( DocumentAdmin::action_prerender_table_page(), "User can't edit" );
	}

	function test_action_prerender_table_page() {
		self::markTestIncomplete();
	}

	function test_action_render_document_table() {
		self::markTestIncomplete();
	}

	function test_action_enqueue_admin_styles() {
		self::markTestIncomplete();
	}

	function test_action_process_upload_form() {
		self::markTestIncomplete();
	}

	function test_render_upload_page() {
		self::markTestIncomplete();
	}

}
