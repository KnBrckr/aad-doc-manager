<?php
/*
 * Class to manage admin screens
 *
 * @package Document Manager
 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
 * @copyright 2015 Kenneth J. Brucker (email: ken@pumastudios.com)
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

// TODO Enable editing of headers on saved files

/**
 * Protect from direct execution
 */
if (!defined('ABSPATH')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

if (! class_exists("aad_doc_manager_admin")) {
	class aad_doc_manager_admin extends aad_doc_manager
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
			// TODO Add settings screen to control available document types
			$this->accepted_doc_types = array('text/csv');
			
			/**
			 * Empty list of admin notices
			 */
			$this->admin_notices = array();

			/**
			 * Setup admin page for managing Documents
			 */
			add_action('admin_menu', array($this, 'action_admin_menu'));

			/**
			 * Do plugin initialization
			 */
			add_action('admin_init', array($this, 'action_admin_init'));
		}
				
		/**
		 * Hook into WP after plugins have loaded
		 *
		 * @return void
		 */
		function action_admin_init()
		{
			/**
			 * Add section for reporting configuration errors and notices
			 */
			add_action('admin_notices', array( $this, 'render_admin_notices'));
		}
		
		/**
		 * Create admin menu section for managing Documents
		 *
		 * @return void
		 */
		function action_admin_menu()
		{
			/**
			 * Create a new Admin Menu page
			 */
			$hook_suffix = add_menu_page(
				$this->labels['name'], // Page Title
				$this->labels['menu_name'], // Menu Title,
				'edit_posts', // User must be able to edit posts for menu to display
				self::parent_slug, // Menu slug
				array($this, 'render_table_page'), // Function to render the menu content
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
				add_action('load-' . $hook_suffix, array($this, 'action_load_table_page'));

				/**
				 * Create first sub-page as a duplicate of the main page
				 */
				add_submenu_page(
					'aad-doc-manager-table', // Submenu of parent
					$this->labels['name'], // Page Title
					$this->labels['all_items'], // Menu Title
					'edit_posts', // Capability
					self::parent_slug, // Menu slug - match the parent
					array($this, 'render_table_page')
				);
			
				/**
				 * Create menu item to upload a new Document
				 */
				$hook_suffix = add_submenu_page(
					self::parent_slug, // Slug of Parent Menu
					$this->labels['add_new_item'], // Page Title 
					$this->labels['add_new'], // Menu Title
					'edit_posts', // User must be able to edit posts for menu to display
					self::upload_slug, // Slug for this Menu Page
					array($this, 'render_upload_page')
				);
			 
				// Pre-render processing for the upload screen
				add_action('load-' . $hook_suffix, array($this, 'action_load_upload_page'));

				// // Add style sheet and scripts needed in the options page
				// add_action('admin_print_scripts-' . $slug, array($this, 'enqueue_admin_scripts'));
				// add_action('admin_print_styles-' . $slug, array($this, 'enqueue_admin_styles'));

			} // End if
		}
		
		/**
		 * Perform actions associated with the Document management page
		 *
		 * Done before render during page load to apply any admin notice feedback that might be needed
		 *
		 * @return void
		 */
		function action_load_table_page()
		{
			global $wpdb;
			
			/**
			 * User must be able to edit posts
			 */
			if (!current_user_can('edit_posts'))
				wp_die('Do you belong here?');
			
			/**
			 * Load class used to manage table of Documents
			 */
			if (! include_once('class_aad_doc_manager_Table.php')) return;
			
			$this->doc_table = new aad_doc_manager_Table(array(
				'singular' => $this->labels['singular_name'], // Singular Label
				'plural' => $this->labels['name'], // Plural label
				'ajax' => false, // Will not support AJAX on this table
				'post_type' => self::post_type,
				'upload_url' => menu_page_url(self::upload_slug, false),
				'table_url' => menu_page_url(self::parent_slug, false),
				'labels' => $this->labels
			));
			
			$pagenum = $this->doc_table->get_pagenum();
			
			/**
			 * Handle bulk actions
			 */
			$action = $this->doc_table->current_action();
			if ($action) {
				/**
				 * Verify nonce - Setup by WP_List_Table base class based on the plural label
				 */
				check_admin_referer('bulk-' . sanitize_key($this->labels['name']));

				/**
				 * Prepare redirect URL
				 */
				$sendback = remove_query_arg(array('trashed','untrashed','deleted','locked','doc_ids', 'ids', 'action', 'action2'), wp_get_referer());
				if (! $sendback)
					$sendback = menu_page_url(self::parent_slug, false); // FIXME This works only if URL contains no embedded '&'
				$sendback = add_query_arg('paged', $pagenum, $sendback);

				/**
				 * Grab the list of post ids to work with
				 */
				if ('delete_all' == $action) {
					// Convert 'delete_all' action to delete action with a list
					$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", self::post_type, 'trash' ) );
					$action = 'delete';
				} elseif (!empty($_REQUEST['doc_ids']))
					$post_ids = array_map('intval', $_REQUEST['doc_ids']);
				else {
					wp_safe_redirect( $sendback ); // List of posts not provided, bail out
					exit;
				}
				
				/**
				 * Perform requested action
				 */
				switch($action) {
					case 'trash':
						/**
						 * Move posts to Trash
						 */
						$trashed = $locked = 0;
						
						foreach ((array)$post_ids as $post_id) {
							if (! current_user_can('delete_post', $post_id))
								wp_die('You are not allowed to move this item to the Trash.');
							
							if (wp_check_post_lock($post_id)) { // TODO - How to lock a post?
								$locked++;
								continue;
							}
							
							if (! wp_trash_post($post_id))
								wp_die('Error moving post to Trash.');
							
							$trashed++;
						}
						
						/**
						 * Send operation results back
						 */
						$sendback = add_query_arg(
							array('trashed' => $trashed, 'ids' => join(',', $post_ids), 'locked' => $locked),
							$sendback
						);
						break;
					
					case 'untrash':
						/**
						 * Restore Posts from Trash
						 */
						$untrashed = 0;
						
						foreach ((array)$post_ids as $post_id) {
							if (! current_user_can('delete_post', $post_id))
								wp_die('You are not allowed to restore this item from the Trash.');
							
							if (! wp_untrash_post($post_id))
								wp_die('Error in restoring from Trash.');
							
							$untrashed++;
						}
						
						$sendback = add_query_arg('untrashed', $untrashed, $sendback);
						break;
						
					case 'delete':
						/**
						 * Permanently delete posts from Trash
						 */
						$deleted = 0;
						
						foreach ((array)$post_ids as $post_id) {
							if (! current_user_can('delete_post', $post_id))
								wp_die('You are not allowed to delete this item.');
							
							if (! wp_delete_post($post_id))
								wp_die('Error in deleting.');
							
							$deleted++;
						}
						
						$sendback = add_query_arg('deleted', $deleted, $sendback);
						break;
				} // End Switch

				wp_safe_redirect($sendback);
				exit;
			} elseif (! empty($_REQUEST['_wp_http_referer'])) {
				/**
				 * No action provided, if nonce was given, redirect back without nonce
				 */
				wp_safe_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), wp_unslash($_SERVER['REQUEST_URI'])));
				exit;
			} // End if
			
			/**
			 * Report on earlier bulk action
			 */
			$locked = isset($_REQUEST['locked']) ? intval($_REQUEST['locked']) : NULL;
			$trashed = isset($_REQUEST['trashed']) ? intval($_REQUEST['trashed']) : NULL;
			$deleted = isset($_REQUEST['deleted']) ? intval($_REQUEST['deleted']) : NULL;
			$untrashed = isset($_REQUEST['untrashed']) ? intval($_REQUEST['untrashed']) : NULL;
			
			if ($locked) $this->log_admin_notice("yellow", "Skipped $locked locked documents.");
			if ($trashed) $this->log_admin_notice("green", "Moved $trashed documents to the trash.");
			if ($deleted) $this->log_admin_notice("green", "Permanently deleted $deleted documents from trash.");
			if ($untrashed) $this->log_admin_notice("green", "Restored $untrashed documents from trash.");
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
			if (! current_user_can('edit_posts')) {
				wp_die( __('You do not have sufficient permissions to access this page.') );
			}
			
			/**
			 * Perform table query
			 */
			$this->doc_table->prepare_items();
			?>
		
			<div class="wrap">
				<h2><?php echo $this->labels['name']; ?> <a href="<?php menu_page_url(self::upload_slug,false); ?>" class="add-new-h2"><?php echo $this->labels['add_new_item']?></a></h2>
				<?php $this->doc_table->views(); // Display the views available on the table ?>
				<form action method="post" accept-charset="utf-8">
					<input type="hidden" name="page" value="<?php echo self::parent_slug ?>">
					<?php
