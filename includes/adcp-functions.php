<?php
/**
 * AdsCampaignPro Global Helper Functions
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get a specific setting from the AdsCampaignPro options.
 *
 * @param string $key The key of the setting.
 * @param mixed $default The default value if not found.
 * @return mixed The setting value.
 */
function adcp_get_setting( $key, $default = '' ) {
    $options = get_option( 'adcp_settings' );
    return isset( $options[$key] ) && $options[$key] !== '' ? $options[$key] : $default;
}