<?php
/*
 * Class to display documents in a list format
 *
 * @package Document Manager
 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
 * @copyright 2016 Kenneth J. Brucker (email: ken.brucker@action-a-day.com)
 *
 * This file is part of Document Manager, a plugin for Wordpress.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Protect from direct execution
 */
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( "aadDocManagerTable" ) ) {
	class aadDocManagerTable extends WP_List_Table {
		/**
		 * Post Type for DB query
		 *
		 * @var string
		 */
		protected $post_type;
		
		/**
		 * Menu page URL for upload page - used to generate URL to update existing documents
		 *
		 * @var string
		 */
		protected $upload_url;
		
		/**
		 * Base URL for displaying table page - used to generate links to table page
		 *
		 * @var string
		 */
		protected $table_url;
		
		/**
		 * Labels describing object]
		 *
		 * @var array
		 */
		protected $labels;
		
		/**
		 * True if active page is for Trash posts
		 *
		 * @var boolean
		 */
		private $is_trash;
		
		/**
		 * Constructor, we override the parent to pass our own arguments
		 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
		 */
		function __construct( $args ) {
			parent::__construct( $args );
			 
			$this->post_type = $args['post_type'];
			$this->upload_url = $args['upload_url'];
			$this->table_url = $args['table_url'];
			$this->labels = $args['labels'];
		}
		
		/**
		 * Create links to be used in the views output of the table. 
		 * Used to display number of posts in various states
		 *
		 * @return associative array (id => link)
		 */
		protected function get_views()
		{
			$states = array( 'publish', 'trash' );	// States that will be used in available views
			$views = array();						// Associative array of views to return
			
			/**
			 * Get the number of posts in each state and calculate total number
			 */
			$num_posts = wp_count_posts( $this->post_type, 'readable' );
			
			$total_posts = 0;
			foreach ( $states as $state ) {
				$total_posts += $num_posts->$state;
			}
			
			/**
			 * Views by page status
			 */
			foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
				$class = '';

				$status_name = $status->name;

				if ( ! in_array( $status_name, $states ) )
					continue;

				if ( empty( $num_posts->$status_name ) )
					continue;

				/**
				 * If post status is not set it's the same as displaying the "Published" view
				 */
				if ( ( isset( $_REQUEST['post_status'] ) && $status_name == $_REQUEST['post_status'] ) ||
					( ! isset( $_REQUEST['post_status'] ) && "publish" == $status_name ) )
					$class = ' class="current"';

				/**
				 * Setup view link
				 */
				$url = add_query_arg( 'post_status', $status_name, $this->table_url );
				$views[$status_name] = "<a href='$url'$class>" . sprintf( translate_nooped_plural( $status->label_count, $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
			}
			
			return $views;
		}
		
		/**
		 * HTML for controls placed between bulk actions and pagination elements
		 *
		 * @param $which String Is nav for "top" or "bottom" of the table?
		 * @return void
		 */
	 	function extra_tablenav( $which ) {
	 	   ?>
	 		<div class="alignleft actions">
				<?php
	 			if ( $this->is_trash && current_user_can( 'delete_posts' ) ) {
		 			submit_button( __( 'Empty Trash' ), 'apply', 'delete_all', false );
	 			}
	 		   ?>
	 		</div>
	 	   <?php
	 	}
		 
	 	/**
	 	 * Define columns that are used in the table
	 	 *
	 	 * Array in form 'column_name' => 'column_title'
	 	 *
	 	 * The values's provided for bulk actions are defined in $this->column_cb()
	 	 *
	 	 * @return array $columns, array of columns
	 	 **/
	 	function get_columns()
	 	{
	 		$columns = array(
	            'cb'            => '<input type="checkbox" />', //Render a checkbox instead of text
				'doc_id'        => __( 'ID' ),
				'title'         => __( 'Title' ),
				'shortcode'     => __( 'Table Shortcode', 'aad-doc-manager' ),
				'date_modified' => __( 'Date Modified', 'aad-doc-manager' ),
				'type'          => __( 'Document Type', 'aad-doc-manager' ),
				'rows'          => __( 'Rows', 'aad-doc-manager' ),
				'columns'       => __( 'Columns', 'aad-doc-manager' )
	 		);
			
			/**
			 * Add internal fields for debug
			 */
			if ( WP_DEBUG ) {
				$columns = array_merge( $columns, array( 'csv_storage_format' => 'CSV Storage Fmt') );
			}
		
	 		return $columns;
	 	}

		/**
		 * Define columns that are sortable
		 *
		 * Array in form 'column_name' => 'database_field_name'
		 *
		 * @return array $sortable, array of columns that can be sorted
		 **/
		function get_sortable_columns()
		{
			$sortable = array(
				'doc_id'        => array( 'ID', false ),
				'title'         => array( 'title', false ),
				'date_modified' => array( 'date', false ),
				'type'          => array( 'type', false )
			);
		
			return $sortable;
		}
	
		/**
		 * Define bulk actions that will work on table
		 *
		 * The actions are dealt with where this class is instantiated.  $this->current_action defines the action requested
		 *
		 * @return array Associative array of bulk actions in form 'slug' => 'visible title'
		 **/
		protected function get_bulk_actions()
		{
			$actions = array();
			
			if ( $this->is_trash ) {
				$actions['untrash'] = __( 'Restore' ); 
			}
			
			if ( current_user_can( 'delete_posts' ) ) {
				if ( $this->is_trash || ! EMPTY_TRASH_DAYS ) {
					$actions['delete'] = __( 'Delete Permanently' );
				} else {
					$actions['trash'] = __( 'Move to Trash' );
				}
			}

			return $actions;
		}
		
		/**
		 * Overload current_action to detect additional buttons applied to table
		 */
		public function current_action() {
			if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) )
				return 'delete_all';

			return parent::current_action();
		}
				
		/**
		 * Prepare table items for display
		 **/
		function prepare_items()
		{
			$num_posts = wp_count_posts( $this->post_type, 'readable' );
			/**
			 * By default, want published posts
			 */
			$post_status = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'publish';
			$this->is_trash = ( 'trash' === $post_status );
			
			/**
			 * Sort order
			 */
		    $orderby = ! empty( $_REQUEST["orderby"] ) ? $_REQUEST["orderby"] : 'title';
		    $order = ! empty( $_REQUEST["order"] ) ? $_REQUEST["order"] : 'asc';
		
			/**
			 * Search string, if specified
			 */
			$search = ! empty( $_REQUEST["s"] ) ? $_REQUEST["s"] : '';

			/**
			 * Pagination of table elements
			 */
	        //How many to display per page?
	        $perpage = $this->get_items_per_page( 'cvs_files_per_page', 20 ); // RFE Allow admin screen to set per page
	        //Which page is this?
			$paged = $this->get_pagenum();
			
			/**
			 * Get the table items
			 */
			$query = new WP_Query( array(
				'post_type'      => $this->post_type,
				'post_status'    => $post_status,
				'posts_per_page' => $perpage,
				'offset'         => ( $paged - 1 ) * $perpage,
				'order_by'       => $orderby,
				'order'          => $order
			));
			
			if ($query) {
				$total_items = $query->found_posts;
				$total_pages = $query->max_num_pages;
				$this->items = $query->posts;
			} else {
				$total_items = 0;
				$total_pages = 0;
				$this->items = array();
			}
			
			/**
			 * Setup pagination links
			 */
			$this->set_pagination_args( array(
				"total_items" => $total_items,
				"total_pages" => $total_pages,
				"per_page"    => $perpage,
			) );
		}
		
		/**
		 * Method to provide checkbox column in table
		 *
		 * Provides the REQUEST variable that will contain the selected values
		 *
	     * @see WP_List_Table::::single_row_columns()
		 * @param $post A post object for display
		 * @return string Text or HTML to be placed in table cell
		 **/
		function column_cb( $post )
		{
	        return sprintf(
	            '<input type="checkbox" name="doc_ids[]" value="%s" />',
	            esc_attr( $post->ID )	                // The value of the checkbox should be the record's id
	        );
		}
		
		/**
		 * Provide formatted CVS spreadsheet title
		 *
		 * Includes set of actions that can be used on the item
		 *
		 * @param $post A post object for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_title( $post )
		{
			$actions = array();
			
			/**
			 * Actions for published documents
			 */
			if ( ! $this->is_trash ) {
				$url = add_query_arg( 'doc_id', $post->ID, $this->upload_url );
				$actions['update'] = "<a title='" . $this->labels['edit_item'] . "' href='$url'>" . 
					$this->labels['edit_item'] . "</a>";
			}
			
			/**
			 * Display trash, restore, permanently delete options based on context
			 */
			if ( current_user_can( 'delete_post', $post->ID ) ) {
				if ( 'trash' == $post->post_status ) {
					/**
					 * Trash view -- Generate "Restore" link
					 */
					$url = $this->get_trash_action_post_link( $post->ID, 'untrash' );
					$actions['untrash'] = 
						'<a  class="submitdelete" title="' . esc_attr__( 'Restore this item from the Trash' ) . '" href="' . esc_url( $url ) . '">' . 
							__( 'Restore' ) . '</a>';
				} elseif ( EMPTY_TRASH_DAYS ) {
					/**
					 * Not on Trash view -- If EMPTY_TRASH_DAYS > 0 then generate a "Trash" link for the document
					 */
					$url = $this->get_trash_action_post_link( $post->ID, 'trash' );
					$actions['trash'] = 
						'<a class="submitdelete" title="' . esc_attr__( 'Move this item to the Trash' ) . '" href="' . esc_url( $url ) . '">' 
							. __( 'Trash' ) . '</a>';
				}
				
				/**
				 * Add Delete Permanently Option for Trash view or if Trash is not being used (EMPTY_TRASH_DAYS == 0)
				 */
				if ( 'trash' == $post->post_status || !EMPTY_TRASH_DAYS ) {
					$url = $this->get_trash_action_post_link( $post->ID, 'delete' );
					$actions['delete'] = 
						'<a class="submitdelete" title="' . esc_attr__( 'Delete this item permanently' ) . '" href="' . esc_url( $url ) . '">' 
							. __( 'Delete Permanently' ) . '</a>';
				}
			}
			
			return esc_attr( $post->post_title ) . $this->row_actions( $actions );
		}
		
		/**
		 * Create URL to perform trash/un-trash action on a post
		 *
		 * @param $post_id, int - Post ID
		 * @param $action, string - name of action
		 * @return string, URL
		 */
		private function get_trash_action_post_link( $post_id, $action )
		{
			$url = add_query_arg( 'action', $action, admin_url('post.php?post=' . strval( $post_id ) ) );
			return wp_nonce_url( $url, $action . '-post_' . $post_id );
		}
        
		/**
		 * Provide formatted shortcode used to display the table
		 *
		 * @param $post, A post object  for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_shortcode( $post )
		{
			return "[docmgr-csv-table id=" . esc_attr( $post->ID ) ."]";
		}
		
		/**
		 * Provide formatted Post Date
		 *
		 * @param $post A post object for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_date( $post )
		{
			return esc_attr( $this->format_date( $post->post_date ) );
		}
		
		/**
		 * Provide formatted Post Modified Date
		 *
		 * @param $post, A post object for display
		 * @return string, Text or HTML to be displayed in a table cell
		 */
		function column_date_modified( $post )
		{
			return esc_attr( $this->format_date( $post->post_modified ) );
		}
		
		/**
		 * Provide formatted CVS spreadsheet ID
		 *
		 * @param $post A post object for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_doc_id( $post )
		{
			return esc_attr( $post->ID );
		}
		
		/**
		 * Provide formatted document type
		 *
		 * @param post, A post object for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_type( $post )
		{
			return esc_attr( $post->post_mime_type );
		}
		
		/**
		 * Provide formatted number of rows for CSV files
		 *
		 * @param post, A post object for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_rows( $post )
		{
			if ( 'text/csv' == $post->post_mime_type ) {
				return esc_attr( get_post_meta( $post->ID, 'csv_rows', true ) );
			} else {
				return "";
			}
		}
		
		/**
		 * Provide formatted number of columns for CSV files
		 *
		 * @param post, A post object for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_columns( $post )
		{
			if ( 'text/csv' == $post->post_mime_type ) {
				return esc_attr( get_post_meta( $post->ID, 'csv_cols', true ) );
			} else {
				return "";
			}
		}
		
		/**
		 * Provide formatted CSV Storage format -- For WP_DEBUG mode
		 *
		 * @param post, A post object for display
		 * @return string, Text or HTML to be placed in table cell
		 */
		function column_csv_storage_format( $post )
		{
			if ( 'text/csv' == $post->post_mime_type ) {
				return esc_attr( get_post_meta( $post->ID, 'csv_storage_format', true ) );
			} else {
				return "";
			}
			
		}
		
		/**
		 * Format a date/time using WP General Settings format
		 *
		 * @param $date, string - Date+Time YYYY-MM-DD HH:MM:SS
		 * @return string, formatted date + time
		 */
		private function format_date( $date )
		{
			static $format;
			
			if ( ! isset( $format ) ) {
				$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			}
			
			return esc_attr( mysql2date( $format, $date ) );
		}
	} // End class aad_doc_manager_Table
} // End if