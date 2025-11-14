<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://hafizurr.com
 * @since      1.0.0
 *
 * @package    AdsCampaignPro
 */

// If uninstall not called from WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

global $wpdb;

// 1. Define all custom table names
$tables = [
    $wpdb->prefix . 'adcp_campaigns',
    $wpdb->prefix . 'adcp_creatives',
    $wpdb->prefix . 'adcp_packages',
    $wpdb->prefix . 'adcp_coupons',
    $wpdb->prefix . 'adcp_contracts',
    $wpdb->prefix . 'adcp_tracking',
    $wpdb->prefix . 'adcp_transactions',
    $wpdb->prefix . 'adcp_extras',
    $wpdb->prefix . 'adcp_tracking_summary'
];

// 2. Drop all custom tables
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// 3. Delete all options from wp_options
delete_option( 'adcp_settings' );
delete_option( 'adcp_version' );

// 4. Clear any scheduled cron jobs
wp_clear_scheduled_hook( 'adcp_hourly_aggregation_event' );