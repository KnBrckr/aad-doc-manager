<?php
/*
 * Class to manage admin screens
 *
 * @package Document Manager
 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
 * @copyright 2017 Kenneth J. Brucker (email: ken.brucker@action-a-day.com)
 *
 * This file is part of Document Manager, a plugin for Wordpress.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// RFE Enable editing of headers on saved files

/**
 * Protect from direct execution
 */
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( "aadDocManagerAdmin" ) ) {
	class aadDocManagerAdmin extends aadDocManager
	{
		/**
		 * Slugs for admin menu pages
		 */
		const parent_slug = 'aad-doc-manager-table';	// Parent Menu page -- Table of all Document files
		const upload_slug = 'aad-doc-manager-upload';	// Upload Document Page

		/**
		 * Nonce name to use for form validation
		 */
		const nonce = 'aad-doc-manager-nonce';

		/**
		 * Accepted document types
		 *
		 * @var array of strings
		 */
		protected $accepted_doc_types;

		/**
		 * Errors and warnings to display on admin screens
		 *
		 * @var array
		 **/
		protected $admin_notices;

		function __construct()
		{
			parent::__construct();

			/**
			 * List of accepted document types
			 */
			// RFE Add settings screen to control available document types
			$this->accepted_doc_types = array( 'text/csv', 'application/pdf' );

			/**
			 * Empty list of admin notices
			 */
			$this->admin_notices = array();

		}

		/**
		 * Do the initial hooking into WordPress
		 *
		 * @return void
		 */
		function setup()
		{
			parent::setup();

			/**
			 * Setup admin page for managing Documents
			 */
			add_action( 'admin_menu', array( $this, 'action_create_menu' ) );

			/**
			 * Do plugin initialization
			 */
			add_action( 'admin_init', array( $this, 'action_plugin_admin_setup' ) );
		}

		/**
		 * Perform WordPress Setup
		 *
		 * @return void
		 */
		function action_plugin_admin_setup()
		{
			/**
			 * Add section for reporting configuration errors and notices
			 */
			add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

			/**
			 * Catch media deletes via wp-admin/upload.php to remove the associated document
			 */
			add_action( 'delete_attachment', array($this, 'action_delete_document'), 10, 1 );
		}

		/**
		 * Create admin menu section for managing Documents
		 *
		 * @return void
		 */
		function action_create_menu()
		{
			/**
			 * Create a new Admin Menu page
			 */
			$hook_suffix = add_menu_page(
				self::$post_type_labels['name'], // Page Title
				self::$post_type_labels['menu_name'], // Menu Title,
				'edit_posts', // User must be able to edit posts for menu to display
				self::parent_slug, // Menu slug
				array( $this, 'render_table_page' ), // Function to render the menu content
				'dashicons-media-spreadsheet', // Icon for  menu
				21.215 // Position - After Pages(20), try for a unique number
			);

			/**
			 * add_menu_page() will return false if user does not have the needed capability
			 * No need to add actions or create sub-pages if that's the case
			 */
			if ($hook_suffix) {
				/**
				 * Pre-render processing for the table screen
				 */
				add_action( 'load-' . $hook_suffix, array( $this, 'action_prerender_table_page' ) );

				/**
				 * Add plugin styles for table screen
				 */
				add_action( 'admin_print_styles-' . $hook_suffix, array( $this, 'action_enqueue_admin_styles' ) );

				/**
				 * Create first sub-page as a duplicate of the main page
				 */
				// FIXME Change privelege to upload_files vs. edit_posts
				add_submenu_page(
					'aad-doc-manager-table', // Submenu of parent
					self::$post_type_labels['name'], // Page Title
					self::$post_type_labels['all_items'], // Menu Title
					'edit_posts', // Capability
					self::parent_slug, // Menu slug - match the parent
					array( $this, 'render_table_page' )
				);

				/**
				 * Create menu item to upload a new Document
				 */
				$hook_suffix = add_submenu_page(
					self::parent_slug, // Slug of Parent Menu
					self::$post_type_labels['add_new_item'], // Page Title
					self::$post_type_labels['add_new'], // Menu Title
					'edit_posts', // User must be able to edit posts for menu to display
					self::upload_slug, // Slug for this Menu Page
					array( $this, 'render_upload_page' )
				);

				/**
				 * Setup form handler to receive new/updated documents
				 */
				add_action( 'load-' . $hook_suffix, array($this, 'action_process_upload_form' ) );

				/**
				 * Add plugin styles for upload screen
				 */
				add_action( 'admin_print_styles-' . $hook_suffix, array( $this, 'action_enqueue_admin_styles' ) );
			}
		}

		/**
		 * Enqueue style sheet needed on admin page
		 *
		 * @param void
		 * @return void
		 */
		function action_enqueue_admin_styles()
		{
			/**
			 * Enqueue Admin area CSS for table
			 */
			wp_register_style(
				'aad-doc-manager-admin-css',					// Handle
				plugins_url( '../aad-doc-manager-admin.css', __FILE__ ), // URL to CSS file, relative to this directory
				false,	 										// No Dependencies
				self::PLUGIN_VER,								// CSS Version
				'all'											// Use for all media types
			);

			wp_enqueue_style( 'aad-doc-manager-admin-css' );
		}

		/**
		 * Perform pre-render actions associated with the Document management page
		 *
		 * Done before render during page load to apply any admin notice feedback that might be needed
		 *
		 * @return void
		 */
		function action_prerender_table_page()
		{
			global $wpdb;

			/**
			 * User must be able to edit posts
			 */
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( 'Do you belong here?' );
            }

   			/**
			 * To avoid a processing loop, remove action to catch media deletion via wp-admin/upload.php
			 */
			remove_action( 'delete_attachment', array($this, 'action_delete_document') );

			/**
			 * Load class used to manage table of Documents
			 */
            if ( ! include_once( 'aadDocManagerTable.php' ) ) { return; }

			$this->doc_table = new aadDocManagerTable( array(
				'singular' => self::$post_type_labels['singular_name'], // Singular Label
				'plural' => self::$post_type_labels['name'], // Plural label
				'ajax' => false, // Will not support AJAX on this table
				'post_type' => self::post_type,
				'upload_url' => menu_page_url( self::upload_slug, false ),
				'table_url' => menu_page_url( self::parent_slug, false ),
				'labels' => self::$post_type_labels,
				'download_url_callback' => array( $this, 'get_download_url_e' )
			));

			$pagenum = $this->doc_table->get_pagenum();

            // FIXME Deletes processed through post.php need to remove the media files

			/**
			 * Handle bulk actions
			 */
			$action = $this->doc_table->current_action();
			if ( $action ) {
				/**
				 * Verify nonce - Setup by WP_List_Table base class based on the plural label
				 */
				check_admin_referer( 'bulk-' . sanitize_key( self::$post_type_labels['name'] ) );

				/**
				 * Prepare redirect URL
				 */
				$sendback = remove_query_arg( array( 'trashed','untrashed','deleted','locked','doc_ids', 'ids', 'action', 'action2' ), wp_get_referer() );
				if ( ! $sendback )
					$sendback = menu_page_url( self::parent_slug, false ); // FIXME This works only if URL contains no embedded '&'
				$sendback = add_query_arg( 'paged', $pagenum, $sendback );

				/**
				 * Grab the list of post ids to work with
				 */
				if ( 'delete_all' == $action ) {
					// Convert 'delete_all' action to delete action with a list
					$doc_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", self::post_type, 'trash' ) );
					$action = 'delete';
				} elseif ( ! empty( $_REQUEST['doc_ids'] ) )
					$doc_ids = array_map( 'intval', $_REQUEST['doc_ids'] );
				else {
					wp_safe_redirect( $sendback ); // List of posts not provided, bail out
					exit;
					// NOT REACHED
				}

				/**
				 * Perform requested action
				 */
				switch( $action ) {
					case 'trash':
						/**
						 * Move posts to Trash
						 */
						$trashed = $locked = 0;

						foreach ( ( array ) $doc_ids as $doc_id ) {
							if ( ! current_user_can( 'delete_post', $doc_id ) )
								wp_die( __( 'You are not allowed to move this item to the Trash.', 'aad-doc-manager' ) );

							if ( wp_check_post_lock( $doc_id ) ) { // TODO - How to lock a post?
								$locked++;
								continue;
							}

							if ( ! wp_trash_post( $doc_id ) )
								wp_die( __( 'Error moving post to Trash.', 'aad-doc-manager' ) );

							$trashed++;
						}

						/**
						 * Send operation results back
						 */
						$sendback = add_query_arg(
							array( 'trashed' => $trashed, 'ids' => join( ',', $doc_ids ), 'locked' => $locked ),
							$sendback );
						break;

					case 'untrash':
						/**
						 * Restore Posts from Trash
						 */
						$untrashed = 0;

						foreach ( ( array ) $doc_ids as $doc_id ) {
							if ( ! current_user_can( 'delete_post', $doc_id ) )
								wp_die( __( 'You are not allowed to restore this item from the Trash.', 'aad-doc-manager' ) );

							if ( ! wp_untrash_post( $doc_id ) )
								wp_die( __( 'Error in restoring from Trash.', 'aad-doc-manager' ) );

							$untrashed++;
						}

						$sendback = add_query_arg( 'untrashed', $untrashed, $sendback );
						break;

					case 'delete':
						/**
						 * Permanently delete posts from Trash
						 */
						$deleted = 0;

						foreach ( ( array ) $doc_ids as $doc_id ) {
							if (! current_user_can( 'delete_post', $doc_id ) )
								wp_die( __( 'You are not allowed to delete this item.', 'aad-doc-manager' ) );

							/**
							 * Delete related media attachment
							 */
							$media_id = get_post_meta( $doc_id, 'document_media_id', true );
							if ( $media_id ) {
								if ( ! wp_delete_attachment( $media_id, true ) )
									wp_die( __( 'Error deleting attached document.', 'aad-doc-manager' ) );
							}

							/**
							 * Delete the document post
							 */
							if (! wp_delete_post($doc_id)) {
								wp_die( __( 'Error in deleting document post.', 'aad-doc-manager' ) );
                            }

							$deleted++;
						}

						$sendback = add_query_arg( 'deleted', $deleted, $sendback );
						break;
				}

				wp_safe_redirect( $sendback );
				exit;
			} elseif ( ! empty($_REQUEST['_wp_http_referer'] ) ) {
				/**
				 * No action provided, if nonce was given, redirect back without nonce
				 */
				wp_safe_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
				exit;
			}

			/**
			 * Report on earlier bulk action
			 */
			$locked    = isset( $_REQUEST['locked'] )    ? intval( $_REQUEST['locked'] ) : NULL;
			$trashed   = isset( $_REQUEST['trashed'] )   ? intval( $_REQUEST['trashed'] ) : NULL;
			$deleted   = isset( $_REQUEST['deleted'] )   ? intval( $_REQUEST['deleted'] ) : NULL;
			$untrashed = isset( $_REQUEST['untrashed'] ) ? intval( $_REQUEST['untrashed'] ) : NULL;

			if ( $locked )
				$this->log_admin_notice( 'yellow', sprintf( __( 'Skipped %d locked documents.', 'aad-doc-manager' ), $locked ) );
			if ( $trashed )
				$this->log_admin_notice( 'green', sprintf( __( 'Moved %d documents to the trash.', 'aad-doc-manager' ), $trashed ) );
			if ( $deleted )
				$this->log_admin_notice( 'green', sprintf( __( 'Permanently deleted %d documents from trash.', 'aad-doc-manager' ), $deleted ) );
			if ( $untrashed )
				$this->log_admin_notice( 'green', sprintf( __( "Restored %d documents from trash.", 'aad-doc-manager' ), $untrashed ) );
		}

		/**
		 * Render page to manage Documents
		 *
		 *
		 * @uses self::parent_slug, slug for this page
		 * @uses self::upload_slug, slug for upload page
		 * @return void
		 */
		function render_table_page()
		{
			/**
			 * User must be able to edit posts
			 */
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			/**
			 * Perform table query
			 */
			$this->doc_table->prepare_items();
			?>

			<div class="wrap">
				<h2><?php echo self::$post_type_labels['name']; ?> <a href="<?php menu_page_url( self::upload_slug, true ); ?>" class="add-new-h2"><?php echo self::$post_type_labels['add_new_item']?></a></h2>
				<?php $this->doc_table->views(); // Display the views available on the table ?>
				<form action method="post" accept-charset="utf-8">
					<input type="hidden" name="page" value="<?php echo self::parent_slug ?>">
					<?php
//	RFE				$this->doc_table->search_box('search', 'search_id'); // Must follow prepare_items() call
					$this->doc_table->display();
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Process upload form content
		 *
		 * Either a new document is being added or an existing document is being updated.
		 * For updates, a new uploaded file is not required.
		 *
		 * Redirects back to document list page if a form was processed
		 *
		 * @return void
		 */
		function action_process_upload_form()
		{
			// FIXME If upload fails, don't update post! Need to refactor order of operations?
			// FIXME CSV file upload does not create attachment/guid for download operation. Maybe only an issue on updating files?
			/**
			 * User must be able to edit posts
			 */
			if ( ! current_user_can( 'edit_posts' ) )
				wp_die( __( 'Do you belong here?', 'aad-doc-manager' ) );

			/**
			 * Has an action been requested?
			 */
			$action = $this->upload_action();
			if ( ! $action ) return;

			/**
			 * Is nonce valid? check_admin_referrer will die if invalid.
			 */
			check_admin_referer( self::upload_slug, self::nonce );

			/**
			 * To avoid a processing loop, remove action to catch media deletion via wp-admin/upload.php
			 */
			remove_action( 'delete_attachment', array($this, 'action_delete_document') );

			/**
			 * Initialize new/updated post content
			 */
			$post = array();
			$post['post_title'] = '';

			switch( $action ) {
			case 'new':
				/**
				 * Adding a new document
				 */
				$post['post_excerpt'] = '';
				$post['post_type'] = self::post_type;
				$post['post_status'] = 'publish';
				$post['comment_status'] = 'closed';
				$post['ping_status'] = 'closed';

				break;

			case 'update';
				/**
				 * Update existing post
				 */
				$doc_id = isset( $_REQUEST['doc_id'] ) ? intval( $_REQUEST['doc_id'] ) : NULL;
				if ( ! $doc_id ) {
					$this->log_admin_notice( 'red', __( 'Bad Request - No post id to update; plugin error?', 'aad-doc-manager' ) );
					return;
				}
				$post['ID'] = $doc_id;

				/**
				 * Make sure we're updating proper post type
				 */
				$old_post = get_post( $doc_id );
				if ( $old_post && $old_post->post_type != self::post_type ) {
					$this->log_admin_notice( 'red', __( 'Sorry - Request to update invalid document id; plugin error?', 'aad-doc-manager' ) );
					return;
				}

				break;
			}

			/**
			 * Setup post content for uploaded file
			 */
			if ( ! empty( $_FILES ) && isset( $_FILES['document'] ) ) {
				switch( $_FILES['document']['error'] ) {
					case UPLOAD_ERR_OK:
						/**
						 * File Uploaded OK
						 */
						$have_upload = true;

						$doc_type = $_FILES['document']['type'];
						if ( ! in_array( $doc_type, $this->accepted_doc_types ) ) {
							$this->log_admin_notice('red', sprintf( __( 'Document type %s is not supported; plugin error?', 'aad-doc-manager' ), esc_attr( $doc_type ) ) );
							return;
						}
						$post['post_mime_type'] = $doc_type;

						if ( ! isset($_FILES['document']['name'] ) ) {
							$this->log_admin_notice( 'red', __( '$_FILES not sane; plugin error?', 'aad-doc-manager' ) );
							return;
						}
						$post['post_title'] = $_FILES['document']['name'];

						/**
						 * Confirm there's a valid uploaded file
						 */
						$tmp_file = $_FILES['document']['tmp_name'];
						if ( ! is_uploaded_file( $tmp_file ) ) {
							$this->log_admin_notice( 'red', __( 'Something fishy is going on, file does not appear to have been uploaded properly', 'aad-doc-manager' ) );
							return;
						}

						/**
						 * Process document content (if appropriate for the content type)
						 */
						$doc_content = $this->get_document_content($doc_type, $tmp_file);
						if ( isset( $doc_content['error'] ) ) {
							$this->log_admin_notice( 'red', sprintf( __( 'Failed processing document content: %s', 'aad-doc-manager' ),  $doc_content['error'] ) );
							return;
						}

						/**
						 * Update post content if new document uploaded
						 */
						$post['post_content'] = $doc_content['post_content'];
						break;

					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						/**
						 * Uploaded file is too large
						 */
						$this->log_admin_notice( 'red', __( "Uploaded file is too large.", 'aad-doc-manager' ) );
						return;

					case UPLOAD_ERR_PARTIAL:
						/**
						 * Partial upload - Retry
						 */
						$this->log_admin_notice( 'red', __( "Partial upload received, please retry.", 'aad-doc-manager' ) );
						return;

					case UPLOAD_ERR_NO_FILE:
						$have_upload = false;
						break;

					case UPLOAD_ERR_NO_TMP_DIR:
						/**
						 * Configuration problem - No temp directory available
						 */
						$this->log_admin_notice( 'red', __( "Server error: No temp directory available", 'aad-doc-manager' ) );
						return;

					case UPLOAD_ERR_CANT_WRITE:
						/**
						 * Configuration problem - Can't write to temp directory
						 */
						$this->log_admin_notice( 'red', __( "Server error: Can’t write to temp directory", 'aad-doc-manager' ) );
						return;

					case UPLOAD_ERR_EXTENSION:
						/**
						 * Upload blocked by a php plugin
						 */
						$this->log_admin_notice( 'red', __( "PHP Plugin blocked upload; server logs may have details.", 'aad-doc-manager' ) );
						return;

					default:
						/**
						 * Unknown error
						 */
						$this->log_admin_notice( 'red', __( sprintf( "Unknown file upload error: %d", $_FILES['document']['error']), 'aad-doc-manager' ) );
						return;
				}

			} else {
				$have_upload = false;
			}

			/**
			 * Document title from request if provided, otherwise use filename
			 */
			$post['post_title'] =
				sanitize_text_field( isset( $_REQUEST['doc_title'] ) && '' != $_REQUEST['doc_title'] ?
				$_REQUEST['doc_title'] : $post['post_title'] );

			switch ( $action ) {
			case 'new':
				/**
				 * New uploads require a file to be provided
				 */
				if ( ! $have_upload ) {
					$this->log_admin_notice( 'red', __( "No document uploaded.", 'aad-doc-manager' ) );
					return;
				}

				/**
				 * Insert new post
				 */
				$doc_id = wp_insert_post( $post );

				if ( ! $doc_id ) {
					$this->log_admin_notice( 'red', __( 'Error inserting post data! WP Config issue?', 'aad-doc-manager' ) );
					return;
				}

				/**
				 * Generate a GUID for this document
				 */
				$guid = $this->generate_guid();
				wp_set_object_terms( $doc_id, $guid, self::term_guid );

				break;

			case 'update':
				/**
				 * Update the post
				 */
				wp_update_post( $post );
				break;
			}


			/**
			 * Save uploaded file as media
			 */
			if ( $have_upload ) {
				$attachment_id = media_handle_upload( 'document', $doc_id );

				if ( is_wp_error( $attachment_id ) ) {
					$this->log_admin_notice('red', sprintf( __( 'Unable to handle upload: %s' , 'aad-doc-manager' ), $attachment_id->get_error_message() ) );
					return;
				} else {
					/**
					 * Upload file saved successfully, preserve the new attachment ID
					 */
					$doc_content['post_meta']['document_media_id'] = $attachment_id;

					/**
					 * On update, remove previously saved revision of the document
					 */
					if ( 'update' == $action ) {
						$old_media_id = get_post_meta( $doc_id, 'document_media_id', true );
						if ( $old_media_id ) {
							if ( ! wp_delete_attachment( $old_media_id, true ) )
								$this->log_admin_notice( sprintf( __('Unable to remove old attachment; ID=%d', 'aad-doc-manager' ), $old_media_id ) );
						}
					}
				}
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
		 * Determine which action is being taken during file upload
		 *
		 * @return string, name of action or NULL
		 */
		private function upload_action()
		{
			if ( empty( $_REQUEST ) || ! isset( $_REQUEST['submit'] ) )
				return NULL;
			elseif ( $_REQUEST['submit'] == self::$post_type_labels['new_item'] )
				return 'new';
			elseif ( $_REQUEST['submit'] == self::$post_type_labels['edit_item'] )
				return 'update';
			else
				return NULL;
		}

		/**
		 * Creates "post_content" based on input type
		 *   - CSV Files will be pre-processed into html table, data will also be stored as serialized post meta data
		 *   - All others have empty post-content
		 *
		 * As a part of processing data, some post meta data may also be needed related to the document type.
		 * It is returned as an associative array of name=>value pairs.
		 *
		 * @return associative array(
		 * 		'post_content' => string, post content
		 *      'post_meta' => associative array (name => value pairs)
		 *    )
		 */
		private function get_document_content( $doc_type, $path )
		{
			if ( 'text/csv' != $doc_type ) {
				return array(
					'post_content' => '',
					'post_meta' => array()
				);
			}

			/**
			 * Does CSV file include column headers in the first row?
			 */
			$csv_has_col_headers = isset( $_REQUEST['csv-has-col-headers'] ) && "yes" == $_REQUEST['csv-has-col-headers'];

			/**
			 * Process received CSV file
			 */
			$table = array();
			if ( ( $handle = fopen( $path, 'r' ) ) !== FALSE ) {
				// If first row has headers grab them
				if ( $csv_has_col_headers ) {
					// RFE Allow for alternate field separator characters
					$row = fgetcsv( $handle, 1000, ',', '"' );
					if ( $row === FALSE ) {
						fclose( $handle );
						// FIXME Handle this and other errors using WP Error class
						return array( 'error' => __( 'Unable to parse CSV contents.', 'aad-doc-manager' ) );
					}

					// Replace line breaks with html
					$header_names = $row;
					$max_cols = count( $row );
				} else {
					$max_cols = 0;
				}

				/**
				 * Collect the table rows
				 */
			    while ( ( $row = fgetcsv( $handle, 1000, ",", '"' ) ) !== FALSE ) {
					/**
					 * Count of Columns in this row and track maximum observed
					 */
					$columns = count( $row );
					if ( $columns > $max_cols ) $max_cols = $columns;
					$table[] = $row;
			    }
			    fclose( $handle );

				/**
				 * Generate headers if not provided
				 */
				if ( empty( $header_names ) ) {
					$header_names = array();
					for ( $col=0; $col < $max_cols; $col++ ) {
						$header_names[$col] = sprintf( __( 'Column %d', 'aad-doc-manager' ), $col+1 );
					}
				}
			} else {
				return array( 'error' => __( 'Could not open uploaded file.', 'aad-doc-manager' ) );
			}

			/**
			 * Setup return data
			 * Table will be stored as Post Meta data in serialized and rendered form
			 */
			$render_data = array(
				'col-headers' => $header_names,
				'columns' => $max_cols,
				'table' => $table
			);
			$retarray = array();
			$retarray['post_content'] = "";
			$retarray['post_meta'] = array(
				'csv_storage_format' => self::CSV_STORAGE_FORMAT, // Save version used to store document content
				'csv_cols' => $max_cols,
				'csv_rows' => count( $table ),
				'csv_col_headers' => $header_names,
				'csv_has_col_headers' => $csv_has_col_headers,
				'csv_table' => $table,
				'csv_rendered' => $this->render_csv( $render_data )
			);
			return $retarray;
		}

		/**
		 * Render page to upload new Documents
		 *
		 * @uses self::upload_slug, slug for this page
		 * @return void
		 */
		function render_upload_page()
		{
			/**
			 * User must be able to edit posts
			 */
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'aad-doc-manager' ) );
				return;
			}

			/**
			 * Document types accepted for upload
			 */
			$accepted_doc_types = implode( $this->accepted_doc_types, ', ' );

			/**
			 * Updating an existing document?
			 */
			$doc_id = isset( $_REQUEST['doc_id'] ) ? intval( $_REQUEST['doc_id'] ) : NULL;
			$post = $doc_id ? get_post( $doc_id ) : NULL;

			/**
			 * Setup default values for new vs. updating existing document
			 */
			if ( ! $post || $post->post_type != self::post_type ) { // Don't allow other post-types
				$doc_id = NULL;
				$post = NULL;
				$title = isset( $_REQUEST['doc_title'] ) ? $_REQUEST['doc_title'] : "";
				$checked = 'checked';
				$file_required = 'required';
			} else {
				$title = $post->post_title;
				$csv_rows = get_post_meta( $doc_id, 'csv_rows', true );
				$csv_cols = get_post_meta( $doc_id, 'csv_cols', true );
				$checked = get_post_meta( $doc_id, 'csv_has_col_headers', true ) ? 'checked' : '';
				$file_required = '';
				
				/**
				 * When updating an existing file, only allow the same mime type to be uploaded
				 */
				$accepted_doc_types = $post->post_mime_type;
			}

			$action = $post ? self::$post_type_labels['edit_item'] : self::$post_type_labels['new_item'];

			?>
			<div class="wrap">
				<h2><?php echo esc_attr( $action ); ?></h2>
				<form action method="post" accept-charset="utf-8" enctype="multipart/form-data">
					<input type="hidden" name="page" value="<?php echo self::upload_slug ?>">
					<?php if ($doc_id) : ?>
						<input type="hidden" name="doc_id" value="<?php echo esc_attr( $doc_id ); ?>" id="doc_id">
					<?php endif; ?>
					<?php wp_nonce_field( self::upload_slug, self::nonce ); ?>
					<div class="titlewrap">
						<label for="title" class="title-prompt-text"><?php _e ( 'Document Title:', 'aad-doc-manager' ); ?></label>
						<input type="text" name="doc_title" value="<?php echo esc_attr($title); ?>" id="title" size="50" autocomplete="off" placeholder="Defaults to name of uploaded file if none specified">
					</div>
					<?php if ($doc_id) : ?>
						<div class="postbox">
							<h3><?php _e( 'Uploaded Document Info', 'aad-doc-manager' ); ?></h3>
							<div class="inside">
								<dl>
									<dt><?php _e( 'Document ID', 'aad-doc-manager' ); ?></dt>
									<dd><?php esc_html_e($doc_id); ?></dd>
									<dt><?php _e( 'Document Type', 'aad-doc-manager' ); ?></dt>
									<dd><?php esc_html_e($post->post_mime_type); ?></dd>
									<dt><?php _e( 'Date Created', 'aad-doc-manager' ); ?></dt>
									<dd><?php esc_html_e($post->post_date); ?></dd>
									<dt><?php _e( 'Date Last Modified', 'aad-doc-manager' ); ?></dt>
									<dd><?php esc_html_e($post->post_modified); ?></dd>
									<?php if ( 'text/csv' == $post->post_mime_type ):  ?>
										<dt><?php _e( 'Rows', 'aad-doc-manager' ); ?></dt>
										<dd><?php echo intval($csv_rows); ?></dd>
										<dt><?php _e( 'Columns', 'aad-doc-manager' ); ?></dt>
										<dd><?php echo intval($csv_cols); ?></dd>
									<?php endif; ?>
								</dl>
							</div>
						</div>
					<?php endif; ?>
					<div class="postbox">
						<h3>Upload</h3>
						<div class="inside">
							<div>
								<label for="document"><?php _e( 'Select document to upload', 'aad-doc-manager' ); ?></label><br>
								<input type="file" id="document" name="document" value="" size="40" accept="<?php echo esc_attr( $accepted_doc_types ); ?>" <?php echo esc_attr( $file_required ); ?>/>
								<div>
									<?php
									_e( 'Supported file types: ', 'aad-doc-manager' );
									esc_html_e( $accepted_doc_types );
									?>
								</div>
							</div>
							<div class="upload_option">
								<?php _e( 'For CSV File Upload (ignored for other document types):', 'aad-doc-manager' ); ?><br />
								<input type="checkbox" <?php echo esc_attr( $checked ); ?> id="csv-has-col-headers" name="csv-has-col-headers" value="yes">
								<label for="csv-has-col-headers"><?php _e( 'First Row contains column names', 'aad-doc-manager' ); ?></label>
							</div>
						</div>
					</div>
					<?php submit_button( $action, 'apply', 'submit', false ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Add a message to notice messages
		 *
		 * @param $class, string "red", "yellow", "green".  Selects log message type
		 * @param $msg, string or HTML content to display.  User input should be scrubbed by caller
		 * @return void
		 **/
		function log_admin_notice( $class, $msg )
		{
			$this->admin_notices[] = array( $class, $msg );
		}

		/**
		 * Display Notice messages at head of admin screen
		 *
		 * @return void
		 **/
		function render_admin_notices()
		{
			/*
				WP defines the following classes for display:
					- error  (Red)
					- updated  (Green)
					- update-nag  (Yellow)
			*/

			static $notice_class = array(
				'red' => 'error',
				'yellow' => 'update-nag',
				'green' => 'updated'
			);

			if ( count( $this->admin_notices ) ) {
				foreach ( $this->admin_notices as $notice ) {
					// TODO Handle undefined notice class
					echo '<div class="'. esc_attr( $notice_class[$notice[0]] ) . '">';
					echo '<p>' . wp_kses($notice[1], array(), array()) . '</p>';
					echo '</div>';
				}
			}
		}

		/**
		 * When deleting a media attachment via WP interfaces, remove the associated document as well
		 *
		 * @param int $attachment_id, post_id for attachment being removed
		 * @return void
		 */
		function action_delete_document($attachment_id)
		{
			$attachment = get_post( $attachment_id );
			if ( $attachment->post_parent ) {
				/**
				 * Attachment has a parent, is it a document type?
				 */
				$post = get_post( $attachment->post_parent );
				if ( self::post_type == $post->post_type ) {
					/**
					 * Delete the associated document
					 */
					if (! wp_delete_post($post->ID, true) )
						wp_die( __( 'Error in deleting.', 'aad-doc-manager' ) );
				}
			}
		}

		/**
		 * Generate a random V4 GUID
		 *
		 * @param none
		 * @return string GUID
		 */
		private function generate_guid() {
			return self::guidv4( openssl_random_pseudo_bytes( 16 ) );
		}

		/**
		 * Turn 128 bit blob into a UUD string
		 *
		 * @param 16 bytes binary data $data
		 * @return string
		 */
		function guidv4( $data ) {
			assert( strlen( $data ) == 16 );

			$data[ 6 ] = chr( ord( $data[ 6 ] ) & 0x0f | 0x40 ); // set version to 0100
			$data[ 8 ] = chr( ord( $data[ 8 ] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

			return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
		}
	}
}