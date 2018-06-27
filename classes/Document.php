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
 * Container class for a WP_Post
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

	/**
	 * @var \WP_post underlying WP_Post
	 */
	private $post = NULL;

	/**
	 * @var WP_Attachment The attached document
	 */
	private $attachment = NULL;

	/**
	 * @var int Document (WP_Post) ID
	 */
	public $ID;

	/**
	 * @var string The document mime type
	 */
	private $post_mime_type;

	/**
	 * Constructor
	 *
	 * @param WP_post $post Wordpress Post to treat as a document
	 * @since 1.0
	 */
	public function __construct( \WP_Post $post ) {
		$this->post				 = $post;
		$this->ID				 = $post->ID;
		$this->post_mime_type	 = get_post_mime_type( $post->ID );
	}

	/**
	 * get object properties
	 *
	 * @param string $name object value requested
	 * @return various
	 * @since 1.0 'ID', 'post_modified', 'post_modified_gmt', 'post_date_gmt', 'post_mime_type', 'post_title'
	 */
	public function __get( $name ) {
		$post_properties = [ 'post_modified', 'post_modified_gmt', 'post_date_gmt', 'post_mime_type', 'post_title' ];
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
	 * @since 1.0
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
		add_action( 'init', [ Document::class, 'register_post_type' ], 10 );
		add_action( 'init', [ Document::class, 'register_taxonomy' ], 11 ); // Depends on post_type being registered
	}

	/**
	 * Retrieve Document instance for an existing Document
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
	 * Create a new document in the DB
	 *
	 * @param array $_postarr Post creation parameters
	 * @param string $file_id Index of the `$_FILES` array that the file was sent.
	 * @return Document|WP_Error Document object on success
	 * @since 1.0
	 */
	public static function create_document( $_postarr, $file_id ) {
		$default_postarr = [
			'post_excerpt'	 => '',
			'post_type'		 => self::POST_TYPE,
			'post_status'	 => 'publish',
			'comment_status' => 'closed',
			'ping_status'	 => 'closed',
		];

		/**
		 * Apply defaults to input
		 */
		$postarr = array_merge( $default_postarr, $_postarr );

		/**
		 * Only continue if a valid upload file is available
		 */
		$file = self::filter_file( $file_id );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$postarr['post_title'] = $file['name'];

		/**
		 * Set mime type based on incoming file contents
		 */
		$mime_type = self::validate_mime_type( $file['name'], $file['tmp_name'] );
		if ( is_wp_error( $mime_type ) ) {
			return $mime_type;
		}
		$postarr['post_mime_type'] = $mime_type;

		/**
		 * Create new post entry for the document
		 */
		$doc_id = wp_insert_post( $postarr );
		if ( !$doc_id ) {
			return new \WP_Error( 'WP_ERROR', __( 'Internal Wordpress error; unable to insert post data.', TEXT_DOMAIN ) );
		}

		$attachment_id = self::handle_upload( $doc_id, $file_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		/**
		 * Generate a GUID for this document
		 */
		$guid = Guid::generate_guid();
		wp_set_object_terms( $doc_id, $guid, self::TERM_GUID );

		return self::get_instance( $doc_id );
	}

	/**
	 * filter $_FILES file_id for processing
	 *
	 * @param string $file_id ID to examine within $_FILES input
	 * @return array|\WP_Error
	 */
	private static function filter_file( $file_id ) {
		/**
		 * @var array Upload error strings
		 */
		$upload_error = [
			UPLOAD_ERR_NO_FILE		 => __( "No file received.", TEXT_DOMAIN ),
			UPLOAD_ERR_INI_SIZE		 => __( "Requested file is too large.", TEXT_DOMAIN ),
			UPLOAD_ERR_FORM_SIZE	 => __( "Requested file is too large.", TEXT_DOMAIN ),
			UPLOAD_ERR_PARTIAL		 => __( "Partial upload received; please retry.", TEXT_DOMAIN ),
			UPLOAD_ERR_NO_TMP_DIR	 => __( "Server error: No temporary directory available", TEXT_DOMAIN ),
			UPLOAD_ERR_CANT_WRITE	 => __( "Server error: Can’t write to temporary directory", TEXT_DOMAIN ),
			UPLOAD_ERR_EXTENSION	 => __( "PHP Plugin blocked upload; server logs may have details.", TEXT_DOMAIN )
		];

		/**
		 * @var array Filter settings to use for $file input
		 */
		$args = [
			'error'		 => FILTER_VALIDATE_INT,
			'name'		 => FILTER_SANITIZE_STRING,
			'type'		 => FILTER_SANITIZE_STRING,
			'size'		 => FILTER_VALIDATE_INT,
			'tmp_name'	 => FILTER_SANITIZE_STRING
		];

		if ( !is_array( $_FILES ) || !array_key_exists( $file_id, $_FILES ) ) {
			return new \WP_Error( 'NO_FILES', __( 'Internal Error: No uploaded file information found in request.', TEXT_DOMAIN ) );
		}

		$filtered_file = filter_var_array( $_FILES[$file_id], $args );

		$error_code = $filtered_file['error'];

		if ( UPLOAD_ERR_OK != $error_code ) {
			if ( array_key_exists( $error_code, $upload_error ) ) {
				$error = new \WP_Error( 'UPLOAD_ERR', $upload_error[$error_code] );
			} else {
				$error = new \WP_Error( 'UPLOAD_ERR', sprintf( __( "Unknown file upload error: %d", TEXT_DOMAIN ), $error_code ) );
			}

			return $error;
		}

		/**
		 * Confirm there's a valid uploaded file
		 */
		if ( !is_uploaded_file( $filtered_file['tmp_name'] ) ) {
			return new \WP_Error( 'UPLOAD_ERR', sprintf( __( 'Upload file "%s" is missing.', TEXT_DOMAIN ), $filtered_file['tmp_name'] ) );
		}

		return $filtered_file;
	}

	private static function validate_mime_type( string $name, string $path ) {
		$mime_type = mime_content_type( $path );

		/**
		 * For plain files, examine file extension to identify CSV files
		 */
		if ( 'text/plain' == $mime_type ) {
			$ext = pathinfo( $name, PATHINFO_EXTENSION );
			if ( strcasecmp( 'csv', $ext ) == 0 ) {
				$mime_type = 'text/csv';
			}
		}

		if ( !self::is_mime_type_supported( $mime_type ) ) {
			$msg = sprintf( __( 'Document type %s is not supported.', TEXT_DOMAIN ), esc_attr( $mime_type ) );
			return new \WP_Error( 'UNSUPPORTED_MIME', $msg );
		}

		return $mime_type;
	}

	private static function handle_upload( $doc_id, $file_id ) {
		$attachment_id = media_handle_upload( $file_id, $doc_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		/**
		 * Remove previously saved revision of the document
		 */
		$old_media_id = get_post_meta( $doc_id, 'document_media_id', true );
		if ( $old_media_id ) {
			if ( !wp_delete_attachment( $old_media_id, true ) ) {
				return new \WP_Error( 'DELETE_ERROR', sprintf( __( 'Unable to remove old document; ID=%d', TEXT_DOMAIN ), $old_media_id ) );
			}
		}

		update_post_meta( $doc_id, 'document_media_id', $attachment_id );

		return $attachment_id;
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
		if ( !$guid || !Guid::is_guidv4( $guid ) ) {
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
	 * @since 1.0
	 */
	public static function is_mime_type_supported( string $mime_type ) {
		if ( in_array( $mime_type, self::$accepted_mime_types ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Is document a CSV document?
	 *
	 * @return boolean true if document is CSV
	 * @since 1.0
	 */
	public function is_csv() {
		if ( 'text/csv' == $this->post_mime_type ) {
			return true;
		} else {
			return false;
		}
	}

}
