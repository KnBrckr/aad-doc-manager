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

/*
			if (isset($this->header_names)) {
				echo "<table>";
				echo "<tr><th>Row</th>";
				array_map(function ($col_data) { echo "<th>" . str_replace("\n", "<br />", $col_data) . "</th>"; }, $this->header_names);
				echo "</tr>";
				foreach ($this->table as $index => $row) {
					echo "<tr><td>$index</td>";
					array_map(function ($col_data) { echo "<td>" . str_replace("\n", "<br />", $col_data) . "</td>"; }, $row);
					echo "</tr>";
					if ($index > 4) break;
				}
				echo "</table>";
			}

*/

if (! class_exists("aad_doc_manager")) {
	class aad_doc_manager
	{
		/**
		 * Plugin version
		 */
		const PLUGIN_VER = "0.1";

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
				'edit_item'          => 'Manage Document',
				'new_item'           => 'Upload Document',
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
			if (! $a['id']) return ""; // No id value received - nothing to do
			
			/**
			 * FIXME - Retrieve the post
			 */
			$post = get_post((int)$a['id']);
			if (!$post) return;
			
			/**
			 * Make sure post type of the retrieved post is valid
			 */
			if (self::post_type != $post->post_type || 'publish' != $post->post_status) return;
			
			$table = json_decode($post->post_content);
			
			/**
			 * Build table format for display on wide screens
			 */
			$result = '<div class="aad-doc-manager"><table class="full-width">';
			// FIXME Output Column headers
			foreach ($table as $index => $row) {
				$result .= "<tr><td>$index</td>";
				// FIXME Newline handling broken
				// FIXME Escape the data before output
				$result .= implode(array_map(function ($col_data){ return "<td>" . str_replace("\n", "<br />", $col_data) . "</td>"; }, $row));
				$result .= "</tr>";
			}
			$result .= "</table>";
			
			/**
			 * Build list format for display on narrow screens
			 */
			$result .= '<ol class="mobile">';
			// FIXME Output Column headers
			foreach ($table as $index => $row) {
				$result .= "<li>" . $row[0];
				$result .= "<ul>";
				// FIXME Newline handling broken
				// FIXME Escape the data before output
				$result .= implode(array_map(function ($col_data){ return "<li>" . str_replace("\n", "<br />", $col_data); }, $row));
				$result .= "</ul>";
			}
			$result .= "</ol>";
			
			$result .= "</div>"; // Close the containing div

			return $result;
		}
		
	} // End class aad_doc_manager
	
} // End if
