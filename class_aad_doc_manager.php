<?php
/*
 * Class to display and download documents
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

// Protect from direct execution
if (!defined('ABSPATH')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

// TODO Add shortcode to download target document

if (! class_exists("aad_doc_manager")) {
	class aad_doc_manager
	{
		/**
		 * Plugin version
		 */
		const PLUGIN_VER = "0.5";

		/**
		 * Custom Post Type id
		 */
		const post_type = 'aad-doc-manager';
		
		function __construct()
		{
			$this->labels = array (	
				'name'               => 'Documents',
				'singular_name'      => 'Document',
				'menu_name'          => 'Documents',
				'name_admin_bar'     => 'Document',
				'all_items'          => 'All Documents',
				'add_new'            => 'Upload Document',
				'add_new_item'       => 'Upload Document',
				'edit_item'          => 'Update Document',
				'new_item'           => 'Upload New Document',
				'view_item'          => 'View Document',
				'search_item'        => 'Search Document',
				'not_found'          => 'No Documents found',
				'not_found_in_trash' => 'No Documents found in Trash'
			);
			
			add_action('init', array($this, 'action_init'));
		} // End function __construct()
		
		/**
		 * Wordpress hook for init action
		 *  - Registers custom post type
		 *  - Setups up shortcode
		 * @return void
		 */
		function action_init()
		{
			register_post_type(self::post_type, array(
				'label' => 'Documents',
				'labels' => $this->labels,
				'description' => 'Upload Documents for display in posts/pages',
				'public' => false, //  Implies exclude_from_search: true, publicly_queryable: false, show_in_nav_menus: false show_ui: false
				'menu_icon' => 'dashicons-media-spreadsheet',
				'hierarchical' => false,
				'supports' => array('title'),
				'has_archive' => false,
				'rewrite' => false,
				'query_var' => false
			));
			
			add_shortcode('csvview', array($this, 'sc_csv_view'));
			
			add_action('wp_enqueue_scripts', array($this, 'action_enqueue_scripts'));
		} // End function action_init()
		
		/**
		 * WP Action 'wp_queue_scripts'
		 *
		 * @return void
		 */
		function action_enqueue_scripts()
		{
			/**
			 * Enqueue plugin CSS file
			 */
			wp_register_style(
				'aad-doc-manager-css', 							// Handle
				plugins_url('aad-doc-manager.css', __FILE__), 	// URL to CSS file
				array(), 										// Dependencies
				self::PLUGIN_VER,								// CSS Version
				'all'											// Use for all media types
			);
			wp_enqueue_style('aad-doc-manager-css');
		}
		
		/**
		 * 'csvview' WP shortcode
		 *
		 * Usage: [csvview id=<post_id>]
		 *  id is mandatory and must specify the post id of a custom post supported by this plugin
		 *
		 * @param attrs, associative array of shortcode parameters
		 * @param content, not expecting any content
		 * @return HTML string
		 */
		function sc_csv_view($attrs, $content = null)
		{
			$a = shortcode_atts( array( 'id' => null), $attrs );
			$doc_id = intval($a['id']);
			
			if (! $doc_id) return ""; // No id value received - nothing to do
			
			/**
			 * Retrieve the post
			 */
			$post = get_post($doc_id);
			if (!$post) return;
			
			/**
			 * Make sure post type of the retrieved post is valid
			 */
			if (self::post_type != $post->post_type || 'publish' != $post->post_status) return;
			
			$table = unserialize($post->post_content);
			$col_headers = array_map(function($col_data){ return sanitize_text_field($col_data);}, get_post_meta($doc_id, 'csv_col_headers', true));
			$columns = get_post_meta($doc_id, 'csv_cols', true);

			$result = '<div class="aad-doc-manager">'; // Start of table/list output
			
			// TODO Use wp_is_mobile() to select output format?
			// TODO Add back-to-top navigation - float it on side?  Every 10?
			
			if (wp_is_mobile()) {
				/**
				 * Build list format for display on narrow screens
				 *
				 * Column headers sanitized above
				 */
				$result .= '<ol class="mobile">';
				foreach ($table as $index => $row) {
					$result .= "<li>";
					$result .= "<dl>";
					for ($col=0; $col < $columns; $col++) {
						if ("" != $row[$col]) {
							$result .= "<dt>" . $col_headers[$col] . "</dt>";
							$result .= "<dd>" . str_replace("\n", "<br />", esc_textarea($row[$col])) . "</dd>";
						}
					}
					$result .= "</dl>";
				}
				$result .= "</ol>";
			} else {
				/**
				 * Build table format for display on wide screens
				 *
				 * Column headers sanitized above
				 */
				$result .= '<table class="full-width">';
				$result .= '<thead><tr><th>#</th>' . implode(array_map(function ($col_data) {return "<th>" . $col_data . "</th>"; }, $col_headers)) . '</tr></thead>';
				$result .= '<tbody>';
				foreach ($table as $index => $row) {
					$result .= "<tr><td>" . intval($index + 1)  . "</td>";
					$result .= implode(array_map(function ($col_data){ return "<td>" . str_replace("\n", "<br />", esc_textarea($col_data)) . "</td>"; }, $row));
					$result .= "</tr>";
				}
				$result .= "</tbody></table>";
			}
			
			$result .= "</div>"; // Close the containing div

			return $result;
		}
		
	} // End class aad_doc_manager
	
} // End if
