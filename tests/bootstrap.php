<?php
/**
 * Autoload classes
 */
include_once('AutoLoader.php');
AutoLoader::registerDirectory('.');
AutoLoader::registerDirectory('admin');

/**
 * Need ABSPATH defined for plugin code to load properly
 */
define( 'ABSPATH', '/some/path');

/**
 * No-debug
 */
define( 'WP_DEBUG', false);

$_vendor_dir = getenv( 'COMPOSER_VENDOR_DIR' );
if ( ! $_vendor_dir ) {
	$_vendor_dir = realpath(dirname( __FILE__ ) . '/../../../../../vendor');
}

require_once $_vendor_dir . '/autoload.php';

