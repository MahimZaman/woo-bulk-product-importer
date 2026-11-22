<?php

namespace WBPI\Import;

use WBPI\Core\Helpers;

defined('ABSPATH') || exit;

class Product
{
    private Images $images;
    private Logger $logger;

    public function __construct()
    {
        $this->images = new Images();
        $this->logger = new Logger();
    }

    public function sync(array $product): int|false
    {
        $product = Helpers::normalizeProduct($product);

        try {

            $productId = wc_get_product_id_by_sku($product['sku']);

            if ($productId) {
                $wcProduct = wc_get_product($productId);

                if (!$wcProduct instanceof \WC_Product_Variable) {
                    wp_delete_post($productId, true);
                    $productId = 0;
                }
            }

            if (!$productId) {
                $wcProduct = new \WC_Product_Variable();
            }

            $wcProduct->set_name($product['product_name']);
            $wcProduct->set_slug($product['slug']);
            $wcProduct->set_sku($product['sku']);
            $wcProduct->set_status('publish');
            $wcProduct->set_description($product['long_description']);
            $wcProduct->set_short_description($product['short_description']);

            $productId = $wcProduct->save();

            $this->syncCategories($productId, $product['categories']);
            $this->syncTags($productId, $product['tags']);
            $this->syncBrand($productId, $product['brand']);

            $attributes = $this->syncAttributes($productId, $product);

            $this->syncVariation($productId, $product, $attributes);

            $this->images->sync($productId, $product['images']);

            update_post_meta($productId, 'long_description', $product['long_description']);
            update_post_meta($productId, 'whats_included', $product['whats_included']);
            update_post_meta($productId, 'technical_specs', $product['technical_specs']);

            $this->logger->info(
                'Product synchronized.',
                [
                    'product_id' => $productId,
                    'sku' => $product['sku']
                ]
            );

            return $productId;
        } catch (\Throwable $e) {

            $this->logger->error(
                $e->getMessage(),
                [
                    'sku' => $product['sku'],
                    'row' => $product['row_number'],
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return false;
        }
    }

    private function syncCategories(int $productId, array $categories): void
    {
        if (empty($categories)) {
            return;
        }

        $ids = [];

        foreach ($categories as $category) {

            $term = term_exists($category, 'product_cat');

            if (!$term) {
                $term = wp_insert_term($category, 'product_cat');
            }

            if (!is_wp_error($term)) {
                $ids[] = (int) $term['term_id'];
            }
        }

        wp_set_object_terms($productId, $ids, 'product_cat');
    }

    private function syncTags(int $productId, array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        wp_set_object_terms($productId, $tags, 'product_tag');
    }

    private function syncBrand(int $productId, string $brand): void
    {
        if (empty($brand)) {
            return;
        }

        if (!taxonomy_exists('product_brand')) {
            return;
        }

        wp_set_object_terms($productId, $brand, 'product_brand');
    }

    private function syncAttributes(int $productId, array $product): array
    {
        $attributes = [];

        $map = [
            'pa_color' => $product['pa_color'],
            'pa_size'  => $product['pa_size']
        ];

        foreach ($map as $taxonomy => $values) {

            if (empty($values)) {
                continue;
            }

            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            wp_set_object_terms(
                $productId,
                $values,
                $taxonomy
            );

            $attribute = new \WC_Product_Attribute();

            $attribute->set_name($taxonomy);
            $attribute->set_options($values);
            $attribute->set_visible(true);
            $attribute->set_variation(true);

            $attributes[] = $attribute;
        }

        $wcProduct = wc_get_product($productId);
        $wcProduct->set_attributes($attributes);
        $wcProduct->save();

        return $attributes;
    }

    private function syncVariation(
        int $productId,
        array $product,
        array $attributes
    ): void {

        $existing = get_posts([
            'post_type' => 'product_variation',
            'post_parent' => $productId,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        foreach ($existing as $variationId) {
            wp_delete_post($variationId, true);
        }

        $variation = new \WC_Product_Variation();

        $variation->set_parent_id($productId);
        $variation->set_regular_price((string) $product['price']);
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity($product['stock']);
        $variation->set_stock_status(
            $product['stock'] > 0 ? 'instock' : 'outofstock'
        );

        $variationAttributes = [];

        if (!empty($product['pa_color'])) {
            $variationAttributes['pa_color'] = sanitize_title($product['pa_color'][0]);
        }

        if (!empty($product['pa_size'])) {
            $variationAttributes['pa_size'] = sanitize_title($product['pa_size'][0]);
        }

        $variation->set_attributes($variationAttributes);

        $variation->save();
    }
}
