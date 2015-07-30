<?php
/*
Plugin Name: Document Manager
Plugin URI:  http://action-a-day.com/
Description: Custom post type to manage documents for display and download
Version:     0.1
Author:      Kenneth J. Brucker
Author URI:  http://action-a-day.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: aad-doc-manager

    Copyright 2015 Kenneth J. Brucker  (email : ken.brucker@action-a-day.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*

 General Strategy:
 - Search across all fields
 - Provide method to replace document content
 - Provide shortcode that will retrieve the table content for search/display
 - For narrow display use list format for data
 - Use is_uploaded_file() to ensure file was uploaded

*/

/**
 * Protect from direct execution
 */
if (!defined('ABSPATH')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

global $aad_doc_manager;

/**
 * Load the required libraries
 */
$required_libs = array(
	'class_aad_doc_manager.php'
); 
if (is_admin()) {
	// For admin pages, setup the extended admin class
	$required_libs[] = 'admin/class_aad_doc_manager_admin.php';
}
foreach ($required_libs as $lib) {
	if (!include_once($lib)) {
		die('Unable to load required library:  "' . $lib . '"');
	}
}

/**
 * Instantiate the main plugin class
 */
if (is_admin()) {
	$aad_doc_manager = new aad_doc_manager_admin();	
} else {
	$aad_doc_manager = new aad_doc_manager();	
}
