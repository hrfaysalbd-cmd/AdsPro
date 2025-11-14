<?php
/**
 * AdsCampaignPro Aggregator
 *
 * Handles processing raw tracking data into summaries via WP Cron.
 */
class Adcp_Aggregator {

    public function __init__() {
        // Register the cron hook
        add_action( 'adcp_hourly_aggregation_event', array( $this, 'run_aggregation' ) );
    }

    /**
     * Main aggregation function.
     */
    public function run_aggregation() {
        global $wpdb;

        $tbl_tracking = $wpdb->prefix . 'adcp_tracking';
        $tbl_summary  = $wpdb->prefix . 'adcp_tracking_summary';

        $process_since = date( 'Y-m-d H:i:s', time() - ( 2 * HOUR_IN_SECONDS ) );

        $sql = $wpdb->prepare( "
            INSERT INTO {$tbl_summary} (campaign_id, event_date, impressions, clicks, uniques)
            SELECT
                campaign_id,
                DATE(created_at) AS event_date,
                SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                COUNT(DISTINCT cookie_id) AS uniques
            FROM
                {$tbl_tracking}
            WHERE
                created_at >= %s
            GROUP BY
                campaign_id, DATE(created_at)
            ON DUPLICATE KEY UPDATE
                impressions = impressions + VALUES(impressions),
                clicks = clicks + VALUES(clicks),
                uniques = uniques + VALUES(uniques)
        ", $process_since );

        $wpdb->query( $sql );
    }

    /**
     * Schedule the cron job if it's not already scheduled.
     */
    public static function schedule_cron() {
        if ( ! wp_next_scheduled( 'adcp_hourly_aggregation_event' ) ) {
            wp_schedule_event( time(), 'hourly', 'adcp_hourly_aggregation_event' );
        }
    }

    /**
     * Unschedule the cron job.
     */
    public static function unschedule_cron() {
        wp_clear_scheduled_hook( 'adcp_hourly_aggregation_event' );
    }
}