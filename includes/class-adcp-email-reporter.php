<?php
/**
 * AdsCampaignPro Email Reporter
 *
 * Handles building and sending HTML email analytics reports.
 */
class Adcp_Email_Reporter {

    /**
     * Main public function. Gets data, builds HTML, and sends email.
     */
    public static function send_report( $to_email, $days = 30 ) {
        
        $data = Adcp_Analytics_Queries::get_analytics_data( $days );
        $chart_url = self::get_chart_image_url( $data['timeseries'] );
        $html_body = self::build_report_html( $data, $chart_url, $days );

        $subject = sprintf( 
            'AdsCampaignPro Report: %s (%d Days)', 
            get_bloginfo('name'), 
            $days 
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        
        return wp_mail( $to_email, $subject, $html_body, $headers );
    }

    /**
     * Generates a static chart image URL from QuickChart.io.
     */
    private static function get_chart_image_url( $timeseries_data ) {
        $labels = array_map( function($item) { return $item->event_date; }, $timeseries_data );
        $impressions = array_map( function($item) { return $item->impressions; }, $timeseries_data );
        $clicks = array_map( function($item) { return $item->clicks; }, $timeseries_data );

        $chart_config = array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => 'Impressions',
                        'data' => $impressions,
                        'borderColor' => 'rgb(75, 192, 192)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'fill' => true,
                    ),
                    array(
                        'label' => 'Clicks',
                        'data' => $clicks,
                        'borderColor' => 'rgb(255, 99, 132)',
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'fill' => true,
                    )
                )
            ),
            'options' => array(
                'title' => array( 'display' => true, 'text' => 'Campaign Performance' )
            )
        );

        $base_url = 'https://quickchart.io/chart?c=';
        return $base_url . urlencode( wp_json_encode( $chart_config ) );
    }

    /**
     * Builds the full HTML email body with inline styles.
     */
    private static function build_report_html( $data, $chart_url, $days ) {
        $stats = $data['global_stats'];
        $top_campaigns = $data['top_campaigns'];
        
        $body = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f9f9f9; color: #333;">';
        $body .= '<table width="100%" max-width="600" align="center" style="background: #ffffff; border: 1px solid #ddd; border-collapse: collapse;">';
        
        // Header
        $body .= '<tr><td style="padding: 20px; border-bottom: 2px solid #0073aa;">';
        $body .= '<h1 style="margin: 0; font-size: 24px;">AdsCampaignPro Report</h1>';
        $body .= '<p style="margin: 4px 0 0;">' . get_bloginfo('name') . ' - Last ' . $days . ' Days</p>';
        $body .= '</td></tr>';
        
        // Global Stats
        $body .= '<tr><td style="padding: 20px;">';
        $body .= '<table width="100%" style="border-collapse: collapse;"><tr>';
        $body .= '<td style="padding: 10px; border: 1px solid #eee; text-align: center;"><h4 style="margin: 0 0 5px;">Impressions</h4><strong style="font-size: 20px;">' . number_format($stats['impressions']) . '</strong></td>';
        $body .= '<td style="padding: 10px; border: 1px solid #eee; text-align: center;"><h4 style="margin: 0 0 5px;">Clicks</h4><strong style="font-size: 20px;">' . number_format($stats['clicks']) . '</strong></td>';
        $body .= '<td style="padding: 10px; border: 1px solid #eee; text-align: center;"><h4 style="margin: 0 0 5px;">Avg. CTR</h4><strong style="font-size: 20px;">' . $stats['ctr'] . '%</strong></td>';
        $body .= '</tr></table>';
        $body .= '</td></tr>';

        // Chart
        $body .= '<tr><td style="padding: 20px; text-align: center; border-top: 1px solid #eee;">';
        $body .= '<h2 style="margin-top: 0;">Performance Over Time</h2>';
        $body .= '<img src="' . esc_url($chart_url) . '" alt="Analytics Chart" style="max-width: 100%; height: auto;">';
        $body .= '</td></tr>';
        
        // Top Campaigns
        $body .= '<tr><td style="padding: 20px; border-top: 1px solid #eee;">';
        $body .= '<h2 style="margin-top: 0;">Top Campaigns</h2>';
        $body .= '<table width="100%" style="border-collapse: collapse; text-align: left;">';
        $body .= '<thead><tr><th style="padding: 8px; border-bottom: 2px solid #ddd;">Campaign</th><th style="padding: 8px; border-bottom: 2px solid #ddd;">Impressions</th><th style="padding: 8px; border-bottom: 2px solid #ddd;">Clicks</th></tr></thead>';
        $body .= '<tbody>';
        
        if ( empty($top_campaigns) ) {
            $body .= '<tr><td colspan="3" style="padding: 8px; border-bottom: 1px solid #eee;">No campaign data for this period.</td></tr>';
        } else {
            foreach ( $top_campaigns as $c ) {
                $body .= '<tr>';
                $body .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html($c->name) . '</td>';
                $body .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . number_format($c->impressions) . '</td>';
                $body .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . number_format($c->clicks) . '</td>';
                $body .= '</tr>';
            }
        }
        $body .= '</tbody></table>';
        $body .= '</td></tr>';
        
        // Footer
        $body .= '<tr><td style="padding: 20px; text-align: center; color: #777; font-size: 12px; background: #f9f9f9; border-top: 1px solid #eee;">';
        $body .= 'Report generated by AdsCampaignPro on ' . date('Y-m-d H:i:s');
        $body .= '</td></tr>';
        
        $body .= '</table></body></html>';
        
        return $body;
    }
}