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

	/**
	 * Create document attachment
	 *
	 * @param string $file
	 * @param int $parent_post_id
	 *
	 * @return int|WP_Error
	 */
	function make_attachment(string $file, int $parent_post_id) {
		$contents = file_get_contents($file);
		$upload = wp_upload_bits(basename($file), null, $contents);
		$type = '';

		if ( !empty( $upload['type'])) {
			$type = $upload['type'];
		} else {
			$mime = wp_check_filetype($upload['file']);
			if ($mime) {
				$type = $mime['type'];
			}
		}

		$attachment = array(
			'post_title' => basename( $upload['file'] ),
			'post_content' => '',
			'post_type' => 'attachment',
			'post_parent' => $parent_post_id,
			'post_mime_type' => $type,
			'guid' => $upload[ 'url' ],
		);

		// Save the data
		$id = wp_insert_attachment( $attachment, $upload[ 'file' ], $parent_post_id );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );

		return $id;
	}

	function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'post_status'    => 'publish',
			'post_title'     => new WP_UnitTest_Generator_Sequence( 'Document title %s' ),
			'post_content'   => '',
			'post_excerpt'   => '',
			'post_type'      => Document::POST_TYPE,
			'post_mime_type' => 'application/pdf'
		);

	}

	function create_object( $args ) {

		$file = $args['target_file'];
		unset( $args['target_file'] );

		$type = 'unknown';
		$mime = wp_check_filetype($file);
		if ($mime) {
			$type = $mime['type'];
		}

		$args['post_mime_type'] = $type;

		$post_id = $this->factory->post->create( $args );

		$attachment_id = $this->make_attachment($file, $post_id);

		update_post_meta( $post_id, 'document_media_id', $attachment_id );

		return $post_id;
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

		$query     = new \WP_Query( [ 'post_type' => Document::POST_TYPE ] );
		$documents = $query->get_posts();

		foreach ( $documents as $document ) {
			$attachments = get_attached_media( '', $document->ID );
			foreach ( $attachments as $attachment ) {
				wp_delete_attachment( $attachment->ID );
			}
		}
	}

}
