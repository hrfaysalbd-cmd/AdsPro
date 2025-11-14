<?php
/**
 * The admin-specific functionality of the plugin.
 */
 
// Include our List Table classes
require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-packages-list-table.php';
require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-contracts-list-table.php';
require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-coupons-list-table.php';
require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-extras-list-table.php';
require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-transactions-list-table.php';
require_once ADCP_PLUGIN_DIR . 'includes/class-adcp-campaigns-list-table.php';


class Adcp_Admin {

	private $version;

	public function __construct( $version ) {
		$this->version = $version;
	}

	/**
	 * Initialize admin hooks
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

        // Form Handlers (Save)
        add_action( 'admin_post_adcp_save_campaign', array( $this, 'handle_save_campaign' ) );
        add_action( 'admin_post_adcp_save_package', array( $this, 'handle_save_package' ) );
        add_action( 'admin_post_adcp_save_coupon', array( $this, 'handle_save_coupon' ) );
        add_action( 'admin_post_adcp_save_extra', array( $this, 'handle_save_extra' ) );
        
        // Form Handlers (Delete)
        add_action( 'admin_post_adcp_delete_campaign', array( $this, 'handle_delete_campaign' ) );
        add_action( 'admin_post_adcp_delete_package', array( $this, 'handle_delete_package' ) );
        add_action( 'admin_post_adcp_delete_coupon', array( $this, 'handle_delete_coupon' ) );
        add_action( 'admin_post_adcp_delete_extra', array( $this, 'handle_delete_extra' ) );

        // Form Handlers (Contract)
        add_action( 'admin_post_adcp_approve_contract', array( $this, 'handle_approve_contract' ) );
        add_action( 'admin_post_adcp_reject_contract', array( $this, 'handle_reject_contract' ) );

        // Form Handlers (Tools)
        add_action( 'admin_post_adcp_send_email_report', array( $this, 'handle_send_email_report' ) );
        add_action( 'admin_post_adcp_recreate_tables', array( $this, 'handle_recreate_tables' ) );
        add_action( 'admin_post_adcp_debug_create_tables', array( $this, 'handle_debug_create_tables' ) );
        add_action( 'admin_post_adcp_fix_transactions', array( $this, 'handle_fix_transactions' ) );
        add_action( 'admin_post_adcp_flush_permalinks', array( $this, 'handle_flush_permalinks' ) );

        // --- FIX: REMOVED the 'add_meta_boxes' hook ---
        // We now add the meta box using the 'load-{$hook}' action in add_plugin_admin_menu()
	}
    
    /**
     * Display persistent admin notices.
     */
    public function display_admin_notices() {
        if ( ! isset( $_GET['page'] ) || ! str_starts_with( $_GET['page'], 'adcp-' ) ) {
            // Only show on our plugin pages
            if( ! isset( $_GET['page'] ) || $_GET['page'] !== 'adcp-main' ) {
                return;
            }
        }

        if ( isset( $_GET['adcp_notice'] ) ) {
            $notice = sanitize_key( $_GET['adcp_notice'] );
            $messages = array(
                'saved' => 'Item saved successfully.',
                'deleted' => 'Item deleted successfully.',
                'save_error' => 'An error occurred while saving.',
                'report_sent' => 'Analytics report has been sent to your admin email.',
                'report_failed' => 'Could not send the analytics report.',
                'approved' => 'Contract approved and client notified.',
                'rejected' => 'Contract has been rejected.',
                'tables_recreated' => 'Database tables have been successfully re-created. The errors should now be gone.',
                'tx_fixed' => 'Missing transaction records have been created.',
                'permalinks_flushed' => 'WordPress permalinks have been flushed. Your ad campaigns should now appear on the site.',
                'debug_run' => 'Database debug tool has been run. See results below.'
            );
            
            if ( isset( $messages[$notice] ) ) {
                $type = str_contains($notice, 'error') || str_contains($notice, 'failed') ? 'error' : 'success';
                printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $type, esc_html( $messages[$notice] ) );
            }
        }
    }
    
    /**
     * Enqueue scripts and styles for the admin area.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        
        $page = $_GET['page'] ?? '';

        // On Campaign Edit Page
        if ( 'adcp-campaign-edit' === $page ) {
            wp_enqueue_media(); 
            wp_enqueue_script(
                'adcp-admin-campaign',
                ADCP_PLUGIN_URL . 'admin/js/admin-campaign.js',
                array( 'jquery' ), 
                $this->version . '.9', // Cache bust
                true
            );
            // Pass public CSS URL to JS for iframe preview
            wp_localize_script('adcp-admin-campaign', 'adcpCampaign', [
                'public_css_url' => ADCP_PLUGIN_URL . 'public/css/tracker.css'
            ]);
            
            // Enqueue public tracker.css so previews look correct
            wp_enqueue_style(
                'adcp-public-tracker',
                ADCP_PLUGIN_URL . 'public/css/tracker.css',
                array(),
                $this->version
            );
        }

        // On Analytics (main) page
        if ( 'adcp-main' === $page || 'adcp-analytics' === $page ) {
            // Enqueue Chart.js from CDN
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(), '3.9.1', true
            );
            
            // Enqueue our analytics script
            wp_enqueue_script(
                'adcp-admin-analytics',
                ADCP_PLUGIN_URL . 'admin/js/admin-analytics.js',
                array( 'jquery', 'chart-js' ), $this->version, true
            );
            
            // Pass API nonce and URL
            wp_localize_script( 'adcp-admin-analytics', 'adcpAnalytics', array(
                'rest_url' => esc_url_raw( rest_url( 'adcp/v1/analytics' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' )
            ));
        }
    }

	/**
	 * Register all menu and submenu pages.
	 */
	public function add_plugin_admin_menu() {

		// Main Menu Page
		add_menu_page(
			'AdsCampaignPro',
			'AdsCampaignPro',
			'manage_options',
			'adcp-main',
			array( $this, 'display_analytics_page' ), 
			'dashicons-chart-area',
			25
		);
        
		// Submenu: All Campaigns (List)
		add_submenu_page(
			'adcp-main', 'All Campaigns', 'All Campaigns', 'manage_options',
			'adcp-campaigns', array( $this, 'display_campaigns_page' )
		);
        
		// --- FIX: Capture the page hook for the edit page ---
		$campaign_edit_hook = add_submenu_page(
			null, 'Add/Edit Campaign', 'Add New', 'manage_options',
			'adcp-campaign-edit', array( $this, 'display_campaign_edit_page' )
		);
        
        // --- FIX: Use the 'load-{$hook}' action to add the meta box ---
        // This fires on the specific admin page *before* it renders.
        add_action( 'load-' . $campaign_edit_hook, array( $this, 'add_placements_meta_box' ) );
        
        // Submenu: Packages
		add_submenu_page(
			'adcp-main', 'Packages', 'Packages', 'manage_options',
			'adcp-packages', array( $this, 'display_packages_page' )
		);
        // Submenu: Add/Edit Package (Hidden)
		add_submenu_page(
			null, 'Add/Edit Package', null, 'manage_options',
			'adcp-package-edit', array( $this, 'display_package_edit_page' )
		);

        // Submenu: Coupons
		add_submenu_page(
            'adcp-main', 'Coupons', 'Coupons', 'manage_options',
            'adcp-coupons', array( $this, 'display_coupons_page' )
        );
        // Submenu: Add/Edit Coupon (Hidden)
		add_submenu_page(
            null, 'Add/Edit Coupon', null, 'manage_options',
            'adcp-coupon-edit', array( $this, 'display_coupon_edit_page' )
        );

        // Submenu: Extra Packages
		add_submenu_page(
            'adcp-main', 'Extra Packages', 'Extra Packages', 'manage_options',
            'adcp-extras', array( $this, 'display_extras_page' )
        );
        // Submenu: Add/Edit Extra (Hidden)
		add_submenu_page(
            null, 'Add/Edit Extra', null, 'manage_options',
            'adcp-extra-edit', array( $this, 'display_extra_edit_page' )
        );

        // Submenu: Contracts
		add_submenu_page(
            'adcp-main', 'Contracts', 'Contracts', 'manage_options',
            'adcp-contracts', array( $this, 'display_contracts_page' )
        );
        // Submenu: View Contract (Hidden)
		add_submenu_page(
            null, 'View Contract', null, 'manage_options',
            'adcp-contract-view', array( $this, 'display_contract_view_page' )
        );

        // Submenu: Analytics (This is the main page, so we add it again to control order)
        add_submenu_page(
			'adcp-main', 'Analytics', 'Analytics', 'manage_options',
			'adcp-main' // Use parent slug
		);

        // Submenu: Payments
		add_submenu_page(
			'adcp-main', 'Payments', 'Payments', 'manage_options',
			'adcp-payments', array( $this, 'display_payments_page' )
		);

        // --- NEW: Add the Guide/Instructions Page ---
        add_submenu_page(
			'adcp-main', // Parent slug
			'Guide / Ads Setup', // Page title
			'Guide / Setup', // Menu title
			'manage_options', // Capability
			'adcp-guide', // Menu slug
			array( $this, 'display_guide_page' ) // Callback function
		);

        // Submenu: Tools
		add_submenu_page(
			'adcp-main', 'Tools', 'Tools', 'manage_options',
			'adcp-tools', array( $this, 'display_tools_page' )
		);
        
        // Submenu: Settings
		add_submenu_page(
			'adcp-main', 'Settings', 'Settings', 'manage_options',
			'adcp-settings', array( $this, 'display_settings_page' )
		);
	}

    // --- NEW PLACEMENT META BOX FUNCTIONS (START) ---

    /**
     * Registers the new "Placements" meta box.
     * This function is now called by the 'load-{$hook}' action.
     */
    public function add_placements_meta_box() {
        add_meta_box(
            'adcp_placements_meta_box',       // ID
            'Ad Placements (for Embed Ads)',  // Title
            array( $this, 'render_placements_meta_box' ), // Callback
            'campaign',                       // The custom screen ID from do_meta_boxes()
            'side',                           // Context
            'default'                         // Priority
        );
    }

    /**
     * Renders the HTML for the "Placements" meta box.
     */
    public function render_placements_meta_box( $post ) {
        // $post is passed, but it's the $campaign object from display_campaign_edit_page
        $campaign_id = $post->ID ?? 0;
        $config = array();
        if ( $campaign_id ) {
             $config_json = get_post_meta( $campaign_id, 'config', true );
             $config = json_decode( $config_json, true );
        }
        if ( ! is_array( $config ) ) {
            $config = array();
        }
        
        // Get saved placements, default to empty array
        $saved_placements = $config['placements'] ?? [];

        // Define all available placements with their guides
        $all_placements = [
            'before_header' => 'Before Header <small>Top of the <code>&lt;body&gt;</code> tag.</small>',
            'before_footer' => 'Before Footer <small>Bottom of the <code>&lt;body&gt;</code> tag.</small>',
            'home_hero' => 'Home Hero <small>Main hero area.</small>',
            'home_inner_section' => 'Home Page Inner Section <small>Between content sections.</small>',
            'blog_sidebar' => 'Blog Post Sidebar <small>300&times;250 recommended.</small>',
            'blog_post_inner' => 'Blog Post Inner Section <small>Inside post content.</small>',
            'blog_page_inner' => 'Blog Page Inner Section <small>Between posts on blog page.</small>',
            'event_hero' => 'Event Hero <small>1200&times;350 recommended.</small>',
            'mobile_banner' => 'Mobile Banner <small>320&times;50 recommended.</small>',
        ];

        // Security nonce. This is CRITICAL for saving.
        // This is the nonce that was missing, causing your save error.
        wp_nonce_field( 'adcp_save_placements_nonce', 'adcp_placements_nonce' );

        echo '<style>.adcp-placement-list div { margin-bottom: 8px; } .adcp-placement-list small { display: block; margin-left: 21px; color: #666; }</style>';
        
        echo '<p>Select locations to automatically inject this ad (<b>Embed types only</b>). Overlays (Popup, etc.) use URL Targeting.</p>';

        // "Select All" option
        echo '<p><input type="checkbox" id="adcp_placement_select_all" /> <strong><label for="adcp_placement_select_all">Select All</label></strong></p>';
        echo '<hr>';
        
        // List all checkboxes
        echo '<div class="adcp-placement-list" style="max-height: 250px; overflow-y: auto;">';

        foreach ( $all_placements as $key => $label_with_guide ) {
            $checked = in_array( $key, $saved_placements ) ? 'checked' : '';
            
            printf(
                '<div><input type="checkbox" class="adcp-placement-checkbox" name="config[placements][]" value="%s" id="placement_%s" %s /> <label for="placement_%s">%s</label></div>',
                esc_attr( $key ),
                esc_attr( $key ),
                $checked,
                esc_attr( $key ),
                $label_with_guide
            );
        }
        echo '</div>'; // .adcp-placement-list
    }
    // --- NEW PLACEMENT META BOX FUNCTIONS (END) ---


	/**
	 * Renders the 'All Campaigns' page.
	 */
	public function display_campaigns_page() {
        $add_new_url = admin_url('admin.php?page=adcp-campaign-edit');
        $list_table = new Adcp_Campaigns_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">All Campaigns</h1>
            <a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
	}
    
    /**
	 * Renders the 'Add/Edit Campaign' page.
	 */
	public function display_campaign_edit_page() {
        global $wpdb;
        
        $campaign_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $is_editing = $campaign_id > 0;
        $campaign = null;
        $config = array();

        if ( $is_editing ) {
            // This is needed for do_meta_boxes
            $campaign = Adcp_Campaigns_DB::get_campaign( $campaign_id );
            if ( $campaign ) {
                $config = json_decode( $campaign->config, true );
            }
        } else {
             // Create a dummy object for the meta box to prevent errors
             $campaign = new stdClass();
             $campaign->ID = 0;
        }
        
        $get_val = function( $key, $default = '' ) use ( $campaign ) {
            return $campaign && isset( $campaign->$key ) ? $campaign->$key : $default;
        };
        $get_conf = function( $key, $default = '' ) use ( $config ) {
            return is_array($config) && isset( $config[$key] ) ? $config[$key] : $default;
        };

        $page_title = $is_editing ? 'Edit Campaign' : 'Add New Campaign';
        
        $approved_contracts = $wpdb->get_results(
            "SELECT id, client_name, client_email FROM {$wpdb->prefix}adcp_contracts WHERE status = 'approved' ORDER BY client_name ASC"
        );
        
        $current_type = $get_val('type', 'popup');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            
            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                
                <input type="hidden" name="action" value="adcp_save_campaign">
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">
                <?php wp_nonce_field( 'adcp_save_campaign_nonce', 'adcp_nonce' ); ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">

                        <div id="post-body-content">
                            <div class="postbox">
                                <h2 class="hndle"><span>Main Configuration</span></h2>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr valign="top">
                                            <th scope="row"><label for="adcp_name">Campaign Name</label></th>
                                            <td><input type="text" id="adcp_name" name="name" value="<?php echo esc_attr( $get_val('name') ); ?>" class="regular-text" required></td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row"><label for="adcp_contract_id">Assign to Client</label></th>
                                            <td>
                                                <select id="adcp_contract_id" name="contract_id">
                                                    <option value="0">-- None (Admin Campaign) --</option>
                                                    <?php foreach ( $approved_contracts as $contract ) : ?>
                                                        <option value="<?php echo esc_attr( $contract->id ); ?>" <?php selected( $get_val('contract_id'), $contract->id ); ?>>
                                                            <?php echo esc_html( $contract->client_name ); ?> (<?php echo esc_html( $contract->client_email ); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="description">Assign this campaign to a client contract. This will allow them to see its analytics.</p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="postbox">
                                <h2 class="hndle"><span>Creative</span></h2>
                                <div class="inside">
                                    <p>
                                        <label><input type="radio" name="creative_source" value="upload" <?php checked( $get_conf('creative_source', 'upload'), 'upload' ); ?>> Upload</label>
                                        <label><input type="radio" name="creative_source" value="html" <?php checked( $get_conf('creative_source'), 'html' ); ?>> HTML</label>
                                    </p>
                                    
                                    <div id="adcp-creative-upload-wrapper">
                                        <table class="form-table">
                                            <tr valign="top">
                                                <th scope="row"><label for="adcp_creative_url">Creative File</label></th>
                                                <td>
                                                    <input type="text" id="adcp_creative_url" name="config[creative_url]" value="<?php echo esc_attr( $get_conf('creative_url') ); ?>" class="regular-text">
                                                    <input type="hidden" id="adcp_creative_type" name="config[creative_type]" value="<?php echo esc_attr( $get_conf('creative_type') ); ?>">
                                                    <button type="button" class="button" id="adcp_upload_creative_button">Upload/Select Creative</button>
                                                    <div id="adcp_creative_preview" style="margin-top:15px;">
                                                        <?php 
                                                            $preview_url = $get_conf('creative_url');
                                                            $preview_type = $get_conf('creative_type');
                                                            if ($preview_url) {
                                                                if (str_starts_with($preview_type, 'image/')) {
                                                                    echo '<img src="' . esc_url($preview_url) . '" style="max-width:100%; height:auto; max-height: 200px;">';
                                                                } elseif (str_starts_with($preview_type, 'video/')) {
                                                                    echo '<video controls src="' . esc_url($preview_url) . '" style="max-width:100%; height:auto; max-height: 200px;"></video>';
                                                                } else {
                                                                    echo '<p>File selected: <code>' . esc_url($preview_url) . '</code></p>';
                                                                }
                                                            }
                                                        ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><label for="adcp_headline">Headline (max 60)</label></th>
                                                <td><input type="text" id="adcp_headline" name="config[headline]" value="<?php echo esc_attr( $get_conf('headline') ); ?>" class="regular-text" maxlength="60"></td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><label for="adcp_subtext">Subtext (max 150)</label></th>
                                                <td><textarea id="adcp_subtext" name="config[subtext]" rows="3" class="large-text" maxlength="150"><?php echo esc_textarea( $get_conf('subtext') ); ?></textarea></td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><label for="adcp_cta_label">CTA Button Label</label></th>
                                                <td><input type="text" id="adcp_cta_label" name="config[cta_label]" value="<?php echo esc_attr( $get_conf('cta_label') ); ?>" class="regular-text"></td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><label for="adcp_cta_url">CTA Button URL (Image Link)</label></th>
                                                <td>
                                                    <input type="url" id="adcp_cta_url" name="config[cta_url]" value="<?php echo esc_attr( $get_conf('cta_url') ); ?>" class="regular-text">
                                                    <p class="description">
                                                        <strong>This link is used for both:</strong><br>
                                                        1. The CTA button (if label is set).<br>
                                                        2. The main image (if using "Upload" source).<br>
                                                        All links open in a new tab.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div id="adcp-creative-html-wrapper" style="display:none;">
                                        <table class="form-table">
                                            <tr valign="top">
                                                <th scope="row"><label for="adcp_creative_html">Custom HTML/Shortcode</label></th>
                                                <td>
                                                    <textarea id="adcp_creative_html" name="config[creative_html]" rows="10" class="large-text"><?php echo esc_textarea( $get_conf('creative_html') ); ?></textarea>
                                                    <p class="description">
                                                        HTML will be sanitized on save, but shortcodes [like_this] will be preserved.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style="padding: 10px 12px; background: #f9f9f9; border-top: 1px solid #eee; margin: -12px;">
                                        <h4>Creative Guidelines</h4>
                                        <p style="margin-top: 0;"><strong>File Formats:</strong> JPG, PNG, WebP. Animated GIFs accepted (max 8s).</p>
                                        <p><strong>File Size:</strong> Preferred under 150 KB. Provide 2× retina assets.</p>
                                        <p><strong>Recommended Sizes:</strong></p>
                                        <ul style="list-style: disc; padding-left: 20px;">
                                            <li><strong>Leaderboard:</strong> 1200×300 (desktop) / 320×100 (mobile)</li>
                                            <li><strong>Sidebar:</strong> 300×250</li>
                                            <li><strong>Event Hero:</strong> 1200×350</li>
                                            <li><strong>Mobile:</strong> 320×50</li>
                                        </ul>
                                    </div>
                                    </div>
                            </div>
                            
                            <div class="postbox">
                                <h2 class="hndle"><span>Targeting & Schedule</span></h2>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr valign="top" class="adcp-type-overlay-show">
                                            <th scope="row"><label for="adcp_target_urls">URL Patterns (for Overlays)</label></th>
                                            <td>
                                                <textarea id="adcp_target_urls" name="config[target_urls]" rows="5" class="large-text"><?php echo esc_textarea( $get_conf('target_urls') ); ?></textarea>
                                                <p class="description">
                                                    One path pattern per line. e.g., <code>/blog/*</code> or <code>/shop/product-name</code>.
                                                    Leave empty to show on all pages.
                                                    <strong>Only used for Popup, Slide, and Scroll types.</strong>
                                                </p>
                                            </td>
                                        </tr>
                                        <tr valign="top" class="adcp-type-overlay-show">
                                            <th scope="row">Device Targeting (for Overlays)</th>
                                            <td>
                                                <label><input type="checkbox" name="config[device_desktop]" value="1" <?php checked( $get_conf('device_desktop', 1) ); ?>> Desktop</label><br>
                                                <label><input type="checkbox" name="config[device_mobile]" value="1" <?php checked( $get_conf('device_mobile', 1) ); ?>> Mobile</label><br>
                                                <label><input type="checkbox" name="config[device_tablet]" value="1" <?php checked( $get_conf('device_tablet', 1) ); ?>> Tablet</label>
                                                <p class="description"><strong>Only used for Popup, Slide, and Scroll types.</strong></p>
                                            </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row"><label for="adcp_start">Schedule Start</label></th>
                                            <td><input type="datetime-local" id="adcp_start" name="start" value="<?php echo esc_attr( str_replace(' ', 'T', $get_val('start') ) ); ?>"></td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row"><label for="adcp_end">Schedule End</label></th>
                                            <td><input type="datetime-local" id="adcp_end" name="end" value="<?php echo esc_attr( str_replace(' ', 'T', $get_val('end') ) ); ?>"></td>
                                        </tr>
                                        
                                        <tr valign="top" class="adcp-type-embed-show">
                                            <th scope="row">Shortcode (Manual Placement)</th>
                                            <td>
                                                <?php if ( $is_editing ) : ?>
                                                    <input type="text" readonly value="[adscampaignpro_render id=&quot;<?php echo $campaign_id; ?>&quot;]" class="regular-text" onclick="this.select();">
                                                    <p class="description">Use this shortcode to place the ad anywhere.</p>
                                                <?php else: ?>
                                                    <p class="description"><em>Save the campaign to generate the shortcode.</em></p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container">
                            <div class="postbox">
                                <h2 class="hndle"><span>Publish</span></h2>
                                <div class="inside">
                                    <div class="submitbox">
                                        <div id="major-publishing-actions">
                                            <div id="preview-action" style="margin-bottom: 10px;">
                                                <button type="button" id="adcp_preview_ad" class="button button-secondary button-large" style="width: 100%; text-align: center;">Preview Ad</button>
                                            </div>
                                            
                                            <div id="publishing-action">
                                                <span class="spinner"></span>
                                                <input type="submit" name="publish" id="publish" class="button button-primary button-large" value="Save Campaign">
                                            </div>
                                            <div class="clear"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php do_meta_boxes('campaign', 'side', $campaign); ?>

                            <div class="postbox">
                                <h2 class="hndle"><span>Campaign Details</span></h2>
                                <div class="inside">
                                    <p>
                                        <label for="adcp_status"><strong>Status</strong></label><br>
                                        <select id="adcp_status" name="status">
                                            <option value="draft" <?php selected( $get_val('status', 'draft'), 'draft' ); ?>>Draft</option>
                                            <option value="active" <?php selected( $get_val('status'), 'active' ); ?>>Active</option>
                                            <option value="paused" <?php selected( $get_val('status'), 'paused' ); ?>>Paused</option>
                                            <option value="ended" <?php selected( $get_val('status'), 'ended' ); ?>>Ended</option>
                                        </select>
                                    </p>
                                    <p>
                                        <label for="adcp_type"><strong>Type</strong></label><br>
                                        <select id="adcp_type" name="type">
                                            <option value="popup" <?php selected( $get_val('type', 'popup'), 'popup' ); ?>>Popup</option>
                                            <option value="slide" <?php selected( $get_val('type'), 'slide' ); ?>>Slide-in</option>
                                            <option value="scroll" <?php selected( $get_val('type'), 'scroll' ); ?>>Scroll Bar</option>
                                            <option value="embed" <?php selected( $get_val('type'), 'embed' ); ?>>Embed (Shortcode)</option>
                                        </select>
                                    </p>
                                    <p>
                                        <label for="adcp_priority"><strong>Priority (z-index)</strong></label><br>
                                        <input type="number" id="adcp_priority" name="priority" value="<?php echo esc_attr( $get_val('priority', 10) ); ?>" class="small-text">
                                        <p class="description">
                                            Stacking order for Overlays.
                                            <strong>10-100</strong> (normal),
                                            <strong>150+</strong> (above content),
                                            <strong>99999+</strong> (covers menus).
                                        </p>
                                    </p>
                                    <p class="adcp-type-overlay-show">
                                        <label for="adcp_frequency"><strong>Frequency Capping (for Overlays)</strong></label><br>
                                        Show 1 impression per <input type="number" name="config[freq_cap]" value="<?php echo esc_attr( $get_conf('freq_cap', 1) ); ?>" class="small-text"> day(s).
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
        <?php
    }

	/**
	 * Handles the saving of campaign data from the form.
	 */
    public function handle_save_campaign() {
        // Main nonce check
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_save_campaign_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('You do not have permission to save campaigns.');
        
        // --- THIS IS THE FIX ---
        // Check for the placements nonce *only if* the meta box was supposed to be there.
        // It's always submitted now, so this check is valid.
        if ( ! isset( $_POST['adcp_placements_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_placements_nonce'], 'adcp_save_placements_nonce' ) ) wp_die('Security check failed (placements). This error is now fixed.');
        
        $campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
        $is_editing = $campaign_id > 0;

        $data = array();
        $config_data = array();

        if ( $is_editing ) {
            $existing_campaign = Adcp_Campaigns_DB::get_campaign( $campaign_id );
            if ( $existing_campaign && $existing_campaign->config ) {
                $config_data = json_decode( $existing_campaign->config, true );
            }
        }

        // --- CORE DATA ---
        $data['name']     = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $data['status']   = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'draft';
        $data['type']     = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'popup';
        $data['priority'] = isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10;
        $data['contract_id'] = isset( $_POST['contract_id'] ) && $_POST['contract_id'] > 0 ? absint( $_POST['contract_id'] ) : null;
        $data['start']    = isset( $_POST['start'] ) && $_POST['start'] ? sanitize_text_field( $_POST['start'] ) : null;
        $data['end']      = isset( $_POST['end'] ) && $_POST['end'] ? sanitize_text_field( $_POST['end'] ) : null;

        if ( isset( $_POST['config'] ) && is_array( $_POST['config'] ) ) {
            $config_in = $_POST['config'];
            
            if ( isset( $_POST['creative_source'] ) ) {
                $config_data['creative_source'] = sanitize_key( $_POST['creative_source'] );
            }
            if ( isset( $config_in['creative_url'] ) ) {
                $config_data['creative_url'] = esc_url_raw( $config_in['creative_url'] );
            }
            if ( isset( $config_in['creative_type'] ) ) {
                $config_data['creative_type'] = sanitize_mime_type( $config_in['creative_type'] );
            }
            if ( isset( $config_in['headline'] ) ) {
                $config_data['headline'] = sanitize_text_field( $config_in['headline'] );
            }
            if ( isset( $config_in['subtext'] ) ) {
                $config_data['subtext'] = sanitize_textarea_field( $config_in['subtext'] );
            }
            if ( isset( $config_in['cta_label'] ) ) {
                $config_data['cta_label'] = sanitize_text_field( $config_in['cta_label'] );
            }
            if ( isset( $config_in['cta_url'] ) ) {
                $config_data['cta_url'] = esc_url_raw( $config_in['cta_url'] );
            }
            
            // --- FIX CONFIRMATION for Embed Shortcode ---
            // This line correctly uses wp_kses_post to preserve shortcodes and safe HTML.
            if ( isset( $config_in['creative_html'] ) ) {
                $config_data['creative_html'] = wp_kses_post( $config_in['creative_html'] );
            }
            
            // --- NEW: Save Placements ---
            // This reads the 'config[placements]' array from our new meta box.
            if ( $data['type'] === 'embed' ) {
                if ( isset( $config_in['placements'] ) && is_array( $config_in['placements'] ) ) {
                    $config_data['placements'] = array_map( 'sanitize_key', $config_in['placements'] );
                } else {
                    $config_data['placements'] = []; // Clear placements if none are checked
                }
            }
            // --- END NEW ---

            if ( $data['type'] !== 'embed' ) { 
                $config_data['device_desktop'] = isset( $config_in['device_desktop'] ) ? 1 : 0;
                $config_data['device_mobile']  = isset( $config_in['device_mobile'] ) ? 1 : 0;
                $config_data['device_tablet']  = isset( $config_in['device_tablet'] ) ? 1 : 0;
                $config_data['freq_cap'] = isset( $config_in['freq_cap'] ) ? absint( $config_in['freq_cap'] ) : 1;
                
                // Clear placements if type is switched to overlay
                $config_data['placements'] = []; 
            }
        }
        $data['config'] = wp_json_encode( $config_data );

        $result = false;
        if ( $is_editing ) {
            $result = Adcp_Campaigns_DB::update_campaign( $campaign_id, $data );
        } else {
            $result = Adcp_Campaigns_DB::insert_campaign( $data );
            if($result) $campaign_id = $result; // Get the new ID
        }
        
        $redirect_url = admin_url( 'admin.php?page=adcp-campaign-edit&id=' . $campaign_id );
        $redirect_url = add_query_arg( 'adcp_notice', ($result ? 'saved' : 'save_error'), $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
	 * Handles deleting a campaign.
	 */
    public function handle_delete_campaign() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) wp_die('No ID specified.');
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'adcp_delete_campaign_' . $id ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        Adcp_Campaigns_DB::delete_campaign( $id );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-campaigns' );
        $redirect_url = add_query_arg( 'adcp_notice', 'deleted', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

	/**
	 * Renders the 'Packages' list page.
	 */
	public function display_packages_page() {
        $add_new_url = admin_url('admin.php?page=adcp-package-edit');
        $list_table = new Adcp_Packages_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Packages</h1>
            <a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
	}

	/**
	 * Renders the 'Add/Edit Package' form.
	 */
	public function display_package_edit_page() {
        $package_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $is_editing = $package_id > 0;
        $package = null;
        $features = array();

        if ( $is_editing ) {
            $package = Adcp_Packages_DB::get_package( $package_id );
            if ( $package && !empty($package->features) ) {
                $decoded_features = json_decode( $package->features, true );
                if (is_array($decoded_features)) {
                    $features = $decoded_features;
                }
            }
        }
        
        $get_val = function( $key, $default = '' ) use ( $package ) {
            return $package && isset( $package->$key ) ? $package->$key : $default;
        };

        $page_title = $is_editing ? 'Edit Package' : 'Add New Package';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="adcp_save_package">
                <input type="hidden" name="package_id" value="<?php echo esc_attr( $package_id ); ?>">
                <?php wp_nonce_field( 'adcp_save_package_nonce', 'adcp_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="adcp_title">Package Title</label></th>
                        <td><input type="text" id="adcp_title" name="title" value="<?php echo esc_attr( $get_val('title') ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_description">Description</label></th>
                        <td><textarea id="adcp_description" name="description" rows="5" class="large-text"><?php echo esc_textarea( $get_val('description') ); ?></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_price">Price ($)</label></th>
                        <td><input type="number" id="adcp_price" name="price" value="<?php echo esc_attr( $get_val('price', '0.00') ); ?>" step="0.01" min="0" class="small-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Billing Cycle</th>
                        <td>
                            <label><input type="radio" name="cycle" value="monthly" <?php checked( $get_val('cycle', 'monthly'), 'monthly' ); ?>> Monthly</label><br>
                            <label><input type="radio" name="cycle" value="yearly" <?php checked( $get_val('cycle'), 'yearly' ); ?>> Yearly</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_features">Features</label></th>
                        <td>
                            <textarea id="adcp_features" name="features" rows="5" class="large-text"><?php echo esc_textarea( implode("\n", $features) ); ?></textarea>
                            <p class="description">One feature per line. (e.g., 100,000 Impressions, Sidebar Placement)</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Allow Coupons?</th>
                        <td>
                            <label><input type="checkbox" name="allow_coupon" value="1" <?php checked( $get_val('allow_coupon', 1) ); ?>> Allow coupons to be applied to this package.</label>
                        </td>
                    </tr>
                </table>
                <?php submit_button( $is_editing ? 'Update Package' : 'Save Package' ); ?>
            </form>
        </div>
        <?php
	}

	/**
	 * Handles the saving of package data from the form.
	 */
    public function handle_save_package() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_save_package_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        $package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
        $is_editing = $package_id > 0;

        $data = array();
        $data['title'] = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
        $data['description'] = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
        $data['price'] = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0.00;
        $data['cycle'] = isset( $_POST['cycle'] ) && $_POST['cycle'] === 'yearly' ? 'yearly' : 'monthly';
        $data['allow_coupon'] = isset( $_POST['allow_coupon'] ) ? 1 : 0;
        
        if ( isset( $_POST['features'] ) ) {
            $features_raw = trim( $_POST['features'] );
            $features = explode( "\n", $features_raw );
            $features = array_map( 'sanitize_text_field', $features );
            $features = array_map( 'trim', $features );
            $features = array_filter( $features );
            $data['features'] = wp_json_encode( $features );
        } else {
            $data['features'] = '[]';
        }

        $result = $is_editing ? Adcp_Packages_DB::update_package( $package_id, $data ) : Adcp_Packages_DB::insert_package( $data );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-packages' );
        $redirect_url = add_query_arg( 'adcp_notice', ($result ? 'saved' : 'save_error'), $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
	 * Handles deleting a package.
	 */
    public function handle_delete_package() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) wp_die('No ID specified.');
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'adcp_delete_package_' . $id ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        Adcp_Packages_DB::delete_package( $id );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-packages' );
        $redirect_url = add_query_arg( 'adcp_notice', 'deleted', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

	/**
	 * Renders the 'Coupons' list page.
	 */
	public function display_coupons_page() {
        $add_new_url = admin_url('admin.php?page=adcp-coupon-edit');
        $list_table = new Adcp_Coupons_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Coupons</h1>
            <a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
	}

	/**
	 * Renders the 'Add/Edit Coupon' form.
	 */
	public function display_coupon_edit_page() {
        $coupon_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $is_editing = $coupon_id > 0;
        $coupon = $is_editing ? Adcp_Coupons_DB::get_coupon( $coupon_id ) : null;
        
        $get_val = function( $key, $default = '' ) use ( $coupon ) {
            return $coupon && isset( $coupon->$key ) ? $coupon->$key : $default;
        };

        $page_title = $is_editing ? 'Edit Coupon' : 'Add New Coupon';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="adcp_save_coupon">
                <input type="hidden" name="coupon_id" value="<?php echo esc_attr( $coupon_id ); ?>">
                <?php wp_nonce_field( 'adcp_save_coupon_nonce', 'adcp_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="adcp_code">Coupon Code</label></th>
                        <td><input type="text" id="adcp_code" name="code" value="<?php echo esc_attr( $get_val('code') ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Coupon Type</th>
                        <td>
                            <label><input type="radio" name="type" value="percent" <?php checked( $get_val('type', 'percent'), 'percent' ); ?>> Percent (%)</label><br>
                            <label><input type="radio" name="type" value="fixed" <?php checked( $get_val('type'), 'fixed' ); ?>> Fixed Amount ($)</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_value">Value</label></th>
                        <td><input type="number" id="adcp_value" name="value" value="<?php echo esc_attr( $get_val('value', '10') ); ?>" step="0.01" min="0" class="small-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_max_uses">Max Uses</label></th>
                        <td><input type="number" id="adcp_max_uses" name="max_uses" value="<?php echo esc_attr( $get_val('max_uses', '0') ); ?>" step="1" min="0" class="small-text">
                        <p class="description">0 for unlimited uses.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_start_date">Start Date</label></th>
                        <td><input type="date" id="adcp_start_date" name="start_date" value="<?php echo esc_attr( $get_val('start_date') ); ?>"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_end_date">End Date</label></th>
                        <td><input type="date" id="adcp_end_date" name="end_date" value="<?php echo esc_attr( $get_val('end_date') ); ?>"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Status</th>
                        <td>
                            <label><input type="radio" name="status" value="active" <?php checked( $get_val('status', 'active'), 'active' ); ?>> Active</label><br>
                            <label><input type="radio" name="status" value="disabled" <?php checked( $get_val('status'), 'disabled' ); ?>> Disabled</label>
                        </td>
                    </tr>
                </table>
                <?php submit_button( $is_editing ? 'Update Coupon' : 'Save Coupon' ); ?>
            </form>
        </div>
        <?php
	}

	/**
	 * Handles the saving of coupon data.
	 */
    public function handle_save_coupon() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_save_coupon_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
        $is_editing = $coupon_id > 0;

        $data = array();
        $data['code'] = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
        $data['type'] = isset( $_POST['type'] ) && $_POST['type'] === 'fixed' ? 'fixed' : 'percent';
        $data['value'] = isset( $_POST['value'] ) ? floatval( $_POST['value'] ) : 0.00;
        $data['max_uses'] = isset( $_POST['max_uses'] ) ? absint( $_POST['max_uses'] ) : 0;
        $data['start_date'] = isset( $_POST['start_date'] ) && $_POST['start_date'] ? sanitize_text_field( $_POST['start_date'] ) : null;
        $data['end_date'] = isset( $_POST['end_date'] ) && $_POST['end_date'] ? sanitize_text_field( $_POST['end_date'] ) : null;
        $data['status'] = isset( $_POST['status'] ) && $_POST['status'] === 'disabled' ? 'disabled' : 'active';
        
        $result = $is_editing ? Adcp_Coupons_DB::update_coupon( $coupon_id, $data ) : Adcp_Coupons_DB::insert_coupon( $data );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-coupons' );
        $redirect_url = add_query_arg( 'adcp_notice', ($result ? 'saved' : 'save_error'), $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
	 * Handles deleting a coupon.
	 */
    public function handle_delete_coupon() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) wp_die('No ID specified.');
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'adcp_delete_coupon_' . $id ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        Adcp_Coupons_DB::delete_coupon( $id );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-coupons' );
        $redirect_url = add_query_arg( 'adcp_notice', 'deleted', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

	/**
	 * Renders the 'Extra Packages' list page.
	 */
	public function display_extras_page() {
        $add_new_url = admin_url('admin.php?page=adcp-extra-edit');
        $list_table = new Adcp_Extras_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Extra Packages</h1>
            <a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
	}

	/**
	 * Renders the 'Add/Edit Extra' form.
	 */
	public function display_extra_edit_page() {
        $extra_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $is_editing = $extra_id > 0;
        $extra = $is_editing ? Adcp_Extras_DB::get_extra( $extra_id ) : null;
        
        $get_val = function( $key, $default = '' ) use ( $extra ) {
            return $extra && isset( $extra->$key ) ? $extra->$key : $default;
        };

        $page_title = $is_editing ? 'Edit Extra Package' : 'Add New Extra Package';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="adcp_save_extra">
                <input type="hidden" name="extra_id" value="<?php echo esc_attr( $extra_id ); ?>">
                <?php wp_nonce_field( 'adcp_save_extra_nonce', 'adcp_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="adcp_title">Service Title</label></th>
                        <td><input type="text" id="adcp_title" name="title" value="<?php echo esc_attr( $get_val('title') ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_description">Description</label></th>
                        <td><textarea id="adcp_description" name="description" rows="5" class="large-text"><?php echo esc_textarea( $get_val('description') ); ?></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_price">Price ($)</label></th>
                        <td><input type="number" id="adcp_price" name="price" value="<?php echo esc_attr( $get_val('price', '0.00') ); ?>" step="0.01" min="0" class="small-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="adcp_delivery_time">Delivery Time (in days)</label></th>
                        <td><input type="number" id="adcp_delivery_time" name="delivery_time" value="<?php echo esc_attr( $get_val('delivery_time', '7') ); ?>" step="1" min="1" class="small-text"></td>
                    </tr>
                </table>
                <?php submit_button( $is_editing ? 'Update Service' : 'Save Service' ); ?>
            </form>
        </div>
        <?php
	}

	/**
	 * Handles the saving of extra package data.
	 */
    public function handle_save_extra() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_save_extra_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        $extra_id = isset( $_POST['extra_id'] ) ? absint( $_POST['extra_id'] ) : 0;
        $is_editing = $extra_id > 0;

        $data = array();
        $data['title'] = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
        $data['description'] = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
        $data['price'] = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0.00;
        $data['delivery_time'] = isset( $_POST['delivery_time'] ) ? absint( $_POST['delivery_time'] ) : 7;
        
        $result = $is_editing ? Adcp_Extras_DB::update_extra( $extra_id, $data ) : Adcp_Extras_DB::insert_extra( $data );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-extras' );
        $redirect_url = add_query_arg( 'adcp_notice', ($result ? 'saved' : 'save_error'), $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
	 * Handles deleting an extra.
	 */
    public function handle_delete_extra() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) wp_die('No ID specified.');
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'adcp_delete_extra_' . $id ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        Adcp_Extras_DB::delete_extra( $id );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-extras' );
        $redirect_url = add_query_arg( 'adcp_notice', 'deleted', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

	/**
	 * Renders the 'Contracts' list page.
	 */
	public function display_contracts_page() {
        $list_table = new Adcp_Contracts_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Contracts</h1>
            <hr class="wp-header-end">
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
	}
    
    /**
     * Renders the 'View Contract' details page.
     */
	public function display_contract_view_page() {
        global $wpdb;
        $contract_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( !$contract_id ) {
            echo '<div class="wrap"><h1>Invalid Contract</h1></div>';
            return;
        }

        $contract = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . $wpdb->prefix . "adcp_contracts WHERE id = %d", $contract_id
        ) );

        if ( !$contract ) {
            echo '<div class="wrap"><h1>Contract Not Found</h1></div>';
            return;
        }

        $contract_data = json_decode( $contract->data, true );
        $proof_image_url = !empty($contract_data['payment_proof_id']) ? 
            wp_get_attachment_image_url( $contract_data['payment_proof_id'], 'large' ) : null;
        ?>
        <div class="wrap">
            <h1>View Contract #<?php echo $contract->id; ?></h1>
            <p><strong>Status:</strong> <?php echo ucfirst($contract->status); ?></p>
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span>Client Details</span></h2>
                            <div class="inside">
                                <p><strong>Name:</strong> <?php echo esc_html($contract->client_name); ?></p>
                                <p><strong>Email:</strong> <?php echo esc_html($contract->client_email); ?></p>
                                <p><strong>Phone:</strong> <?php echo esc_html($contract->client_phone); ?></p>
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 class="hndle"><span>Payment Proof (Manual)</span></h2>
                            <div class="inside">
                                <?php if ( $proof_image_url ) : ?>
                                    <p><strong>Payment Method:</strong> <?php echo esc_html($contract_data['payment_method']); ?></p>
                                    <a href="<?php echo esc_url($proof_image_url); ?>" target="_blank">
                                        <img src="<?php echo esc_url($proof_image_url); ?>" style="max-width:100%; height:auto; border: 1px solid #ddd;">
                                    </a>
                                <?php else: ?>
                                    <p>No payment proof was uploaded (or payment not manual).</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><span>Actions</span></h2>
                            <div class="inside">
                                <p>Review the payment proof and contract details. Once verified, approve to activate the client's dashboard.</p>
                                <?php if ( $contract->status === 'pending' ) : ?>
                                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="adcp_approve_contract">
                                        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                                        <?php wp_nonce_field( 'adcp_contract_action_nonce', 'adcp_nonce' ); ?>
                                        <button type="submit" class="button button-primary button-large" style="width:100%;">Approve & Send Welcome</button>
                                    </form>
                                    <br>
                                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Are you sure you want to reject this?');">
                                        <input type="hidden" name="action" value="adcp_reject_contract">
                                        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                                        <?php wp_nonce_field( 'adcp_contract_action_nonce', 'adcp_nonce' ); ?>
                                        <button type="submit" class="button button-secondary button-large" style="width:100%; color: red; border-color: red;">Reject Contract</button>
                                    </form>
                                <?php elseif ( $contract->status === 'approved' ) : ?>
                                    <p><strong>Contract Approved.</strong></p>
                                    <p>Client Dashboard URL:</p>
                                    <input type="text" value="<?php echo esc_url( home_url('/adclient/' . $contract->tracking_token . '/') ); ?>" readonly style="width:100%;" onclick="this.select();">
                                <?php else: ?>
                                    <p><strong>Contract Rejected.</strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 class="hndle"><span>Order Details</span></h2>
                            <div class="inside">
                                <p><strong>Package:</strong> <?php echo esc_html($contract_data['package_title'] ?? 'N/A'); ?></p>
                                <p><strong>Extra:</strong> <?php echo esc_html($contract_data['extra_title'] ?? 'N/A'); ?></p>
                                <p><strong>Subtotal:</strong> $<?php echo number_format($contract_data['subtotal'], 2); ?></p>
                                <p><strong>Discount:</strong> -$<?php echo number_format($contract_data['discount'], 2); ?></p>
                                <p><strong>Tax:</strong> +$<?php echo number_format($contract_data['tax'], 2); ?></p>
                                <hr>
                                <p><strong>Grand Total: $<?php echo number_format($contract->grand_total, 2); ?></strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
	}
    
    /**
     * Handles the "Approve" button.
     */
    public function handle_approve_contract() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_contract_action_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        global $wpdb;
        $contract_id = absint( $_POST['contract_id'] );
        $token = 'adcp_' . wp_generate_uuid4();
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'adcp_contracts',
            array(
                'status'         => 'approved',
                'payment_status' => 'verified',
                'tracking_token' => $token
            ),
            array( 'id' => $contract_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $updated ) {
            $wpdb->update(
                $wpdb->prefix . 'adcp_transactions',
                array( 'status' => 'verified' ),
                array( 'contract_id' => $contract_id ),
                array( '%s' ),
                array( '%d' )
            );

            $contract = $wpdb->get_row( $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "adcp_contracts WHERE id = %d", $contract_id) );
            $client_email = $contract->client_email;
            $client_name = $contract->client_name;
            $dashboard_url = home_url('/adclient/' . $token . '/');
            
            $subject = 'Your Campaign is Approved!';
            $message = "Hi $client_name,\n\n";
            $message .= "Your contract (ID: $contract_id) has been approved and your campaigns are now being set up.\n\n";
            $message .= "You can view your private analytics dashboard here:\n$dashboard_url\n\n";
            $message .= "Thank you,\nThe " . get_bloginfo('name') . " Team";
            wp_mail( $client_email, $subject, $message );
        }
        
        wp_redirect( admin_url( 'admin.php?page=adcp-contract-view&id=' . $contract_id . '&adcp_notice=approved' ) );
        exit;
    }

    /**
     * Handles the "Reject" button.
     */
    public function handle_reject_contract() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_contract_action_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        global $wpdb;
        $contract_id = absint( $_POST['contract_id'] );
        
        $wpdb->update(
            $wpdb->prefix . 'adcp_contracts',
            array( 'status' => 'rejected' ),
            array( 'id' => $contract_id ),
            array( '%s' ),
            array( '%d' )
        );

        $wpdb->update(
            $wpdb->prefix . 'adcp_transactions',
            array( 'status' => 'failed' ),
            array( 'contract_id' => $contract_id ),
            array( '%s' ),
            array( '%d' )
        );
        
        wp_redirect( admin_url( 'admin.php?page=adcp-contract-view&id=' . $contract_id . '&adcp_notice=rejected' ) );
        exit;
    }

	/**
	 * Renders the 'Analytics' page.
	 */
	public function display_analytics_page() {
        ?>
        <div class="wrap" id="adcp-analytics-dashboard">
            <div style="display:flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 10px;">
                <h1 style="display: inline-block; margin-bottom: 0;">Analytics Dashboard</h1>
                <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
                    <input type="hidden" name="action" value="adcp_send_email_report">
                    <input type="hidden" id="email_report_days" name="days" value="30">
                    <?php wp_nonce_field( 'adcp_send_report_nonce', 'adcp_report_nonce' ); ?>
                    <?php submit_button( 'Email 30-Day Report', 'secondary', 'submit', false, array('id' => 'email-report-btn') ); ?>
                </form>
            </div>
            
            <div class="adcp-filters">
                <button class="button button-primary" data-days="30">30 Days</button>
                <button class="button" data-days="60">60 Days</button>
                <button class="button" data-days="90">90 Days</button>
            </div>

            <div id="adcp-loader" style="display:none; padding: 20px 0;">
                <span class="spinner is-active" style="float:none;"></span> Loading analytics...
            </div>

            <div id="adcp-analytics-content" style="display:none;">
                <div id="adcp-global-stats" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
                    <div class="stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; flex-basis: 200px; flex-grow: 1;">
                        <h4>Total Impressions</h4>
                        <strong style="font-size: 2em;" id="stat-impressions">0</strong>
                    </div>
                    <div class="stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; flex-basis: 200px; flex-grow: 1;">
                        <h4>Total Clicks</h4>
                        <strong style="font-size: 2em;" id="stat-clicks">0</strong>
                    </div>
                    <div class="stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; flex-basis: 200px; flex-grow: 1;">
                        <h4>Unique Users</h4>
                        <strong style="font-size: 2em;" id="stat-uniques">0</strong>
                    </div>
                    <div class="stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; flex-basis: 200px; flex-grow: 1;">
                        <h4>Avg. CTR</h4>
                        <strong style="font-size: 2em;" id="stat-ctr">0%</strong>
                    </div>
                </div>
                <div class="chart-wrapper" style="background: #fff; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                    <canvas id="adcp-main-chart" style="max-height: 400px;"></canvas>
                </div>
                <div class="top-campaigns-wrapper" style="background: #fff; padding: 20px; border: 1px solid #ddd;">
                    <h3>Top Performing Campaigns</h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Campaign Name</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                            </tr>
                        </thead>
                        <tbody id="adcp-top-campaigns-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
	}
    
    /**
     * Handles the "Email Report" button submission.
     */
    public function handle_send_email_report() {
        if ( ! isset( $_POST['adcp_report_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_report_nonce'], 'adcp_send_report_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('You do not have permission to send reports.');
        
        $days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;
        $to_email = get_option( 'admin_email' );
        
        $sent = Adcp_Email_Reporter::send_report( $to_email, $days );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-main' );
        $redirect_url = add_query_arg( 'adcp_notice', ($sent ? 'report_sent' : 'report_failed'), $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

	/**
	 * Renders the 'Payments' transaction log page.
	 */
	public function display_payments_page() {
        $list_table = new Adcp_Transactions_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Payments</h1>
            <p>This is a log of all transactions recorded by the system.</p>
            <hr class="wp-header-end">
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
	}

    /**
     * --- NEW FUNCTION ---
     * Renders the 'Guide / Ads Setup' page.
     */
    public function display_guide_page() {
        ?>
        <div class="wrap">
            <h1>Guide / Ads Setup Instructions</h1>
            <p>There are two main ways to display ads: <strong>Manual Placement</strong> (with a shortcode) and <strong>Automatic Placement</strong> (with theme hooks).</p>
            <p>Both methods only work for campaigns with the type <strong>"Embed"</strong>.</p>
            <hr>
            
            <div class="postbox">
                <h2 class="hndle"><span>Method 1: Manual Placement (Shortcode)</span></h2>
                <div class="inside">
                    <p>This is the simplest way to place an ad in a specific location.</p>
                    <ol>
                        <li>Go to <a href="<?php echo admin_url('admin.php?page=adcp-campaigns'); ?>">All Campaigns</a> and create or edit a campaign.</li>
                        <li>Set the campaign <b>Type</b> to <b>"Embed (Shortcode)"</b>.</li>
                        <li>Configure your creative (Upload or HTML) and save the campaign.</li>
                        <li>On the edit page, you will now see the <b>Shortcode</b>. Copy it.</li>
                        <pre style="font-size: 1.2em; background: #f0f0f0; padding: 10px;">[adscampaignpro_render id="123"]</pre>
                        <li>Paste this shortcode anywhere on your site: in a post, a page, or a Text Widget.</li>
                    </ol>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span>Method 2: Automatic Placement (Theme Hooks)</span></h2>
                <div class="inside">
                    <p>This method lets you "tag" a campaign to show in a general location (like "Blog Post Sidebar"). The plugin will automatically find an active ad for that location and display it. This is great for rotating ads in one location.</p>
                    
                    <h3>Step 1: Assign a Placement to your Campaign</h3>
                    <ol>
                        <li>Go to <a href="<?php echo admin_url('admin.php?page=adcp-campaigns'); ?>">All Campaigns</a> and edit an <b>"Embed"</b> type campaign.</li>
                        <li>On the right side, find the <b>"Ad Placements (for Embed Ads)"</b> box.</li>
                        <li>Check the location where you want this ad to be eligible to show (e.g., "Blog Post Sidebar").</li>
                        <li>You can assign the same placement to multiple ads. The plugin will pick one at random to display.</li>
                        <li>Save the campaign.</li>
                    </ol>

                    <h3>Step 2: Add the Hook to your Theme (One-Time Setup)</h3>
                    <p>You only need to do this once for each location. Add the correct PHP snippet to your theme's files.</p>
                    
                    <style>
                        .adcp-guide-table { width: 100%; border-collapse: collapse; }
                        .adcp-guide-table th, .adcp-guide-table td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
                        .adcp-guide-table th { background: #f9f9f9; }
                        .adcp-guide-table code { background: #eee; padding: 2px 5px; font-size: 0.9em; }
                        .adcp-guide-table pre { background: #333; color: #fff; padding: 10px; border-radius: 4px; font-size: 14px; }
                    </style>

                    <table class="adcp-guide-table">
                        <thead>
                            <tr>
                                <th>Placement Key</th>
                                <th>Theme File</th>
                                <th>Code to Add</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><b>Before Header</b><br>(<code>before_header</code>)</td>
                                <td><code>header.php</code></td>
                                <td><pre>&lt;?php do_action('adcp_placement_before_header'); ?&gt;</pre>
                                    <small>Place this right after the opening <code>&lt;body&gt;</code> tag.</small>
                                </td>
                            </tr>
                            <tr>
                                <td><b>Before Footer</b><br>(<code>before_footer</code>)</td>
                                <td><code>footer.php</code></td>
                                <td><pre>&lt;?php do_action('adcp_placement_before_footer'); ?&gt;</pre>
                                    <small>Place this right before the closing <code>&lt;/body&gt;</code> tag.</small>
                                </td>
                            </tr>
                            <tr>
                                <td><b>Blog Post Sidebar</b><br>(<code>blog_sidebar</code>)</td>
                                <td><code>sidebar.php</code> (or in a Text Widget)</td>
                                <td><pre>&lt;?php do_action('adcp_placement_blog_sidebar'); ?&gt;</pre>
                                    <small>Place this inside your sidebar container. Alternatively, add a Text Widget and paste in the <b>Manual Shortcode</b>.</small>
                                </td>
                            </tr>
                            <tr>
                                <td><b>Home Hero</b><br>(<code>home_hero</code>)</td>
                                <td><code>front-page.php</code> or <code>home.php</code></td>
                                <td><pre>&lt;?php do_action('adcp_placement_home_hero'); ?&gt;</pre>
                                    <small>Place this where your theme's hero section is rendered.</small>
                                </td>
                            </tr>
                            <tr>
                                <td><b>Blog Post Inner Section</b><br>(<code>blog_post_inner</code>)</td>
                                <td><code>functions.php</code></td>
                                <td>
                                    <p>This one is more complex and uses a filter. Add this *entire block* to your theme's <code>functions.php</code> file:</p>
                                    <pre>
add_filter( 'the_content', 'adcp_inject_ad_into_content' );
function adcp_inject_ad_into_content( $content ) {
    if ( is_single() && ! is_admin() ) {
        // Run the action to display the ad
        ob_start();
        do_action( 'adcp_placement_blog_post_inner' );
        $ad_markup = ob_get_clean();

        if ( ! empty( $ad_markup ) ) {
            // Find the 3rd paragraph
            $paragraphs = explode( '</p>', $content );
            $insert_after = 3;
            
            if ( count( $paragraphs ) > $insert_after ) {
                $paragraphs[$insert_after] .= $ad_markup;
                $content = implode( '</p>', $paragraphs );
            }
        }
    }
    return $content;
}</pre>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <h3>Step 3: Connect the Hooks to the Plugin (One-Time Setup)</h3>
                    <p>The code in Step 2 creates "empty" hooks. Now, add this code to your theme's <b><code>functions.php</code></b> file to tell AdsCampaignPro what to do when it sees those hooks.</p>
                    <pre style="font-size: 1.2em; background: #333; color: #fff; padding: 10px; border-radius: 4px;">
// --- AdsCampaignPro Custom Hooks ---

// Before Header
add_action( 'adcp_placement_before_header', function() {
    if ( class_exists('Adcp_Public') ) {
        Adcp_Public::display_ad_for_placement('before_header');
    }
} );

// Before Footer
add_action( 'adcp_placement_before_footer', function() {
    if ( class_exists('Adcp_Public') ) {
        Adcp_Public::display_ad_for_placement('before_footer');
    }
} );

// Blog Sidebar
add_action( 'adcp_placement_blog_sidebar', function() {
    if ( class_exists('Adcp_Public') ) {
        Adcp_Public::display_ad_for_placement('blog_sidebar');
    }
} );

// Home Hero
add_action( 'adcp_placement_home_hero', function() {
    if ( class_exists('Adcp_Public') ) {
        Adcp_Public::display_ad_for_placement('home_hero');
    }
} );

// Blog Post Inner
add_action( 'adcp_placement_blog_post_inner', function() {
    if ( class_exists('Adcp_Public') ) {
        Adcp_Public::display_ad_for_placement('blog_post_inner');
    }
} );

// --- End AdsCampaignPro Hooks ---
</pre>
                    <p>By adding these, you can now simply check a box in the "Ad Placements" meta box, and the ad will automatically appear in the location you defined.</p>
                </div>
            </div>
            
        </div>
        <?php
    }

	/**
	 * Renders the 'Tools' page.
	 */
	public function display_tools_page() {
        ?>
        <div class="wrap">
            <h1>Tools</h1>

            <div class="postbox">
                <h2 class="hndle"><span>Ad Display Repair Tool (START HERE)</span></h2>
                <div class="inside">
                    <p>
                        <strong>CRITICAL: If your 'Active' campaigns (Popups, Slides, etc.) are not appearing on your website, your first step is to click this button.</strong>
                        This tool flushes your website's permalinks, which forces WordPress to recognize the plugin's API endpoints. This is a very common fix.
                    </p>
                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="adcp_flush_permalinks">
                        <?php wp_nonce_field( 'adcp_flush_permalinks_nonce', 'adcp_nonce' ); ?>
                        <button type="submit" class="button button-primary">Flush Permalinks (Fixes Ads Not Showing)</button>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span>Data Repair Tools</span></h2>
                <div class="inside">
                    <p>
                        If your "Payments" page is missing transactions for old, approved contracts, click this button.
                        It will scan all approved contracts and create missing payment records.
                    </p>
                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="adcp_fix_transactions">
                        <?php wp_nonce_field( 'adcp_fix_transactions_nonce', 'adcp_nonce' ); ?>
                        <button type="submit" class="button button-secondary">Fix Missing Transactions</button>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span>Database Tools</span></h2>
                <div class="inside">
                    <p>
                        Warning: If you are seeing "Table doesn't exist" errors, your database tables may not have been created correctly.
                        Clicking this button will attempt to re-create all necessary tables using the standard WordPress <code>dbDelta</code> function. This is safe to run multiple times.
                    </p>
                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="adcp_recreate_tables">
                        <?php wp_nonce_field( 'adcp_recreate_tables_nonce', 'adcp_nonce' ); ?>
                        <button type="submit" class="button button-secondary" style="color: red; border-color: red;">Force Re-create Database Tables</button>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span>Database Debug Tool</span></h2>
                <div class="inside">
                    <p>
                       If the tool above fails, this tool will run the raw SQL queries directly, bypassing the <code>dbDelta</code> function.
                       It will provide a detailed success/error report.
                    </p>
                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="adcp_debug_create_tables">
                        <?php wp_nonce_field( 'adcp_debug_create_tables_nonce', 'adcp_nonce' ); ?>
                        <button type="submit" class="button button-primary">Run Direct Database Debug</button>
                    </form>

                    <?php
                // Display the debug results if they exist
                $debug_results = get_transient( 'adcp_debug_results' );
                if ( $debug_results ) {
                        echo '<h3>Database Debug Results:</h3>';
                        echo '<ul style="background: #f9f9f9; border: 1px solid #ccc; padding: 10px 30px; font-family: monospace;">';
                        foreach ( $debug_results as $result ) {
                            $color = $result['success'] ? 'green' : 'red';
                            $message = esc_html($result['message']);
                            
                            printf(
                                '<li><strong>%s</strong> <span style="color:%s;">[%s]</span></li>',
                                esc_html( $result['table'] ),
                                $color,
                                $message
                            );
                        }
                        echo '</ul>';
                        // Delete the transient so it only shows once
                    delete_transient( 'adcp_debug_results' );
                }
                ?>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle"><span>Tracking Code Generator</span></h2>
                <div class="inside">
                    <p>
                        This is the **generic tracking code** for this website. 
                        It will display all 'Active' campaigns that match this site's URLs.
                    </p>
                    <p>
                        <strong>Note:</strong> Your approved clients receive a *separate, unique* tracking token 
                        (<code>data-client="..."</code>) on their dashboard. 
                        That token is used to attribute analytics to them.
                    </p>
                    
                    <textarea readonly="readonly" style="width: 100%; height: 120px; font-family: monospace; background: #f9f9f9;">
&lt;!-- AdsCampaignPro Tracker --&gt;
&lt;script src="<?php echo esc_url( ADCP_PLUGIN_URL . 'public/js/tracker.js' ); ?>" async defer&gt;&lt;/script&gt;
                    </textarea>
                </div>
            </div>
            <div class="postbox">
                <h2 class="hndle"><span>Import / Export</span></h2>
                <div class="inside">
                    <p>Future: Tools for importing/exporting campaigns and settings will go here.</p>
                </div>
            </div>
        </div>
        <?php
	}

    /**
     * Handler for the "Re-create Database Tables" button.
     */
    public function handle_recreate_tables() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_recreate_tables_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        Adcp_Activator::create_database_tables();
        
        $redirect_url = admin_url( 'admin.php?page=adcp-tools' );
        $redirect_url = add_query_arg( 'adcp_notice', 'tables_recreated', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }
    
    /**
     * Handler for the "Run Direct Database Debug" button.
     */
    public function handle_debug_create_tables() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_debug_create_tables_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        $results = Adcp_Activator::debug_create_tables();
        set_transient( 'adcp_debug_results', $results, 60 );
        
        $redirect_url = admin_url( 'admin.php?page=adcp-tools' );
        $redirect_url = add_query_arg( 'adcp_notice', 'debug_run', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Finds approved contracts without transactions and creates them.
     */
    public function handle_fix_transactions() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_fix_transactions_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        global $wpdb;
        $contracts_table = $wpdb->prefix . 'adcp_contracts';
        $transactions_table = $wpdb->prefix . 'adcp_transactions';

        // Find all approved contracts that DO NOT have a matching transaction
        $missing_tx_contracts = $wpdb->get_results( "
            SELECT c.* FROM {$contracts_table} c
            LEFT JOIN {$transactions_table} t ON c.id = t.contract_id
            WHERE c.status = 'approved'
            AND t.id IS NULL
        " );

        $fixed_count = 0;
        foreach ( $missing_tx_contracts as $contract ) {
            $contract_data = json_decode( $contract->data, true );
            
            $wpdb->insert(
                $transactions_table,
                array(
                    'contract_id' => $contract->id,
                    'provider'    => $contract_data['payment_method'] ?? 'manual',
                    'txn_id'      => 'manual_' . $contract->id,
                    'amount'      => $contract->grand_total,
                    'status'      => 'verified', // Assume approved means verified
                    'meta'        => wp_json_encode(array('proof_id' => $contract_data['payment_proof_id'] ?? 0))
                ),
                array( '%d', '%s', '%s', '%f', '%s', '%s' )
            );
            $fixed_count++;
        }
        
        $redirect_url = admin_url( 'admin.php?page=adcp-tools' );
        $redirect_url = add_query_arg( 'adcp_notice', 'tx_fixed', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Flushes the site's rewrite rules to fix API endpoints.
     */
    public function handle_flush_permalinks() {
        if ( ! isset( $_POST['adcp_nonce'] ) || ! wp_verify_nonce( $_POST['adcp_nonce'], 'adcp_flush_permalinks_nonce' ) ) wp_die('Security check failed.');
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Permission denied.');
        
        // This is the function that fixes the REST API
        flush_rewrite_rules();
        
        $redirect_url = admin_url( 'admin.php?page=adcp-tools' );
        $redirect_url = add_query_arg( 'adcp_notice', 'permalinks_flushed', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

	/**
	 * Renders the 'Settings' page.
	 */
	public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <p>Configure global settings for AdsCampaignPro.</p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'adcp_settings_group' );
                do_settings_sections( 'adcp-settings' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
	}
}