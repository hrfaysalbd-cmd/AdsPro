<?php
/**
 * Fired during plugin activation
 */
class Adcp_Activator {

	/**
	 * Main activation method.
	 */
	public static function activate() {
		self::create_database_tables();
		add_option( 'adcp_version', ADCP_VERSION );
	}

	/**
	 * Create all custom tables needed for the plugin using dbDelta.
	 */
	public static function create_database_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Table prefixes
		$tbl_campaigns    = $wpdb->prefix . 'adcp_campaigns';
		$tbl_creatives    = $wpdb->prefix . 'adcp_creatives';
		$tbl_packages     = $wpdb->prefix . 'adcp_packages';
		$tbl_coupons      = $wpdb->prefix . 'adcp_coupons';
		$tbl_contracts    = $wpdb->prefix . 'adcp_contracts';
		$tbl_tracking     = $wpdb->prefix . 'adcp_tracking';
		$tbl_transactions = $wpdb->prefix . 'adcp_transactions';
        $tbl_extras       = $wpdb->prefix . 'adcp_extras';
        $tbl_summary      = $wpdb->prefix . 'adcp_tracking_summary';

		$sql = "
		CREATE TABLE IF NOT EXISTS $tbl_campaigns (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  name VARCHAR(191) NOT NULL,
		  type ENUM('popup','slide','scroll','embed') NOT NULL,
		  status ENUM('draft','active','paused','ended') NOT NULL DEFAULT 'draft',
		  config JSON NOT NULL,
		  start DATETIME NULL,
		  end DATETIME NULL,
		  priority INT DEFAULT 10,
          contract_id BIGINT UNSIGNED NULL,
		  created_by BIGINT UNSIGNED,
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  KEY idx_status (status),
		  KEY idx_start_end (start,end),
          KEY idx_contract (contract_id)
		) $charset_collate;

		CREATE TABLE IF NOT EXISTS $tbl_creatives (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  campaign_id BIGINT UNSIGNED NOT NULL,
		  file_url TEXT NOT NULL,
		  type VARCHAR(30) NOT NULL,
		  meta JSON,
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		  KEY idx_campaign (campaign_id)
		) $charset_collate;

		CREATE TABLE IF NOT EXISTS $tbl_packages (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  title VARCHAR(191) NOT NULL,
		  description TEXT,
		  price DECIMAL(10,2) NOT NULL,
		  cycle ENUM('monthly','yearly') DEFAULT 'monthly',
		  features JSON,
		  allow_coupon TINYINT(1) DEFAULT 1,
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		) $charset_collate;

		CREATE TABLE IF NOT EXISTS $tbl_coupons (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  code VARCHAR(64) NOT NULL UNIQUE,
		  type ENUM('percent','fixed') NOT NULL,
		  value DECIMAL(10,2) NOT NULL,
		  max_uses INT DEFAULT 0,
		  used_count INT DEFAULT 0,
		  limit_per_user INT DEFAULT 0,
		  start_date DATE NULL,
		  end_date DATE NULL,
		  applicable_packages JSON NULL,
          status ENUM('active','disabled') NOT NULL DEFAULT 'active',
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          KEY idx_status (status)
		) $charset_collate;

		CREATE TABLE IF NOT EXISTS $tbl_contracts (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  client_name VARCHAR(191),
		  client_email VARCHAR(191),
		  client_phone VARCHAR(50),
		  data JSON NOT NULL,
		  status ENUM('pending','approved','rejected') DEFAULT 'pending',
		  tracking_token VARCHAR(64) UNIQUE,
		  grand_total DECIMAL(10,2) DEFAULT 0,
		  payment_status ENUM('unpaid','pending','paid','verified') DEFAULT 'unpaid',
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		) $charset_collate;

		CREATE TABLE IF NOT EXISTS $tbl_tracking (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  campaign_id BIGINT UNSIGNED,
		  event_type ENUM('impression','click','engagement'),
		  cookie_id VARCHAR(128),
		  ip_hash VARCHAR(128),
		  user_agent TEXT,
		  page_url TEXT,
		  meta JSON,
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		  KEY idx_campaign (campaign_id),
		  KEY idx_event_type (event_type)
		) $charset_collate;

		CREATE TABLE IF NOT EXISTS $tbl_transactions (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  contract_id BIGINT UNSIGNED,
		  provider VARCHAR(64),
		  txn_id VARCHAR(191),
		  amount DECIMAL(10,2),
		  status ENUM('pending','completed','failed', 'verified') DEFAULT 'pending',
		  meta JSON,
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		) $charset_collate;

        CREATE TABLE IF NOT EXISTS $tbl_extras (
		  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		  title VARCHAR(191) NOT NULL,
		  description TEXT,
		  price DECIMAL(10,2) NOT NULL,
		  delivery_time INT DEFAULT 7,
		  meta JSON,
		  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		) $charset_collate;

        CREATE TABLE IF NOT EXISTS $tbl_summary (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          campaign_id BIGINT UNSIGNED NOT NULL,
          event_date DATE NOT NULL,
          impressions INT DEFAULT 0,
          clicks INT DEFAULT 0,
          uniques INT DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY idx_campaign_date (campaign_id, event_date),
          KEY idx_event_date (event_date)
        ) $charset_collate;
		";

