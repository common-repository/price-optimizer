<?php

class Pricimizer_Cache
{
    /**
     * Singleton memory cache if deleted old records in this request cycle
     * @var bool
     */
    public static $oldsCleared = false;

    /**
     * @param $userId
     * @param $ip
     * @param string|array $productId
     * @return stdClass|array
     */
    public static function get($userId = null, $ip = null, $productId = null)
    {
        global $wpdb;

        // Delete old cached records
        self::clearAllOlds();

        $query = "SELECT * FROM {$wpdb->prefix}pricimizer_price_sessions WHERE ";

        $where = [];
        $bindings = [];
        if (!empty($ip)) {
            $where[] = " ip=%s ";
            $bindings[] = $ip;
        }
        if (!empty($userId)) {
            $where[] = " user_id=%d ";
            $bindings[] = $userId;
        }
        if (!empty($productId)) {
            if (is_array($productId)) {
                $where[] = " product_id IN ( " . implode(',', array_fill(0, count($productId), '%d')) . ' ) ';
                $bindings = array_merge($bindings, $productId);
            } else {
                $where[] = " product_id=%d ";
                $bindings[] = $productId;
            }
        }
        $query .= implode(' AND ', $where);

        $results = $wpdb->get_results($wpdb->prepare($query, $bindings));
        wp_reset_query();

        return !empty($results) ? $results : [];
    }

    /**
     * @param int $productId
     * @param float $price
     * @param $userId
     * @param $ip
     * @return bool|int|mysqli_result|resource|null
     */
    public static function remember($productId, $price, $userId = null, $ip = null)
    {
        global $wpdb;

        // Insert
        $query = "INSERT IGNORE INTO {$wpdb->prefix}pricimizer_price_sessions(product_id, user_id, ip, price) VALUES
            (%d, %d, %s, %s)";
        $result = $wpdb->query($wpdb->prepare($query, [$productId, $userId, $ip, $price]));
        wp_reset_query();

        return $result;
    }

    public static function clearByProductId($id)
    {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}pricimizer_price_sessions WHERE product_id=%d", [$id]));
        wp_reset_query();

        return $result;
    }

    public static function clearAll() {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}pricimizer_price_sessions"));
        wp_reset_query();

        self::$oldsCleared = true;

        return $result;
    }

    public static function clearAllOlds() {
        global $wpdb;

        if (self::$oldsCleared) {
            return 0;
        }

        $olderThan = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}pricimizer_price_sessions WHERE created_at < %s", [$olderThan]));
        wp_reset_query();

        self::$oldsCleared = true;

        return $result;
    }
}