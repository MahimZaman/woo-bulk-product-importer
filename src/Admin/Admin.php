<?php

namespace WBPI\Admin;

defined('ABSPATH') || exit;

class Admin
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Woo Bulk Importer', 'wbpi'),
            __('WBPI', 'wbpi'),
            'manage_woocommerce',
            'wbpi',
            [$this, 'dashboard'],
            'dashicons-database-import',
            56
        );

        add_submenu_page(
            'wbpi',
            __('Dashboard', 'wbpi'),
            __('Dashboard', 'wbpi'),
            'manage_woocommerce',
            'wbpi',
            [$this, 'dashboard']
        );

        add_submenu_page(
            'wbpi',
            __('Logs', 'wbpi'),
            __('Logs', 'wbpi'),
            'manage_woocommerce',
            'wbpi-logs',
            [$this, 'logs']
        );
    }

    public function assets(string $hook): void
    {
        if (strpos($hook, 'wbpi') === false) {
            return;
        }

        wp_enqueue_style(
            'wbpi-admin',
            WBPI_URL . 'assets/css/admin.css',
            [],
            WBPI_VERSION
        );

        wp_enqueue_style(
            'wbpi-logs',
            WBPI_URL . 'assets/css/logs.css',
            [],
            WBPI_VERSION
        );

        wp_enqueue_style(
            'wbpi-admin-bar',
            WBPI_URL . 'assets/css/admin-bar.css',
            [],
            WBPI_VERSION
        );

        wp_enqueue_script(
            'wbpi-admin',
            WBPI_URL . 'assets/js/admin.js',
            ['jquery'],
            WBPI_VERSION,
            true
        );

        wp_enqueue_script(
            'wbpi-logs',
            WBPI_URL . 'assets/js/logs.js',
            ['jquery'],
            WBPI_VERSION,
            true
        );

        wp_enqueue_script(
            'wbpi-admin-bar',
            WBPI_URL . 'assets/js/admin-bar.js',
            ['jquery'],
            WBPI_VERSION,
            true
        );

        wp_localize_script('wbpi-admin', 'WBPI', [
            'ajax' => admin_url('admin-ajax.php'),
            'rest' => rest_url('wbpi/v1/'),
            'nonce' => wp_create_nonce('wbpi_admin'),
            'version' => WBPI_VERSION
        ]);
    }

    public function dashboard(): void
    {
?>
        <div class="wrap wbpi-dashboard">

            <h1><?php esc_html_e('Woo Bulk Product Importer', 'wbpi'); ?></h1>

            <div class="wbpi-dashboard-grid">

                <div class="wbpi-card">
                    <h2><?php esc_html_e('REST Endpoint', 'wbpi'); ?></h2>
                    <code><?php echo esc_html(rest_url('wbpi/v1/import')); ?></code>
                </div>

                <div class="wbpi-card">
                    <h2><?php esc_html_e('Plugin Version', 'wbpi'); ?></h2>
                    <strong><?php echo esc_html(WBPI_VERSION); ?></strong>
                </div>

                <div class="wbpi-card">
                    <h2><?php esc_html_e('Status', 'wbpi'); ?></h2>
                    <span class="wbpi-status success">
                        <?php esc_html_e('Ready', 'wbpi'); ?>
                    </span>
                </div>

            </div>

        </div>
<?php
    }

    public function logs(): void
    {
        (new Logs())->render();
    }
}
