<?php
/*
Plugin Name: SiteOrigin Site Packs Addon
Description: Handle exporting Site Packs
Version: dev
Author: SiteOrigin
Author URI: https://siteorigin.com
License: GPL3
License URI: http://www.gnu.org/licenses/gpl.html
*/

include plugin_dir_path( __FILE__ ) . 'inc/utils.php';

/**
 * The general manager for SiteOrigin Hosting plugin
 *
 * Class SiteOrigin_Hosting
 */
class SiteOrigin_Packs {

	function __construct() {
		spl_autoload_register( array( $this, 'autoload' ) );

		// Load all the extra classes
		SiteOrigin_Packs_Export::single();
	}

	static function single(){
		static $single;
		if( empty( $single ) ) {
			$single = new self();
		}

		return $single;
	}

	function autoload( $class_name ){
		if( strpos( $class_name, 'SiteOrigin_Packs_' ) === 0 ) {
			$file = str_replace( '_', '-', strtolower( str_replace( 'SiteOrigin_Packs_', '', $class_name ) ) ) . '.php';

			if( file_exists( plugin_dir_path( __FILE__ ) . 'inc/' . $file ) ) {
				include plugin_dir_path( __FILE__ ) . 'inc/' . $file;
			}
		}
	}
}

SiteOrigin_Packs::single();