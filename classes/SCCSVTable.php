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
 * Shortcode to display a CVS table in HTML format
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class SCCSVTable {

	/**
	 * @var int Increments for each table on current page
	 */
	static $index = 0;

	/**
	 * Plug into WP
	 *
	 * @since 1.0
	 */
	public static function run() {
		add_shortcode( 'csvview', [ self::class, 'sc_csvview' ] ); // Deprecated in v0.3
		add_shortcode( 'docmgr-csv-table', [ self::class, 'sc_docmgr_csv_table' ] );

		/**
		 * Setup Datatables
		 */
		add_action( 'wp_enqueue_scripts', [ self::class, 'action_enqueue_datatables' ] );
		add_action( 'wp_footer', [ self::class, 'action_init_datatables' ] );
	}

	/**
	 * Register and enqueue required Datatables JS scripts
	 *
	 * @since 1.0
	 */
	public static function action_enqueue_datatables() {
		/**
		 * Register DataTable Jquery plugin (See https://datatables.net)
		 *
		 * URL for access built using Download builder at https://datatables.net/download/index
		 * Selected options: DataTables, DataTables Styling, Responsive Extension
		 */
		wp_register_script(
			'aad-doc-manager-datatable-js', // handle
   Plugin::get_asset_url( 'DataTables', 'datatables.min.js' ), // script URL
						  [ 'jquery' ], // Dependencies
						  '1.10.16', // Datatables version
						  true  // Enqueue in footer
		);

		/**
		 * Register mark.js highlighting plugin used by Datatables for highlighting
		 * Ref: https://datatables.net/blog/2017-01-19
		 *
		 * Retrieved via
		 *  % wget -O jquery.mark.min.js https://cdn.jsdelivr.net/g/mark.js\(jquery.mark.min.js\)
		 */
		wp_register_script(
			'aad-doc-manager-mark-js', //handle
   Plugin::get_asset_url( 'DataTables', '/jquery.mark.min.js' ), // script URL
						  [], // Dependencies
						  '8.9.1', // Version of mark.js
						  true  // Enqueue in footer
		);
		wp_enqueue_script( 'aad-doc-manager-mark-js' );

		/**
		 * Register DataTables highlighting plugin based on mark.js
		 * Ref: https://datatables.net/blog/2017-01-19
		 *
		 * Retrieved from https://cdn.datatables.net/plug-ins/1.10.16/features/mark.js/datatables.mark.js
		 */
		wp_register_script(
			'aad-doc-manager-datatable-mark-js', //handle
   Plugin::get_asset_url( 'DataTables', '/datatables.mark.js' ), // Script URL
						  [ 'aad-doc-manager-datatable-js', 'aad-doc-manager-mark-js' ], // Dependencies
						  '1.10.16', // Datatables version
						  true  // Enqueue in footer
		);
		wp_enqueue_script( 'aad-doc-manager-datatable-mark-js' );

		/**
		 * Enqueue DataTable CSS
		 *
		 * See action_enqueue_scripts for notes on source URL for DataTables
		 */
		wp_register_style(
			'aad-doc-manager-datatable-css', // Handle
   Plugin::get_asset_url( 'DataTables', '/datatables.min.css' ), // Script URL
						  [], // No dependencies
						  '1.10.16', // Version of Data Tables
						  'all'  // All media types
		);
		wp_enqueue_style( 'aad-doc-manager-datatable-css' );
	}

	/**
	 * Emit HTML required to initialize DataTables Javascript plugin
	 *
	 * Setup as wp_footer action
	 *
	 * @since 1.0
	 */
	public static function action_init_datatables() {
		/**
		 * Add javascript to get DataTables running with highlighting (mark) enabled
		 */
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function () {
				jQuery( '.aad-doc-manager-csv' ).DataTable( {
					mark: true
				} );
			} );
		</script>
		<?php

	}

	/**
	 * 'docmgr-csv-table' WP shortcode
	 *
	 * Usage: [docmgr-csv-table id=<post_id> {disp_date=0|1}]
	 *  id is mandatory and must specify the post id of a custom post supported by this plugin
	 *  date boolean 1 ==> Include last modified date in table caption
	 *  row-colors string comma separated list of row color for each n-rows.
	 *  row-number boolean 1 ==> Include row numbers
	 *  page-length integer number of rows to display by default in table
	 *  rows string list of rows to include in output
	 *
	 * @param array _attrs associative array of shortcode parameters
	 * @param string $content Expected to be empty
	 * @return string HTML content
	 * @since 0.3
	 */
	public static function sc_docmgr_csv_table( $_attrs, $content = null ) {
		/**
		 * Large tables cause performance issues on backend, provide an empty result
		 */
		if ( is_admin() ) {
			return '';
		}

		/**
		 * Setup default shortcode attributes
		 */
		$default_attrs = array(
			'id'			 => null,
			'date'			 => 1, // Display modified date in caption by default
			'row-colors'	 => null, // Use default row colors
			'row-number'	 => 1, // Display row numbers
			'page-length'	 => 10, // Default # rows to display per page
			'rows'			 => NULL   // List of rows to display
		);

		/**
		 * Get shortcode parameters and sanitize
		 */
		$attrs = shortcode_atts( $default_attrs, $_attrs );

		$doc_id			 = intval( $attrs['id'] );
		$caption_date	 = filter_var( $attrs['date'], FILTER_VALIDATE_BOOLEAN );
		$number_rows	 = filter_var( $attrs['row-number'], FILTER_VALIDATE_BOOLEAN );
		$row_colors		 = self::sanitize_row_colors( $attrs['row-colors'] );
		$include_rows	 = self::parse_numbers( $attrs['rows'] );

		$_page_length	 = intval( $attrs['page-length'] );
		$page_length	 = $_page_length > 0 ? $_page_length : 10;

		/**
		 * Retrieve the post
		 */
		$document = Document::get_instance( $doc_id );
		if ( !( $document && $document->is_csv() ) ) {
			return "";
		}

		/**
		 * Start of content area
		 *
		 *     *******************************************************************
		 *     ******** IF table format changes, bump CSV_STORAGE_FORMAT *********
		 *     *******************************************************************
		 */
		$result	 = '<div class="aad-doc-manager">';
		$result	 .= self::render_row_color_css( $row_colors );
		$result	 .= '<table id="aad-doc-manager-csv-' . self::$index++ . '" class="aad-doc-manager-csv responsive no-wrap" width="100%" data-page-length="' . $page_length . '">';

		/**
		 * Include caption if needed
		 */
		if ( $caption_date ) {
			if ( $document->post_modified_gmt == $document->post_date_gmt ) {
				$text = __( 'Created', TEXT_DOMAIN );
			} else {
				$text = __( 'Updated', TEXT_DOMAIN );
			}
			$result	 .= '<caption>';
			$result	 .= "$text: " . \get_the_modified_date( '', $doc_id );
			$result	 .= '</caption>';
		}

		$result	 .= self::render_header( $document, $number_rows );
		$result	 .= self::render_rows( $document, $number_rows, $row_colors, $include_rows );

		$result .= '</table></div>';

		return $result;
	}

	/**
	 * Deprecated version of sc_docmgr_csv_table()
	 *
	 * @global type $post
	 * @param type $_attrs
	 * @param type $content
	 * @return string HTML result of shortcode
	 * @deprecated since version 0.3
	 */
	public static function sc_csvview( $_attrs, $content = NULL ) {
		global $post;

		$message = sprintf( __( 'Deprecated shortcode [sc_csvview] used in post %d', TEXT_DOMAIN ), $post->ID );
		_deprecated_hook( __CLASS__ . '\sc_csvview', '0.3', __CLASS__ . '\sc_docmgr_csv_table', $message );

		return self::sc_docmgr_csv_table( $_attrs, $content );
	}

	/**
	 * Render CSS required to apply colors to rows of table
	 *
	 * The colors will stay with the row as the table is sorted.
	 *
	 * @param array $row_colors Array of sanitized row colors
	 * @return string HTML
	 */
	private static function render_row_color_css( $row_colors ) {
		if ( empty( $row_colors ) ) {
			return '';
		}

		$result = '<style>';
		foreach ( $row_colors as $index => $row_color ) {
			$result .= '#aad-doc-manager-csv-' . self::$index . ' tr.color-' . $index . ' { background-color: ' . $row_color . '; }';
		}
		$result .= '</style>';
		return $result;
	}

	/**
	 * Render the table header
	 *
	 * @param \PumaStudios\DocManager\Document $document
	 * @param boolean $number_rows True if rows should include number
	 * @return HTML for table header row
	 */
	private static function render_header( Document $document, $number_rows ) {
		$result = '<tr>';

		if ( $number_rows ) {
			$result .= '<th>#</th>';
		}

		$headers = $document->get_csv_header();
		foreach ( $headers as $header ) {
			$result .= '<th>' . esc_html( trim( $header ) ) . '</th>';
		}
		$result .= '</tr>';
		return $result;
	}

	/**
	 * Render the table body
	 *
	 * @param \PumaStudios\DocManager\Document $document
	 * @param boolean $number_rows True if rows should include number
	 * @param array $row_colors Array of row colors to apply in repeating sequence
	 * @param array $include_rows rows to include in display
	 * @return string HTML for table body
	 */
	private static function render_rows( Document $document, $number_rows, $row_colors, $include_rows ) {
		$headers = $document->get_csv_header();

		$result	 = '';
		$row_num = 0;
		foreach ( $document->get_csv_records() as $row ) {

			if ( empty( $row_colors ) ) {
				$class = '';
			} else {
				$class = ' class="color-' . $row_num % count($row_colors) . '"';
			}

			$result .= '<tr' . $class . '>';
			/**
			 * Include table row # if requested
			 */
			if ( $number_rows ) {
				$result .= '<td>' . ($row_num + 1) . '</td>';
			}

			foreach ( $headers as $index ) {
				$result .= '<td>' . self::nl2list( $row[$index] ) . '</td>';
			}
			$result .= '</tr>';
			$row_num++;
		}

		return $result;
	}

	/**
	 * Convert block of text with embedded new lines into a list
	 *
	 * @param string $text with embedded new-line characters
	 * @return string HTML
	 */
	private static function nl2list( $text ) {
		if ( !$text ) {
			return '';
		}
		/**
		 * Split the input on new-line and turn into a list format if more than one line present
		 */
		$list = explode( "\n", $text );
		if ( count( $list ) > 1 ) {
			/**
			 * Build list of multi-line item
			 */
			return '<ul class="aad-doc-manager-csv-cell-list">' .
				implode( array_map( function( $li ) {
						return '<li>' . esc_html( trim( $li ) );
					}, $list ) ) . '</ul>';
		} else {
			return esc_attr( trim( $text ) );
		}
	}

	/**
	 * Parse a string containing sets of numbers such as:
	 * 3
	 * 1,3,5
	 * 2-4
	 * 1-5,7,15-17
	 * spaces are ignored
	 *
	 * routine returns a sorted array containing all the numbers
	 * duplicates are removed - e.g. '5,4-7' returns 4,5,6,7
	 *
	 * @param string $input
	 * @return array Numbers as specified by provided input
	 */
	private static function parse_numbers( $input ) {
		if ( NULL == $input ) {
			return [];
		}

		$input	 = str_replace( ' ', '', $input ); // strip out spaces
		$output	 = array();
		foreach ( explode( ',', $input ) as $nums ) {
			if ( strpos( $nums, '-' ) !== false ) {
				list($from, $to) = explode( '-', $nums );
				$output = array_merge( $output, range( (int) $from, (int) $to ) );
			} else {
				$output[] = (int) $nums;
			}
		}

		$output = array_unique( $output, SORT_NUMERIC ); // remove duplicates
		sort( $output );

		return $output;
	}

	/**
	 * Sanitize text string of comma separated row colors and convert to array
	 *
	 * @param string $colors comma separated string of row colors
	 * @return array of strings, row colors
	 */
	private static function sanitize_row_colors( $colors ) {
		if ( !isset( $colors ) )
			return null;

		$row_colors = array_map( array( self::class, 'sanitize_row_color' ), explode( ',', $colors ) );
		return $row_colors;
	}

	/**
	 * Sanitize a given row color text string
	 *
	 * @param string $color cell color
	 * @return string
	 */
	private static function sanitize_row_color( $color ) {
		$color = trim( $color );

		/**
		 * 3 or 6 digit hex color?
		 */
		if ( preg_match( '/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color, $matches ) ) {
			return '#' . $matches[1];
		}

		/**
		 * Maybe a text color name - return it.
		 */
		return sanitize_key( $color );
	}

}
