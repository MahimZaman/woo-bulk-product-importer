<?php

namespace WBPI\API;

use WBPI\Import\Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class Rest
{
    private Manager $manager;

    public function __construct()
    {
        $this->manager = new Manager();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('wbpi/v1', '/import', [
            'methods'             => 'POST',
            'callback'            => [$this, 'import'],
            'permission_callback' => '__return_true',
            //'permission_callback' => [$this, 'permission'],
        ]);

        register_rest_route('wbpi/v1', '/progress/(?P<batch>[a-zA-Z0-9\-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'progress'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wbpi/v1', '/pause/(?P<batch>[a-zA-Z0-9\-_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'pause'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wbpi/v1', '/resume/(?P<batch>[a-zA-Z0-9\-_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'resume'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wbpi/v1', '/stop/(?P<batch>[a-zA-Z0-9\-_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'stop'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wbpi/v1', '/restart/(?P<batch>[a-zA-Z0-9\-_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restart'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wbpi/v1', '/queue/(?P<batch>[a-zA-Z0-9\-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'queue'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wbpi/v1', '/history', [
            'methods' => 'GET',
            'callback' => [$this, 'history'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function permission(): bool|WP_Error
    {
        return true;
    }

    public function import(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $products = $request->get_json_params();

        if (!is_array($products) || empty($products)) {
            return new WP_Error(
                'wbpi_invalid_payload',
                __('Invalid JSON payload.', 'wbpi'),
                ['status' => 400]
            );
        }

        foreach ($products as $index => $product) {

            if (!is_array($product)) {
                return new WP_Error(
                    'wbpi_invalid_product',
                    sprintf(
                        __('Invalid product at row %d.', 'wbpi'),
                        $index + 1
                    ),
                    ['status' => 400]
                );
            }

            if (empty($product['sku'])) {
                return new WP_Error(
                    'wbpi_missing_sku',
                    sprintf(
                        __('Missing SKU at row %d.', 'wbpi'),
                        $index + 1
                    ),
                    ['status' => 400]
                );
            }

            if (empty($product['product_name'])) {
                return new WP_Error(
                    'wbpi_missing_name',
                    sprintf(
                        __('Missing product name at row %d.', 'wbpi'),
                        $index + 1
                    ),
                    ['status' => 400]
                );
            }
        }

        $batch = $this->manager->createBatch($products);

        return new WP_REST_Response([
            'success'   => true,
            'batch_key' => $batch['batch_key'],
            'total'     => $batch['total'],
            'message'   => __('Import queued successfully.', 'wbpi')
        ], 200);
    }

    public function progress(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $batchKey = sanitize_text_field($request->get_param('batch'));

        $progress = $this->manager->getProgress($batchKey);

        if (empty($progress)) {
            return new WP_Error(
                'wbpi_batch_not_found',
                __('Batch not found.', 'wbpi'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response($progress, 200);
    }

    public function pause(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => $this->manager->pauseBatch(
                sanitize_text_field($request->get_param('batch'))
            )
        ]);
    }

    public function resume(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => $this->manager->resumeBatch(
                sanitize_text_field($request->get_param('batch'))
            )
        ]);
    }

    public function stop(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => $this->manager->stopBatch(
                sanitize_text_field($request->get_param('batch'))
            )
        ]);
    }

    public function restart(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => $this->manager->restartBatch(
                sanitize_text_field($request->get_param('batch'))
            )
        ]);
    }

    public function queue(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(
            $this->manager->queueStatus(
                sanitize_text_field($request->get_param('batch'))
            )
        );
    }

    public function history(): WP_REST_Response
    {
        return new WP_REST_Response(
            $this->manager->batchHistory()
        );
    }
}
