<?php
/**
 * Tests based on https://github.com/10up/wp_mock
 */
class aadShortcodeTests extends PHPUnit_Framework_TestCase {
    public function setUp() {
        \WP_Mock::setUp();
		
		\WP_Mock::wpPassthruFunction( '__' );
    }

    public function tearDown() {
        \WP_Mock::tearDown();
    }
	
	function test_sc_docmgr_csv_table() {
		$docmgr = new aadDocManager;

		$default_attrs = array(
			'id'         => null,
			'date'       => 1,		// Display modified date in caption by default
			'row-colors' => null,	// Use default row colors
			'row-number' => 1		// Display row numbers	
		);
		
		$post = $this->create_post();
				
		$attrs_return = array(
			'id'         => 0,
			'date'       => 1,		// Display modified date in caption by default
			'row-colors' => null,	// Use default row colors
			'row-number' => 1		// Display row numbers	
		);
		
		/**
		 * Null input returns empty string
		 */
		$attrs = array();
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $default_attrs
		) );
		
		$this->assertEquals("", $docmgr->sc_docmgr_csv_table(array()), "Null input returns empty string");

		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs = array('id' => "Some weird stuff");
		$attrs_return['id'] = 0;
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $attrs_return
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_csv_table($attrs), "Text input returns empty string");
		
		// Post-id that does not exist
		$attrs = array('id' => "8");
		$attrs_return['id'] = 8;
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $attrs_return
		) );

		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => 8,
			 'times' => 1,
			 'return' => null
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_csv_table($attrs), "Post does not exist");

		/**
		 * Wrong post type
		 */
		$attrs = array('id' => $post->ID);
		$attrs_return['id'] = $post->ID;
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $attrs_return
		) );

		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
				
		$this->assertEquals("", $docmgr->sc_docmgr_csv_table($attrs), "Wrong post type");

		/**
		 * Wrong post status
		 */
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $attrs_return
		) );

		// Valid post type
		$post->post_type = aadDocManager::post_type;
		
		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
				
		$this->assertEquals("", $docmgr->sc_docmgr_csv_table($attrs), "Wrong post status");
		
	}
	
	/**
	 * Good post
	 */
	function test_sc_docmgr_csv_table_good() {
		$docmgr = new aadDocManager;

		$default_attrs = array(
			'id'         => null,
			'date'       => 1,		// Display modified date in caption by default
			'row-colors' => null,	// Use default row colors
			'row-number'    => 1		// Display row numbers	
		);
		
		$post = $this->create_post();

		// Valid post status and type
		$post->post_status = 'publish';
		$post->post_type = aadDocManager::post_type;
						
		$attrs = array('id' => $post->ID);
		$attrs_return = array(
			'id'         => $post->ID,
			'date'       => 1,		// Display modified date in caption by default
			'row-colors' => null,	// Use default row colors
			'row-number' => 1		// Display row numbers	
		);
		
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $attrs_return
		) );

		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
		
		\WP_Mock::wpPassthruFunction( 'esc_attr', array( 'times' => 2 ) );
		
		\WP_Mock::wpFunction( 'get_option', array(
			 'args' => 'date_format',
			 'times' => '1-',
			 'return' => 'Y m d',
		) );
		
		\WP_Mock::wpFunction( 'get_option', array(
			 'args' => 'time_format',
			 'times' => '1-',
			 'return' => 'h:m:s',
		) );
		
		\WP_Mock::wpFunction( 'mysql2date', array(
			 'args' => array('Y m d h:m:s', $post->post_modified),
			 'times' => 1,
			 'return' => $post->post_modified,
		) );
		
		\WP_Mock::wpFunction( 'get_post_meta', array(
			 'args' => array($post->ID, 'csv_storage_format', true),
			 'times' => 1,
			 'return' => 2,
		) );
		
		$this->assertStringMatchesFormat('<div class="aad-doc-manager"><table class="aad-doc-manager-csv searchHighlight responsive no-wrap" width="100%"><caption>%a</caption>%a</table></div>', $docmgr->sc_docmgr_csv_table($attrs), "Valid document post type");		
	}
	
	/**
	 * Test [docmgr_created]
	 */
	function test_sc_docmgr_created() {
		$docmgr = new aadDocManager;

		$default_attrs = array(
			'id' => null
		);
		
		$post = $this->create_post();

		/**
		 * Null input
		 */
		$attrs = array();
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $default_attrs
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_created(array()), "Null input returns empty string");
		
		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs = array('id' => "Some weird stuff");
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => "0", 'disp_date' => 1)
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_created($attrs), "Text input returns empty string");
		
		// Post-id that does not exist
		$attrs = array('id' => "8");
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => "8", 'disp_date' => 1)
		) );

		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => 8,
			 'times' => 1,
			 'return' => null
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_created($attrs), "Post does not exist");

		/**
		 * Wrong post type
		 */
		$attrs = array('id' => $post->ID);
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => $post->ID, 'disp_date' => 1)
		) );

		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
				
		$this->assertEquals("", $docmgr->sc_docmgr_created($attrs), "Wrong post type");

		/**
		 * Wrong post status
		 */
		$attrs = array('id' => $post->ID);
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => $post->ID, 'disp_date' => 1)
		) );

		// Valid post type
		$post->post_type = aadDocManager::post_type;
		
		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
				
		$this->assertEquals("", $docmgr->sc_docmgr_created($attrs), "Wrong post status");

		/**
		 * Good post
		 */
		$attrs = array('id' => $post->ID);
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => $post->ID, 'disp_date' => 1)
		) );

		// Valid post status
		$post->post_status = 'publish';
		
		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
		
		\WP_Mock::wpPassthruFunction( 'esc_attr', array( 'times' => 2 ) );
		
		\WP_Mock::wpFunction( 'get_option', array(
			 'args' => 'date_format',
			 'times' => '1-',
			 'return' => 'Y m d',
		) );
		
		\WP_Mock::wpFunction( 'get_option', array(
			 'args' => 'time_format',
			 'times' => '1-',
			 'return' => 'h:m:s',
		) );
				
		\WP_Mock::wpFunction( 'mysql2date', array(
			 'args' => array('Y m d h:m:s', $post->post_date),
			 'times' => 1,
			 'return' => $post->post_date,
		) );
		
		$this->assertEquals($post->post_date, $docmgr->sc_docmgr_created($attrs), "Valid post");
	}
	
	/**
	 * Test [docmgr_modified]
	 */
	function test_sc_docmgr_modified() {
		$docmgr = new aadDocManager;

		$default_attrs = array(
			'id' => null
		);
		
		$post = $this->create_post();

		/**
		 * Null input
		 */
		$attrs = array();
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => $default_attrs
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_modified(array()), "Null input returns empty string");
		
		/**
		 * non-numeric Post type returns empty string
		 */
		$attrs = array('id' => "Some weird stuff");
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => "0", 'disp_date' => 1)
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_modified($attrs), "Text input returns empty string");
		
		// Post-id that does not exist
		$attrs = array('id' => "8");
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => "8", 'disp_date' => 1)
		) );

		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => 8,
			 'times' => 1,
			 'return' => null
		) );
		$this->assertEquals("", $docmgr->sc_docmgr_modified($attrs), "Post does not exist");

		/**
		 * Wrong post type
		 */
		$attrs = array('id' => $post->ID);
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => $post->ID, 'disp_date' => 1)
		) );

		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
				
		$this->assertEquals("", $docmgr->sc_docmgr_modified($attrs), "Wrong post type");

		/**
		 * Wrong post status
		 */
		$attrs = array('id' => $post->ID);
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => $post->ID, 'disp_date' => 1)
		) );

		// Valid post type
		$post->post_type = aadDocManager::post_type;
		
		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
				
		$this->assertEquals("", $docmgr->sc_docmgr_modified($attrs), "Wrong post status");

		/**
		 * Good post
		 */
		$attrs = array('id' => $post->ID);
		\WP_Mock::wpFunction( 'shortcode_atts', array(
			 'args' => array($default_attrs, $attrs),
			 'times' => 1,
			 'return' => array('id' => $post->ID, 'disp_date' => 1)
		) );

		// Valid post status
		$post->post_status = 'publish';
		
		\WP_Mock::wpFunction( 'get_post', array(
			 'args' => $post->ID,
			 'times' => 1,
			 'return' => $post
		) );
		
		\WP_Mock::wpPassthruFunction( 'esc_attr', array( 'times' => 2 ) );
		
		// get_options not expected to be called - static format should be used
		
		\WP_Mock::wpFunction( 'mysql2date', array(
			 'args' => array('Y m d h:m:s', $post->post_modified),
			 'times' => 1,
			 'return' => $post->post_modified,
		) );
		
		$this->assertEquals($post->post_modified, $docmgr->sc_docmgr_modified($attrs), "Valid post");
	}

	private function create_post() {
		$post = new \stdClass;
		$post->ID = 8;
		$post->post_type = 'page';
		$post->post_status = 'draft';
		$post->post_date_gmt = '2016-01-01 10:10:10';
		$post->post_modified_gmt = '2016-01-01 10:10:10';
		$post->post_date = '2016-01-01 12:12:12';
		$post->post_modified = '2016-01-01 10:10:10';
		$post->post_content = 'Post Content';

		return $post;
	}
}
