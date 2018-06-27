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

	/**
	 * Instantiate
	 */
	public function __construct() {

	}

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

		// FIXME sanitize array of attrs

		$doc_id				 = intval( $attrs['id'] );
		$caption_date		 = intval( $attrs['date'] );
		$attrs['row-number'] = intval( $attrs['row-number'] );
		$attrs['row-colors'] = self::sanitize_row_colors( $attrs['row-colors'] );
		$page_length		 = intval( $attrs['page-length'] );
		$attrs['rows']		 = self::parse_numbers( $attrs['rows'] );

		/**
		 * Must re-render if any options change default rendering
		 */
		$render_defaults = $attrs['row-colors'] == NULL && $attrs['row-number'] == 1 && $attrs['rows'] == NULL;

		/**
		 * Retrieve the post
		 */
		$document = Document::get_instance( $doc_id );
		if ( NULL == $document || 'text/csv' != $document->post_mime_type ) {
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
		$result	 .= '<table class="aad-doc-manager-csv responsive no-wrap" width="100%" data-page-length="' . $page_length . '">';

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

		/**
		 * CSV Storage format 1:
		 *   post_content has pre-rendered HTML, post_meta[csv_table] is table in array form
		 * Original format (Undefined value for csv_storage_format):
		 *   post_content is serialized array of table content
		 */
		$storage_format = \get_post_meta( $doc_id, 'csv_storage_format', true );
		switch ( $storage_format ) {
			case 1:
			case 2:
				/**
				 * Some formatting changes have been made in HTML - Re-render
				 *
				 * Save result only if default options have been selected
				 */
				$result .= self::re_render_csv( $doc_id, $attrs, $render_defaults );
				break;

			case self::CSV_STORAGE_FORMAT:
				/**
				 * If non-default options or DEBUG mode specific, must re-render table
				 */
				if ( !$render_defaults || WP_DEBUG ) {
					$result .= self::re_render_csv( $doc_id, $attrs, false ); // re-render, do not save result in DB
				} else {
					$result .= \get_post_meta( $doc_id, 'csv_rendered', true ); // Use pre-rendered content for default display
				}
				break;

			default:
				/**
				 * Unknown storage format
				 */
				if ( WP_DEBUG ) {
					$result	 .= '<tr><td>';
					$result	 .= sprintf( __( 'Unknown CSV Storage format "%s" found for post_id %d', TEXT_DOMAIN ), esc_attr( $storage_format ), esc_attr( $doc_id ) );
					$result	 .= '<td></tr>';
				}
		}
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
	 * Re-render previously saved HTML and update DB
	 *
	 * @param string $doc_id post ID for target document
	 * @param array $attrs shortcode attributes
	 * @param boolean $save true=>Save re-render to DB
	 * @return string document rendered as HTML
	 */
	private static function re_render_csv( $doc_id, $attrs, $save ) {
		$render_data = array(
			'col-headers'	 => \get_post_meta( $doc_id, 'csv_col_headers', true ),
			'columns'		 => \get_post_meta( $doc_id, 'csv_cols', true ),
			'table'			 => \get_post_meta( $doc_id, 'csv_table', true ),
			'row-number'	 => $attrs['row-number'],
			'row-colors'	 => $attrs['row-colors'],
			'rows'			 => $attrs['rows']
		);

		$html = self::render_csv( $render_data );

		/**
		 * Save the newly rendered data if requested
		 *
		 * There will be obsolete HTML content still stored as $post->post_content.
		 * Unavoidable as it results in incorrectly modifying the post update date
		 */
		if ( $save ) {
			\update_post_meta( $doc_id, 'csv_rendered', $html );
			\update_post_meta( $doc_id, 'csv_storage_format', self::CSV_STORAGE_FORMAT );
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
	private static function render_csv( $render_data ) {
		$number_rows	 = array_key_exists( 'row-number', $render_data ) ? $render_data['row-number'] : 1;
		$row_colors		 = array_key_exists( 'row-colors', $render_data ) ? $render_data['row-colors'] : null;
		$include_rows	 = array_key_exists( 'rows', $render_data ) ? $render_data['rows'] : [];
		$column_cnt		 = $render_data['columns'];


		/**
		 * Build table header and body
		 *
		 *     *******************************************************************
		 *     ******** IF table format changes, bump CSV_STORAGE_FORMAT *********
		 *     *******************************************************************
		 */
		$result = '<thead><tr>';

		/**
		 * Include row number in output?
		 */
		if ( $number_rows ) {
			$result .= '<th>#</th>';
		}

		$result .= implode( array_map( function ( $col_data ) {
				return "<th>" . sanitize_text_field( $col_data ) . "</th>";
			}, $render_data['col-headers'] ) );
		$result	 .= '</tr></thead>';
		$result	 .= '<tbody>';

		/**
		 * Smaller loop if only certain rows requested
		 */
		if ( count( $include_rows ) > 0 ) {
			foreach ( $include_rows as $index ) {
				/**
				 * include_rows is 1 based
				 */
				$result .= self::render_csv_row( $index - 1, $render_data['table'][$index - 1], $column_cnt, $row_colors, $number_rows );
			}
		} else {
			foreach ( $render_data['table'] as $index => $row ) {
				$result .= self::render_csv_row( $index, $row, $column_cnt, $row_colors, $number_rows );
			}
		}
		$result .= '</tbody></table>';

		return $result;
	}

	/**
	 * Render a row of CSV data
	 *
	 * @param int $index Index number of the row
	 * @param array $row CSV data
	 * @param int $column_cnt Number of columns in table data
	 * @param type $row_colors Colors to apply to rows
	 * @param type $number_rows should row index be included in the output?
	 * @return string HTML for row of csv data
	 */
	private static function render_csv_row( $index, $row, $column_cnt, $row_colors, $number_rows ) {
		if ( $row_colors ) {
			$cell_style = 'style="background-color: ' . $row_colors[$index % count( $row_colors )] . '"';
		} else {
			$cell_style = '';
		}
		$result = '<tr>';

		if ( $number_rows ) {
			$result .= '<td>' . intval( $index + 1 ) . '</td>';
		} // Include row number?

		$row = array_pad( $row, $column_cnt, '' ); // Pad the row to the overall column count
		foreach ( $row as $col_data ) {
			$result .= '<td ' . $cell_style . '>' . self::nl2list( $col_data ) . '</td>';
		}
		$result .= '</tr>';

		return $result;
	}

	/**
	 * Convert block of text with embedded new lines into a list
	 *
	 * @param string $text with embedded new-line characters
	 * @return string HTML
	 */
	private static function nl2list( $text ) {
		/**
		 * Split the input on new-line and turn into a list format if more than one line present
		 */
		$list = explode( "\n", $text );
		if ( count( $list ) > 1 ) {
			/**
			 * Build list of multi-line item
			 */
			return '<ul class="aad-doc-manager-csv-list">' .
				implode( array_map( function( $li ) {
						return '<li>' . esc_attr( $li );
					}, $list ) ) . '</ul>';
		} else
			return esc_attr( $text );
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
