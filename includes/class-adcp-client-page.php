<?php
/**
 * AdsCampaignPro Client Page
 *
 * Handles the virtual client dashboard pages (/adclient/{token}/)
 */
class Adcp_Client_Page {

    public function init() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_filter( 'template_include', array( $this, 'load_template' ) );
    }

    /**
     * Add the rewrite rule for /adclient/{token}
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^adclient/([^/]+)/?$',
            'index.php?adcp_token=$matches[1]',
            'top'
        );
    }

    /**
     * Add our token to the list of known query variables.
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'adcp_token';
        return $vars;
    }

    /**
     * Check if we are on a client page, and if so, load our template.
     */
    public function load_template( $template ) {
        $token = get_query_var( 'adcp_token' );

        if ( $token ) {
            $new_template = ADCP_PLUGIN_DIR . 'templates/client-dashboard.php';
            if ( file_exists( $new_template ) ) {
                return $new_template;
            }
        }
        return $template;
    }
}