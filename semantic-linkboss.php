<?php
/*
 * Plugin Name: LinkBoss - Semantic Internal Linking
 * Plugin URI: https://linkboss.io
 * Description: NLP, AI, and Machine Learning-powered semantic interlinking tool. Supports manual incoming/outgoing, SILO, and bulk auto internal links.
 * Version: 2.5.2
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: ZVENTURES LLC
 * Author URI: https://linkboss.io
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       semantic-linkboss
 * Domain Path:       /languages
 *
 * @package SEMANTIC_LB
 * @author LinkBoss <hi@zventures.io>
 * @license           GPL-2.0-or-later
 *
 */

/**
 * Prevent direct access
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEMANTIC_LB_VERSION', '2.5.2' );
define( 'SEMANTIC_LB_PLUGIN__FILE__', __FILE__ );
define( 'SEMANTIC_LB_PLUGIN_PATH', plugin_dir_path( SEMANTIC_LB_PLUGIN__FILE__ ) );
define( 'SEMANTIC_LB_PLUGIN_URL', plugins_url( '/', SEMANTIC_LB_PLUGIN__FILE__ ) );
define( 'SEMANTIC_LB_PLUGIN_INC_PATH', SEMANTIC_LB_PLUGIN_PATH . 'includes/' );
define( 'SEMANTIC_LB_PLUGIN_ASSETS_URL', SEMANTIC_LB_PLUGIN_URL . 'assets/' );

if ( ! defined( 'SEMANTIC_LB_REMOTE_ROOT_URL' ) ) {
	define( 'SEMANTIC_LB_REMOTE_ROOT_URL', 'https://api.linkboss.io' );
}

define( 'SEMANTIC_LB_REMOTE_URL', SEMANTIC_LB_REMOTE_ROOT_URL . '/api/v2/wp/' );
define( 'SEMANTIC_LB_AUTH_URL', SEMANTIC_LB_REMOTE_ROOT_URL . '/api/v2/auth/' );
define( 'SEMANTIC_LB_FETCH_REPORT_URL', SEMANTIC_LB_REMOTE_URL . 'options' );
define( 'SEMANTIC_LB_POSTS_SYNC_URL', SEMANTIC_LB_REMOTE_URL . 'sync' );
define( 'SEMANTIC_LB_OPTIONS_URL', SEMANTIC_LB_REMOTE_URL . 'options' );
define( 'SEMANTIC_LB_SYNC_INIT', SEMANTIC_LB_REMOTE_URL . 'sync/init' );
define( 'SEMANTIC_LB_SYNC_FINISH', SEMANTIC_LB_REMOTE_URL . 'sync/fin' );

/**
 * Installer
 *
 * @since 1.0.0
 */
require_once SEMANTIC_LB_PLUGIN_PATH . 'includes/class-installer.php';

/**
 * The main function responsible for returning the one true LinkBoss instance to functions everywhere.
 *
 * @since 0.0.0
 */

if ( ! function_exists( 'semantic_lb' ) ) {

	/**
	 * Load gettext translate for our text domain.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function semantic_lb_elementor_load_plugin() {

		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		define( 'SEMANTIC_LB_ELEMENTOR', true );
	}

	add_action( 'plugins_loaded', 'semantic_lb_elementor_load_plugin' );

	function semantic_lb_classic_editor_plugin_state() {
		/**
		 * Ensure the function is available
		 */
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin = 'classic-editor/classic-editor.php';

		if ( is_plugin_active( $plugin ) ) {
			define( 'SEMANTIC_LB_CLASSIC_EDITOR', true );
		}
	}

	add_action( 'plugins_loaded', 'semantic_lb_classic_editor_plugin_state' );

	function semantic_lb_divi_builder_loaded() {
		if ( ! class_exists( 'ET_Builder_Module' ) ) {
			return;
		}
	}

	add_action( 'et_builder_ready', 'semantic_lb_divi_builder_loaded' );

	add_action( 'plugins_loaded', function () {
		if ( class_exists( 'CT_Component' ) ) {
			if ( ! defined( "SHOW_CT_BUILDER_LB" ) ) {
				define( 'SHOW_CT_BUILDER_LB', true );
			}
		}
	} );

	function semantic_lb() {
		require_once __DIR__ . '/plugin.php';
	}

	function semantic_lb_activate() {
		$installer = new SEMANTIC_LB\Installer();
		$installer->run();
	}

	/**
	 * Creating tables for all blogs in a WordPress Multisite installation
	 * 
	 * @since 2.2.4
	 */
	function semantic_lb_on_activate( $network_wide ) {
		if ( is_admin() && is_multisite() && $network_wide ) {
			global $wpdb;
			/**
			 * Get all blogs in the network and activate plugin on each one
			 */
			// phpcs:ignore
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				semantic_lb_activate();
				restore_current_blog();
			}
		} else {
			semantic_lb_activate();
		}
	}

	/**
	 * Register activation hook for multisite
	 */
	register_activation_hook( __FILE__, 'semantic_lb_on_activate' );

	/**
	 * Add backward compatibility for the sent_status column
	 * 
	 * @since 2.2.4
	 * Delete this function after 3.0.0
	 */
	global $wpdb;
	$table_name = $wpdb->prefix . 'linkboss_sync_batch';

	/**
	 * Check if the table exists && sent_status column exists
	 */
	// phpcs:ignore
	if ( is_admin() && $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) ) ) == $table_name ) {
		$column_name = 'sent_status';
		// phpcs:ignore
		$column_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$table_name` LIKE %s", $column_name ) );

		if ( ! $column_exists ) {
			function semantic_lb_column_notice() {
				$class = 'notice notice-error';
				$message = esc_html__( 'LinkBoss plugin has been updated. Please deactivate and activate the plugin to update the database.', 'semantic-linkboss' );

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			}

			add_action( 'admin_notices', 'semantic_lb_column_notice' );
			return;
		}
	}


	/**
	 * End backward compatibility
	 */

	add_action( 'init', 'semantic_lb' );
}
