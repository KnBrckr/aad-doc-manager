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
 * Shortcode to display modified date of a Document
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class SCModified {

	/**
	 * Instantiate
	 */
	public function __construct() {

	}

	/**
	 * Plug into WP
	 */
	public static function run() {
		add_shortcode( 'docmgr-modified', array( self::class, 'sc_docmgr_modified' ) );
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
	public static function sc_docmgr_modified( $_attrs, $content = null ) {
		$default_attrs = array( 'id' => null );

		$attrs	 = shortcode_atts( $default_attrs, $_attrs ); // Get shortcode parameters
		$doc_id	 = intval( $attrs['id'] );

		if ( !$doc_id ) {
			return "";
		} // No id value received - nothing to do

		/**
		 * Retrieve the Document
		 */
		$document = Document::get_document( $doc_id );
		if ( !$document ) {
			return "";
		}

		return get_the_modified_date( '', $doc_id );
	}

}
