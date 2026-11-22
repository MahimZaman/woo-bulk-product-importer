<?php

namespace WBPI\Database;

defined('ABSPATH') || exit;

class Schema
{
    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $batches = $wpdb->prefix . 'wbpi_batches';
        $queue   = $wpdb->prefix . 'wbpi_queue';

        dbDelta("
            CREATE TABLE {$batches} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                batch_key VARCHAR(100) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'queued',
                total INT UNSIGNED NOT NULL DEFAULT 0,
                processed INT UNSIGNED NOT NULL DEFAULT 0,
                failed INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY batch_key (batch_key),
                KEY status (status)
            ) {$charset};
        ");

        dbDelta("
            CREATE TABLE {$queue} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                batch_id BIGINT UNSIGNED NOT NULL,
                sku VARCHAR(191) NOT NULL,
                payload LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                error LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY batch_id (batch_id),
                KEY sku (sku),
                KEY status (status)
            ) {$charset};
        ");
    }
}
