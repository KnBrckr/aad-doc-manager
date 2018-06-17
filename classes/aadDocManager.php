<?php
/*
 * Class to display and download documents
 *
 * @package PumaStudios-DocManager
 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
 * @copyright 2017 Kenneth J. Brucker (email: ken.brucker@action-a-day.com)
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

if ( ! class_exists( "aadDocManager" ) ) {
	class aadDocManager
	{

		/**
		 * Do the initial hooking into WordPress
		 *
		 * @param void
		 * @return void
		 */
		function setup()
		{

		}

        /**
         * Plugin Activation actions
         *
         * @param void
         * @return void
         */
        static function plugin_activation() {
            /**
             * Register document post type
             */
            self::register_document_post_type();

            /**
             *  Reset permalinks after post type registration and endpoint creation
             */
            flush_rewrite_rules();
        }

        /**
         * Plugin Deactivation actions
         *
         * @param void
         * @return void
         */
        static function plugin_deactivation() {
            /**
             * Reset permalinks
             */
            flush_rewrite_rules();
        }


	} // End class aad_doc_manager

} // End if
