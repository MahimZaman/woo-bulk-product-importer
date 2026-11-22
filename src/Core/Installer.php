<?php

namespace WBPI\Core;

use WBPI\Database\Schema;

defined('ABSPATH') || exit;

class Installer
{
    public static function activate(): void
    {
        self::checkRequirements();

        Schema::createTables();

        update_option('wbpi_plugin_version', WBPI_VERSION);
        update_option('wbpi_db_version', WBPI_VERSION);

        flush_rewrite_rules();
    }

    private static function checkRequirements(): void
    {
        global $wp_version;

        if (version_compare(PHP_VERSION, '8.1', '<')) {
            wp_die(
                esc_html__('Woo Bulk Product Importer requires PHP 8.1 or higher.', 'wbpi')
            );
        }

        if (version_compare($wp_version, '6.5', '<')) {
            wp_die(
                esc_html__('Woo Bulk Product Importer requires WordPress 6.5 or higher.', 'wbpi')
            );
        }

        if (!class_exists('WooCommerce')) {
            wp_die(
                esc_html__('WooCommerce must be installed and activated.', 'wbpi')
            );
        }
    }
}
