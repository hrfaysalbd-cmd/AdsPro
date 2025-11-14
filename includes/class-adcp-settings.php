<?php
/**
 * AdsCampaignPro Settings
 *
 * Uses the WordPress Settings API to create our options page.
 */
class Adcp_Settings {

    private $options;

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Get the plugin settings, caching them locally.
     */
    private function get_options() {
        if ( ! $this->options ) {
            $this->options = get_option( 'adcp_settings' );
        }
        return $this->options;
    }

    /**
     * Helper to get a single setting value.
     */
    private function get_option_value( $key, $default = '' ) {
        $options = $this->get_options();
        return isset( $options[$key] ) ? $options[$key] : $default;
    }

    /**
     * Register all settings, sections, and fields.
     */
    public function register_settings() {
        
        register_setting(
            'adcp_settings_group', // Option group
            'adcp_settings',       // Option name in wp_options
            array( $this, 'sanitize_settings' ) // Sanitization callback
        );

        // Section 1: Payment Settings
        add_settings_section(
            'adcp_payment_section', 'Payment Settings',
            null, 'adcp-settings'
        );
        add_settings_field( 'etransfer_email', 'E-transfer Email', array( $this, 'render_text_field' ), 'adcp-settings', 'adcp_payment_section', 
            ['id' => 'etransfer_email', 'desc' => 'Email for manual e-transfer instructions.'] );
        add_settings_field( 'paypal_email', 'PayPal Email', array( $this, 'render_text_field' ), 'adcp-settings', 'adcp_payment_section', 
            ['id' => 'paypal_email', 'desc' => 'Email for manual PayPal payment instructions.'] );
        
        // Section 2: Tax Settings
        add_settings_section( 'adcp_tax_section', 'Tax Settings', null, 'adcp-settings' );
        add_settings_field( 'tax_rate', 'Global Tax Rate (%)', array( $this, 'render_text_field' ), 'adcp-settings', 'adcp_tax_section', 
            ['id' => 'tax_rate', 'type' => 'number', 'desc' => 'e.g., 5 for 5%. Used in the contract form.'] );

        // Section 3: Stripe Settings
        add_settings_section( 'adcp_stripe_section', 'Stripe API Keys (Optional)', null, 'adcp-settings' );
        add_settings_field( 'stripe_pk', 'Stripe Publishable Key', array( $this, 'render_text_field' ), 'adcp-settings', 'adcp_stripe_section', 
            ['id' => 'stripe_pk', 'desc' => 'Key starting with pk_live_...'] );
        add_settings_field( 'stripe_sk', 'Stripe Secret Key', array( $this, 'render_text_field' ), 'adcp-settings', 'adcp_stripe_section', 
            ['id' => 'stripe_sk', 'type' => 'password', 'desc' => 'Key starting with sk_live_...'] );
        
        // Section 4: Compliance
        add_settings_section( 'adcp_compliance_section', 'Compliance (GDPR)', null, 'adcp-settings' );
        add_settings_field( 'gdpr_enabled', 'Enable Cookie Consent Mode', array( $this, 'render_checkbox_field' ), 'adcp-settings', 'adcp_compliance_section',
            ['id' => 'gdpr_enabled', 'desc' => 'If checked, the tracker.js will *not* fire until a cookie consent plugin gives consent.'] );
    }

    /**
     * Sanitization callback for all settings.
     */
    public function sanitize_settings( $input ) {
        $clean_input = array();
        $clean_input['etransfer_email'] = isset( $input['etransfer_email'] ) ? sanitize_email( $input['etransfer_email'] ) : '';
        $clean_input['paypal_email'] = isset( $input['paypal_email'] ) ? sanitize_email( $input['paypal_email'] ) : '';
        $clean_input['tax_rate'] = isset( $input['tax_rate'] ) ? floatval( $input['tax_rate'] ) : 0;
        $clean_input['stripe_pk'] = isset( $input['stripe_pk'] ) ? sanitize_text_field( $input['stripe_pk'] ) : '';
        $clean_input['stripe_sk'] = isset( $input['stripe_sk'] ) ? sanitize_text_field( $input['stripe_sk'] ) : '';
        $clean_input['gdpr_enabled'] = isset( $input['gdpr_enabled'] ) ? 1 : 0;
        return $clean_input;
    }

    /**
     * Renders a standard text or number input.
     */
    public function render_text_field( $args ) {
        $id = $args['id'];
        $type = $args['type'] ?? 'text';
        $desc = $args['desc'] ?? '';
        $value = $this->get_option_value( $id );
        
        printf(
            '<input type="%s" id="%s" name="adcp_settings[%s]" value="%s" class="regular-text" />',
            esc_attr( $type ), esc_attr( $id ), esc_attr( $id ), esc_attr( $value )
        );
        if ($desc) {
            printf( '<p class="description">%s</p>', esc_html( $desc ) );
        }
    }
    
    /**
     * Renders a standard checkbox.
     */
    public function render_checkbox_field( $args ) {
        $id = $args['id'];
        $desc = $args['desc'] ?? '';
        $value = $this->get_option_value( $id );
        
        printf(
            '<label><input type="checkbox" id="%s" name="adcp_settings[%s]" value="1" %s /> %s</label>',
            esc_attr( $id ), esc_attr( $id ), checked( $value, 1, false ), esc_html( $desc )
        );
    }
}