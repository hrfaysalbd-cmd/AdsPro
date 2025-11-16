<?php
/**
 * Plugin Name:       AdsCampaignPro
 * Plugin URI:        https://example.com/adscampaignpro
 * Description:       Sell and run Popup, Slide, and Scroll ad campaigns directly on your WordPress site.
 * Version:           1.1.0
 * Author:            Hafizur Rahman
 * Author URI:        https://hafizurr.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       adscampaignpro
 * Domain Path:       /languages
 *
 * PHP Version:       7.4
 * WP Version:        6.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants
 */
define( 'ADCP_VERSION', '1.1.0' );
define( 'ADCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Include helper functions
 */
require ADCP_PLUGIN_DIR . 'includes/adcp-functions.php';

/**
 * The code that runs during plugin activation.
 */
function activate_adcp() {
	require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-activator.php';
	Adcp_Activator::activate();
    
    require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-aggregator.php';
    Adcp_Aggregator::schedule_cron();
    
    // Register our new rewrite rule
    require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-client-page.php';
    $client_page = new Adcp_Client_Page();
    $client_page->add_rewrite_rules(); // Add the rule
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'activate_adcp' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_adcp() {
    require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-aggregator.php';
    Adcp_Aggregator::unschedule_cron();
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'deactivate_adcp' );


//
// --- LOAD ALL PLUGIN CLASSES ---
//

// 0. Activation/Deactivation Class (Needed for Tools page)
require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-activator.php';

// 1. Database & Model Classes (MUST be loaded first)
require ADCP_PLUGIN_DIR . 'includes/class-adcp-campaigns-db.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-db-commerce.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-tracking-db.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-analytics-queries.php';

// 2. Core Controller Classes
require ADCP_PLUGIN_DIR . 'includes/class-adcp-admin.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-public.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-rest-api.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-settings.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-client-page.php';

// 3. Reporting & Background Process Classes
require ADCP_PLUGIN_DIR . 'includes/class-adcp-aggregator.php';
require ADCP_PLUGIN_DIR . 'includes/class-adcp-email-reporter.php';
//
// --- END CLASS LOADING ---
//


/**
 * Begins execution of the admin-facing part of the plugin.
 */
function run_adcp_admin() {
	$plugin_admin = new Adcp_Admin( ADCP_VERSION );
	$plugin_admin->init();
    
    // Initialize the settings page
    $plugin_settings = new Adcp_Settings();
}
run_adcp_admin();

/**
 * Begins execution of the public-facing part of the plugin.
 */
function run_adcp_public() {
	$plugin_public = new Adcp_Public( ADCP_VERSION );
	$plugin_public->init();
}
run_adcp_public();

/**
 * Initialize REST API
 */
function run_adcp_api() {
    $plugin_api = new Adcp_Rest_Api();
}
run_adcp_api();

/**
 * Initialize Aggregator
 */
function run_adcp_aggregator() {
    $plugin_aggregator = new Adcp_Aggregator();
    $plugin_aggregator->__init__();
}
run_adcp_aggregator();

/**
 * Initialize Client Page
 */
function run_adcp_client_page() {
    $client_page = new Adcp_Client_Page();
    $client_page->init();
}
run_adcp_client_page();