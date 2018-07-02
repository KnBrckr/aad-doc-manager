<?php
/*
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
 * Manage Admin screens for Document Post Type
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class DocumentAdmin {

	/**
	 * @var const slug for Parent Menu page -- Table of all Document files
	 */
	const DOCUMENT_MENU_SLUG = 'pumastudios-docmgr-table';

	/**
	 * @var const Slug for page to upload documents
	 */
	const UPLOAD_PAGE_SLUG = 'pumastudios-docmgr-upload';

	/**
	 * @var const Nonce name to use for form validation
	 */
	const NONCE = 'pumastudios-docmgr-nonce';

	/**
	 * Instantiate
	 */
	public function __construct() {

	}

	/**
	 * Plug into WP
	 */
	public static function run() {
		/**
		 * Setup admin page for managing Documents
		 */
		add_action( 'admin_menu', array( DocumentAdmin::class, 'action_create_menu' ) );
	}

	/**
	 * Create admin menu section for managing Documents
	 */
	public static function action_create_menu() {

		/**
		 * Get post object for access to Document post type labels
		 */
		$obj = get_post_type_object( Document::POST_TYPE );

		/**
		 * Create a new Admin Menu page
		 */
		$hook_suffix = add_menu_page( $obj->labels->name, // Page Title
								$obj->labels->menu_name, // Menu Title,
								'edit_posts', // User must be able to edit posts for menu to display
								self::DOCUMENT_MENU_SLUG, // Slug name for this menu page
								[ DocumentAdmin::class, 'render_document_table' ], // Function to render the menu content
								'dashicons-media-spreadsheet', // Icon for  menu
								21.215 // Position - After Pages(20), try for a unique number
		);

		/**
		 * add_menu_page() will return false if user does not have the needed capability
		 * No need to add actions or create sub-pages if that's the case
		 */
		if ( $hook_suffix ) {
			/**
			 * Pre-render processing for the table screen
			 */
			add_action( 'load-' . $hook_suffix, [ self::class, 'action_prerender_table_page' ] );

			/**
			 * Add plugin styles for table screen
			 */
			add_action( 'admin_print_styles-' . $hook_suffix, array( self::class, 'action_enqueue_admin_styles' ) );

			/**
			 * Create first sub-page as a duplicate of the main page
			 */
			// RFE Change privelege to upload_files vs. edit_posts
			add_submenu_page( 'aad-doc-manager-table', // Submenu of parent
					 $obj->labels->name, // Page Title
					 $obj->labels->all_items, // Menu Title
					 'edit_posts', // Capability
					 self::DOCUMENT_MENU_SLUG, // Menu slug - match the parent
					 [ self::class, 'render_document_table' ]
			);

			/**
			 * Create menu item to upload a new Document
			 */
			$hook_suffix = add_submenu_page( self::DOCUMENT_MENU_SLUG, // Slug of Parent Menu
									$obj->labels->add_new_item, // Page Title
									$obj->labels->add_new, // Menu Title
									'edit_posts', // User must be able to edit posts for menu to display
									self::UPLOAD_PAGE_SLUG, // Slug for this Menu Page
									[ self::class, 'render_upload_page' ]
			);

			/**
			 * Setup form handler to receive new/updated documents
			 */
			add_action( 'load-' . $hook_suffix, array( self::class, 'action_process_upload_form' ) );

			/**
			 * Add plugin styles for upload screen
			 */
			add_action( 'admin_print_styles-' . $hook_suffix, array( self::class, 'action_enqueue_admin_styles' ) );
		}
	}

	/**
	 * Perform pre-render actions associated with the Document management page
	 *
	 * Done before render during page load to apply any admin notice feedback that might be needed
	 */
	public static function action_prerender_table_page() {
		global $wpdb;

		/**
		 * User must be able to edit posts
		 */
		if ( !current_user_can( 'edit_posts' ) ) {
			return false;
		}

		/**
		 * Get post object for access to Document post type labels
		 */
		$obj = get_post_type_object( Document::POST_TYPE );

		/**
		 * To avoid a processing loop, remove action to catch media deletion via wp-admin/upload.php
		 */
		remove_action( 'delete_attachment', array( self::class, 'action_delete_document' ) );

		$doc_table = new DocManagerTable( array(
			'singular'				 => $obj->labels->singular_name,
			'plural'				 => $obj->labels->name, // Plural label
			'ajax'					 => false, // Will not support AJAX on this table
			'upload_url'			 => menu_page_url( self::UPLOAD_PAGE_SLUG, false ),
			'table_url'				 => menu_page_url( self::DOCUMENT_MENU_SLUG, false )
			) );

		$pagenum = $doc_table->get_pagenum();

		// FIXME Deletes processed through post.php need to remove the media files

		/**
		 * Handle bulk actions
		 */
		$action = $doc_table->current_action();
		if ( $action ) {
			/**
			 * Verify nonce - Setup by WP_List_Table base class based on the plural label
			 */
			check_admin_referer( 'bulk-' . sanitize_key( self::$post_type_labels['name'] ) );

			/**
			 * Prepare redirect URL
			 */
			$sendback	 = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'doc_ids', 'ids', 'action', 'action2' ), wp_get_referer() );
			if ( !$sendback )
				$sendback	 = menu_page_url( self::parent_slug, false ); // FIXME This works only if URL contains no embedded '&'
			$sendback	 = add_query_arg( 'paged', $pagenum, $sendback );

			/**
			 * Grab the list of post ids to work with
			 */
			if ( 'delete_all' == $action ) {
				// Convert 'delete_all' action to delete action with a list
				$doc_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", self::post_type, 'trash' ) );
				$action	 = 'delete';
			} elseif ( !empty( $_REQUEST['doc_ids'] ) )
				$doc_ids = array_map( 'intval', $_REQUEST['doc_ids'] );
			else {
				wp_safe_redirect( $sendback ); // List of posts not provided, bail out
				exit;
				// NOT REACHED
			}

			/**
			 * Perform requested action
			 */
			switch ( $action ) {
				case 'trash':
					/**
					 * Move posts to Trash
					 */
					$trashed = $locked	 = 0;

					foreach ( (array) $doc_ids as $doc_id ) {
						if ( !current_user_can( 'delete_post', $doc_id ) )
							wp_die( __( 'You are not allowed to move this item to the Trash.', TEXT_DOMAIN ) );

						if ( wp_check_post_lock( $doc_id ) ) { // TODO - How to lock a post?
							$locked++;
							continue;
						}

						if ( !wp_trash_post( $doc_id ) )
							wp_die( __( 'Error moving post to Trash.', TEXT_DOMAIN ) );

						$trashed++;
					}

					/**
					 * Send operation results back
					 */
					$sendback = add_query_arg(
						array( 'trashed' => $trashed, 'ids' => join( ',', $doc_ids ), 'locked' => $locked ), $sendback );
					break;

				case 'untrash':
					/**
					 * Restore Posts from Trash
					 */
					$untrashed = 0;

					foreach ( (array) $doc_ids as $doc_id ) {
						if ( !current_user_can( 'delete_post', $doc_id ) )
							wp_die( __( 'You are not allowed to restore this item from the Trash.', TEXT_DOMAIN ) );

						if ( !wp_untrash_post( $doc_id ) )
							wp_die( __( 'Error in restoring from Trash.', TEXT_DOMAIN ) );

						$untrashed++;
					}

					$sendback = add_query_arg( 'untrashed', $untrashed, $sendback );
					break;

				case 'delete':
					/**
					 * Permanently delete posts from Trash
					 */
					$deleted = 0;

					foreach ( (array) $doc_ids as $doc_id ) {
						if ( !current_user_can( 'delete_post', $doc_id ) )
							wp_die( __( 'You are not allowed to delete this item.', TEXT_DOMAIN ) );

						/**
						 * Delete related media attachment
						 */
						$media_id = get_post_meta( $doc_id, 'document_media_id', true );
						if ( $media_id ) {
							if ( !wp_delete_attachment( $media_id, true ) )
								wp_die( __( 'Error deleting attached document.', TEXT_DOMAIN ) );
						}

						/**
						 * Delete the document post
						 */
						if ( !wp_delete_post( $doc_id ) ) {
							wp_die( __( 'Error in deleting document post.', TEXT_DOMAIN ) );
						}

						$deleted++;
					}

					$sendback = add_query_arg( 'deleted', $deleted, $sendback );
					break;
			}

			wp_safe_redirect( $sendback );
			exit;
		} elseif ( !empty( $_REQUEST['_wp_http_referer'] ) ) {
			/**
			 * No action provided, if nonce was given, redirect back without nonce
			 */
			wp_safe_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}

		/**
		 * Report on earlier bulk action
		 */
		$locked		 = isset( $_REQUEST['locked'] ) ? intval( $_REQUEST['locked'] ) : NULL;
		$trashed	 = isset( $_REQUEST['trashed'] ) ? intval( $_REQUEST['trashed'] ) : NULL;
		$deleted	 = isset( $_REQUEST['deleted'] ) ? intval( $_REQUEST['deleted'] ) : NULL;
		$untrashed	 = isset( $_REQUEST['untrashed'] ) ? intval( $_REQUEST['untrashed'] ) : NULL;

		if ( $locked ) {
			Plugin::admin_warn( sprintf( __( 'Skipped %d locked documents.', TEXT_DOMAIN ), $locked ) );
		}
		if ( $trashed ) {
			Plugin::admin_log( sprintf( __( 'Moved %d documents to the trash.', TEXT_DOMAIN ), $trashed ) );
		}
		if ( $deleted ) {
			Plugin::admin_log( sprintf( __( 'Permanently deleted %d documents from trash.', TEXT_DOMAIN ), $deleted ) );
		}
		if ( $untrashed ) {
			Plugin::admin_log( sprintf( __( "Restored %d documents from trash.", TEXT_DOMAIN ), $untrashed ) );
		}

		$container = plugin_container();
		$container->set( 'doc_table', $doc_table );

		return true;
	}

	/**
	 * Render page to manage Documents
	 *
	 *
	 * @uses self::parent_slug, slug for this page
	 * @uses self::upload_slug, slug for upload page
	 * @return void
	 */
	public static function render_document_table() {
		/**
		 * User must be able to edit posts
		 */
		if ( !current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		/**
		 * Get post object for access to Document post type labels
		 */
		$obj = get_post_type_object( Document::POST_TYPE );

		/**
		 * Perform table query
		 */
		$doc_table = plugin_container()->get( 'doc_table' );
		$doc_table->prepare_items();
		?>

		<div class="wrap">
			<h2><?php echo $obj->name; ?> <a href="<?php menu_page_url( self::UPLOAD_PAGE_SLUG, true ); ?>" class="add-new-h2"><?php echo $obj->labels->add_new_item; ?></a></h2>
			<?php $doc_table->views(); // Display the views available on the table     ?>
			<form action method="post" accept-charset="utf-8">
				<input type="hidden" name="page" value="<?php echo self::DOCUMENT_MENU_SLUG ?>">
				<?php
//	RFE				$this->doc_table->search_box('search', 'search_id'); // Must follow prepare_items() call
				$doc_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue style sheet needed on admin page
	 */
	public static function action_enqueue_admin_styles() {
		$container = plugin_container();
		/**
		 * Enqueue Admin area CSS for table
		 */
		wp_register_style( 'aad-doc-manager-admin-css', // Handle
					 $container->get( 'plugin' )->get_asset_url( 'css', 'aad-doc-manager-admin.css' ), // Asset URL
												  false, // No Dependencies
												  PLUGIN_VERSION, // CSS Version
												  'all'  // Use for all media types
		);

		wp_enqueue_style( 'aad-doc-manager-admin-css' );
	}

	/**
	 * Process upload form content
	 *
	 * Either a new document is being added or an existing document is being updated.
	 * For updates, a new uploaded file is not required.
	 *
	 * Redirects back to document list page if a form was processed
	 */
	public static function action_process_upload_form() {
		// FIXME If upload fails, don't update post! Need to refactor order of operations?
		// FIXME CSV file upload does not create attachment/guid for download operation. Maybe only an issue on updating files?
		/**
		 * User must be able to edit posts
		 */
		if ( !current_user_can( 'edit_posts' ) ) {
			return;
		}

		/**
		 * Has an action been requested?
		 */
		$action = self::get_action();
		if ( !$action ) {
			return;
		}

		/**
		 * Is nonce valid? check_admin_referrer will die if invalid.
		 */
		check_admin_referer( self::upload_slug, self::NONCE );

		/**
		 * To avoid a processing loop, remove action to catch media deletion via wp-admin/upload.php
		 */
		remove_action( 'delete_attachment', array( $this, 'action_delete_document' ) );

		/**
		 * Initialize new/updated post content
		 */
		$post = array();

		switch ( $action ) {
			case 'new':
				/**
				 * Adding a new document
				 */


				break;

			case 'update';
				/**
				 * Update existing post
				 */
				$doc_id = isset( $_REQUEST['doc_id'] ) ? intval( $_REQUEST['doc_id'] ) : NULL;
				if ( !$doc_id ) {
					Plugin::admin_error( __( 'Bad Request - No post id to update; plugin error?', TEXT_DOMAIN ) );
					return;
				}
				$post['ID'] = $doc_id;

				/**
				 * Make sure we're updating proper post type
				 */
				$old_post = get_post( $doc_id );
				if ( $old_post && $old_post->post_type != self::post_type ) {
					Plugin::admin_error( __( 'Sorry - Request to update invalid document id; plugin error?', TEXT_DOMAIN ) );
					return;
				}

				break;
		}

		/**
		 * Setup post content for uploaded file
		 */
		if ( !empty( $_FILES ) && isset( $_FILES['document'] ) ) {
			switch ( $_FILES['document']['error'] ) {
				case UPLOAD_ERR_OK:
					/**
					 * File Uploaded OK
					 */
					$have_upload = true;

					/**
					 * Process document content (if appropriate for the content type)
					 */
					$doc_content = $this->get_document_content( $doc_type, $tmp_file );
					if ( isset( $doc_content['error'] ) ) { // FIXME - should be a thrown error
						Plugin::admin_error( sprintf( __( 'Failed processing document content: %s', TEXT_DOMAIN ), $doc_content['error'] ) );
						return;
					}

					/**
					 * Update post content if new document uploaded
					 */
					$post['post_content'] = $doc_content['post_content'];
					break;


			}
		} else {
			$have_upload = false;
		}

		/**
		 * Document title from request if provided, otherwise use filename
		 */
		$post['post_title'] = sanitize_text_field( isset( $_REQUEST['doc_title'] ) && '' != $_REQUEST['doc_title'] ?
			$_REQUEST['doc_title'] : $post['post_title'] );

		switch ( $action ) {
			case 'new':
				/**
				 * New uploads require a file to be provided
				 */
				if ( !$have_upload ) {
					Plugin::admin_error( __( "Required document not provided.", TEXT_DOMAIN ) );
					return;
				}

				/**
				 * Insert new post
				 */
				$document = Document::create_document( $post, 'document' );
				if ( is_wp_error( $document )) {
					Plugin::admin_error( $document->get_error_message() );
					return;
				}

				break;

			case 'update':
				/**
				 * Update the post
				 */
				wp_update_post( $post );
				break;
		}


		/**
		 * Save meta data related to document content
		 */
		if ( isset( $doc_content ) ) {
			// FIXME Remove old document meta data
			foreach ( $doc_content['post_meta'] as $key => $value ) {
				update_post_meta( $doc_id, $key, $value );
			}
		}

		// RFE Add result reporting
		wp_safe_redirect( menu_page_url( self::parent_slug, false ) );
		exit;
	}

	/**
	 * Render page to upload new Documents
	 *
	 * @uses self::upload_slug, slug for this page
	 * @return void
	 */
	public static function render_upload_page() {
		/**
		 * User must be able to edit posts
		 */
		if ( !current_user_can( 'edit_posts' ) ) {
			return;
		}

		/**
		 * Updating an existing document?
		 */
		$doc_id		 = isset( $_REQUEST['doc_id'] ) ? intval( $_REQUEST['doc_id'] ) : NULL;
		$document	 = Document::get_instance( $doc_id );
		$post		 = $document ? $document->get_post() : NULL;

		/**
		 * Setup default values for new vs. updating existing document
		 */
		if ( !$document ) { // Don't allow other post-types
			$doc_id			 = NULL;
			$post			 = NULL;
			$title			 = isset( $_REQUEST['doc_title'] ) ? $_REQUEST['doc_title'] : "";
			$checked		 = 'checked';
			$file_required	 = 'required';

			/**
			 * All available Document types accepted for upload
			 */
			$accepted_doc_types = implode( Document::get_supported_mime_types(), ', ' );
		} else {
			$title			 = $post->post_title;
			$csv_rows		 = get_post_meta( $doc_id, 'csv_rows', true );
			$csv_cols		 = get_post_meta( $doc_id, 'csv_cols', true );
			$checked		 = get_post_meta( $doc_id, 'csv_has_col_headers', true ) ? 'checked' : '';
			$file_required	 = '';

			/**
			 * When updating an existing file, only allow the same mime type to be uploaded
			 */
			$accepted_doc_types = $post->post_mime_type;
		}

		$document_object = get_post_type_object( Document::POST_TYPE );
		$action			 = $document ? $document_object->labels->edit_item : $document_object->labels->new_item;
		?>
		<div class="wrap">
			<h2><?php echo esc_attr( $action ); ?></h2>
			<form action method="post" accept-charset="utf-8" enctype="multipart/form-data">
				<input type="hidden" name="page" value="<?php echo self::UPLOAD_PAGE_SLUG ?>">
				<?php if ( $doc_id ) : ?>
					<input type="hidden" name="doc_id" value="<?php echo esc_attr( $doc_id ); ?>" id="doc_id">
				<?php endif; ?>
				<?php wp_nonce_field( self::UPLOAD_PAGE_SLUG, self::NONCE ); ?>
				<div class="titlewrap">
					<label for="title" class="title-prompt-text"><?php _e( 'Document Title:', TEXT_DOMAIN ); ?></label>
					<input type="text" name="doc_title" value="<?php echo esc_attr( $title ); ?>" id="title" size="50" autocomplete="off" placeholder="Defaults to name of uploaded file if none specified">
				</div>
				<?php if ( $doc_id ) : ?>
					<div class="postbox">
						<h3><?php _e( 'Uploaded Document Info', TEXT_DOMAIN ); ?></h3>
						<div class="inside">
							<dl>
								<dt><?php _e( 'Document ID', TEXT_DOMAIN ); ?></dt>
								<dd><?php esc_html_e( $doc_id ); ?></dd>
								<dt><?php _e( 'Document Type', TEXT_DOMAIN ); ?></dt>
								<dd><?php esc_html_e( $post->post_mime_type ); ?></dd>
								<dt><?php _e( 'Date Created', TEXT_DOMAIN ); ?></dt>
								<dd><?php esc_html_e( $post->post_date ); ?></dd>
								<dt><?php _e( 'Date Last Modified', TEXT_DOMAIN ); ?></dt>
								<dd><?php esc_html_e( $post->post_modified ); ?></dd>
								<?php if ( 'text/csv' == $post->post_mime_type ): ?>
									<dt><?php _e( 'Rows', TEXT_DOMAIN ); ?></dt>
									<dd><?php echo intval( $csv_rows ); ?></dd>
									<dt><?php _e( 'Columns', TEXT_DOMAIN ); ?></dt>
									<dd><?php echo intval( $csv_cols ); ?></dd>
								<?php endif; ?>
							</dl>
						</div>
					</div>
				<?php endif; ?>
				<div class="postbox">
					<h3>Upload</h3>
					<div class="inside">
						<div>
							<label for="document"><?php _e( 'Select document to upload', TEXT_DOMAIN ); ?></label><br>
							<input type="file" id="document" name="document" value="" size="40" accept="<?php echo esc_attr( $accepted_doc_types ); ?>" <?php echo esc_attr( $file_required ); ?>/>
							<div>
								<?php
								_e( 'Supported file types: ', TEXT_DOMAIN );
								esc_html_e( $accepted_doc_types );
								?>
							</div>
						</div>
						<div class="upload_option">
							<?php _e( 'For CSV File Upload (ignored for other document types):', TEXT_DOMAIN ); ?><br />
							<input type="checkbox" <?php echo esc_attr( $checked ); ?> id="csv-has-col-headers" name="csv-has-col-headers" value="yes">
							<label for="csv-has-col-headers"><?php _e( 'First Row contains column names', TEXT_DOMAIN ); ?></label>
						</div>
					</div>
				</div>
				<?php submit_button( $action, 'apply', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Determine which action is being taken during file upload
	 *
	 * @return string, name of action or NULL
	 */
	private static function get_action() {
		if ( empty( $_REQUEST ) || !isset( $_REQUEST['submit'] ) )
			return NULL;
		elseif ( $_REQUEST['submit'] == self::$post_type_labels['new_item'] )
			return 'new';
		elseif ( $_REQUEST['submit'] == self::$post_type_labels['edit_item'] )
			return 'update';
		else
			return NULL;
	}

}
