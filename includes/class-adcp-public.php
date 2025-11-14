<?php
/**
 * The public-facing functionality of the plugin.
 */
class Adcp_Public {

    private $version;
    private $namespace = 'adcp/v1';

    public function __construct( $version ) {
        $this->version = $version;
    }

    /**
     * Initialize public hooks
     */
    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_shortcode( 'adscampaignpro_contract_form', array( $this, 'render_contract_form_shortcode' ) );
        
        // --- FIX: Register the Ad Render Shortcode ---
        add_shortcode( 'adscampaignpro_render', array( $this, 'render_campaign_shortcode' ) );
        // --- END FIX ---

        // Register a new, secure AJAX endpoint for public file uploads
        add_action( 'wp_ajax_nopriv_adcp_upload_proof', array( $this, 'handle_proof_upload' ) );
        add_action( 'wp_ajax_adcp_upload_proof', array( $this, 'handle_proof_upload' ) );
    }
    
    /**
     * Enqueue scripts and styles for the public-facing site.
     */
    public function enqueue_public_assets() {
        
        wp_enqueue_style(
            'adcp-public',
            ADCP_PLUGIN_URL . 'public/css/tracker.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_script(
            'adcp-tracker',
            ADCP_PLUGIN_URL . 'public/js/tracker.js',
            array(),
            $this->version,
            true
        );

        wp_localize_script(
            'adcp-tracker',
            'adcp',
            array(
                'rest_url' => esc_url_raw( rest_url( 'adcp/v1' ) ),
                'gdpr_enabled' => adcp_get_setting( 'gdpr_enabled', 0 ),
            )
        );
        
        $token = get_query_var( 'adcp_token' );

        // --- Logic for Contract Form Page ---
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'adscampaignpro_contract_form' ) ) {
            
            wp_enqueue_script(
                'adcp-contract-form',
                ADCP_PLUGIN_URL . 'public/js/contract-form.js',
                array('jquery'),
                $this->version . '.3', 
                true 
            );

            wp_localize_script(
                'adcp-contract-form',
                'adcpContract',
                array(
                    'rest_url' => esc_url_raw( rest_url( $this->namespace ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                    'media_upload_nonce' => wp_create_nonce( 'adcp_upload_proof_nonce' ),
                    'tax_rate' => adcp_get_setting('tax_rate', 0),
                    'payment_instructions' => array(
                        'e_transfer' => 'Transfer Payment TO: ' . adcp_get_setting('etransfer_email', get_option('admin_email')),
                        'paypal'     => 'Send PayPal Payment TO: ' . adcp_get_setting('paypal_email', get_option('admin_email')),
                        'cash'       => 'Please contact us to arrange cash payment.'
                    )
                )
            );
            
            wp_enqueue_media();
        }
        
        // --- Logic for Client Dashboard Page ---
        if ( $token ) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(), '3.9.1', true
            );
            
            wp_enqueue_script(
                'adcp-client-analytics',
                ADCP_PLUGIN_URL . 'public/js/client-analytics.js',
                array( 'jquery', 'chart-js' ),
                $this->version,
                true 
            );
            
            wp_localize_script(
                'adcp-client-analytics',
                'adcpClient',
                array(
                    'rest_url' => esc_url_raw( rest_url( 'adcp/v1' ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                    'token'    => $token
                )
            );
        }
    }

    /**
     * Renders the [adscampaignpro_contract_form] shortcode.
     */
    public function render_contract_form_shortcode() {
        $packages = Adcp_Packages_DB::get_all_packages_for_form();
        $extras = Adcp_Extras_DB::get_all_extras_for_form();

        // --- NEW STYLING AND HTML STRUCTURE ---
        ob_start();
        ?>
        <style>
            #adcp-contract-form-wrapper {
                font-family: 'Times New Roman', Times, serif;
                font-size: 16px;
                line-height: 1.6;
                max-width: 800px;
                margin: 20px auto;
                padding: 30px 40px;
                border: 1px solid #000;
                background: #fff;
            }
            #adcp-contract-form-wrapper h2,
            #adcp-contract-form-wrapper h3 {
                text-align: center;
                text-transform: uppercase;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            #adcp-contract-form-wrapper p {
                margin-bottom: 15px;
            }
            #adcp-contract-form-wrapper .form-section {
                margin-bottom: 20px;
            }
            #adcp-contract-form-wrapper label {
                font-weight: bold;
                display: block;
                margin-bottom: 5px;
            }
            #adcp-contract-form-wrapper input[type="text"],
            #adcp-contract-form-wrapper input[type="email"],
            #adcp-contract-form-wrapper input[type="tel"],
            #adcp-contract-form-wrapper select,
            #adcp-contract-form-wrapper textarea {
                width: 100%;
                padding: 8px;
                font-size: 15px;
                font-family: Arial, sans-serif;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }
            #adcp-contract-form-wrapper .inline-input {
                width: auto;
                display: inline-block;
                border: none;
                border-bottom: 1px solid #000;
                border-radius: 0;
                padding: 2px 5px;
                font-family: 'Times New Roman', Times, serif;
                font-size: 16px;
            }
            #adcp-contract-form-wrapper .inline-input:focus {
                outline: none;
                box-shadow: none;
                border-bottom: 2px solid #0073aa;
            }
            #price-summary {
                background: #f9f9f9;
                border: 1px solid #eee;
                padding: 15px;
                margin: 20px 0;
            }
            #price-summary div {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                font-family: Arial, sans-serif;
            }
            #price-summary strong { font-size: 1.2em; }
            #manual-payment-instructions {
                display:none;
                border: 1px dashed #ccc;
                padding: 15px;
                margin-top: 15px;
                font-family: Arial, sans-serif;
            }
            .payment-box {
                background: #f9f9f9;
                padding: 10px;
                display:flex;
                justify-content: space-between;
                align-items: center;
            }
            .spinner { vertical-align: middle; visibility: hidden; }
            .adcp-submit-wrapper {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-family: Arial, sans-serif;
            }
        </style>
        
        <div id="adcp-contract-form-wrapper">
            <form id="ad-contract-form">
                <h2>Service Contract</h2>

                <p><strong>THE PARTIES.</strong> This Service Contract (the “Agreement”) made on <?php echo date('m/d/Y'); ?> (the “Effective Date”) is by and between:</p>

                <p style="margin-left: 20px;">
                    <strong>Service Provider:</strong> CANADIAN ALUMNI ASSOCIATION OF RAJSHAHI UNIVERSITY (CAARU), with a mailing address of Unit # 3, 3000 Danforth Avenue, Suite 118, East York, ON M4C 1M7 (the “Service Provider”), and
                </p>
                <p style="margin-left: 20px;">
                    <strong>Client:</strong> <label for="client_name" class="screen-reader-text">Client Name</label>
                    <input type="text" id="client_name" name="client_name" placeholder="[CLIENT NAME]" class="inline-input" required style="width: 300px;">, 
                    with a mailing address of
                    <label for="client_address" class="screen-reader-text">Client Address</label>
                    <input type="text" id="client_address" name="client_address" placeholder="[CLIENT ADDRESS]" class="inline-input" required style="width: 100%;">
                    (the “Client”).
                </p>
                
                <div class="form-section">
                    <label for="client_email">Client Email *</label>
                    <input type="email" id="client_email" name="client_email" required>
                </div>
                
                <div class="form-section">
                    <label for="client_phone">Client Phone</label>
                    <input type="tel" id="client_phone" name="client_phone">
                </div>

                <p>IN CONSIDERATION of the provisions contained in this Agreement and for other good and valuable consideration, the Client hires the Service Provider to work under the terms and conditions hereby agreed upon by the Parties:</p>

                <p><strong>SERVICES.</strong> The Service Provider agrees to provide the following:</p>
                
                <div class="form-section">
                    <label for="package-select">Service Package *</label>
                    <select id="package-select" name="package_id" required>
                        <option value="">-- Select a Package --</option>
                        <?php foreach ( $packages as $pkg ) : ?>
                            <option value="<?php echo esc_attr( $pkg->id ); ?>" data-price="<?php echo esc_attr( $pkg->price ); ?>">
                                <?php echo esc_html( $pkg->title ); ?> ($<?php echo esc_html( number_format($pkg->price, 2) ); ?> / <?php echo esc_html( $pkg->cycle ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-section">
                    <label for="extra-select">One-time Extras (Optional)</label>
                    <select id="extra-select" name="extra_id">
                        <option value="">-- Select an Extra Service --</option>
                        <?php foreach ( $extras as $ext ) : ?>
                            <option value="<?php echo esc_attr( $ext->id ); ?>" data-price="<?php echo esc_attr( $ext->price ); ?>">
                                <?php echo esc_html( $ext->title ); ?> ($<?php echo esc_html( number_format($ext->price, 2) ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p>Hereinafter known as the “Services.”</p>
                
                <p><strong>PAYMENT AMOUNT.</strong> The Client agrees to pay the Service Provider the amount calculated below for the Services performed under this Agreement.</p>
                
                <div class="form-section" style="font-family: Arial, sans-serif;">
                    <label for="coupon-code">Coupon Code</label>
                    <input type="text" id="coupon-code" style="max-width: 280px;">
                    <button type="button" id="apply-coupon" class="button">Apply</button>
                    <span id="coupon-message" style="margin-left: 10px;"></span>
                </div>
                
                <div id="price-summary">
                    <div><span>Subtotal:</span> <span id="subtotal">$0.00</span></div>
                    <div><span>Discount:</span> <span id="discount">-$0.00</span></div>
                    <div><span id="tax-label">Tax (<?php echo adcp_get_setting('tax_rate', 0); ?>%):</span> <span id="tax">+$0.00</span></div>
                    <hr>
                    <div><strong>Grand Total:</strong> <strong id="grand-total">$0.00</strong></div>
                </div>

                <p><strong>PAYMENT METHOD.</strong> The Client shall pay the full amount as an advance before the service starts.</p>
                
                <div class="form-section" style="font-family: Arial, sans-serif;">
                    <label>Select Payment Method *</label>
                    <label style="font-weight: normal;"><input type="radio" name="paidby" value="e_transfer" required> E-transfer</label>
                    <label style="font-weight: normal;"><input type="radio" name="paidby" value="paypal"> PayPal</label>
                    <label style="font-weight: normal;"><input type="radio" name="paidby" value="cash"> Cash</label>
                </div>

                <div id="manual-payment-instructions">
                    <p><strong>Payment Instructions:</strong></p>
                    <div class="payment-box">
                        <code id="transfer-text" style="font-size: 1.1em;"></code>
                        <button type="button" id="copy-pay" class="button">Copy</button>
                    </div>
                    <p style="margin-top: 15px;">
                        <label for="screenshot">Attach Payment Screenshot *</label>
                        <input id="screenshot" type="file" accept="image/*" required>
                        <span id="screenshot-status"></span>
                    </p>
                </div>
                
                <p><strong>ENTIRE AGREEMENT.</strong> This Agreement constitutes the entire agreement between the Parties to its subject matter and supersedes all prior agreements, representations, and understandings of the Parties. No supplement, modification, or amendment of this Agreement shall be binding unless executed in writing by the Parties.</p>
                
                <p>IN WITNESS WHEREOF, the Parties have agreed to the terms by submitting this form.</p>

                <div class="adcp-submit-wrapper">
                    <p>
                        <label><input type="checkbox" id="terms" required> <strong>I agree to the terms and conditions outlined in this Service Contract.</strong></label>
                    </p>
                    <p>
                        <button type="submit" id="submit-contract" class="button button-primary button-large">Submit Contract</button>
                        <span class="spinner"></span>
                        <span id="form-submit-message" style="margin-left: 10px;"></span>
                    </p>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renders the [adscampaignpro_render] shortcode.
     */
    public function render_campaign_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'adscampaignpro_render' );
        $campaign_id = absint( $atts['id'] );

        if ( !$campaign_id ) {
            return ''; // No ID, no render
        }

        $campaign = Adcp_Campaigns_DB::get_campaign( $campaign_id );

        // 1. Check if campaign exists, is active, and is of type 'embed'
        if ( !$campaign || $campaign->status !== 'active' || $campaign->type !== 'embed' ) {
            return '';
        }
        
        // 2. Check schedule
        $now = current_time( 'mysql' );
        if ( ( $campaign->start && $campaign->start > $now ) || ( $campaign->end && $campaign->end < $now ) ) {
            return '';
        }

        // 3. Decode config and get creative details
        $config = json_decode( $campaign->config, true );
        
        $creative_source = $config['creative_source'] ?? 'upload';
        $content_html = '';
        
        $cta_url = $config['cta_url'] ?? '';

        if ( $creative_source === 'html' && !empty($config['creative_html']) ) {
            // Use custom HTML directly
            // This is where the Embed Shortcode fix is confirmed.
            // wp_kses_post in class-adcp-admin.php saved it,
            // and do_shortcode here renders it.
            $content_html = do_shortcode( $config['creative_html'] );
        } else {
            // Build the default creative structure
            $creative_url  = $config['creative_url'] ?? '';
            $creative_type = $config['creative_type'] ?? '';
            $headline      = $config['headline'] ?? '';
            $subtext       = $config['subtext'] ?? '';
            $cta_label     = $config['cta_label'] ?? '';
            
            // This is the "Image Link" feature.
            if ( $cta_url ) {
                $content_html .= '<a href="' . esc_url($cta_url) . '" target="_blank" rel="noopener noreferrer" class="adcp-creative-img-link" style="text-decoration: none; color: inherit;">';
            }
            
            if ( $creative_url ) {
                if ( str_starts_with($creative_type, 'image/') ) {
                    $content_html .= '<img src="' . esc_url($creative_url) . '" alt="' . esc_attr($headline) . '" class="adcp-creative-img">';
                } else if ( str_starts_with($creative_type, 'video/') ) {
                    $content_html .= '<video controls muted src="' . esc_url($creative_url) . '" class="adcp-creative-video"></video>';
                }
            }

            if ( $cta_url && !$creative_url ) {
                $content_html .= '</a>';
            }

            if ( $headline ) {
                $content_html .= '<h3 class="adcp-headline">' . esc_html($headline) . '</h3>';
            }
            if ( $subtext ) {
                $content_html .= '<p class="adcp-subtext">' . esc_html($subtext) . '</p>';
            }
            
            if ( $cta_url && $creative_url ) {
                $content_html .= '</a>';
            }

            if ( $cta_label && $cta_url ) {
                $content_html .= '<a href="' . esc_url($cta_url) . '" target="_blank" class="adcp-cta">' . esc_html($cta_label) . '</a>';
            }
        }
        
        if ( empty($content_html) ) {
            return ''; // No creative content generated
        }

        // 4. Wrap the ad in the required tracker element
        $output = sprintf(
            '<div class="adcp-embed-wrapper adcp-type-embed" data-campaign-id="%d" data-freq-cap="%d">%s</div>',
            $campaign_id,
            $config['freq_cap'] ?? 1,
            $content_html
        );

        return $output;
    }
    
    /**
     * --- NEW FUNCTION ---
     * This is the "brains" for the Automatic Placement feature.
     * It queries for an ad and echoes the shortcode.
     */
    public static function display_ad_for_placement( $placement_key ) {
        global $wpdb;

        // 1. Find a single, random, active, scheduled 'embed' campaign
        //    that has this placement_key in its config['placements'] array.
        
        $now = current_time( 'mysql' );
        $table_name = $wpdb->prefix . 'adcp_campaigns';
        
        // The `LIKE` query on JSON is effective for "contains"
        $sql = $wpdb->prepare(
            "SELECT id FROM {$table_name}
             WHERE 
                type = 'embed' AND
                status = 'active' AND
                ( start IS NULL OR start <= %s ) AND
                ( end IS NULL OR end >= %s ) AND
                ( config LIKE %s )
             ORDER BY RAND()
             LIMIT 1",
            $now,
            $now,
            '%"' . $wpdb->esc_like( $placement_key ) . '"%' // Looks for "placement_key" in the JSON
        );

        $campaign_id = $wpdb->get_var( $sql );
        
        if ( $campaign_id ) {
            // 2. If we found one, render it using the existing shortcode function
            $ad_public = new Adcp_Public( ADCP_VERSION );
            echo $ad_public->render_campaign_shortcode( array( 'id' => $campaign_id ) );
        }
        
        // Note: This function is static, so it can be called from functions.php
        // as shown in the new Guide page.
    }
    
    /**
     * Handles the secure AJAX file upload from the contract form.
     */
    public function handle_proof_upload() {
        // 1. Check the security nonce
        check_ajax_referer( 'adcp_upload_proof_nonce', '_wpnonce' );

        // 2. Check if a file was sent
        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => 'No file was uploaded.' ), 400 );
        }

        // 3. Load WordPress upload-handling functions
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        $uploaded_file = $_FILES['file'];
        $upload_overrides = array( 'test_form' => false );
        
        // 4. Move the file to the WordPress uploads directory
        $move_file = wp_handle_upload( $uploaded_file, $upload_overrides );

        if ( $move_file && ! isset( $move_file['error'] ) ) {
            // 5. File is uploaded, now create an attachment post for it
            $attachment = array(
                'guid'           => $move_file['url'],
                'post_mime_type' => $move_file['type'],
                'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $move_file['file'] ) ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            $attach_id = wp_insert_attachment( $attachment, $move_file['file'] );
            
            // 6. Generate attachment metadata
            if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
            }
            $attach_data = wp_generate_attachment_metadata( $attach_id, $move_file['file'] );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            // 7. Send a success response back
            wp_send_json_success( array(
                'id'       => $attach_id,
                'filename' => basename( $move_file['file'] ),
                'url'      => $move_file['url']
            ) );

        } else {
            // Send an error response
            wp_send_json_error( array( 'message' => $move_file['error'] ), 500 );
        }
    }
}