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
		if ( !$requested_doc )
			return;

		/**
		 * Get Document to be downloaded
		 */
		$document = Document::get_document_by_guid( $requested_doc );
		if ( !$document ) {
			$this->error_404();
			// Not Reached
		}

		$file = $document->get_attachment_realpath();

		if ( NULL != $file && file_exists( $file ) ) {
			/**
			 * Log download of the file
			 */
			self::log_download( $document->ID );

			/**
			 * Output headers and dump the file
			 */
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: ' . esc_attr( $document->post_mime_type ) );
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
	 * @return void
	 */
	private static function log_download( $doc_id ) {
		/**
		 * Atomic Increment download count
		 */
		do {
			$value	 = $old	 = get_post_meta( $doc_id, 'download_count', true );
			$value++;
		} while ( !update_post_meta( $doc_id, 'download_count', $value, $old ) );
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

}
