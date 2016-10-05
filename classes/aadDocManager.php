<?php
/*
 * Class to display and download documents
 *
 * @package Document Manager
 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
 * @copyright 2016 Kenneth J. Brucker (email: ken.brucker@action-a-day.com)
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

/**
 * Protect from direct execution
 */
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

// RFE Add shortcode to download target document

if ( ! class_exists( "aadDocManager" ) ) {
	class aadDocManager
	{
		/**
		 * @var string Plugin version
		 */
		const PLUGIN_VER = "0.4";

		/**
		 * @var string Post Type Name
		 */
		const post_type = 'aad-doc-manager';
		
		/**
		 * @var array Post Labels
		 */
		static $post_type_labels = array();
		
		/**
		 * @var string UUID Taxonomy name
		 */
		const term_uuid = 'aad-doc-uuid';
		
		/**
		 * @var array UUID Taxonomy labels
		 */
		static $term_uuid_labels = array();

		/**
		 * CSV storage format version
		 *
		 * Undefined => post->content is serialized data
		 * 1 => HTML rendered during upload, saved as post_content.
		 *      table array stored in post meta data field csv_table
		 * 2 => Storage same as (1).
		 *      HTML content modified to support highlighting plugin - requires regen of older HTML to support
		 * 3 => Rendered data stored as meta data - post_content is empty
		 *      Allows re-save of pre-rendered data without affecting post update date
		 */
		const CSV_STORAGE_FORMAT = 3;
		
		function __construct () {
			;
		}

		/**
		 * Do the initial hooking into WordPress
		 *
		 * @param void
		 * @return void
		 */
		function setup()
		{
			self::$post_type_labels = array (
				'name'               => __( 'Documents', 'aad-doc-manager' ),
				'singular_name'      => __( 'Document', 'aad-doc-manager' ),
				'menu_name'          => __( 'Documents', 'aad-doc-manager' ),
				'name_admin_bar'     => __( 'Document', 'aad-doc-manager' ),
				'all_items'          => __( 'All Documents', 'aad-doc-manager' ),
				'add_new'            => __( 'Upload Document', 'aad-doc-manager' ),
				'add_new_item'       => __( 'Upload Document', 'aad-doc-manager' ),
				'edit_item'          => __( 'Update Document', 'aad-doc-manager' ),
				'new_item'           => __( 'Upload New Document', 'aad-doc-manager' ),
				'view_item'          => __( 'View Document', 'aad-doc-manager' ),
				'search_item'        => __( 'Search Document', 'aad-doc-manager' ),
				'not_found'          => __( 'No Documents found', 'aad-doc-manager' ),
				'not_found_in_trash' => __( 'No Documents found in Trash', 'aad-doc-manager' )
			);
			
			self::$term_uuid_labels = array (
				'name'						 => __( 'Document UUIDs', 'aad-doc-manager' ),
				'singular_name'				 => __( 'Document UUID', 'aad-doc-manager' ),
				'menu_name'					 => __( 'Document UUIDs', 'aad-doc-manager' ),
				'all_items'					 => __( 'All Document UUIDs', 'aad-doc-manager' ),
				'edit_item'					 => __( 'Edit Document UUID', 'aad-doc-manager' ),
				'view_item'					 => __( 'View Document UUID', 'aad-doc-manager' ),
				'update_item'				 => __( 'Update Document UUID', 'aad-doc-manager' ),
				'add_new_item'				 => __( 'Add new Document UUID', 'aad-doc-manager' ),
				'new_item_name'				 => __( 'New Document UUID', 'aad-doc-manager' ),
				'search_items'				 => __( 'Search Document UUIDs', 'aad-doc-manager' ),
				'separate_items_with_commas' => __( 'Separate UUIDs with commands', 'aad-doc-manager' ),
				'add_or_remove_items'		 => __( 'Add or Remove UUIDs', 'aad-doc-manager' ),
				'not_found'					 => __( 'No Document UUIDs found', 'aad-doc-manager' )
			);

			add_action( 'init', array( $this, 'action_plugin_setup' ) );
		}

        /**
         * Plugin Activation actions
         *
         * @param void
         * @return void
         */
        static function plugin_activation() {
            /**
             * Register document post type
             */
            self::register_document_post_type();

            /**
             *  Reset permalinks after post type registration and endpoint creation
             */
            flush_rewrite_rules();
        }

        /**
         * Plugin Deactivation actions
         *
         * @param void
         * @return void
         */
        static function plugin_deactivation() {
            /**
             * Reset permalinks
             */
            flush_rewrite_rules();
        }

		/**
		 * Hook into WordPress - Call from WP init action
		 *  - Register custom post type
		 *  - Setup up shortcodes
		 *  - Register actions
         *
		 * @param void
		 * @return void
		 */
		function action_plugin_setup()
		{
            /**
             * Register document post type
             */
            self::register_document_post_type();

            /**
             * Add shortcodes
             */
			add_shortcode( 'csvview', array( $this, 'sc_docmgr_csv_table') ); // Deprecated, but leave in
			add_shortcode( 'docmgr-csv-table', array( $this, 'sc_docmgr_csv_table') );
			add_shortcode( 'docmgr-created', array( $this, 'sc_docmgr_created' ) );
			add_shortcode( 'docmgr-modified', array( $this, 'sc_docmgr_modified' ) );

            /**
             * Setup needed actions
             */
			add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_styles' ) );
			add_action( 'wp_footer', array( $this, 'action_init_datatables' ) );

            /**
             * Check for usage of endpoint in template_redirect
             */
            add_action( 'template_redirect', array( $this, 'action_try_endpoint' ) );
		}

        /**
         * Register document post type and associated taxonomy
         *
         * @param void
         * @return void
         */
        private static function register_document_post_type() {
            register_post_type( self::post_type, array(
				'labels'		 => self::$post_type_labels,
				'description'	 => __( 'Upload Documents for display in posts/pages', 'aad-doc-manager' ),
				'public'		 => false,	//  Implies exclude_from_search: true,
											//          publicly_queryable: false,
											//          show_in_nav_menus: false,
											//          show_ui: false
				'menu_icon'		 => 'dashicons-media-spreadsheet',
				'hierarchical'	 => false,
				'supports'		 => array( 'title' ),
				'has_archive'	 => false,
				'rewrite'		 => false,
				'query_var'		 => false,
				'taxonomies'     => array( self::term_uuid )
			) );
			
			register_taxonomy( self::term_uuid, self::post_type, array(
				'labels' => self::$term_uuid_labels,
				'public'		 => true,
				'show_ui'		 => false,
				'show_in_nav_menus' => false,
				'show_tag_cloud' => false,
				'show_in_quick_edit' => false,
				'show_admin_column' => false,
				'show_in_menu' => false,
				'show_in_ui' => false,
				'description' => __( 'Document UUID to internal ID mapping', 'aad-doc-manager' ),
				'query_var' => 'aad-document',
				'rewrite' => array(
					'slug' => 'aad-document',
					'with_front' => true,
					'hierarchical' => false,
					'ep_mask' => EP_ROOT
				)
			) );
			
			register_taxonomy_for_object_type( self::term_uuid, self::post_type );
        }
		
        /**
         * Redirect to document download
         */
        function action_try_endpoint() {
            global $wp_query;

            /**
             * Does query match taxonomy endpoint?
             */
            $requested_doc = $wp_query->get( 'aad-document' );
            if ( ! $requested_doc )
                return;

            /**
             * Find document based on provided UUID
             *
             * document type should match custom post type
             * Post meta data should point to a media attachment
             */
			$args	 = array(
				'post_type' => self::post_type,
				'tax_query' => array(
					array(
						'taxonomy'	 => self::term_uuid,
						'field'		 => 'name',
						'terms'		 => $requested_doc
					)
				)
			);
			$query	 = new WP_Query( $args );
			
            $document = $query->post;
            if ( ! $document || self::post_type != $document->post_type ) {
                $this->error_404();
                // Not Reached
            }
			$doc_id = $document->ID;

            $attachment_id = get_post_meta( $doc_id, 'document_media_id', true );
            $attachment = get_post( $attachment_id );
            if ( ! $attachment_id || ! $attachment || 'attachment' != $attachment->post_type ) {
                $this->error_404();
                // Not Reached
            }

            /**
             * Dump the file
             */
            $file = get_attached_file( $attachment_id );
            
            if (file_exists( $file ) ) {
                /**
                 * Log download of the file
                 */
                $this->log_download( $doc_id );

                /**
                 * Output headers and dump the file
                 */
                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: ' . esc_attr( $attachment->post_mime_type ) );
                header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
                header( 'Content-Length: ' . filesize( $file ) );
                readfile( $file );
                
                die();
            } else {
                $this->error_404();
                // Not Reached
            }
            // Not Reached
        }
        
        /**
         * Display a 404 error
         * 
         * @param void
         * @return void
         */
        private function error_404() {
            global $wp_query;
            
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            die();
        }
        
        /**
         * Log download of a file
         * 
         * @param int $doc_id, document ID that was downloaded
         * @return void
         */
        private function log_download( $doc_id ) {
            /**
             * Atomic Increment download count
             */
            do {
                $value = $old = get_post_meta( $doc_id, 'download_count', true );
                $value++;
            } while ( ! update_post_meta( $doc_id, 'download_count', $value, $old ) );
        }

		/**
		 * WP Action 'wp_enqueue_scripts'
		 *
		 * @param void
		 * @return void
		 */
		function action_enqueue_scripts()
		{
			/**
			 * Register DataTable Jquery plugin (See https://datatables.net)
			 *
			 * URL for access built using Download builder at https://datatables.net/download/index
			 * Selected options: DataTables, DataTables Styling, Responsive Extension
			 */
			wp_register_script(
				'aad-doc-manager-datatable-js',
				'//cdn.datatables.net/v/dt/dt-1.10.12/r-2.1.0/datatables.min.js',
				array( 'jquery' ), 								// Dependencies
				'1.10.12', 										// DataTables version
				true											// Enqueue in footer
			);

			/**
			 * Register jQuery Text Highlighter (retrieved from //bartaz.github.io/sandbox.js/jquery.highlight.js)
			 */
			wp_register_script(
				'aad-doc-manager-jquery-highlight-js',
				plugins_url( 'pkgs/jquery.highlight/jquery.highlight.js', dirname( __FILE__ ) ),
				array( 'jquery' ), 								// Dependencies
				'2010.10.1',									// Script version - Released Dec 1, 2010
				true											// Enqueue in footer
			);

			/**
			 * Register DataTables Highlighting plugin (https://datatables.net/blog/2014-10-22)
			 *
			 * Depends on jQuery Text Highlighter
			 */
			wp_register_script(
				'aad-doc-manager-datatable-highlight-js',
				'//cdn.datatables.net/plug-ins/1.10.12/features/searchHighlight/dataTables.searchHighlight.min.js',
				array( 'jquery', 'aad-doc-manager-datatable-js', 'aad-doc-manager-jquery-highlight-js' ), // Dependencies
				'1.10.12', 										// DataTables version
				true											// Enqueue in footer
			);

			/**
			 * Enqueue the Datatables scripts - dependencies will get them all pulled in
			 */
			wp_enqueue_script( 'aad-doc-manager-datatable-highlight-js' );

		}

		/**
		 * WP Action 'wp_enqueue_styles'
		 *
		 * @param void
		 * @return void
		 */
		function action_enqueue_styles()
		{
			/**
			 * Enqueue plugin CSS file
			 */
			wp_register_style(
				'aad-doc-manager-css', 							// Handle
				plugins_url( 'aad-doc-manager.css', dirname( __FILE__ ) ),	// URL to CSS file
				false,	 										// No Dependencies
				self::PLUGIN_VER,								// CSS Version
				'all'											// Use for all media types
			);
			wp_enqueue_style( 'aad-doc-manager-css' );

			/**
			 * Enqueue DataTable CSS
			 *
			 * See action_enqueue_scripts for notes on source URL for DataTables
			 */
			wp_register_style(
				'aad-doc-manager-datatable-css',
				'//cdn.datatables.net/v/dt/dt-1.10.12/r-2.1.0/datatables.min.css',
				false,			 								// No dependencies
				'1.10.12', 										// DataTables version
				'all'											// All media types
			);
			wp_enqueue_style('aad-doc-manager-datatable-css');

			/**
			 * Enqueue DataTable Highlighter CSS
			 *
			 * See action_enqueue_scripts for notes on source URL for DataTables
			 */
			wp_register_style(
				'aad-doc-manager-datatable-highlight-css',
				'//cdn.datatables.net/plug-ins/1.10.12/features/searchHighlight/dataTables.searchHighlight.css',
				false,			 								// No dependencies
				'1.10.12', 										// DataTables version
				'all'											// All media types
			);
			wp_enqueue_style('aad-doc-manager-datatable-highlight-css');
		}

		/**
		 * 'docmgr-csv-table' WP shortcode
		 *
		 * Usage: [docmgr-csv-table id=<post_id> {disp_date=0|1}]
		 *  id is mandatory and must specify the post id of a custom post supported by this plugin
		 *  date, boolean, 1 ==> Include last modified date in table caption
		 *  row-colors, string, comma separated list of row color for each n-rows.
		 *  row-number, boolean, 1 ==> Include row numbers
		 *
		 * @param $_attrs, associative array of shortcode parameters
		 * @param $content, not expecting any content
		 * @return HTML string
		 */
		function sc_docmgr_csv_table( $_attrs, $content = null )
		{
			$default_attrs = array(
				'id'         => null,
				'date'       => 1,		// Display modified date in caption by default
				'row-colors' => null,	// Use default row colors
				'row-number' => 1		// Display row numbers
			);

			/**
			 * Get shortcode parameters and sanitize
			 */
			$attrs = shortcode_atts( $default_attrs, $_attrs );

			$doc_id              = $attrs['id'] = intval( $attrs['id'] );
			$caption_date        = $attrs['date'] = intval( $attrs['date'] );
			$attrs['row-number'] = intval( $attrs['row-number'] );
			$attrs['row-colors'] = $this->sanitize_row_colors( $attrs['row-colors'] );

			/**
			 * Must re-render if any options change default rendering
			 */
			$render_defaults = $attrs['row-colors'] == null && $attrs['row-number'] == 1;

			if ( ! $doc_id ) return ""; // No id value received - nothing to do

			/**
			 * Retrieve the post
			 */
			$document = get_post($doc_id);
			if ( ! $document ) return "";

			/**
			 * Make sure post type of the retrieved post is valid
			 */
			if ( self::post_type != $document->post_type || 'publish' != $document->post_status ) return "";

			/**
			 * Start of content area
			 *
			 *     *******************************************************************
			 *     ******** IF table format changes, bump CSV_STORAGE_FORMAT *********
			 *     *******************************************************************
			 */
			$result = '<div class="aad-doc-manager">';
			$result .= '<table class="aad-doc-manager-csv searchHighlight responsive no-wrap" width="100%">';

			/**
			 * Include caption if needed
			 */
			if ( $caption_date ) {
				if ( $document->post_modified_gmt == $document->post_date_gmt ) {
					$text = __( 'Created', 'aad-doc-manager' );
				} else {
					$text = __( 'Updated', 'aad-doc-manager' );
				}
				$result .= '<caption>';
				$result .= "$text: " . esc_attr( $this->format_date( $document->post_modified ) );
				$result .= '</caption>';
			}

			/**
			 * CSV Storage format 1:
			 *   post_content has pre-rendered HTML, post_meta[csv_table] is table in array form
			 * Original format (Undefined value for csv_storage_format):
			 *   post_content is serialized array of table content
			 */
			$storage_format = get_post_meta( $doc_id, 'csv_storage_format', true );
			switch ( $storage_format ) {
			case 1:
			case 2:
				/**
				 * Some formatting changes have been made in HTML - Re-render
				 *
				 * Save result only if default options have been selected
				 */

				$result .= $this->re_render_csv( $doc_id, $attrs, $render_defaults );
				break;

			case self::CSV_STORAGE_FORMAT:
				/**
				 * If non-default options or DEBUG mode specific, must re-render table
				 */
				if ( !$render_defaults || WP_DEBUG) {
					$result .= $this->re_render_csv( $doc_id, $attrs, false ); // re-render, do not save result in DB
				} else {
					$result .= get_post_meta( $doc_id, 'csv_rendered', true );	// Use pre-rendered content for default display
				}
				break;

			default:
				/**
				 * Unknown storage format
				 */
				if (WP_DEBUG) {
					$result .= '<tr><td>';
					$result .= 'Nothing to display - Unknown CSV Storage format "' . esc_attr( $storage_format ) .
						'" found for post_id ' . esc_attr( $doc_id );
					$result .= '<td></tr>';
				}
			}
			$result .= '</table></div>';

			return $result;
		}

		/**
		 * 'docmgr-created' WP shortcode
		 *
		 * @param _attrs, associative array of shortcode parameters
		 * @param content, not expecting any content
		 * @return HTML string
		 */
		function sc_docmgr_created( $_attrs, $content = null )
		{
			$default_attrs = array( 'id' => null );

			$attrs = shortcode_atts( $default_attrs, $_attrs ); // Get shortcode parameters
			$doc_id = intval( $attrs['id'] );

			if ( ! $doc_id ) return ""; // No id value received

			/**
			 * Retrieve the post
			 */
			$document = get_post( $doc_id );
			if ( ! $document ) return "";

			/**
			 * Make sure post type of the retrieved post is valid
			 */
			if ( self::post_type != $document->post_type || 'publish' != $document->post_status ) return "";

			return esc_attr( $this->format_date( $document->post_date ) );
		}

		/**
		 * 'docmgr-modified' WP shortcode
		 *
		 * @param _attrs, associative array of shortcode parameters
		 * @param content, not expecting any content
		 * @return HTML string
		 */
		function sc_docmgr_modified( $_attrs, $content = null )
		{
			$default_attrs = array( 'id' => null );

			$attrs = shortcode_atts( $default_attrs, $_attrs ); // Get shortcode parameters
			$doc_id = intval( $attrs['id'] );

			if ( ! $doc_id ) return ""; // No id value received - nothing to do

			/**
			 * Retrieve the post
			 */
			$document = get_post( $doc_id );
			if ( ! $document ) return "";

			/**
			 * Make sure post type of the retrieved post is valid
			 */
			if ( self::post_type != $document->post_type || 'publish' != $document->post_status ) return;

			return esc_attr( $this->format_date( $document->post_modified ) );
		}

		/**
		 * Re-render previously saved HTML and update DB
		 *
		 * @param $doc_id, post ID for target document
		 * @param $attrs, shortcode attributes
		 * @param $save, boolean true=>Save re-render to DB
		 * @return string, document rendered as HTML
		 */
		protected function re_render_csv( $doc_id, $attrs, $save )
		{
			$render_data = array(
				'col-headers' => get_post_meta( $doc_id, 'csv_col_headers', true ),
				'columns'     => get_post_meta( $doc_id, 'csv_cols', true ),
				'table'       => get_post_meta( $doc_id, 'csv_table', true ),
				'row-number'  => $attrs['row-number'],
				'row-colors'  => $attrs['row-colors']
			);

			$html = $this->render_csv( $render_data );

			/**
			 * Save the newly rendered data if requested
			 *
			 * There will be obsolete HTML content still stored as $post->post_content.
			 * Unavoidable as it results in incorrectly modifying the post update date
			 */
			if ( $save ) {
				update_post_meta( $doc_id, 'csv_rendered', $html );
				update_post_meta( $doc_id, 'csv_storage_format', self::CSV_STORAGE_FORMAT );
			}

			return $html;
		}

		/**
		 * Generate HTML to display table header and body of CSV data
		 *
		 * @param $render_data, associative array
		 *    col-headers, string array of headers for each column of table
		 *    table, array of rows of of strings for each row column
		 *    columns, number of rows in table
		 * @return string, HTML
		 */
		protected function render_csv( $render_data )
		{
			$row_num = array_key_exists( 'row-number', $render_data ) ? $render_data['row-number'] : 1;
			$row_colors = array_key_exists( 'row-colors', $render_data ) ? $render_data['row-colors'] : null;

			/**
			 * Build table header and body
			 *
			 *     *******************************************************************
			 *     ******** IF table format changes, bump CSV_STORAGE_FORMAT *********
			 *     *******************************************************************
			 */
			$result = '<thead><tr>';

			if ($row_num) $result .= '<th>#</th>'; // Include row number?

			$result .= implode( array_map( function ( $col_data ) {return "<th>" . sanitize_text_field( $col_data ) . "</th>"; }, $render_data['col-headers'] ) );
			$result .= '</tr></thead>';
			$result .= '<tbody>';
			$cell_style = "";
			foreach ( $render_data['table'] as $index => $row ) {
				if ( $row_colors ) {
					$cell_style = 'style="background-color: ' . $row_colors[$index % count( $row_colors )] . '"';
				}
				$result .= '<tr>';

				if ( $row_num ) $result .= '<td>' . intval( $index + 1 )  . '</td>'; // Include row number?

				$row = array_pad( $row, $render_data['columns'], '' ); // Pad the row to the overall column count
				foreach ($row as $col_data) {
					$result .= '<td ' . $cell_style . '>' . $this->nl2list( $col_data ) . '</td>';
				}
				$result .= '</tr>';
			}
			$result .= '</tbody></table>';

			return $result;
		}

		/**
		 * Convert block of text with embedded new lines into a list
		 *
		 * @param $text, string with embedded new-line characters
		 * @return string, HTML
		 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
		 */
		private function nl2list( $text )
		{
			/**
			 * Split the input on new-line and turn into a list format if more than one line present
			 */
			$list = explode( "\n", $text );
			if (count($list) > 1) {
				/**
				 * Build list of multi-line item
				 */
				return '<ul class="aad-doc-manager-csv-list">' .
					implode( array_map (function( $li ){ return '<li>' . esc_attr( $li ); }, $list ) ) . '</ul>';
			} else
				return esc_attr( $text );
		}

		/**
		 * Emit HTML required to initialize DataTables Javascript plugin
		 *
		 * Setup as wp_footer action
		 *
		 * @return void
		 */
		function action_init_datatables()
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

		/**
		 * Format a date/time using WP General Settings format
		 *
		 * @param $date, string - Date+Time YYYY-MM-DD HH:MM:SS
		 * @return string, formatted date + time
		 */
		private function format_date( $date )
		{
			static $format;

			if ( ! isset( $format ) ) {
				$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			}

			return esc_attr( mysql2date( $format, $date ) );
		}

		/**
		 * Sanitize text string of comma separated row colors and convert to array
		 *
		 * @param $string, comma separated string of row colors
		 * @return array of strings, row colors
		 */
		private function sanitize_row_colors( $string )
		{
			if ( ! isset( $string ) ) return null;

			$row_colors = array_map( array( $this, 'sanitize_row_color' ), explode( ',', $string ) );
			return $row_colors;
		}

		/**
		 * Sanitize a given row color text string
		 *
		 * @param $string, cell color
		 * @return string
		 */
		private function sanitize_row_color( $string )
		{
			$string = trim( $string );

			/**
			 * 3 or 6 digit hex color?
			 */
			if ( preg_match( '/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $string, $matches ) ) {
				return '#' . $matches[1];
			}

			/**
			 * Maybe a text color name - return it.
			 */
			return sanitize_key( $string );
		}
	} // End class aad_doc_manager

} // End if
