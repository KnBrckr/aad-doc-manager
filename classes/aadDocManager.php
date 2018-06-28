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
	 * CSV storage format version
	 *
	 * Undefined => post->content is serialized data
	 * 1 => HTML rendered during upload, saved as post_content.
	 *      table array stored in post meta data field csv_table
	 * 2 => Storage same as (1).
	 *      HTML content modified to support highlighting plugin - requires regen of older HTML to support
	 * 3 => Rendered data stored as meta data - post_content is empty
	 *      Allows re-save of pre-rendered data without affecting post update date
	 * 4 => No cache
	 */
	const CSV_STORAGE_FORMAT = 4;



		/**
		 * Do the initial hooking into WordPress
		 *
		 * @param void
		 * @return void
		 */
		function setup()
		{

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
		 * CSV Storage format 1:
		 *   post_content has pre-rendered HTML, post_meta[csv_table] is table in array form
		 * Original format (Undefined value for csv_storage_format):
		 *   post_content is serialized array of table content
		 */
//		$storage_format = \get_post_meta( $doc_id, 'csv_storage_format', true );
//		switch ( $storage_format ) {
//			case 1:
//			case 2:
//			case 3:
//				/**
//				 * Some formatting changes have been made in HTML or stored data format - Re-render
//				 *
//				 * Save result only if default options have been selected
//				 */
//				$result .= self::re_render_csv( $doc_id, $attrs, $render_defaults );
//				break;
//
//			case self::CSV_STORAGE_FORMAT:
//				/**
//				 * If non-default options or DEBUG mode specific, must re-render table
//				 */
//				if ( !$render_defaults || WP_DEBUG ) {
//					$result .= self::re_render_csv( $doc_id, $attrs, false ); // re-render, do not save result in DB
//				} else {
//					$result .= \get_post_meta( $doc_id, 'csv_rendered', true ); // Use pre-rendered content for default display
//				}
//				break;
//
//			default:
//				/**
//				 * Unknown storage format
//				 */
//				if ( WP_DEBUG ) {
//					$result	 .= '<tr><td>';
//					$result	 .= sprintf( __( 'Unknown CSV Storage format "%s" found for post_id %d', TEXT_DOMAIN ), esc_attr( $storage_format ), esc_attr( $doc_id ) );
//					$result	 .= '<td></tr>';
//				}
//		}

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

	} // End class aad_doc_manager

} // End if
