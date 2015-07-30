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

// FIXME Enable editing of headers on saved files

// Protect from direct execution
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
		 * Errors and warnings to display on admin screens
		 *
		 * @var array 
		 **/
		protected $admin_notices;
						
		function __construct()
		{
			parent::__construct();
			
			// Empty list of admin notices
			$this->admin_notices = array();

			// Setup admin page for managing Documents
			add_action('admin_menu', array($this, 'action_admin_menu'));

			// Do plugin initialization
			add_action('admin_init', array($this, 'action_admin_init'));
		}
				
		/**
		 * Hook into WP after plugins have loaded
		 *
		 * @return void
		 */
		function action_admin_init()
		{
			// FIXME User must be able to ...
			// if (! current_user_can('something')) return;
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
				// Pre-render processing for the table screen
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
				// // FIXME $this->slug_edit_variations_table_page = $slug; // Save identifier for later

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
				'post_type' => self::post_type
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
					$sendback = menu_page_url(self::parent_slug);
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
					wp_redirect( $sendback );
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
							
							if (wp_check_post_lock($post_id)) { // FIXME - How to lock a post?
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

				wp_redirect($sendback);
				exit;
			} elseif (! empty($_REQUEST['_wp_http_referer'])) {
				/**
				 * No action provided, if nonce was given, redirect back without nonce
				 */
				wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), wp_unslash($_SERVER['REQUEST_URI'])));
				exit;
			}
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
			// FIXME - Add reporting of results from bulk actions and Document upload
			
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
				<h2><?php echo $this->labels['name']; ?> <a href="<?php menu_page_url(self::upload_slug); ?>" class="add-new-h2"><?php echo $this->labels['add_new_item']?></a></h2>
				<?php $this->doc_table->views(); // Display the views available on the table ?>
				<form action method="post" accept-charset="utf-8">
					<input type="hidden" name="page" value="<?php echo self::parent_slug ?>">
					<?php
//	FIXME				$this->doc_table->search_box('search', 'search_id'); // Must follow prepare_items() call
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
			
			/**
			 * Confirm action bound for this page
			 */
			if (empty($_REQUEST) || ! isset($_REQUEST['submit']) || $_REQUEST['submit'] != $this->labels['new_item'] )
				return;

			/**
			 * Is nonce valid? check_admin_referrer will die if invalid
			 */
			check_admin_referer(self::upload_slug, self::nonce);
			
			/**
			 * Confirm file is a valid type
			 */
			if (empty($_FILES) || ! isset($_FILES['document']) || 'text/csv' != $_FILES['document']['type']) { // FIXME document
				// FIXME Setup error
				return;
			}
			
			/**
			 * Confirm name was provided
			 */
			if (! isset($_FILES['document']['name']))
				wp_die("Invalid Request detected in " . __FILE__);
			
			/**
			 * Fields needed to insert a post into WP
			 */
			$post_title = wp_strip_all_tags($_FILES['document']['name']);
			
			/**
			 * Does CSV file include column headers in the first row?
			 */
			$headers = isset($_REQUEST['header-row']) && "yes" == $_REQUEST['header-row'];
			
			/**
			 * Process received CSV file
			 */
			$tmp_file = $_FILES['document']['tmp_name'];
			if (! is_uploaded_file($tmp_file))
				wp_die('Something fishy is going on, file does not appear to have been uploaded');
			
			if (($handle = fopen($tmp_file, "r")) !== FALSE) {
				// If first row has headers grab them
				if ($headers) {
					// FIXME Allow for alternate field separator characters
					$row = fgetcsv($handle, 1000, ",", '"');
					if ($row === FALSE) {
						// FIXME Report Empty or bad CSV file
						return;
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
				$table = array();
			    while (($row = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
					// Count of Columns in this row and track maximum observed
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
								
				/**
				 * Save table as post data
				 */
				$post_id = wp_insert_post(array(
					'post_content' => json_encode($table),
					'post_title' => $post_title,
					'post_excerpt' => '',
					'post_type' => self::post_type,
					'post_status' => 'publish'
				));
				
				if (!$post_id)
					wp_die('Error inserting post data');
				
				$meta_id = update_post_meta($post_id, 'csv_col_headers', json_encode($header_names));
				if (! $meta_id) {
					wp_delete_post($post_id);
					wp_die('Error adding header meta data, CSV data deleted.');
				}
				
				$meta_id = update_post_meta($post_id, 'csv_rows', count($table));
				if (! $meta_id) {
					wp_delete_post($post_id);
					wp_die('Error adding row count meta data, CSV data deleted.');
				}
				
				// FIXME Add result reporting
				wp_redirect(menu_page_url(self::parent_slug));
			}
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
							
			// FIXME Display info about existing CSV data if editing existing file?
			// FIXME Make file a mandatory field
			?>
			<form action method="post" accept-charset="utf-8" enctype="multipart/form-data">
				<input type="hidden" name="page" value="<?php echo self::upload_slug ?>">
				<?php wp_nonce_field(self::upload_slug, self::nonce); ?>
				<p class="description">
					Upload a [New|Replace] CSV File.
				</p>
				<p>
					<input type="checkbox" checked id="header-row" name="header-row" value="yes">
					<label for="header-row">First Row of CSV file contains column names</label>
				</p>
				<p><input type="file" id="document" name="document" value="" size="40" accept=".csv,text/csv" /></p>
				<p><input type="submit" name="submit" value="<?php echo $this->labels['new_item'] ?>"></p>
			</form>
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
