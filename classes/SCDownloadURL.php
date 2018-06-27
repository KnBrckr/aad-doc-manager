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
 * Description of SCDownloadURL
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class SCDownloadURL {

	/**
	 * Plug into WP
	 *
	 * @since 1.0
	 */
	public static function run() {
		add_shortcode( 'docmgr-download-url', array( self::class, 'sc_docmgr_download_url' ) );
	}

	/**
	 * 'sc_docmgr-download-url' WP shortcode
	 *
	 * Usage: [docmgr-download-url id=<doc_id]text[/docmgr-download-url]
	 *
	 * @param array $_attrs associative array of shortcode parameters
	 * @param string $content
	 * @return string HTML content
	 *
	 * @since 1.0
	 */
	public static function sc_docmgr_download_url( $_attrs, $content = null ) {
		$default_attrs = array( 'id' => null );

		$attrs	 = shortcode_atts( $default_attrs, $_attrs ); // Get shortcode parameters
		$doc_id	 = intval( $attrs['id'] );

		if ( !$doc_id ) {
			return $content;
		} // No id value received - nothing to do

		/**
		 * Retrieve the post
		 */
		$document = Document::get_instance( $doc_id );
		if ( !$document ) {
			return $content;
		}

		$url = DocumentDownload::get_download_url( $document );
		if ( '' != $url ) {
			$text = '<a href="' . esc_url( $url ) . '">' . $content . '</a>';
		} else {
			$text = $content;
		}

		return $text;
	}

}
