<?php

namespace WBPI\Core;

use WBPI\Admin\Admin;
use WBPI\Admin\Logs;
use WBPI\Admin\Progress;
use WBPI\API\Rest;
use WBPI\Import\Manager;

defined('ABSPATH') || exit;

class Plugin
{
    private static ?Plugin $instance = null;

    private bool $booted = false;

    private function __construct() {}

    public static function instance(): Plugin
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        load_plugin_textdomain(
            'wbpi',
            false,
            dirname(WBPI_BASENAME) . '/languages'
        );

        (new Manager())->register();
        (new Rest())->register();
        (new Admin())->register();
        (new Progress())->register();
        (new Logs())->register();

        $this->booted = true;

        do_action('wbpi_loaded');
    }
}
