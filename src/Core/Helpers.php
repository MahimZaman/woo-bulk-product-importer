<?php

namespace WBPI\Core;

defined('ABSPATH') || exit;

class Helpers
{
    public static function text($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function html($value): string
    {
        return wp_kses_post((string) $value);
    }

    public static function slug($value): string
    {
        return sanitize_title((string) $value);
    }

    public static function int($value): int
    {
        return (int) $value;
    }

    public static function float($value): float
    {
        return (float) $value;
    }

    public static function bool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function csv($value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    public static function json($value): string
    {
        return wp_json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public static function decode($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function batchKey(): string
    {
        return 'wbpi_' . wp_generate_uuid4();
    }

    public static function now(): string
    {
        return current_time('mysql');
    }

    public static function secondsToTime(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds >= 3600) {
            return gmdate('H:i:s', $seconds);
        }

        return gmdate('i:s', $seconds);
    }

    public static function isImage(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'bmp',
            'avif'
        ], true);
    }

    public static function normalizeProduct(array $product): array
    {
        return [
            'row_number'         => self::int($product['row_number'] ?? 0),
            'sku'                => self::text($product['sku'] ?? ''),
            'product_name'       => self::text($product['product_name'] ?? ''),
            'slug'               => self::slug($product['slug'] ?? ''),
            'price'              => self::float($product['price'] ?? 0),
            'stock'              => self::int($product['stock'] ?? 0),
            'status'             => self::text('publish' ?? 'publish'),
            'categories'         => self::csv($product['categories'] ?? ''),
            'tags'               => self::csv($product['tags'] ?? ''),
            'brand'              => self::text($product['brand'] ?? ''),
            'images'             => self::csv($product['images'] ?? ''),
            'pa_color'           => self::csv($product['pa_color'] ?? ''),
            'pa_size'            => self::csv($product['pa_size'] ?? ''),
            'short_description'  => self::html($product['short_description'] ?? ''),
            'long_description'   => self::html($product['long_description'] ?? ''),
            'technical_specs'    => self::html($product['technical_specs'] ?? ''),
            'whats_included'      => self::html($product['whats_included'] ?? ''),
        ];
    }
}
