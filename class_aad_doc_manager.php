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
		const PLUGIN_VER = "0.6";

		/**
		 * Custom Post Type id
		 */
		const post_type = 'aad-doc-manager';
		
		/**
		 * CSV storage format version
		 */
		const CSV_STORAGE_FORMAT = 1;
		
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
			add_action('wp_enqueue_scripts', array($this, 'action_enqueue_styles'));
			
			add_action('wp_footer', array($this, 'action_wp_footer'));
		} // End function action_init()
		
		/**
		 * WP Action 'wp_enqueue_scripts'
		 *
		 * @return void
		 */
		function action_enqueue_scripts()
		{
			/**
			 * Enqueue Datatable Jquery plugin
			 */
			wp_register_script(
				'aad-doc-manager-datatable-js',
				plugins_url('pkgs/DataTables-1.10.7/media/js/jquery.dataTables.min.js', __FILE__),
				array('jquery'), 								// Depends on jquery
				'1.10.7', 										// DataTables version
				true											// Enqueue in footer
			);
			wp_enqueue_script('aad-doc-manager-datatable-js');
			
			/**
			 * Enqueue Responsive extension to DataTable
			 */
			wp_register_script(
				'aad-doc-manager-datatable-responsive-js',
				plugins_url('pkgs/DataTables-1.10.7/extensions/Responsive/js/dataTables.responsive.min.js', __FILE__),
				array('jquery', 'aad-doc-manager-datatable-js'),// Depends on DataTable
				'1.10.7', 										// DataTables version
				true											// Enqueue in footer
			);
			wp_enqueue_script('aad-doc-manager-datatable-responsive-js');
			
		}
		
		/**
		 * WP Action 'wp_enqueue_styles'
		 *
		 * @return void
		 */
		function action_enqueue_styles()
		{
			/**
			 * Enqueue plugin CSS file
			 */
			wp_register_style(
				'aad-doc-manager-css', 							// Handle
				plugins_url('aad-doc-manager.css', __FILE__), 	// URL to CSS file, relative to this directory
				false,	 										// No Dependencies
				self::PLUGIN_VER,								// CSS Version
				'all'											// Use for all media types
			);
			wp_enqueue_style('aad-doc-manager-css');

			/**
			 * Enqueue DataTable CSS
			 */
			wp_register_style(
				'aad-doc-manager-datatable-css',
				plugins_url('pkgs/DataTables-1.10.7/media/css/jquery.dataTables.min.css', __FILE__),
				false,			 								// No dependencies
				'1.10.7', 										// DataTables version
				'all'											// All media types
			);
			wp_enqueue_style('aad-doc-manager-datatable-css');
			
			/**
			 * Enqueue DataTable Responsive Extension CSS
			 */
			wp_register_style(
				'aad-doc-manager-datatable-responsive-css',
				plugins_url('pkgs/DataTables-1.10.7/extensions/Responsive/css/dataTables.responsive.css', __FILE__),
				false,			 								// No dependencies
				'1.10.7', 										// DataTables version
				'all'											// All media types
			);
			wp_enqueue_style('aad-doc-manager-datatable-responsive-css');
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
			
			/**
			 * Start of content area
			 */
			$result = '<div class="aad-doc-manager">';
			
			/**
			 * CSV Storage format 1:
			 *   post_content has pre-rendered HTML, post_meta[csv_table] is table in array form
			 * Original format (Undefined value for csv_storage_format):
			 *   post_content is serialized array of table content
			 */
			if (1 == get_post_meta($doc_id, 'csv_storage_format', true)) {
				$result .= $post->post_content;
			} else {
				$col_headers = get_post_meta($doc_id, 'csv_col_headers', true);
				$table = unserialize($post->post_content);
			
				$result .= $this->render_csv($col_headers, $table);
			}
			
			$result .= '</div>';

			return $result;
		}
		
		/**
		 * Generate HTML to display table of CSV data
		 *
		 * @return string, HTML
		 */
		protected function render_csv($headers, $table)
		{
			/**
			 * Build table format
			 */
			$result = '<table class="aad-doc-manager-csv responsive no-wrap" width="100%">';
			$result .= '<thead><tr><th>#</th>' . implode(array_map(function ($col_data) {return "<th>" . sanitize_text_field($col_data) . "</th>"; }, $headers)) . '</tr></thead>';
			$result .= '<tbody>';
			foreach ($table as $index => $row) {
				$result .= '<tr><td>' . intval($index + 1)  . '</td>';
				$result .= implode(array_map(function ($col_data){ return '<td>' . $this->nl2list($col_data) . '</td>'; }, $row));
				$result .= '</tr>';
			}
			$result .= '</tbody></table>';
			
			return $result;
		}
		
		/**
		 * Convert block of text with embedded new lines into a list
		 *
		 * @return string, HTML
		 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
		 */
		private function nl2list($item)
		{
			/**
			 * Split the input on new-line and turn into a list format if more than one line present
			 */
			$list = explode("\n", $item);
			if (count($list) > 1) {
				/**
				 * Build list of multi-line item
				 */
				return '<ul class="aad-doc-manager-csv-list">' . implode(array_map(function ($li){ return '<li>' . esc_attr($li); }, $list)) . '</ul>';
			} else 
				return esc_attr($item);
		}
		
		/**
		 * WP Action registered for 'wp_footer'
		 *
		 * Sends inline javascript required for DataTable operation
		 *
		 * @return void
		 */
		function action_wp_footer()
		{
			/**
			 * Add javascript to get DataTables running
			 */
			?>
			<script type="text/javascript">
			jQuery(document).ready(function() {
			    jQuery('.aad-doc-manager-csv').DataTable();
			} );
			</script>
			<?php	
		}
		
	} // End class aad_doc_manager
	
} // End if
