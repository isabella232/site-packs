<?php

/**
 * Export your site as a plugin that you can install to get your entire site up and running.
 *
 * Class SiteOrigin_Hosting_Export
 */
class SiteOrigin_Packs_Export {

	/**
	 * Affiliate codes taken from SiteOrigin.com
	 *
	 * @var array
	 */
	static $affiliates = array(
		'ibrossiter@gmail.com' => 2
	);

	function __construct(){
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'init', array( $this, 'frontend_download' ) );
		add_action( 'wp_ajax_download_import', array( $this, 'download_import_plugin' ) );
		add_action( 'wp_ajax_nopriv_download_import', array( $this, 'download_import_plugin' ) );

		add_action( 'wp_ajax_theme_download_info', array( $this, 'theme_download_info' ) );
		add_action( 'wp_ajax_nopriv_theme_download_info', array( $this, 'theme_download_info' ) );
	}

	static function single(){
		static $single;
		if( empty( $single ) ) {
			$single = new self();
		}

		return $single;
	}

	function render_page(){
		include plugin_dir_path( __FILE__ ) . '../tpl/export.php';
	}

	function enqueue_admin_scripts( $prefix ){
		if( $prefix == 'tools_page_so-export-page' ) {
			wp_enqueue_style( 'siteorigin-hosting-export', plugin_dir_url( __FILE__ ) . '../css/export.css', array( ), md5_file( plugin_dir_path( __FILE__ ) . '../css/export.css' ) );
		}
	}

	function frontend_download(){
		if( ! is_admin( ) && isset( $_GET['action'] ) && $_GET['action'] == 'download' ) {
			$this->download_import_plugin();
		}
	}

	/**
	 * Create the importer plugin
	 */
	function download_import_plugin(){
		// Create the plugin name
		$site = get_blog_details( get_current_blog_id() );
		$site_url = str_replace( 'http://', '', $site->siteurl );
		$site_url = trim( $site_url, '/' );
		$site_url = str_replace( '/', '.', $site_url );

		$plugin_name = sanitize_title( $site_url );
		$plugin_file = WP_CONTENT_DIR . '/packs/' . $plugin_name . '.zip';

		// Regenerate this file every 5 minutes
		if( ! file_exists( $plugin_file ) || time()-filemtime( $plugin_file ) > 5 * 60 ) {
			// Create a temporary directory
			$tmpdir = tempnam( sys_get_temp_dir(), '' );
			if ( file_exists( $tmpdir ) ) {
				unlink( $tmpdir );
			}
			mkdir( $tmpdir );
			if ( ! is_dir( $tmpdir ) ) exit();

			mkdir( $tmpdir . '/' . $plugin_name );
			$plugin_path = $tmpdir . '/' . $plugin_name;

			// Add the actual importer code
			mkdir( $plugin_path . '/importer' );
			recurse_copy( ABSPATH . 'wp-content/plugins/siteorigin-importer/importer/', $plugin_path . '/importer' );
			copy( ABSPATH . 'wp-content/plugins/siteorigin-importer/importer.php', $plugin_path . '/importer.php' );
			copy( ABSPATH . 'wp-content/plugins/siteorigin-importer/functions.php', $plugin_path . '/functions.php' );
			copy( ABSPATH . 'wp-content/plugins/siteorigin-importer/style.css', $plugin_path . '/style.css' );
			copy( ABSPATH . 'wp-content/plugins/siteorigin-importer/index.php', $plugin_path . '/index.php' );

			// Add the export name
			$contents = file_get_contents( $plugin_path . '/importer.php' );
			$contents = preg_replace( '/Plugin Name\:(.*)/', 'Plugin Name:$1 [' . preg_quote( $site->blogname ) . ']', $contents );
			file_put_contents( $plugin_path . '/importer.php', $contents );

			// Create the data file
			file_put_contents(
				$plugin_path . '/import-data.php',
				"<?php\nreturn " . var_export( $this->get_export_data( array( 'import_id' => get_current_blog_id() ) ), true ) . ";"
			);

			// Create and output the ZIP file
			if( ! is_dir( WP_CONTENT_DIR . '/packs/' ) ) {
				mkdir( WP_CONTENT_DIR . '/packs/' );
				chmod( WP_CONTENT_DIR . '/packs/', 755 );
			}

			// Now, lets create the ZIP file
			$root_path = realpath( $plugin_path );

			// Initialize archive object
			$zip = new ZipArchive();
			$zip->open( $plugin_file, ZipArchive::CREATE | ZipArchive::OVERWRITE );

			// Create recursive directory iterator
			/** @var SplFileInfo[] $files */
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root_path ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($files as $name => $file) {
				// Skip directories
				if( $file->isDir() ) continue;

				// Get real and relative path for current file
				$plugin_path = $file->getRealPath();
				$relative_path = substr( $plugin_path, strlen($root_path) + 1 );

				// Add current file to archive
				$zip->addFile( $plugin_path, $plugin_name . '/' . $relative_path );
			}

			// Zip archive will be created only after closing object
			$zip->close();
			recurse_rmdir( $tmpdir );
		}

		// Output the file
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public");
		header("Content-Description: File Transfer");
		header("Content-type: application/zip");
		header("Content-Disposition: attachment; filename=\"" . basename( $plugin_file ) . "\"");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: " . filesize( $plugin_file ) );
		@readfile( $plugin_file );

		exit();
	}

	/**
	 * Maps the meta array
	 *
	 * @param $m
	 * @return mixed
	 */
	static function meta_map( $m ){
		return $m[ count( $m ) - 1 ];
	}

	/**
	 * Return the main data for the site.
	 *
	 * @param array $data The initial data.
	 *
	 * @return array
	 */
	function get_export_data( $data ){

		// All the options that we need to save
		$options = array(
			// Basic settings
			'page_on_front',
			'show_on_front',
			'page_for_posts',
			'blogname',
			'blogdescription',
			'sticky_posts',
			'permalink_structure',

			// Image sizes
			'thumbnail_size_w',
			'thumbnail_size_h',
			'thumbnail_crop',
			'medium_size_w',
			'medium_size_h',
			'large_size_w',
			'large_size_h',

			// Page Builder
			'siteorigin_panels_settings',
			'siteorigin_panels_home_page_id',

			// Widgets Bundle
			'siteorigin_widgets_active',

			// SiteOrigin CSS
			'siteorigin_custom_css[' . get_template() . ']',

			// Sidebars
			'sidebars_widgets',
		);

		// Get any non empty widget options
		global $wpdb;
		$results = $wpdb->get_results( "SELECT * FROM $wpdb->options WHERE option_name LIKE 'widget_%'", ARRAY_A );
		foreach( $results as $result ) {
			$result['option_value'] = maybe_unserialize( $result['option_value'] );

			if( count( $result['option_value'] ) > 1 ) {
				$options[] = $result['option_name'];
			}
		}

		// Get all the basic site settings
		$data[ 'options' ] = array();
		foreach( $options as $option ) {
			$data[ 'options' ][ $option ] = get_option( $option );
		}

		// Add the affiliate ID
		$admin_email = get_option( 'admin_email' );
		if( ! empty( self::$affiliates[ $admin_email ] ) ) {
			$data[ 'options' ][ 'siteorigin_premium_affiliate_id' ] = self::$affiliates[ $admin_email ];
		}

		// All the theme mods
		$data[ 'theme_mods' ] = get_theme_mods();

		// Store the original URL
		$data[ 'site_url' ] = site_url( '/' );

		// And the theme we're using
		$data['template'] = get_template();

		// Add in the plugins
		$plugins = array();

		$active = array_merge( array_keys( get_site_option('active_sitewide_plugins') ), get_option('active_plugins') );
		foreach( $active as $a ) {
			if( strpos( $a, 'site-packs/site-packs' ) !== false ) continue;
			$plugin_data = get_file_data(
				WP_PLUGIN_DIR . '/' . $a,
				array(
					'Name' => 'Plugin Name',
				),
				'plugin'
			);
			$plugins[ $a ] = $plugin_data['Name'];
		}


		$data['plugins'] = $plugins;

		$theme_data = wp_get_theme();
		$data[ 'theme_version' ] = $theme_data->get( 'Version' );
		$data[ 'theme_name' ] = $theme_data->get( 'Name' );
		$data[ 'theme_uri' ] = $theme_data->get( 'ThemeURI' );

		// Add in all the extra import data
		$data[ 'posts' ] = $this->get_posts_data();
		$data[ 'terms' ] = $this->get_terms_data();

		return $data;
	}

	/**
	 * Create all the export files for posts
	 *
	 * @return array
	 */
	function get_posts_data( ) {
		global $wpdb;

		$taxonomies = get_taxonomies();
		$data = array( );

		$results = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_type != 'revision'", ARRAY_A );
		foreach( $results as $result ) {
			$result['post_meta'] = get_post_meta( $result['ID'] );
			$result[ 'post_meta' ] = array_map( array( $this, 'meta_map' ), $result[ 'post_meta' ] );

			// We don't need the preview version
			unset( $result['post_meta']['_panels_data_preview'] );

			// Store the author information.
			$result['author_login'] = get_the_author_meta( 'user_login', $result['ID'] );
			$result['author_email'] = get_the_author_meta( 'user_email', $result['ID'] );

			if( $result['post_type'] == 'attachment' ) {
				$result['original_file'] = wp_get_attachment_url( $result['ID'] );
			}

			// Add all the relevant terms
			$result['terms'] = array();
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy_terms = wp_get_post_terms( $result['ID'], $taxonomy );
				if( !empty( $taxonomy_terms ) ) {

					// We want this as an array
					foreach( $taxonomy_terms as & $term ) {
						$term = (array) $term;
					}

					$result['terms'][ $taxonomy ] = $taxonomy_terms;
				}
			}

			$data[ $result['ID'] ] = $result;
		}

		return $data;
	}

	/**
	 * Create all the export files for taxonomy
	 *
	 * @return array
	 */
	function get_terms_data( ) {
		global $wpdb;

		$data = array();
		$results = $wpdb->get_results( "SELECT * FROM $wpdb->terms", ARRAY_A );
		foreach( $results as $result ) {
			$result[ 'term_meta' ] = get_term_meta( $result[ 'term_id' ] );
			$result[ 'term_meta' ] = array_map( array( $this, 'meta_map' ), $result[ 'term_meta' ] );
			$result['term_taxonomy'] = $wpdb->get_results( "SELECT * FROM $wpdb->term_taxonomy WHERE term_id = " . intval( $result['term_id'] ), ARRAY_A );

			$data[ $result['term_id'] ] = $result;
		}

		return $data;
	}

	/**
	 * Create all the comments files
	 */
	function get_comments_data( ) {
		global $wpdb;

		$data = array();

		$results = $wpdb->get_results( "SELECT * FROM $wpdb->comments WHERE comment_approved != 'spam'", ARRAY_A );
		foreach( $results as $result ) {
			$result[ 'comment_meta' ] = get_comment_meta( $result['comment_ID'] );
			$result[ 'comment_meta' ] = array_map( array( $this, 'meta_map' ), $result[ 'comment_meta' ] );

			$data[ $result['comment_ID'] ] = $result;
		}

		return $data;
	}

	/**
	 * Get update information about a theme.
	 */
	function theme_download_info( ){
		$theme = wp_get_theme( isset( $_GET[ 'theme' ] ) ? $_GET[ 'theme' ] : null );

		if( empty( $theme ) ) return;

		$return = get_transient( 'theme_download[' . $theme->get_template() . ']' );

		if( empty( $return ) ) {
			// Check the version hosted on WordPress.org
			$response = wp_remote_get( 'https://themes.svn.wordpress.org/' . $theme->get_template() . '/' );
			if( !is_wp_error( $response ) ) {
				$doc = new DOMDocument();
				$doc->loadHTML( $response['body'] );
				$xpath = new DOMXPath( $doc );
				$versions = array();
				foreach( $xpath->query('//body/ul/li/a') as $el ) {
					preg_match( '/([0-9\.]+)\//', $el->getAttribute('href') , $matches);
					if( empty($matches[1]) || $matches[1] == '..' ) continue;
					$versions[] = $matches[1];
				}
				if( ! empty($versions) ) {
					usort($versions, 'version_compare');
					$latest_version = end( $versions );
					$return = array(
						'source' => 'wordpress.org',
						'zip_name' => $theme->get_template() . '.' . $latest_version . '.zip',
						'theme' => $theme,
						'version' => $latest_version,
						'package' => 'https://downloads.wordpress.org/theme/' . $theme->get_template() . '.' . $latest_version . '.zip',
					);
				}
			}

			// Check the local version
			if( empty( $return ) || version_compare( $theme->get( 'Version' ), $return['version'], '>' ) ) {
				// The local version is higher than the hosted version
				$return = array(
					'source' => 'siteorigin.com',
					'zip_name' => $theme->get_template() . '.' . $theme->get( 'Version' ) . '.zip',
					'theme' => $theme,
					'version' => $theme->get( 'Version' ),
					'package' => content_url( 'downloads/' . $theme->get_template() . '.' . $theme->get( 'Version' ) . '.zip' ),
				);
			}

			// Set a transient that lasts 5 minutes
			set_transient( 'theme_download[' . $theme->get_template() . ']', $return, 5*60 );
		}


		if( $return['source'] == 'siteorigin.com' && ! file_exists( WP_CONTENT_DIR . '/downloads/' . $return[ 'zip_name' ] ) ) {
			// We need to create the ZIP file
			// Initialize archive object
			$zip = new ZipArchive();
			$zip->open( WP_CONTENT_DIR . '/downloads/' . $return[ 'zip_name' ], ZipArchive::CREATE | ZipArchive::OVERWRITE );

			// This is the path where we'll be getting the
			$root_path = WP_CONTENT_DIR . '/themes/' . $theme->get_template();

			// Create recursive directory iterator
			/** @var SplFileInfo[] $files */
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root_path ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($files as $name => $file) {
				// Skip directories
				if( $file->isDir() ) continue;

				// Get real and relative path for current file
				$file_path = $file->getRealPath();
				$relative_path = substr( $file_path, strlen($root_path) + 1 );

				// Add current file to archive
				$zip->addFile( $file_path, $theme->get_template() . '/' . $relative_path );
			}

			$zip->close();
		}

		// Return the result
		header( 'content-type: application/json' );
		echo json_encode( $return );
		exit();
	}
}