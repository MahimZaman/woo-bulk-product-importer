<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

delete_option('wbpi_plugin_version');
delete_option('wbpi_db_version');

wp_clear_scheduled_hook('wbpi_process_queue');

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wbpi_queue");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wbpi_batches");

$logFile = plugin_dir_path(__FILE__) . 'logs/import-errors.log';

if (file_exists($logFile)) {
    @unlink($logFile);
}