//	TODO				$this->doc_table->search_box('search', 'search_id'); // Must follow prepare_items() call
					$this->doc_table->display();
					?>
				</form>
			</div>
			<?php
			
		}
		
		/**
		 * Perform actions associated with the upload page
		 *
		 * @return void
		 */
		function action_load_upload_page()
		{
			/**
			 * User must be able to edit posts
			 */
			if (!current_user_can('edit_posts')) 
				wp_die('Do you belong here?');

			$action = $this->upload_action();
			$post = array(); // New/Updated Post content
			$post['post_title'] = '';
			
			/**
			 * If a valid action has been requested ...
			 */
			if ($action) {
				/**
				 * Is nonce valid? check_admin_referrer will die if invalid
				 */
				check_admin_referer(self::upload_slug, self::nonce);
			
				/**
				 * Confirm file is a valid type
				 */
				if (! empty($_FILES) && isset($_FILES['document']) && $_FILES['document']['error'] == UPLOAD_ERR_OK) {
					$doc_type = $_FILES['document']['type'];
					if (! in_array($doc_type, $this->accepted_doc_types)) {
						$this->log_admin_notice('red', "Document type ". esc_attr($doc_type) . " is not supported.");
						// TODO Handle error via redirect
						return;
					}
					$post['post_mime_type'] = $doc_type;
					
					if (! isset($_FILES['document']['name']))
						wp_die("Malformed request detected in " . __FILE__);
					$post['post_title'] = $_FILES['document']['name'];

					/**
					 * Confirm name provided and temp filename is a valid uploaded file
					 */
					$tmp_file = $_FILES['document']['tmp_name'];
					if (! is_uploaded_file($tmp_file))
						wp_die('Something fishy is going on, file does not appear to have been uploaded');

					$doc_content = $this->get_document_content($doc_type, $tmp_file);
					if (isset($doc_content['error']))
						wp_die($doc_content['error']);
				} else {
					/**
					 * New uploads require a file to be provided
					 */
					if ('new' == $action) {
						$this->log_admin_notice('red', "Missing Upload Document");
						return;
					}

					$doc_name = ''; // If no file provided, setup blank name for later
				}

				/**
				 * Document title from request if provided, otherwise use filename
				 */
				$post['post_title'] =
					sanitize_text_field(isset($_REQUEST['doc_title']) && '' != $_REQUEST['doc_title'] ? 
					$_REQUEST['doc_title'] : $post['post_title']);
				
				/**
				 * Update post content if new document uploaded
				 *   already confirmed that a file was supplied for new document creation
				 */
				if (isset($doc_content)) {
					$post['post_content'] = $doc_content['post_content'];
				}
				
				switch ($action) {
				case 'new':
					/**
					 * Insert new post
					 */
					$post['post_excerpt'] = '';
					$post['post_type'] = self::post_type;
					$post['post_status'] = 'publish';
					$post['comment_status'] = 'closed';
					$post['ping_status'] = 'closed';
					
					$post_id = wp_insert_post($post);

					if (!$post_id)
						wp_die('Error inserting post data');
					break;
					
				case 'update':	
					/**
					 * Update existing post
					 */
					$post_id = isset($_REQUEST['doc_id']) ? intval($_REQUEST['doc_id']) : NULL;
					if (!$post_id) {
						wp_die('Bad Request - No post id to update');
					}
					$post['ID'] = $post_id;
					
					/**
					 * Make sure we're updating proper post type
					 */
					$old_post = get_post($post_id);
					if ($old_post && $old_post->post_type != self::post_type) {
						wp_die('Sorry - Request to update invalid document id');
					}
					
					/**
					 * Update the post
					 */
					wp_update_post($post);
					break;
				}

				/**
				 * Save meta data related to document content
				 */
				if (isset($doc_content)) {
					// TODO Remove old document meta data
					foreach ($doc_content['post_meta'] as $key => $value) {
						update_post_meta($post_id, $key, $value);
					}
				}

				// TODO Save uploaded file in media area - and manage a revision count
				// TODO On post delete cleanup the media area
			
				// TODO Add result reporting
				wp_safe_redirect(menu_page_url(self::parent_slug, false));
				exit;
			} // End if			
		}
		
		/**
		 * Determine which action is being taken during file upload
		 *
		 * @return string, name of action or NULL
		 */
		private function upload_action()
		{
			if (empty($_REQUEST) || ! isset($_REQUEST['submit']))
				return NULL;
			elseif ($_REQUEST['submit'] == $this->labels['new_item'])
				return "new";
			elseif ($_REQUEST['submit'] == $this->labels['edit_item'])
				return "update";
			else
				return NULL;
		}
		
		/**
		 * Creates "post_content" based on input type
		 *   - CSV Files will be pre-processed into a serial encoded table
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
		private function get_document_content($doc_type, $path)
		{
			if ("text/csv" != $doc_type) {
				return array(
					'post_content' => '',
					'post_meta' => array()
				);
			}
			
			/**
			 * Does CSV file include column headers in the first row?
			 */
			$csv_has_col_headers = isset($_REQUEST['csv-has-col-headers']) && "yes" == $_REQUEST['csv-has-col-headers'];
			
			/**
			 * Process received CSV file
			 */
			$table = array();
			if (($handle = fopen($path, "r")) !== FALSE) {
				// If first row has headers grab them
				if ($csv_has_col_headers) {
					// TODO Allow for alternate field separator characters
					$row = fgetcsv($handle, 1000, ",", '"');
					if ($row === FALSE) {
						fclose($handle);
						return array('error' => 'Unable to parse CSV contents.');
					}
					
					// Replace line breaks with html
					$header_names = $row;
					$max_cols = count($row);
				} else {
					$max_cols = 0;
				}

				/**
				 * Collect the table rows
				 */
			    while (($row = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
					/**
					 * Count of Columns in this row and track maximum observed
					 */
					$columns = count($row);
					if ($columns > $max_cols) $max_cols = $columns;
					$table[] = $row;
			    }
			    fclose($handle);
				
				/**
				 * Generate headers if not provided
				 */
				if (empty($header_names)) {
					$header_names = array();
					for ($col=0; $col < $max_cols; $col++) { 
						$header_names[$col] = "Column " . strval($col+1);
					}
				}
			} else {
				return array('error' => 'Could not open uploaded file.');
			} // End if
			
			/**
			 * Setup return data
			 */
			$retarray = array();
			$retarray['post_content'] = serialize($table);
			$retarray['post_meta'] = array(
				'csv_cols' => $max_cols,
				'csv_rows' => count($table),
				'csv_col_headers' => $header_names,
				'csv_has_col_headers' => $csv_has_col_headers
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
			if (! current_user_can('edit_posts')) {
				wp_die( __('You do not have sufficient permissions to access this page.') );
				return;
			}
			
			/**
			 * Document types accepted for upload
			 */
			$accepted_doc_types = implode($this->accepted_doc_types, ",");
			
			/**
			 * Updating an existing document?
			 */
			$doc_id = isset($_REQUEST['doc_id']) ? intval($_REQUEST['doc_id']) : NULL;
			$post = $doc_id ? get_post($doc_id) : NULL;
			
			/**
			 * Setup default values for new vs. updating existing document
			 */
			if (! $post || $post->post_type != self::post_type) { // Don't allow other post-types
				$doc_id = NULL;
				$post = NULL;
				$title = isset($_REQUEST['doc_title']) ? $_REQUEST['doc_title'] : "";
				$checked = "checked";
				$file_required = "required";
			} else {
				$doc_type = get_post_meta($doc_id, 'doc_type', true);
				$title = $post->post_title;
				$csv_rows = get_post_meta($doc_id, 'csv_rows', true);
				$csv_cols = get_post_meta($doc_id, 'csv_cols', true);
				$checked = get_post_meta($doc_id, 'csv_has_col_headers', true) == 'yes' ? 'checked' : '';
				$file_required = "";
			}
			
			$action = $post ? $this->labels['edit_item'] : $this->labels['new_item'];

			?>
			<div class="wrap">
				<h2><?php echo $action; ?></h2>
				<form action method="post" accept-charset="utf-8" enctype="multipart/form-data">
					<input type="hidden" name="page" value="<?php echo self::upload_slug ?>">
					<?php if ($doc_id) : ?>
						<input type="hidden" name="doc_id" value="<?php echo $doc_id; ?>" id="doc_id">
					<?php endif; ?>
					<?php wp_nonce_field(self::upload_slug, self::nonce); ?>
					<div class="titlewrap">
						<label for="title" class="title-prompt-text">Document Title:</label>
						<input type="text" name="doc_title" value="<?php echo esc_attr($title); ?>" id="title" size="50" autocomplete="off" placeholder="Defaults to name of uploaded file if none specified">
					</div>
					<?php if ($doc_id) : ?>
						<div class="postbox">
							<h3>Uploaded Document Info</h3>
							<div class="inside">
								<dl>
									<dt>Document Type</dt>
									<dd><?php echo esc_attr($post->post_mime_type); ?></dd>
									<dt>Date Created</dt>
									<dd><?php echo esc_attr($post->post_date); ?></dd>
									<dt>Date Last Modified</dt>
									<dd><?php echo esc_attr($post->post_modified); ?></dd>
									<dt>Rows</dt>
									<dd><?php echo intval($csv_rows); ?></dd>
									<dt>Columns</dt>
									<dd><?php echo intval($csv_cols); ?></dd>
								</dl>
							</div>
						</div>
					<?php endif; ?>
					<div class="postbox">
						<h3>Upload</h3>
						<div class="inside">
							<div>
								For CSV File Upload (ignored for other document types):<br>
								<input type="checkbox" <?php echo $checked; ?> id="csv-has-col-headers" name="csv-has-col-headers" value="yes">
								<label for="csv-has-col-headers">First Row contains column names</label>							
							</div>
							<div>
								<label for="document">Select document to upload</label><br>
								<input type="file" id="document" name="document" value="" size="40" accept="<?php echo $accepted_doc_types ?>" <?php echo $file_required; ?>/>
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
		function log_admin_notice($class, $msg)
		{
			$this->admin_notices[] = array($class, $msg);
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
		
			if (count($this->admin_notices)) {
				foreach ($this->admin_notices as $notice) {
					// TODO Handle undefined notice class
					echo '<div class="'. $notice_class[$notice[0]] . '">';
					echo '<p>' . wp_kses($notice[1], array(), array()) . '</p>';
					echo '</div>';			
				}
			}
		}
	}
}
