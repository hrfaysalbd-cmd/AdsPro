<?php
/**
 * AdsCampaignPro Analytics Queries
 *
 * A reusable class to fetch aggregated analytics data.
 */
class Adcp_Analytics_Queries {

    /**
     * Get aggregated analytics data for a given date range. (Admin)
     *
     * @param int $days The number of days to look back.
     * @return array
     */
    public static function get_analytics_data( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'adcp_tracking_summary';
        $campaigns_table = $wpdb->prefix . 'adcp_campaigns';

        $date_threshold = date( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
        
        // 1. Get Time-series data
        $sql_timeseries = $wpdb->prepare( "
            SELECT
                event_date,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(uniques) as uniques
            FROM {$table}
            WHERE event_date >= %s
            GROUP BY event_date
            ORDER BY event_date ASC
        ", $date_threshold );
        
        $timeseries = $wpdb->get_results( $sql_timeseries );
        
        // 2. Get Top Campaigns
        // --- FIX: Use LEFT JOIN from campaigns to summary ---
        // This ensures campaigns with 0 stats are also shown.
        $sql_top_campaigns = $wpdb->prepare( "
            SELECT
                c.id as campaign_id,
                c.name,
                COALESCE(SUM(s.impressions), 0) as impressions,
                COALESCE(SUM(s.clicks), 0) as clicks
            FROM {$campaigns_table} c
            LEFT JOIN {$table} s ON c.id = s.campaign_id AND s.event_date >= %s
            GROUP BY c.id, c.name
            ORDER BY impressions DESC
            LIMIT 10
        ", $date_threshold );
        
        $top_campaigns = $wpdb->get_results( $sql_top_campaigns );

        // 3. Get Global Stats
        $sql_global = $wpdb->prepare( "
            SELECT
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                SUM(uniques) as total_uniques
            FROM {$table}
            WHERE event_date >= %s
        ", $date_threshold );
        $global = $wpdb->get_row( $sql_global );
        
        $ctr = ($global->total_impressions > 0) ? ($global->total_clicks / $global->total_impressions) * 100 : 0;

        return array(
            'global_stats' => array(
                'impressions' => (int) $global->total_impressions,
                'clicks'      => (int) $global->total_clicks,
                'uniques'     => (int) $global->total_uniques,
                'ctr'         => round( $ctr, 2 ),
            ),
            'timeseries'    => $timeseries,
            'top_campaigns' => $top_campaigns
        );
    }
    
    /**
     * Get analytics data, but filtered by a specific list of campaign IDs. (Client)
     *
     * @param array $campaign_ids List of campaign IDs.
     * @param int $days The number of days to look back.
     * @return array
     */
    public static function get_analytics_data_for_campaigns( $campaign_ids, $days = 30 ) {
        global $wpdb;
        
        if ( empty($campaign_ids) ) {
            return array(
                'global_stats' => array('impressions' => 0, 'clicks' => 0, 'uniques' => 0, 'ctr' => 0),
                'timeseries' => array(),
                'top_campaigns' => array()
            );
        }

        $table = $wpdb->prefix . 'adcp_tracking_summary';
        $campaigns_table = $wpdb->prefix . 'adcp_campaigns';
        $date_threshold = date( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
        
        $id_placeholders = implode( ', ', array_fill( 0, count($campaign_ids), '%d' ) );
        $params = $campaign_ids;
        array_unshift( $params, $date_threshold );
        
        // 1. Get Time-series data
        $sql_timeseries = $wpdb->prepare( "
            SELECT event_date, SUM(impressions) as impressions, SUM(clicks) as clicks
            FROM {$table}
            WHERE event_date >= %s AND campaign_id IN ($id_placeholders)
            GROUP BY event_date ORDER BY event_date ASC
        ", $params );
        $timeseries = $wpdb->get_results( $sql_timeseries );
        
        // 2. Get Top Campaigns
        // --- FIX: Use LEFT JOIN from campaigns to summary ---
        // This ensures all client campaigns are listed, even with 0 stats.
        $sql_top_campaigns = $wpdb->prepare( "
            SELECT c.id as campaign_id, c.name, 
                   COALESCE(SUM(s.impressions), 0) as impressions, 
                   COALESCE(SUM(s.clicks), 0) as clicks
            FROM {$campaigns_table} c
            LEFT JOIN {$table} s ON c.id = s.campaign_id AND s.event_date >= %s
            WHERE c.id IN ($id_placeholders)
            GROUP BY c.id, c.name 
            ORDER BY impressions DESC
        ", $params );
        $top_campaigns = $wpdb->get_results( $sql_top_campaigns );

        // 3. Get Global Stats
        $sql_global = $wpdb->prepare( "
            SELECT SUM(impressions) as total_impressions, SUM(clicks) as total_clicks
            FROM {$table}
            WHERE event_date >= %s AND campaign_id IN ($id_placeholders)
        ", $params );
        $global = $wpdb->get_row( $sql_global );
        
        $ctr = ($global->total_impressions > 0) ? ($global->total_clicks / $global->total_impressions) * 100 : 0;

        return array(
            'global_stats' => array(
                'impressions' => (int) $global->total_impressions,
                'clicks'      => (int) $global->total_clicks,
                'uniques'     => 0, // Note: Unique tracking per-client is a more complex query
                'ctr'         => round( $ctr, 2 ),
            ),
            'timeseries'    => $timeseries,
            'top_campaigns' => $top_campaigns
        );
    }
}