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
 * Description of Document
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class Document {

	/**
	 * @var string Custom Post type for Recipes
	 */
	const POST_TYPE = "aad-doc-manager";

	/**
	 * @var \WP_post base WP_Post
	 */
	private $post;

	/**
	 * @var string GUID Taxonomy name
	 */
	const TERM_GUID = 'aad-doc-uuid';

	/**
	 * @var string Download slug
	 */
	const DOWNLOAD_SLUG = 'aad-document';

	/**
	 * Constructor
	 *
	 * @param WP_post $post Wordpress Post to treat as a document
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Plug into WP
	 */
	public static function run() {
		add_action( 'init', function () {
			Document::register_post_type();
			Document::register_taxonomy();
		} );
	}

	/**
	 * Retrieve Document instance
	 *
	 * @param int|NULL $id get a new Document instance for the given post id
	 * @param string $status requested status to retrieve
	 * @return Document|NULL
	 */
	public static function get_document( $id, $status = 'publish' ) {
		/* @var $post \WP_Post */
		$post = \get_post( $id );

		if ( !$post || get_post_type( $post ) != self::POST_TYPE ) {
			return NULL;
		}

		/**
		 * Only return post if of requested status
		 */
		if ( '' != $status && get_post_status( $post ) != $status ) {
			return NULL;
		}

		return new Document( $post );
	}

	/**
	 * Get status for document
	 *
	 * @return string
	 */
	public function get_post_status() {
		return $this->post->post_status;
	}

	/**
	 * Get modified date in gmt for document
	 *
	 * @return string
	 */
	public function get_modified_gmt() {
		return $this->post->post_modified_gmt;
	}

	/**
	 * Get modified date for document
	 *
	 * @return string
	 */
	public function get_modified() {
		return $this->post->post_modified;
	}

	/**
	 * Get create date in GMT for document
	 * @return string
	 */
	public function get_date_gmt() {
		return $this->post->post_date_gmt;
	}

	/**
	 * Retrieve WP_Post for this document
	 *
	 * @return WP_Post
	 */
	public function get_post() {
		_doing_it_wrong( __FUNCTION__, 'Should use methods to get access to specific post content', '0.9' );
		return $this->post;
	}

	/**
	 * Find document based on provided UUID
	 *
	 * @param string $guid UUID for requested document
	 * @return WP_post of the requested document
	 */
	protected static function get_document_by_guid( $guid ) {
		if ( !$guid || !self::is_guidv4( $guid ) ) {
			return NULL;
		}

		$args	 = array(
			'post_type'	 => self::post_type,
			'tax_query'	 => array(
				array(
					'taxonomy'	 => self::term_guid,
					'field'		 => 'name',
					'terms'		 => $guid
				)
			)
		);
		$query	 = new WP_Query( $args );

		/**
		 * document type should match custom post type
		 */
		$post = $query->post;
		if ( !$post || self::POST_TYPE != $post->post_type ) {
			return NULL;
		}

		return new Document( $post );
	}

	/**
	 * Get the attachment for a downloadable document
	 *
	 * @param string $doc_id Document ID
	 * @return WP_ Attachment Object
	 */
	private static function get_attachment_by_docid( $doc_id ) {
		/**
		 * Must have a valid attachment for the document
		 */
		$attachment_id	 = get_post_meta( $doc_id, 'document_media_id', true );
		$attachment		 = get_post( $attachment_id );
		if ( !$attachment || 'attachment' != $attachment->post_type ) {
			return null;
		}

		return $attachment;
	}

	/**
	 * Generate a random V4 GUID
	 *
	 * @access private
	 * @return string GUID
	 */
	private function generate_guid() {
		return self::guidv4( openssl_random_pseudo_bytes( 16 ) );
	}

	/**
	 * Turn 128 bit blob into a UUD string
	 *
	 * @param blob $data 16 bytes binary data
	 * @return string
	 */
	private function guidv4( $data ) {
		assert( strlen( $data ) == 16 );

		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Is $uuid a valid GUID V4 string?
	 *
	 * @param string $guid GUID to test
	 * @return boolean true if string is valid GUID v4 format
	 */
	private function is_guidv4( $guid ) {
		$UUIDv4 = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
		return preg_match( $UUIDv4, $guid );
	}

	/*
	 * Register the Document post type with WP
	 */

	public static function register_post_type() {
		register_post_type( self::POST_TYPE, [
			'labels'		 => self::get_post_type_labels(),
			'description'	 => __( 'Upload Documents for display in posts/pages', TEXT_DOMAIN ),
			'public'		 => false, // Implies exclude_from_search: true, publicly_queryable: false, show_in_nav_menus: false, show_ui: false
			'menu_icon'		 => 'dashicons-media-spreadsheet',
			'hierarchical'	 => false,
			'supports'		 => array( 'title' ),
			'has_archive'	 => false,
			'rewrite'		 => false,
			'query_var'		 => false,
			'taxonomies'	 => [ self::TERM_GUID ]
		] );
	}

	/**
	 * Provide labels for managing Document post type
	 *
	 * @return array WP Post label names
	 */
	public static function get_post_type_labels() {
		$post_type_labels = [
			'name'				 => _x( 'Documents', 'post type general name', TEXT_DOMAIN ),
			'singular_name'		 => __( 'Document', TEXT_DOMAIN ),
			'add_new'			 => __( 'Upload New Document', TEXT_DOMAIN ),
			'add_new_item'		 => __( 'Upload New Document', TEXT_DOMAIN ),
			'edit_item'			 => __( 'Update Document', TEXT_DOMAIN ),
			'new_item'			 => __( 'Upload New Document', TEXT_DOMAIN ),
			'view_item'			 => __( 'View Document', TEXT_DOMAIN ),
			'search_items'		 => __( 'Search Documents', TEXT_DOMAIN ),
			'not_found'			 => __( 'No Documents found', TEXT_DOMAIN ),
			'not_found_in_trash' => __( 'No Documents found in Trash', TEXT_DOMAIN ),
			'all_items'			 => __( 'All Documents', TEXT_DOMAIN ),
			'archives'			 => __( 'Documents', TEXT_DOMAIN ),
			'attributes'		 => __( 'Document Attributes', TEXT_DOMAIN ),
			'menu_name'			 => __( 'Documents', TEXT_DOMAIN ),
			'name_admin_bar'	 => __( 'Document', TEXT_DOMAIN ),
		];

		return $post_type_labels;
	}

	/**
	 * Register UUID Taxonomy for documents
	 */
	public static function register_taxonomy() {
		$term_uuid_labels = array(
			'name'						 => __( 'Document UUIDs', TEXT_DOMAIN ),
			'singular_name'				 => __( 'Document UUID', TEXT_DOMAIN ),
			'menu_name'					 => __( 'Document UUIDs', TEXT_DOMAIN ),
			'all_items'					 => __( 'All Document UUIDs', TEXT_DOMAIN ),
			'edit_item'					 => __( 'Edit Document UUID', TEXT_DOMAIN ),
			'view_item'					 => __( 'View Document UUID', TEXT_DOMAIN ),
			'update_item'				 => __( 'Update Document UUID', TEXT_DOMAIN ),
			'add_new_item'				 => __( 'Add new Document UUID', TEXT_DOMAIN ),
			'new_item_name'				 => __( 'New Document UUID', TEXT_DOMAIN ),
			'search_items'				 => __( 'Search Document UUIDs', TEXT_DOMAIN ),
			'separate_items_with_commas' => __( 'Separate UUIDs with commands', TEXT_DOMAIN ),
			'add_or_remove_items'		 => __( 'Add or Remove UUIDs', TEXT_DOMAIN ),
			'not_found'					 => __( 'No Document UUIDs found', TEXT_DOMAIN )
		);

		register_taxonomy( self::TERM_GUID, self::POST_TYPE, [
			'labels'			 => $term_uuid_labels,
			'public'			 => true,
			'show_ui'			 => false,
			'show_in_nav_menus'	 => false,
			'show_tag_cloud'	 => false,
			'show_in_quick_edit' => false,
			'show_admin_column'	 => false,
			'show_in_menu'		 => false,
			'show_in_ui'		 => false,
			'description'		 => __( 'Document UUID to internal ID mapping', TEXT_DOMAIN ),
			'query_var'			 => 'aad-document',
			'rewrite'			 => [
				'slug'			 => self::DOWNLOAD_SLUG,
				'with_front'	 => true,
				'hierarchical'	 => false,
				'ep_mask'		 => EP_ROOT
			]
		] );

		register_taxonomy_for_object_type( self::TERM_GUID, self::POST_TYPE );
	}

	/**
	 * Get escaped download URL for a given document id
	 *
	 * @param string $doc_id Document ID
	 * @return string escaped URL or '' if unable to create URL
	 */
	public static function get_download_url_e( $doc_id ) {
		$terms = wp_get_object_terms( $doc_id, self::TERM_GUID, array( 'fields' => 'names' ) );
		if ( count( $terms ) > 0 ) {
			return esc_url( '/' . self::DOWNLOAD_SLUG . '/' . $terms[0] );
		} else {
			return '';
		}
	}

}
