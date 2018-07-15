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
 * Utility class to generate and test Global Unique IDs (GUIDs)
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class Guid {

	/**
	 * Instantiate
	 */
	public function __construct() {

	}

	/**
	 * Generate a random V4 GUID
	 *
	 * @access private
	 * @return string GUID
	 */
	public static function generate_guid() {
		return self::guidv4( openssl_random_pseudo_bytes( 16 ) );
	}

	/**
	 * Turn 128 bit blob into a UUD string
	 *
	 * @param string $data 16 bytes binary data
	 *
	 * @return string
	 */
	private static function guidv4( $data ) {
		assert( strlen( $data ) == 16 );

		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Is $uuid a valid GUID V4 string?
	 *
	 * @param string $guid GUID to test
	 *
	 * @return boolean true if string is valid GUID v4 format
	 */
	public static function is_guidv4( $guid ) {
		$UUIDv4 = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';

		return preg_match( $UUIDv4, $guid ) ? true : false;
	}

}
