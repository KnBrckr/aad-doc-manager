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

use PumaStudios\DocManager\Document;

/**
 * Factory to create PDF documents
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class WP_UnitTest_Factory_For_Document extends WP_UnitTest_Factory_For_Thing {

	function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'post_status'	 => 'publish',
			'post_title'	 => new WP_UnitTest_Generator_Sequence( 'Document title %s' ),
			'post_content'	 => '',
			'post_excerpt'	 => '',
			'post_type'		 => Document::POST_TYPE,
			'post_mime_type' => 'application/pdf'
		);
	}

	function create_object( $args ) {

		$file = $args['target_file'];

		$tmpfile = tempnam( sys_get_temp_dir(), 'phpunit-uploadtest' );
		copy( $file, $tmpfile );

		$_FILES = [
			'document' => [
				'error'		 => UPLOAD_ERR_OK,
				'name'		 => basename( $file ),
				'type'		 => mime_content_type( $file ),
				'size'		 => filesize( $file ),
				'tmp_name'	 => $tmpfile
			]
		];

		$document = Document::create_document( $args, 'document' );

		@unlink( $tmpfile );

		if ( $document ) {
			return $document->ID;
		} else {
			return NULL;
		}
	}

	function update_object( $post_id, $fields ) {
		$fields['ID'] = $post_id;
		return wp_update_post( $fields );
	}

	function get_object_by_id( $post_id ) {
		return get_post( $post_id );
	}

	/**
	 * Remove any attachments that were created
	 */
	function destroy() {

		$query = new \WP_Query( [ 'post_type' => Document::POST_TYPE ] );
		$documents = $query->get_posts();

		foreach ( $documents as $document ) {
			$attachments = get_attached_media( '', $document->ID );
			foreach ( $attachments as $attachment ) {
				wp_delete_attachment( $attachment->ID );
			}
		}
	}

}
