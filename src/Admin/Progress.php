<?php

namespace WBPI\Admin;

use WBPI\Import\Manager;

defined('ABSPATH') || exit;

class Progress
{
    private Manager $manager;

    public function __construct()
    {
        $this->manager = new Manager();
    }

    public function register(): void
    {
        add_action('admin_bar_menu', [$this, 'adminBar'], 100);
        add_action('wp_ajax_wbpi_progress', [$this, 'progress']);
        add_action('wp_ajax_wbpi_pause_batch', [$this, 'pauseBatch']);
        add_action('wp_ajax_wbpi_resume_batch', [$this, 'resumeBatch']);
        add_action('wp_ajax_wbpi_stop_batch', [$this, 'stopBatch']);
        add_action('wp_ajax_wbpi_restart_batch', [$this, 'restartBatch']);
    }

    public function adminBar($adminBar): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $adminBar->add_node([
            'id'    => 'wbpi-progress',
            'title' => __('WBPI', 'wbpi'),
            'href'  => '#'
        ]);

        $adminBar->add_node([
            'id'     => 'wbpi-progress-status',
            'parent' => 'wbpi-progress',
            'title' => '
                <div id="wbpi-progress-container">

                    <div id="wbpi-progress-text">
                        Waiting...
                    </div>

                    <div id="wbpi-progress-bar">
                        <span></span>
                    </div>

                    <div id="wbpi-progress-meta">
                        <span class="wbpi-count">0 / 0</span>
                        <span class="wbpi-speed">0/min</span>
                        <span class="wbpi-eta">ETA --</span>
                    </div>

                    <div id="wbpi-progress-actions">
                        <button class="button button-small" id="wbpi-pause">Pause</button>
                        <button class="button button-small" id="wbpi-resume">Resume</button>
                        <button class="button button-small" id="wbpi-stop">Stop</button>
                        <button class="button button-small button-primary" id="wbpi-restart">Restart</button>
                    </div>

                </div>',
            'meta'   => [
                'html' => true
            ]
        ]);
    }

    public function pauseBatch(): void
    {
        check_ajax_referer('wbpi_admin', 'nonce');

        wp_send_json_success([
            'success' => $this->manager->pauseBatch(
                sanitize_text_field($_POST['batch_key'] ?? '')
            )
        ]);
    }

    public function resumeBatch(): void
    {
        check_ajax_referer('wbpi_admin', 'nonce');

        wp_send_json_success([
            'success' => $this->manager->resumeBatch(
                sanitize_text_field($_POST['batch_key'] ?? '')
            )
        ]);
    }

    public function stopBatch(): void
    {
        check_ajax_referer('wbpi_admin', 'nonce');

        wp_send_json_success([
            'success' => $this->manager->stopBatch(
                sanitize_text_field($_POST['batch_key'] ?? '')
            )
        ]);
    }

    public function restartBatch(): void
    {
        check_ajax_referer('wbpi_admin', 'nonce');

        wp_send_json_success([
            'success' => $this->manager->restartBatch(
                sanitize_text_field($_POST['batch_key'] ?? '')
            )
        ]);
    }

    public function progress(): void
    {
        check_ajax_referer('wbpi_admin', 'nonce');

        $progress = $this->manager->getProgress();

        wp_send_json_success($progress);
    }
}
