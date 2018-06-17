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
	 * @var string GUID Taxonomy name
	 */
	const TERM_GUID = 'aad-doc-uuid';

	/**
	 *  @var array Accepted document types
	 */
	protected static $accepted_mime_types = [ 'text/csv', 'application/pdf' ];
	// if ( !in_array( $doc_type, $this->accepted_doc_types ) )

	/**
	 * @var \WP_post base WP_Post
	 */
	private $post = NULL;

	/**
	 *
	 * @var WP_Attachment WP Attachment object
	 */
	private $attachment = NULL;

	/**
	 * Constructor
	 *
	 * @param WP_post $post Wordpress Post to treat as a document
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * get object properties
	 *
	 * @param string $name object value requested
	 * @return various
	 * @since 1.0 'ID', 'post_modified', 'post_modified_gmt', 'post_date_gmt', 'post_mime_type'
	 */
	public function __get( $name ) {
		$post_properties = [ 'ID', 'post_modified', 'post_modified_gmt', 'post_date_gmt', 'post_mime_type' ];
		if ( in_array( $name, $post_properties ) ) {
			$result = $this->post->$name;
		} else {
			$result = null;
		}

		return $result;
	}

	/**
	 * Get mime types supported by class
	 *
	 * @return array of strings
	 */
	public static function get_supported_mime_types() {
		return self::$accepted_mime_types;
	}

	/**
	 * Plug into WP
	 *
	 * @since 1.0
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
	 * @param int|WP_Post|NULL $_post get a new Document instance for the given post id
	 * @param string $status requested status to retrieve
	 * @return Document|NULL
	 * @since 1.0
	 */
	public static function get_instance( $_post, $status = 'publish' ) {
		/* @var $post \WP_Post */
		$post = get_post( $_post );

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
	 * Retrieve WP_Post for this document
	 *
	 * @return WP_Post
	 * @deprecated since version 1.0
	 */
	public function get_post() {
		_doing_it_wrong( __FUNCTION__, 'Should use methods to get access to specific post content', '1.0' );
		return $this->post;
	}

	/**
	 * Find document based on provided GUID
	 *
	 * @param string $guid GUID for requested document
	 * @param string $status request post status
	 * @return WP_post of the requested document
	 * @since 1.0
	 */
	public static function get_document_by_guid( $guid, $status = 'publish' ) {
		if ( !$guid || !self::is_guidv4( $guid ) ) {
			return NULL;
		}

		$args	 = array(
			'post_type'	 => self::POST_TYPE,
			'tax_query'	 => array(
				array(
					'taxonomy'	 => self::TERM_GUID,
					'field'		 => 'name',
					'terms'		 => $guid
				)
			)
		);
		$query	 = new \WP_Query( $args );

		/**
		 * document type should match custom post type
		 */
		$post = $query->post;
		if ( !$post || self::POST_TYPE != get_post_type( $post ) ) {
			return NULL;
		}

		if ( '' != $status && get_post_status( $post ) != $status ) {
			return NULL;
		}

		return new Document( $post );
	}

	/**
	 * Get the attachment for a downloadable document
	 *
	 * @return WP_Attachment Object
	 */
	private function get_attachment() {
		if ( $this->attachment ) {
			return $this->attachment;
		}

		/**
		 * Must have a valid attachment for the document
		 */
		$attachment_id	 = get_post_meta( $this->ID, 'document_media_id', true );
		$attachment		 = get_post( $attachment_id );
		if ( !$attachment || 'attachment' != get_post_type( $attachment ) ) {
			return null;
		}

		$this->attachment = $attachment;
		return $attachment;
	}

	/**
	 * Get the real path to document attachment
	 *
	 * @return string path to downloadable file
	 * @since 1.0
	 */
	public function get_attachment_realpath() {
		$attachment = $this->get_attachment();
		if ( !$attachment ) {
			return NULL;
		}

		$filename = realpath( get_attached_file( $attachment->ID ) ); // Path to attached file

		/**
		 * 	Only allow a path that is in the uploads directory.
		 */
		$upload_dir		 = wp_upload_dir();
		$upload_dir_base = realpath( $upload_dir['basedir'] );
		if ( strncmp( $filename, $upload_dir_base, strlen( $upload_dir_base ) ) !== 0 ) {
			return NULL;
		}

		return $filename;
	}

	/**
	 * Generate a random V4 GUID
	 *
	 * @access private
	 * @return string GUID
	 */
	private static function generate_guid() {
		return self::guidv4( openssl_random_pseudo_bytes( 16 ) );
	}

	/**
	 * Turn 128 bit blob into a UUD string
	 *
	 * @param blob $data 16 bytes binary data
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
	 * @return boolean true if string is valid GUID v4 format
	 */
	private static function is_guidv4( $guid ) {
		$UUIDv4 = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
		return preg_match( $UUIDv4, $guid );
	}

	/*
	 * Register the Document post type with WP
	 *
	 * @since 1.0
	 */

	public static function register_post_type() {
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

		register_post_type( self::POST_TYPE, [
			'labels'		 => $post_type_labels,
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
	 * Register UUID Taxonomy for documents
	 *
	 * @since 1.0
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
			'rewrite'			 => false
//			'rewrite'			 => [
//				'slug'			 => self::DOWNLOAD_SLUG,
//				'with_front'	 => true,
//				'hierarchical'	 => false,
//				'ep_mask'		 => EP_ROOT
//			]
		] );

		register_taxonomy_for_object_type( self::TERM_GUID, self::POST_TYPE );
	}

	/**
	 * Is given mime type supported as a document?
	 *
	 * @param string $mime_type
	 * @return boolean true if mime type is supported
	 */
	public static function is_mime_type_supported( string $mime_type ) {
		if ( in_array( $mime_type, self::$accepted_mime_types ) ) {
			return true;
		}

		return false;
	}

}