		dbDelta( $sql );
	}
    
    /**
     * --- NEW DEBUG FUNCTION ---
     * Bypasses dbDelta and runs queries directly, returning a full report.
     * This fixes the "blank page" issue on the Tools page.
     */
    public static function debug_create_tables() {
        global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
        $results = [];

        $queries = [
            'adcp_campaigns' => "CREATE TABLE {$wpdb->prefix}adcp_campaigns (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL,
              type ENUM('popup','slide','scroll','embed') NOT NULL, status ENUM('draft','active','paused','ended') NOT NULL DEFAULT 'draft',
              config JSON NOT NULL, start DATETIME NULL, end DATETIME NULL, priority INT DEFAULT 10,
              contract_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_status (status),
              KEY idx_start_end (start,end), KEY idx_contract (contract_id)
            ) $charset_collate;",

            'adcp_creatives' => "CREATE TABLE {$wpdb->prefix}adcp_creatives (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, campaign_id BIGINT UNSIGNED NOT NULL,
              file_url TEXT NOT NULL, type VARCHAR(30) NOT NULL, meta JSON, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              KEY idx_campaign (campaign_id)
            ) $charset_collate;",

            'adcp_packages' => "CREATE TABLE {$wpdb->prefix}adcp_packages (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(191) NOT NULL, description TEXT,
              price DECIMAL(10,2) NOT NULL, cycle ENUM('monthly','yearly') DEFAULT 'monthly',
              features JSON, allow_coupon TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            'adcp_coupons' => "CREATE TABLE {$wpdb->prefix}adcp_coupons (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(64) NOT NULL UNIQUE,
              type ENUM('percent','fixed') NOT NULL, value DECIMAL(10,2) NOT NULL, max_uses INT DEFAULT 0,
              used_count INT DEFAULT 0, limit_per_user INT DEFAULT 0, start_date DATE NULL, end_date DATE NULL,
              applicable_packages JSON NULL, status ENUM('active','disabled') NOT NULL DEFAULT 'active',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY idx_status (status)
            ) $charset_collate;",

            'adcp_contracts' => "CREATE TABLE {$wpdb->prefix}adcp_contracts (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_name VARCHAR(191), client_email VARCHAR(191),
              client_phone VARCHAR(50), data JSON NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending',
              tracking_token VARCHAR(64) UNIQUE, grand_total DECIMAL(10,2) DEFAULT 0,
              payment_status ENUM('unpaid','pending','paid','verified') DEFAULT 'unpaid',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            'adcp_tracking' => "CREATE TABLE {$wpdb->prefix}adcp_tracking (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, campaign_id BIGINT UNSIGNED,
              event_type ENUM('impression','click','engagement'), cookie_id VARCHAR(128), ip_hash VARCHAR(128),
              user_agent TEXT, page_url TEXT, meta JSON, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              KEY idx_campaign (campaign_id), KEY idx_event_type (event_type)
            ) $charset_collate;",

            'adcp_transactions' => "CREATE TABLE {$wpdb->prefix}adcp_transactions (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contract_id BIGINT UNSIGNED, provider VARCHAR(64),
              txn_id VARCHAR(191), amount DECIMAL(10,2), status ENUM('pending','completed','failed','verified') DEFAULT 'pending',
              meta JSON, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",
            
            'adcp_extras' => "CREATE TABLE {$wpdb->prefix}adcp_extras (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(191) NOT NULL, description TEXT,
              price DECIMAL(10,2) NOT NULL, delivery_time INT DEFAULT 7, meta JSON,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;",

            'adcp_summary' => "CREATE TABLE {$wpdb->prefix}adcp_tracking_summary (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, campaign_id BIGINT UNSIGNED NOT NULL,
              event_date DATE NOT NULL, impressions INT DEFAULT 0, clicks INT DEFAULT 0, uniques INT DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY idx_campaign_date (campaign_id, event_date), KEY idx_event_date (event_date)
            ) $charset_collate;"
        ];

        // We use $wpdb->query which can execute CREATE TABLE statements.
        // We suppress errors with @ because we will catch them with $wpdb->last_error.
        foreach ($queries as $table_short_name => $sql) {
            $wpdb->last_error = ''; // Clear last error
            @$wpdb->query($sql);
            
            $table_name = $wpdb->prefix . $table_short_name;
            
            if ( empty($wpdb->last_error) ) {
                $results[] = [ 'table' => $table_name, 'success' => true, 'message' => 'Success'];
            } else {
                // If the table already exists, it's a success for our purposes.
                if (str_contains($wpdb->last_error, 'already exists')) {
                    $results[] = [ 'table' => $table_name, 'success' => true, 'message' => 'Table already exists'];
                } else {
                    // This will capture the *real* error, e.g., "CREATE command denied"
                    $results[] = [ 'table' => $table_name, 'success' => false, 'message' => $wpdb->last_error];
                }
            }
        }

        return $results;
    }
}