<?php

/**
 * Plugin Name: Woo Bulk Product Importer
 * Plugin URI: https://condixia.com
 * Author: Mahim Zaman (Condixia)
 * Author URI: https://mahimzaman.com
 * Description: Enterprise-grade WooCommerce JSON product synchronization plugin.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: wbpi
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('WBPI_VERSION', '2.0.0');
define('WBPI_FILE', __FILE__);
define('WBPI_BASENAME', plugin_basename(__FILE__));
define('WBPI_PATH', plugin_dir_path(__FILE__));
define('WBPI_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Woo Bulk Product Importer requires PHP 8.1 or higher.', 'wbpi');
        echo '</p></div>';
    });
    return;
}

spl_autoload_register(function ($class) {

    if (strpos($class, 'WBPI\\') !== 0) {
        return;
    }

    $class = str_replace('WBPI\\', '', $class);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

    $file = WBPI_PATH . 'src/' . $class . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, ['WBPI\Core\Installer', 'activate']);

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('wbpi_process_queue');
    flush_rewrite_rules();
});

add_action('plugins_loaded', function () {

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('WooCommerce must be installed and activated to use Woo Bulk Product Importer.', 'wbpi');
            echo '</p></div>';
        });
        return;
    }

    try {
        WBPI\Core\Plugin::instance()->boot();
    } catch (Throwable $e) {

        error_log('[WBPI] ' . $e->getMessage());

        add_action('admin_notices', function () use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}, 20);
