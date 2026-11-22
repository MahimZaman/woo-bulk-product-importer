<?php

namespace WBPI\Admin;

use WBPI\Import\Logger;

defined('ABSPATH') || exit;

class Logs
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger();
    }

    public function register(): void
    {
        add_action('wp_ajax_wbpi_get_logs', [$this, 'getLogs']);
        add_action('wp_ajax_wbpi_clear_logs', [$this, 'clearLogs']);
    }

    public function render(): void
    {
        $stats = $this->logger->stats();
?>
        <div class="wrap wbpi-logs-wrap">

            <h1><?php esc_html_e('Import Logs', 'wbpi'); ?></h1>

            <div class="wbpi-log-stats">

                <div class="wbpi-stat-card">
                    <strong><?php echo esc_html($stats['total']); ?></strong>
                    <span><?php esc_html_e('Total', 'wbpi'); ?></span>
                </div>

                <div class="wbpi-stat-card">
                    <strong><?php echo esc_html($stats['info']); ?></strong>
                    <span><?php esc_html_e('Info', 'wbpi'); ?></span>
                </div>

                <div class="wbpi-stat-card">
                    <strong><?php echo esc_html($stats['warning']); ?></strong>
                    <span><?php esc_html_e('Warnings', 'wbpi'); ?></span>
                </div>

                <div class="wbpi-stat-card">
                    <strong><?php echo esc_html($stats['error']); ?></strong>
                    <span><?php esc_html_e('Errors', 'wbpi'); ?></span>
                </div>

                <div class="wbpi-stat-card">
                    <strong><?php echo esc_html($stats['fatal']); ?></strong>
                    <span><?php esc_html_e('Fatal', 'wbpi'); ?></span>
                </div>

            </div>

            <div class="wbpi-log-actions">
                <button class="button button-secondary" id="wbpi-refresh-logs">
                    <?php esc_html_e('Refresh', 'wbpi'); ?>
                </button>

                <button class="button button-danger" id="wbpi-clear-logs">
                    <?php esc_html_e('Clear Logs', 'wbpi'); ?>
                </button>
            </div>

            <pre id="wbpi-log-viewer" class="wbpi-log-viewer"></pre>

        </div>
<?php
    }

    public function getLogs(): void
    {
        check_ajax_referer('wbpi_admin', 'nonce');

        wp_send_json_success([
            'logs' => $this->logger->read(),
            'stats' => $this->logger->stats()
        ]);
    }

    public function clearLogs(): void
    {
        check_ajax_referer('wbpi_admin', 'nonce');

        $this->logger->clear();

        wp_send_json_success([
            'message' => __('Logs cleared successfully.', 'wbpi')
        ]);
    }
}
