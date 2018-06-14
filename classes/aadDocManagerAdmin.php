<?php
/*
 * Class to manage admin screens
 *
 * @package Document Manager
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

// RFE Enable editing of headers on saved files

/**
 * Protect from direct execution
 */
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( "aadDocManagerAdmin" ) ) {
	class aadDocManagerAdmin extends aadDocManager
	{


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


		}

		/**
		 * Do the initial hooking into WordPress
		 *
		 * @return void
		 */
		function setup()
		{
			parent::setup();

			/**
			 * Do plugin initialization
			 */
			add_action( 'admin_init', array( $this, 'action_plugin_admin_setup' ) );
		}

		/**
		 * Perform WordPress Setup
		 *
		 * @return void
		 */
		function action_plugin_admin_setup()
		{
			/**
			 * Add section for reporting configuration errors and notices
			 */
			add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

			/**
			 * Catch media deletes via wp-admin/upload.php to remove the associated document
			 */
			add_action( 'delete_attachment', array($this, 'action_delete_document'), 10, 1 );
		}

		/**
		 * Creates "post_content" based on input type
		 *   - CSV Files will be pre-processed into html table, data will also be stored as serialized post meta data
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
		private function get_document_content( $doc_type, $path )
		{
			if ( 'text/csv' != $doc_type ) {
				return array(
					'post_content' => '',
					'post_meta' => array()
				);
			}

			/**
			 * Does CSV file include column headers in the first row?
			 */
			$csv_has_col_headers = isset( $_REQUEST['csv-has-col-headers'] ) && "yes" == $_REQUEST['csv-has-col-headers'];

			/**
			 * Process received CSV file
			 */
			$table = array();
			if ( ( $handle = fopen( $path, 'r' ) ) !== FALSE ) {
				// If first row has headers grab them
				if ( $csv_has_col_headers ) {
					// RFE Allow for alternate field separator characters
					$row = fgetcsv( $handle, 1000, ',', '"' );
					if ( $row === FALSE ) {
						fclose( $handle );
						// FIXME Handle this and other errors using WP Error class
						return array( 'error' => __( 'Unable to parse CSV contents.', 'aad-doc-manager' ) );
					}

					// Replace line breaks with html
					$header_names = $row;
					$max_cols = count( $row );
				} else {
					$max_cols = 0;
				}

				/**
				 * Collect the table rows
				 */
			    while ( ( $row = fgetcsv( $handle, 1000, ",", '"' ) ) !== FALSE ) {
					/**
					 * Count of Columns in this row and track maximum observed
					 */
					$columns = count( $row );
					if ( $columns > $max_cols ) $max_cols = $columns;
					$table[] = $row;
			    }
			    fclose( $handle );

				/**
				 * Generate headers if not provided
				 */
				if ( empty( $header_names ) ) {
					$header_names = array();
					for ( $col=0; $col < $max_cols; $col++ ) {
						$header_names[$col] = sprintf( __( 'Column %d', 'aad-doc-manager' ), $col+1 );
					}
				}
			} else {
				return array( 'error' => __( 'Could not open uploaded file.', 'aad-doc-manager' ) );
			}

			/**
			 * Setup return data
			 * Table will be stored as Post Meta data in serialized and rendered form
			 */
			$render_data = array(
				'col-headers' => $header_names,
				'columns' => $max_cols,
				'table' => $table
			);
			$retarray = array();
			$retarray['post_content'] = "";
			$retarray['post_meta'] = array(
				'csv_storage_format' => self::CSV_STORAGE_FORMAT, // Save version used to store document content
				'csv_cols' => $max_cols,
				'csv_rows' => count( $table ),
				'csv_col_headers' => $header_names,
				'csv_has_col_headers' => $csv_has_col_headers,
				'csv_table' => $table,
				'csv_rendered' => $this->render_csv( $render_data )
			);
			return $retarray;
		}

		/**
		 * When deleting a media attachment via WP interfaces, remove the associated document as well
		 *
		 * @param int $attachment_id, post_id for attachment being removed
		 * @return void
		 */
		function action_delete_document($attachment_id)
		{
			$attachment = get_post( $attachment_id );
			if ( $attachment->post_parent ) {
				/**
				 * Attachment has a parent, is it a document type?
				 */
				$post = get_post( $attachment->post_parent );
				if ( self::post_type == $post->post_type ) {
					/**
					 * Delete the associated document
					 */
					if (! wp_delete_post($post->ID, true) )
						wp_die( __( 'Error in deleting.', 'aad-doc-manager' ) );
				}
			}
		}

	}
}