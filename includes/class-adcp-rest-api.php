<?php
/**
 * AdsCampaignPro REST API
 *
 * Registers and handles all public REST API endpoints.
 */
class Adcp_Rest_Api {

    protected $namespace = 'adcp/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register the routes for the objects.
     */
    public function register_routes() {
        
        // --- PUBLIC TRACKING ENDPOINTS ---

        // GET /campaigns (For tracker.js)
        register_rest_route( $this->namespace, '/campaigns', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_campaigns_for_render' ),
                'permission_callback' => '__return_true', // Public
                'args'                => array(
                    'url' => array( 'required' => false, 'sanitize_callback' => 'esc_url_raw' ),
                    'device' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key' ),
                    'token' => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                ),
            ),
        ) );

        // POST /track (For tracker.js)
        register_rest_route( $this->namespace, '/track', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'track_event' ),
                'permission_callback' => '__return_true', // Public
            ),
        ) );

        // --- PUBLIC CONTRACT FORM ENDPOINTS ---

        // POST /apply-coupon
        register_rest_route( $this->namespace, '/apply-coupon', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'apply_coupon' ),
                'permission_callback' => '__return_true', // Public
                'args' => array(
                    'code' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'package_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
                ),
            ),
        ) );

        // POST /contracts
        register_rest_route( $this->namespace, '/contracts', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'submit_contract' ),
                'permission_callback' => '__return_true', // Public
            ),
        ) );

        // --- ADMIN ANALYTICS ENDPOINT ---

        // GET /analytics (For Admin Dashboard)
        register_rest_route( $this->namespace, '/analytics', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_analytics_data' ),
                'permission_callback' => array( $this, 'check_admin_permission' ), // Secure
                'args'                => array(
                    'days' => array( 'required' => false, 'sanitize_callback' => 'absint', 'default' => 30 ),
                ),
            ),
        ) );

        // --- CLIENT ANALYTICS ENDPOINT ---
        
        // GET /client-analytics/{token}
        register_rest_route( $this->namespace, '/client-analytics/(?P<token>[a-zA-Z0-9_-]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_client_analytics' ),
                'permission_callback' => '__return_true', // Public, but token is auth
                'args'                => array(
                    'token' => array(
                        'required' => true,
                        'validate_callback' => function($param) { return !empty($param); }
                    ),
                ),
            ),
        ) );
    }

    /**
     * Permission check: Only allow admins.
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Callback for GET /campaigns
     */
    public function get_campaigns_for_render( $request ) {
        $page_url = $request->get_param( 'url' ) ? $request->get_param( 'url') : (isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '');
        $device   = $request->get_param( 'device' );
        $token    = $request->get_param( 'token' ); 

        $campaigns = Adcp_Campaigns_DB::get_active_campaigns_for_render();
        
        $eligible_campaigns = array();

        foreach ( $campaigns as $campaign ) {
            $config = json_decode( $campaign->config, true );

            // 1. Check Device Targeting
            if ( $device ) {
                if ( ($device === 'mobile' && empty($config['device_mobile'])) ||
                     ($device === 'tablet' && empty($config['device_tablet'])) ||
                     ($device === 'desktop' && empty($config['device_desktop'])) ) {
                    continue; 
                }
            }

            // 2. Check URL Targeting
            if ( ! empty($config['target_urls']) ) {
                $patterns = explode( "\n", $config['target_urls'] );
                if ( ! $this->matches_url_pattern( $page_url, $patterns ) ) {
                    continue; 
                }
            }
            
            // 3. If passed, format for output
            $eligible_campaigns[] = array(
                'id'       => $campaign->id,
                'type'     => $campaign->type,
                'config'   => array(
                    'headline'  => $config['headline'] ?? '',
                    'subtext'   => $config['subtext'] ?? '',
                    'cta_label' => $config['cta_label'] ?? '',
                    'cta_url'   => $config['cta_url'] ?? '',
                ),
                'creative' => array(
                    'url'  => $config['creative_url'] ?? '',
                    'type' => $config['creative_type'] ?? '',
                    'html' => $config['creative_html'] ?? '',
                ),
                'frequency' => array(
                    'per_day' => $config['freq_cap'] ?? 1,
                ),
            );
        }

        return new WP_REST_Response( array( 'campaigns' => $eligible_campaigns ), 200 );
    }

    /**
     * Helper to check URL against patterns.
     */
    private function matches_url_pattern( $url, $patterns ) {
        $patterns = array_map( 'trim', $patterns );
        $patterns = array_filter( $patterns );

        if ( empty( $patterns ) ) {
            return true;
        }
        
        $current_url = trailingslashit( $url );
        $current_path = parse_url( $current_url, PHP_URL_PATH );
        
        foreach ( $patterns as $pattern ) {
            $regex = preg_quote( $pattern, '/' );
            $regex = str_replace( '\*', '.*', $regex );
            
            // --- FIX: Use . for string concatenation, not + ---
            if ( preg_match( '/^' . $regex . '$/i', $current_path ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Callback for POST /track
     */
    public function track_event( $request ) {
        $json_params = $request->get_json_params();

        $data = array(
            'campaign_id' => isset($json_params['campaign_id']) ? absint($json_params['campaign_id']) : 0,
            'event_type'  => isset($json_params['event']) ? sanitize_key($json_params['event']) : null,
            'cookie_id'   => isset($json_params['cookie_id']) ? sanitize_text_field($json_params['cookie_id']) : null,
            'page_url'    => isset($json_params['page_url']) ? esc_url_raw($json_params['page_url']) : null,
            'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'ip_hash'     => md5( $_SERVER['REMOTE_ADDR'] ),
            'meta'        => isset($json_params['meta']) ? wp_json_encode($json_params['meta']) : '{}',
        );

        if ( empty($data['campaign_id']) || empty($data['event_type']) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Missing required fields.' ), 400 );
        }

        Adcp_Tracking_DB::insert_event( $data );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Callback for POST /apply-coupon
     */
    public function apply_coupon( $request ) {
        $code = $request->get_param('code');
        $package_id = $request->get_param('package_id');

        $package = Adcp_Packages_DB::get_package( $package_id );
        $coupon = Adcp_Coupons_DB::get_coupon_by_code( $code );

        if ( !$package ) {
            return new WP_REST_Response( array( 'valid' => false, 'message' => 'Package not found.' ), 404 );
        }
        if ( !$coupon || $coupon->status !== 'active' ) {
            return new WP_REST_Response( array( 'valid' => false, 'message' => 'Invalid or expired coupon.' ), 400 );
        }
        if ( empty($package->allow_coupon) ) {
            return new WP_REST_Response( array( 'valid' => false, 'message' => 'Coupon not valid for this package.' ), 400 );
        }

        $subtotal = (float) $package->price;
        $discount = 0.00;

        if ( $coupon->type === 'percent' ) {
            $discount = $subtotal * ( (float) $coupon->value / 100 );
        } else {
            $discount = (float) $coupon->value;
        }
        
        $discount = min( $subtotal, $discount );
        
        // --- FIX: Use 0 as default tax rate ---
        $tax_rate = adcp_get_setting( 'tax_rate', 0 ) / 100;
        $tax = ( $subtotal - $discount ) * $tax_rate;
        $total = ( $subtotal - $discount ) + $tax;

        return new WP_REST_Response( array(
            'valid'    => true,
            'message'  => 'Coupon applied!',
            'subtotal' => number_format( $subtotal, 2 ),
            'discount' => number_format( $discount, 2 ),
            'tax'      => number_format( $tax, 2 ),
            'total'    => number_format( $total, 2 ),
        ), 200 );
    }
    
    /**
     * Callback for POST /contracts
     */
    public function submit_contract( $request ) {
        $params = $request->get_json_params();

        $client_name = isset($params['client']['name']) ? sanitize_text_field($params['client']['name']) : '';
        $client_email = isset($params['client']['email']) ? sanitize_email($params['client']['email']) : '';
        $client_phone = isset($params['client']['phone']) ? sanitize_text_field($params['client']['phone']) : '';

        $subtotal = 0.00;
        $package = null;
        $extra = null;

        if ( !empty($params['package_id']) ) {
            $package = Adcp_Packages_DB::get_package( absint($params['package_id']) );
            if ($package) $subtotal += (float) $package->price;
        }
        if ( !empty($params['extra_id']) ) {
            $extra = Adcp_Extras_DB::get_extra( absint($params['extra_id']) );
            if ($extra) $subtotal += (float) $extra->price;
        }

        $discount = 0.00;
        if ( !empty($params['coupon_code']) && $package && !empty($package->allow_coupon) ) {
            $coupon = Adcp_Coupons_DB::get_coupon_by_code( $params['coupon_code'] );
            if ( $coupon && $coupon->status === 'active' ) {
                $discount = ($coupon->type === 'percent') ? ($subtotal * ((float) $coupon->value / 100)) : (float) $coupon->value;
                $discount = min( $subtotal, $discount );
            }
        }
        
        // --- FIX: Use 0 as default tax rate ---
        $tax_rate = adcp_get_setting( 'tax_rate', 0 ) / 100;
        $tax = ( $subtotal - $discount ) * $tax_rate;
        $grand_total = ( $subtotal - $discount ) + $tax;

        $contract_data = array(
            'package_id' => $package ? $package->id : null,
            'package_title' => $package ? $package->title : null,
            'extra_id' => $extra ? $extra->id : null,
            'extra_title' => $extra ? $extra->title : null,
            'coupon_code' => $params['coupon_code'] ?? null,
            'payment_method' => sanitize_key($params['payment']['method']),
            'payment_proof_id' => isset($params['payment']['proof_file_id']) ? absint($params['payment']['proof_file_id']) : 0,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
        );

        $insert_id = Adcp_Contracts_DB::insert_contract(array(
            'client_name'    => $client_name,
            'client_email'   => $client_email,
            'client_phone'   => $client_phone,
            'data'           => wp_json_encode($contract_data),
            'grand_total'    => $grand_total,
            'payment_status' => ($grand_total == 0) ? 'paid' : 'pending',
        ));

        if ( !$insert_id ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Could not save contract.' ), 500 );
        }
        
        $admin_email = get_option('admin_email');
        $subject = 'New Contract Submission - AdsCampaignPro';
        $message = "A new contract (ID: $insert_id) has been submitted by $client_name ($client_email).\n\n";
        $message .= "Total: $" . number_format($grand_total, 2) . "\n";
        $message .= "Payment Method: " . $params['payment']['method'] . "\n\n";
        $message .= "Please log in to the admin panel to review and approve it.";
        wp_mail( $admin_email, $subject, $message );

        return new WP_REST_Response( array( 'success' => true, 'contract_id' => $insert_id, 'status' => 'pending' ), 201 );
    }

    /**
     * Callback for GET /analytics
     */
    public function get_analytics_data( $request ) {
        $days = $request->get_param( 'days' );
        $data = Adcp_Analytics_Queries::get_analytics_data( $days );
        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Callback for GET /client-analytics/{token}
     */
    public function get_client_analytics( $request ) {
        global $wpdb;
        $token = $request->get_param('token');
        
        $contract = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM " . $wpdb->prefix . "adcp_contracts WHERE tracking_token = %s AND status = 'approved'",
            $token
        ) );
        
        if ( !$contract ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid or expired token.' ), 403 );
        }
        
        $campaign_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM " . $wpdb->prefix . "adcp_campaigns WHERE contract_id = %d",
            $contract->id
        ) );
        
        if ( empty( $campaign_ids ) ) {
            return new WP_REST_Response( Adcp_Analytics_Queries::get_analytics_data_for_campaigns( array() ), 200 );
        }
        
        $data = Adcp_Analytics_Queries::get_analytics_data_for_campaigns( $campaign_ids, 90 );
        
        return new WP_REST_Response( $data, 200 );
    }
}