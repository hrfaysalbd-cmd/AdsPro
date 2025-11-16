<?php
/**
 * AdsCampaignPro Client Dashboard Template
 */

global $wpdb;
$token = get_query_var( 'adcp_token' );
$contract = null;
$contract_page_url = home_url('/ad-contract/'); // <-- URL for your page with the contract form

if ( $token ) {
    $contract = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM " . $wpdb->prefix . "adcp_contracts WHERE tracking_token = %s",
        $token
    ) );
}

get_header(); 
?>

<div id="primary" class="content-area" style="padding: 20px;">
    <main id="main" class="site-main" role="main">
        <article class="page type-page status-publish hentry">
            
            <?php if ( $contract ) : ?>
                
                <!-- Page Header -->
                <header class="entry-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
                    <h1 class="entry-title" style="margin-bottom: 0;">Welcome, <?php echo esc_html( $contract->client_name ); ?></h1>
                    <a href="<?php echo esc_url( $contract_page_url ); ?>" style="display: inline-block; padding: 10px 15px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">
                        Submit New Ad Contract
                    </a>
                </header>
                
                <div class="entry-content">
                    <div id="adcp-client-dashboard">
                        
                        <!-- 
                        REMOVED: The "Your Tracking Code" section has been removed.
                        The tracker.js script is already loaded on every page of your website 
                        by the plugin, so your clients do not need to do this.
                        -->
                        
                        <h3>Your Analytics</h3>
                        
                        <div id="adcp-loader" style="padding: 20px 0;">
                            <p>Loading analytics...</p>
                        </div>

                        <div id="adcp-analytics-content" style="display:none;">
                            
                            <div id="adcp-global-stats" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
                                <div class="stat-box" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; flex-basis: 200px; flex-grow: 1;">
                                    <h4>Total Impressions</h4>
                                    <strong style="font-size: 2em;" id="stat-impressions">0</strong>
                                </div>
                                <div class="stat-box" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; flex-basis: 200px; flex-grow: 1;">
                                    <h4>Total Clicks</h4>
                                    <strong style="font-size: 2em;" id="stat-clicks">0</strong>
                                </div>
                                <div class="stat-box" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; flex-basis: 200px; flex-grow: 1;">
                                    <h4>Avg. CTR</h4>
                                    <strong style="font-size: 2em;" id="stat-ctr">0%</strong>
                                </div>
                            </div>

                            <div class="chart-wrapper" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                                <canvas id="adcp-client-chart" style="max-height: 400px;"></canvas>
                            </div>

                            <div class="top-campaigns-wrapper" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd;">
                                <h3>Your Campaigns</h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="text-align: left;">
                                            <th style="padding: 8px; border-bottom: 2px solid #ddd;">Campaign Name</th>
                                            <th style="padding: 8px; border-bottom: 2px solid #ddd;">Impressions</th>
                                            <th style="padding: 8px; border-bottom: 2px solid #ddd;">Clicks</th>
                                            <th style="padding: 8px; border-bottom: 2px solid #ddd;">CTR</th>
                                        </tr>
                                    </thead>
                                    <tbody id="adcp-top-campaigns-body">
                                        </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else : ?>
                <header class="entry-header">
                    <h1 class="entry-title">Access Denied</h1>
                </header>
                <div class="entry-content">
                    <p>This analytics dashboard link is invalid or has expired. Please contact support if you believe this is an error.</p>
                </div>
            <?php endif; ?>
            
        </article>
    </main>
</div>

<?php
get_footer();