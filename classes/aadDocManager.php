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
             * Setup needed actions
             */
			add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_styles' ) );

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

	} // End class aad_doc_manager

} // End if
