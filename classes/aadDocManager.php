<?php
/*
 * Class to display and download documents
 *
 * @package PumaStudios-DocManager
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

/**
 * Protect from direct execution
 */
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( "aadDocManager" ) ) {
	class aadDocManager
	{
		/**
		 * @var string Path to Datatables assets
		 */
		private $datatables_dir_url;

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
			$this->datatables_dir_url = plugins_url( 'aad-doc-manager/assets/DataTables' );
		}

		/**
		 * Do the initial hooking into WordPress
		 *
		 * @param void
		 * @return void
		 */
		function setup()
		{


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
			add_shortcode( 'docmgr-download-url', array( $this, 'sc_docmgr_download_url' ) );

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

			if ( class_exists( 'WooCommerce' ) ) {
				/**
				 * Add a woocommerce filter to enable the use of Document Manager documents as downloadable content
				 */
				add_filter( 'woocommerce_downloadable_file_exists', array( $this, 'filter_woo_downloadable_file_exists' ), 10, 2 );

				/**
				 * Fixup document path for woocommerce downloadable products
				 */
				//add_filter( 'woocommerce_file_download_path', array( $this, 'filter_woo_file_download_path' ), 10, 3 );
			}
		}

        /**
         * Redirect to document download
         */
        function action_try_endpoint() {
            global $wp_query;

            /**
             * Does query match taxonomy endpoint?
             */
            $requested_doc = $wp_query->get( self::download_slug );
            if ( ! $requested_doc )
                return;

			/**
			 * Get Document to be downloaded
			 */
			$document = $this->get_document_by_guid( $requested_doc );
            if ( ! is_a( $document, 'WP_post') ) {
                $this->error_404();
                // Not Reached
            }

			$attachment = $this->get_attachment_by_docid( $document->ID );
			$file = $this->get_realpath_by_attachment( $attachment );

            if ( '' != $file && file_exists( $file ) ) {
                /**
                 * Log download of the file
                 */
                $this->log_download( $document->ID );

                /**
                 * Output headers and dump the file
                 */
                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: ' . esc_attr( $attachment->post_mime_type ) );
                header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
                header( 'Content-Length: ' . filesize( $file ) );
				nocache_headers();

				/**
				 * Flush headers to avoid over-write of the Content Type by PHP or Server
				 */
				flush();

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
			//	TODO Deal with situation when theme does not provide a 404 template.
            get_template_part(404);
            die();
        }

        /**
         * Log download of a file
         *
         * @param int $doc_id document ID that was downloaded
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
				$this->datatables_dir_url . '/datatables.min.js',
				array( 'jquery' ), 								// Dependencies
				'1.10.16',	 									// Datatables version
				true											// Enqueue in footer
			);

			/**
			 * Register mark.js highlighting plugin used by Datatables for highlighting
			 * Ref: https://datatables.net/blog/2017-01-19
			 *
			 * Retrieved via
			 *  % wget -O jquery.mark.min.js https://cdn.jsdelivr.net/g/mark.js\(jquery.mark.min.js\)
			 */
			wp_register_script(
				'aad-doc-manager-mark-js',
				$this->datatables_dir_url . '/jquery.mark.min.js',
				array(),										// Dependencies
				'8.9.1',										// Version of mark.js
				true											// Enqueue in footer
			);
			wp_enqueue_script( 'aad-doc-manager-mark-js' );

			/**
			 * Register DataTables highlighting plugin based on mark.js
			 * Ref: https://datatables.net/blog/2017-01-19
			 *
			 * Retrieved from https://cdn.datatables.net/plug-ins/1.10.16/features/mark.js/datatables.mark.js
			 */
			wp_register_script(
				'aad-doc-manager-datatable-mark-js',
				$this->datatables_dir_url . '/datatables.mark.js',
				array ( 'aad-doc-manager-datatable-js', 'aad-doc-manager-mark-js' ), // Dependencies
				'1.10.16',										// Datatables version
				true											// Enqueue in footer
			);
			wp_enqueue_script( 'aad-doc-manager-datatable-mark-js' );

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
				$this->datatables_dir_url . '/datatables.min.css',
				false,			 								// No dependencies
				'1.10.16',										// Version of Data Tables
				'all'											// All media types
			);
			wp_enqueue_style('aad-doc-manager-datatable-css');
		}

		/**
		 * Allow WooCommerce to understand that a document provided by Document Manager is downloadable
		 *
		 * Uses filter defined in class-wc-product-download.php:file_exists()
		 *
		 * @param boolean $file_exists Earlier filters may have already decided if file exists
		 * @param string $file_url path to the downloadable file
		 */
		function filter_woo_downloadable_file_exists( $file_exists, $file_url ) {
			if ( '/' . self::download_slug === substr( $file_url, 0, strlen( self::download_slug) + 1 ) ) {
				/**
				 * link is for the plugin. Confirm GUID provided is valid
				 */
				$guid = substr( $file_url, strlen( self::download_slug ) + 2 );
				if ( $this->is_guidv4( $guid ) &&  is_a( $this->get_document_by_guid( $guid ), 'WP_post') )
					return true;
				else
					return false;
			} else
				return $file_exists;
		}

		/**
		 * Provide woocommerce with real path for managed documents
		 *
		 * @param string $file file path recorded in woocommerce downloadable product
		 * @param WC_product $product_id woocommerce product id
		 * @param string $key woocommerce download document key
		 * @return string path to file
		 */
		function filter_woo_file_download_path( $file, $product, $key ) {
			if ( '/' . self::download_slug === substr( $file, 0, strlen( self::download_slug) + 1 ) ) {
				/**
				 * link is for the plugin. Confirm GUID provided is valid
				 */
				$guid = substr( $file, strlen( self::download_slug ) + 2 );

				if ( ! $this->is_guidv4( $guid ) )
					return $file;

				$document = $this->get_document_by_guid( $guid );
				if ( ! is_a( $document, 'WP_post' ) )
					return $file;

				$attachment = $this->get_attachment_by_docid( $document->ID );
				$fileurl = $this->get_url_by_attachment( $attachment );

				return $fileurl ? $fileurl : $file;
			} else
				return $file;
		}


		/**
		 * Get the real path to an attachment
		 *
		 * @param WP_ $attachment Attachment object
		 * @return string path to downloadable file
		 */
		private function get_realpath_by_attachment( $attachment ) {
			if (! is_a( $attachment, 'WP_post' ) || 'attachment' != $attachment->post_type )
				return null;

			$filename = realpath( get_attached_file( $attachment->ID ) ); // Path to attached file

			/**
			 * 	Only allow a path that is in the uploads directory.
			 */
			$upload_dir		 = wp_upload_dir( $create_dir = false );
			$upload_dir_base = realpath( $upload_dir[ 'basedir' ] );
			if ( strncmp( $filename, $upload_dir_base, strlen( $upload_dir_base ) ) !== 0 ) {
				return '';
			}

			return $filename;
		}

		/**
		 * Get url to an attachment
		 *
		 * @param WP_post $attachment attachment object
		 * @return string File URL
		 */
		private function get_url_by_attachment( $attachment ) {
			$filename = $this->get_realpath_by_attachment( $attachment );

			/* Strip off content directory */
			$real_content_dir = realpath(WP_CONTENT_DIR);
			if (substr($filename, 0, strlen($real_content_dir)) == $real_content_dir) {
			    $filename = substr($filename, strlen($real_content_dir));
			}

			return WP_CONTENT_URL . $filename;
		}

		/**
		 * 'docmgr-csv-table' WP shortcode
		 *
		 * Usage: [docmgr-csv-table id=<post_id> {disp_date=0|1}]
		 *  id is mandatory and must specify the post id of a custom post supported by this plugin
		 *  date, boolean, 1 ==> Include last modified date in table caption
		 *  row-colors, string, comma separated list of row color for each n-rows.
		 *  row-number, boolean, 1 ==> Include row numbers
		 *  page-length, integer, number of rows to display by default in table
		 *
		 * @param array _attrs associative array of shortcode parameters
		 * @param string $content Expected to be empty
		 * @return string HTML content
		 */
		function sc_docmgr_csv_table( $_attrs, $content = null )
		{
			$default_attrs = array(
				'id'         => null,
				'date'       => 1,		// Display modified date in caption by default
				'row-colors' => null,	// Use default row colors
				'row-number' => 1,		// Display row numbers
				'page-length' => 10     // Default # rows to display per page
			);

			/**
			 * Get shortcode parameters and sanitize
			 */
			$attrs = shortcode_atts( $default_attrs, $_attrs );

			$doc_id	= $attrs[ 'id' ]         = intval( $attrs[ 'id' ] );
			$caption_date = $attrs[ 'date' ] = intval( $attrs[ 'date' ] );
			$attrs[ 'row-number' ]           = intval( $attrs[ 'row-number' ] );
			$attrs[ 'row-colors' ]           = $this->sanitize_row_colors( $attrs[ 'row-colors' ] );
			$page_length                     = intval( $attrs['page-length'] );

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
			$result .= '<table class="aad-doc-manager-csv responsive no-wrap" width="100%" data-page-length="' . $page_length . '">';

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
		 * Displays document creation date
		 *
		 * Usage: [docmgr-created id=<doc_id>]
		 *
		 * @param array _attrs associative array of shortcode parameters
		 * @param string $content Expected to be empty
		 * @return string HTML content
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
		 * Displays document modified date
		 *
		 * Usage: [docmgr-modified id=<doc_id>]
		 *
		 * @param array _attrs associative array of shortcode parameters
		 * @param string $content Expected to be empty
		 * @return string HTML content
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
		 * 'sc_docmgr-download-url' WP shortcode
		 *
		 * Usage: [docmgr-download-url id=<doc_id]text[/docmgr-download-url]
		 *
		 * @param array $_attrs associative array of shortcode parameters
		 * @param string $content
		 * @return string HTML content
		 */
		function sc_docmgr_download_url( $_attrs, $content = null ) {
			$default_attrs = array( 'id' => null );

			$attrs = shortcode_atts( $default_attrs, $_attrs ); // Get shortcode parameters
			$doc_id = intval( $attrs['id'] );

			if ( ! $doc_id ) return $content; // No id value received - nothing to do

			/**
			 * Retrieve the post
			 */
			$document = get_post( $doc_id );
			if ( ! $document ) return $content;

			/**
			 * Make sure post type of the retrieved post is valid
			 */
			if ( self::post_type != $document->post_type || 'publish' != $document->post_status )
				return $content;

			$url = $this->get_download_url_e( $doc_id );
			if ( '' != $url ) {
				$text = '<a href="' . $url . '">' . $content . '</a>';
			} else {
				$text = $content;
			}

			return $text;
		}

		/**
		 * Re-render previously saved HTML and update DB
		 *
		 * @param string $doc_id post ID for target document
		 * @param array $attrs shortcode attributes
		 * @param boolean $save true=>Save re-render to DB
		 * @return string document rendered as HTML
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
		 * @param string $text with embedded new-line characters
		 * @return string HTML
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
			 * Add javascript to get DataTables running with highlighting (mark) enabled
			 */
			?>
			<script type="text/javascript">
			jQuery(document).ready(function() {
			    jQuery('.aad-doc-manager-csv').DataTable({
					mark:true
				});
			} );
			</script>
			<?php
		}

		/**
		 * Format a date/time using WP General Settings format
		 *
		 * @param string $date Date+Time YYYY-MM-DD HH:MM:SS
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
