<?php

/**
 * Class TestDocumentAdmin
 *
 * @package PumaStudios-DocManager
 */
use PumaStudios\DocManager\DocumentAdmin;
use PumaStudios\DocManager\Document;

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
		 * Clear any actions
		 */
		unset( $_REQUEST['action'] );

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
	 * Get singleton instance of DocumentAdmin
	 *
	 * @return DocumentAdmin
	 */
	private function get_document_admin() {
		static $document_admin;

		if ( $document_admin ) {
			return $document_admin;
		}

		$document_admin = new DocumentAdmin();
		return $document_admin;
	}

	/**
	 * Confirm initial setup hooks admin_menu
	 */
	function test_run() {
		$this->get_document_admin()->run();
		$this->assertEquals( 10, has_action( 'admin_menu', array( $this->get_document_admin(), 'action_create_menu' ) ), 'Hooked to action action_create_menu' );
	}

	/**
	 * Test Creation of admin menu item and related sub-pages
	 */
	function test_action_create_menu() {
		$this->get_document_admin()->action_create_menu();

		/**
		 * Main page exists?
		 */
		$this->assertStringEndsWith( '?page=' . DocumentAdmin::DOCUMENT_MENU_SLUG, menu_page_url( DocumentAdmin::DOCUMENT_MENU_SLUG, false ) );

		/**
		 * Actions for main page
		 */
		$hook_suffix_main = get_plugin_page_hookname( DocumentAdmin::DOCUMENT_MENU_SLUG, '' );
		$this->assertEquals( 10, has_action( 'load-' . $hook_suffix_main, array( $this->get_document_admin(), 'action_prerender_table_page' ) ), 'Main admin screen load action' );
		$this->assertEquals( 10, has_action( 'admin_print_styles-' . $hook_suffix_main, array( $this->get_document_admin(), 'action_enqueue_admin_styles' ) ), 'Main admin print style' );

		/**
		 * Upload page exists?
		 */
		$this->assertStringEndsWith( 'admin.php?page=' . DocumentAdmin::UPLOAD_PAGE_SLUG, menu_page_url( DocumentAdmin::UPLOAD_PAGE_SLUG, false ) );

		/**
		 * Actions for upload sub-page
		 */
		$hook_suffix_upload = get_plugin_page_hookname( DocumentAdmin::UPLOAD_PAGE_SLUG, DocumentAdmin::DOCUMENT_MENU_SLUG );
		$this->assertEquals( 10, has_action( 'load-' . $hook_suffix_upload, array( $this->get_document_admin(), 'action_process_upload_form' ) ), 'Doc Upload screen load action' );
		$this->assertEquals( 10, has_action( 'admin_print_styles-' . $hook_suffix_upload, array( $this->get_document_admin(), 'action_enqueue_admin_styles' ) ), 'Doc Upload print style' );
	}

	/**
	 * Test pre-render for user without edit privilege
	 */
	function test_action_prerender_table_page_no_edit() {
		$saved_user_id = get_current_user_id();

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$result = $this->get_document_admin()->action_prerender_table_page();

		wp_set_current_user( $saved_user_id );

		$this->assertFalse( $result, "User can't edit" );
	}

	/**
	 * Test pre-render when no action specified
	 */
	function test_action_prerender_table_page_no_action() {
		$this->assertTrue( $this->get_document_admin()->action_prerender_table_page(), "No table action" );
	}

	/**
	 * Test pre-render with expired NONCE
	 */
	function test_action_prerender_table_page_expired() {
		$_REQUEST['action'] = 'dosomething';

		$this->setExpectedException( WPDieException::class, 'The link you followed has expired.' );
		$this->get_document_admin()->action_prerender_table_page();
	}

	/**
	 * Test pre-render with valid nonce, no docids
	 */
	function test_action_prerender_table_page_no_docids() {
		$_REQUEST['action'] = 'dosomething';

		$post_type_object		 = get_post_type_object( Document::POST_TYPE );
		$nonce					 = wp_create_nonce( 'bulk-' . sanitize_key( $post_type_object->labels->name ) );
		$_REQUEST['_wpnonce']	 = $nonce;

		$doc_admin = $this->getMockBuilder( PumaStudios\DocManager\DocumentAdmin::class )
			->setMethods( [ 'call_wp_redirect' ] )
			->getMock();

		$doc_admin->expects( $this->once() )
			->method( 'call_wp_redirect' ); // 	->with( $this->equalTo('?paged=1'));

		$doc_admin->action_prerender_table_page();
	}

	function test_action_prerender_table_page_delete_all() {
		self::markTestIncomplete();
	}

	function test_action_prerender_table_page_trash() {
		self::markTestIncomplete();
	}

	function test_action_prerender_table_page_untrash() {
		self::markTestIncomplete();
	}

	function test_action_prerender_table_page_delete() {
		self::markTestIncomplete();
	}

	function test_action_prerender_table_page() {
		self::markTestIncomplete();
	}

	function test_render_document_table() {
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
