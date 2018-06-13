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
 * Initialize Plugin using singleton class
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 */
class Plugin {

	/**
	 * @var string Real Path to plugin directory
	 */
	private static $plugin_realpath;

	/**
	 * @var array Array of URLs to asset directories
	 */
	private static $urls;

	/**
	 * Instantiate
	 *
	 * @param string $plugin base plugin file
	 */
	public function __construct( string $plugin ) {
		self::$plugin_realpath = realpath( plugin_dir_path( $plugin ) ) . DIRECTORY_SEPARATOR;

		$plugin_dir_url	 = plugin_dir_url( $plugin );
		self::$urls		 = [
			'plugin'	 => $plugin_dir_url,
			'js'		 => $plugin_dir_url . 'assets/js/',
			'css'		 => $plugin_dir_url . 'assets/css/',
			'fonts'		 => $plugin_dir_url . 'assets/fonts/',
			'images'	 => $plugin_dir_url . 'assets/images/',
			'DataTables' => $plugin_dir_url . 'assets/DataTables/' // TODO need better way to locate libraries
		];
	}

	/**
	 * Cloning is not allowed
	 */
	private function __clone() {

	}

	/**
	 * Get path to the plugin
	 *
	 * @param string $append Pathname to append to the realpath
	 * @return string
	 */
	public function get_plugin_path( $append = '' ) {
		return self::$plugin_realpath . $append;
	}

	/**
	 * Get url to asset directory
	 *
	 * @param string $asset asset type
	 * @param string $file name of file in asset directory
	 * @return string|null
	 */
	public static function get_asset_url( $asset, $file = '' ) {
		if ( !array_key_exists( $asset, self::$urls ) ) {
			return null;
		}

		return self::$urls[$asset] . $file;
	}

	/**
	 * Add default services to container
	 *
	 * @param \PumaStudios\Container $container
	 */
	public function register_services( $container ) {
		if ( !self::version_check() ) {
			return;
		}

		/**
		 * Save reference to Plugin object in container
		 */
		$container->set( 'plugin', $this );

		/**
		 * Setup i18n using default languages subdirectory
		 */
		load_plugin_textdomain( TEXT_DOMAIN );

		/**
		 * Hook up other services here
		 */
		Document::run();
		if ( is_admin() ) {
			DocumentAdmin::run();
		}
		DocumentDownload::run();

		/**
		 * Start Shortcodes
		 */
		SCCSVTable::run();
		SCCreated::run();
		SCModified::run();
		SCDownloadURL::run();

//		/**
//		 * Instantiate the main plugin class
//		 */
//		if ( is_admin() ) {
//			$aad_doc_manager = new aadDocManagerAdmin();
//		} else {
//			$aad_doc_manager = new aadDocManager();
//		}
//		$aad_doc_manager->setup();
//
//
//		register_activation_hook( __FILE__, array( 'aadDocManager', 'plugin_activation') );
//		register_deactivation_hook( __FILE__, array( 'aadDocManager', 'plugin_deactivation' ) );
	}

	/**
	 * Confirm environment is OK to run plugin before starting
	 *
	 * @global string $wp_version WordPress version
	 * @return bool True if versions check out OK
	 */
	private function version_check() {
		global $wp_version;

		$version_ok = true;

		/**
		 * Enforce minimum PHP version requirements
		 */
		if ( version_compare( MIN_PHP_VERSION, phpversion(), '>' ) ) {
			self::admin_error( sprintf(
					__( '%s plugin requires minimum PHP v%s, you are runing v%s', TEXT_DOMAIN ), __NAMESPACE__, MIN_PHP_VERSION, phpversion() )
			);
			$version_ok = false;
		}

		/**
		 * Enforce minimum WP version requirements
		 */
		if ( version_compare( MIN_WP_VERSION, $wp_version, '>' ) ) {
			self::admin_error( sprintf(
					__( '%s plugin requires minimum Wordpress v%s, you are runing v%s', TEXT_DOMAIN ), __NAMESPACE__, MIN_WP_VERSION, $wp_version )
			);
			$version_ok = false;
		}

		/**
		 * Enforce minimum WooCommerce version requirements
		 */
		if ( defined( 'MIN_WOO_VERSION' ) && function_exists( 'WC' ) && version_compare( MIN_WOO_VERSION, WC()->version, '>' ) ) {
			self::admin_error( sprintf(
					__( '%s plugin requires minimum WooCommerce v%s, you are runing v%s', TEXT_DOMAIN ), __NAMESPACE__, MIN_WOO_VERSION, WC()->version )
			);
			$version_ok = false;
		}

		return $version_ok;
	}


	/**
	 * Display error message in admin screen
	 *
	 * @param type $notice string for display in admin screen
	 * @since 1.0
	 */
	public static function admin_error( $notice ) {
		self::admin_notice( 'error', $notice);
	}

	/**
	 * Add admin_notice action to report a message with given status
	 *
	 * @access private
	 * @param string $class class name for admin notice
	 * @param string $notice string to display
	 */
	private static function admin_notice( string $class, string $notice ) {
		add_action( 'admin_notices', function() use ($class, $notice) {
			printf( '<div class=%s><p>%s</p></div>', esc_attr( $class), esc_html( $notice ) );
		} );
	}

}
