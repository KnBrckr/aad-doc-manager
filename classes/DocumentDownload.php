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
 * Description of DocumentDownload
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class DocumentDownload {

	/**
	 * @var string Download slug
	 */
	const DOWNLOAD_SLUG = 'aad-document';

	/**
	 * Instantiate
	 */
	public function __construct() {

	}

	/**
	 * Plug into WP
	 */
	public static function run() {
		/**
		 * Check for usage of endpoint in template_redirect
		 */
		add_action( 'template_redirect', [ self::class, 'action_try_endpoint' ] );

		/**
		 * Hook into WooCommerce if it's installed
		 */
		if ( class_exists( 'WooCommerce' ) ) {
			/**
			 * Add a woocommerce filter to enable the use of Document Manager documents as downloadable content
			 */
			add_filter( 'woocommerce_downloadable_file_exists', [
				self::class,
				'filter_woo_downloadable_file_exists'
			], 10, 2 );

			/**
			 * Fixup document path for woocommerce downloadable products
			 */
			//add_filter( 'woocommerce_file_download_path', array( $this, 'filter_woo_file_download_path' ), 10, 3 );
		}
	}

	/**
	 * Redirect to document download
	 */
	public static function action_try_endpoint() {
		global $wp_query;

		/**
		 * Does query match taxonomy endpoint?
		 */
		$requested_doc = $wp_query->get( self::DOWNLOAD_SLUG );
		if ( ! $requested_doc ) {
			return;
		}

		/**
		 * Get Document to be downloaded
		 */
		$document = Document::get_document_by_guid( $requested_doc );
		if ( ! $document ) {
			self::error_404();
			// Not Reached
		}

		$file = $document->get_attachment_realpath();

		if ( null != $file && file_exists( $file ) ) {
			/**
			 * Log download of the file
			 */
			self::log_download( $document->ID );

			/**
			 * Output headers and dump the file
			 */
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: ' . esc_attr( get_post_mime_type( $document ) ) );
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
			self::error_404();
			// Not Reached
		}
		// Not Reached
	}

	/**
	 * Get escaped download URL for Document instance
	 *
	 * @param Document $document Document on which to operate
	 *
	 * @return string escaped URL or '' if unable to create URL
	 */
	public static function get_download_url( $document ) {
		$terms = get_the_terms( $document->ID, Document::TERM_GUID );

		if ( $terms && count( $terms ) > 0 ) {
			return '/' . self::DOWNLOAD_SLUG . '/' . $terms[0]->slug; // TODO Create a Document method to get GUID
		} else {
			return '';
		}
	}

	/**
	 * Log download of a file
	 *
	 * @param int $doc_id document ID that was downloaded
	 *
	 * @return void
	 */
	private static function log_download( $doc_id ) {
		/**
		 * Atomic Increment download count
		 */
		do {
			$value = $old = get_post_meta( $doc_id, 'download_count', true );
			$value ++;
		} while ( ! update_post_meta( $doc_id, 'download_count', $value, $old ) );
	}

	/**
	 * Display a 404 error and die
	 */
	private static function error_404() {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		//	TODO Deal with situation when theme does not provide a 404 template.
		get_template_part( 404 );
		die();
	}

	/**
	 * Allow WooCommerce to understand that a document provided by Document Manager is downloadable
	 *
	 * Uses filter defined in class-wc-product-download.php:file_exists()
	 *
	 * @param boolean $file_exists Earlier filters may have already decided if file exists
	 * @param string $file_url path to the downloadable file
	 * @return boolean
	 */
	public static function filter_woo_downloadable_file_exists( $file_exists, $file_url ) {
		if ( '/' . self::DOWNLOAD_SLUG !== substr( $file_url, 0, strlen( self::DOWNLOAD_SLUG ) + 1 ) ) {
			return $file_exists;
		}

		/**
		 * link is for the plugin. Does requested GUID match an available document?
		 */
		$guid = substr( $file_url, strlen( self::DOWNLOAD_SLUG ) + 2 );
		if ( is_a( Document::get_document_by_guid( $guid ), 'Document' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Provide woocommerce with real path for managed documents
	 *
	 * TODO Is this function needed?
	 *
	 * @param string $file file path recorded in woocommerce downloadable product
	 * @param \WC_product $product woocommerce product id
	 * @param string $key woocommerce download document key
	 *
	 * @return string path to file
	 */
//	private function filter_woo_file_download_path( $file, $product, $key ) {
//		if ( '/' . self::DOWNLOAD_SLUG === substr( $file, 0, strlen( self::DOWNLOAD_SLUG ) + 1 ) ) {
//			/**
//			 * link is for the plugin. Confirm GUID provided is valid
//			 */
//			$guid = substr( $file, strlen( self::DOWNLOAD_SLUG ) + 2 );
//
//			if ( ! Guid::is_guidv4( $guid ) ) {
//				return $file;
//			}
//
//			$document = Document::get_document_by_guid( $guid );
//			if ( ! is_a( $document, 'WP_post' ) ) {
//				return $file;
//			}
//
//			$attachment = $document->get_attachment_by_docid( $document->ID );
//			$fileurl    = $this->get_url_by_attachment( $attachment );
//
//			return $fileurl ? $fileurl : $file;
//		} else {
//			return $file;
//		}
//	}

	/**
	 * Get url to an attachment
	 *
	 * TODO Is this function needed?
	 *
	 * @param \WP_post $attachment attachment object
	 *
	 * @return string File URL
	 */
//	private function get_url_by_attachment( $attachment ) {
//		$filename = $this->get_realpath_by_attachment( $attachment );
//
//		/* Strip off content directory */
//		$real_content_dir = realpath( WP_CONTENT_DIR );
//		if ( substr( $filename, 0, strlen( $real_content_dir ) ) == $real_content_dir ) {
//			$filename = substr( $filename, strlen( $real_content_dir ) );
//		}
//
//		return WP_CONTENT_URL . $filename;
//	}

}
