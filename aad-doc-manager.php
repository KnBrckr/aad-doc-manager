<?php
/**
 * @package   PumaStudios-DocManager
 * @author    Kenneth J. Brucker <ken@pumastudios.com>
 * @license   GPL-2.0+
 * @link      http://pumastudios.com/document-manager-wordpress-plugin/
 * @copyright 2018 Kenneth J. Brucker
 *
 * @wordpress-plugin
 * Plugin Name: Document Manager
 * Plugin URI:  http://pumastudios.com/document-manager-wordpress-plugin/
 * Description: Custom post type to manage documents for display and download
 * Version:     0.9
 * Author:      Kenneth J. Brucker
 * Author URI:  https://pumastudios.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: pumastudios-doc-manager-locale
 * GitHub Plugin URI: https://github.com/KnBrckr/<repo>
 *
 * Copyright 2018 Kenneth J. Brucker  (email : ken@pumastudios.com)
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
 * Protect from direct execution
 */
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'You are doing it wrong.' );
}

/**
 * Include composer provided components, e.g. Pimple (https://pimple.sensiolabs.org)
 */
include_once __DIR__ . '/vendor/autoload.php';

/**
 * @var const Version of this plugin
 */
const PLUGIN_VERSION = "0.9";

/**
 * @var const Minimum Wordpress version supported
 */
const MIN_WP_VERSION = "4.6";

/**
 * @var const Minimum PHP version supported
 */
const MIN_PHP_VERSION = "7.0";

/**
 * @var const Text domain for i18n
 */
const TEXT_DOMAIN = "aad-doc-manager-locale"; //FIXME

/**
 * Connect plugin to WordPress
 */
add_action( 'plugins_loaded', function() {
	( new Plugin( __FILE__ ) )->register_services( plugin_container() );
} );

/**
 * Provide container instance for plugin
 *
 * @staticvar \PumaStudios\Container $container Plugin container
 * @return \PumaStudios\Container
 */
function plugin_container() {
	static $container = NULL;

	if ( !$container ) {
		$container = new \PumaStudios\Container;
	}

	return $container;
}

