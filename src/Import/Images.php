<?php

namespace WBPI\Import;

use WBPI\Core\Helpers;

defined('ABSPATH') || exit;

class Images
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger();

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    public function sync(int $productId, array $images): void
    {
        if (empty($images)) {
            return;
        }

        $gallery = [];

        foreach ($images as $index => $url) {

            $url = trim($url);

            if (!Helpers::isImage($url)) {
                continue;
            }

            $attachmentId = $this->findExisting($url);

            if (!$attachmentId) {
                $attachmentId = $this->download($url);
            }

            if (!$attachmentId) {
                continue;
            }

            if ($index === 0) {
                set_post_thumbnail($productId, $attachmentId);
            } else {
                $gallery[] = $attachmentId;
            }
        }

        update_post_meta(
            $productId,
            '_product_image_gallery',
            implode(',', $gallery)
        );
    }

    public function download(string $url): int|false
    {
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            $this->logger->warning(
                'Image download failed.',
                ['url' => $url]
            );

            return false;
        }

        $file = [
            'name' => wp_basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ];

        $attachmentId = media_handle_sideload($file, 0);

        if (is_wp_error($attachmentId)) {

            @unlink($tmp);

            $this->logger->warning(
                'Image upload failed.',
                [
                    'url' => $url,
                    'error' => $attachmentId->get_error_message(),
                ]
            );

            return false;
        }

        update_post_meta(
            $attachmentId,
            '_wbpi_source_url',
            esc_url_raw($url)
        );

        return (int) $attachmentId;
    }

    public function findExisting(string $url): int|false
    {
        global $wpdb;

        $attachmentId = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_wbpi_source_url'
                AND meta_value = %s
                LIMIT 1
                ",
                esc_url_raw($url)
            )
        );

        return $attachmentId ? (int) $attachmentId : false;
    }

    public function deleteGallery(int $productId): void
    {
        delete_post_meta($productId, '_product_image_gallery');
        delete_post_thumbnail($productId);
    }

    public function imageExists(string $url): bool
    {
        return (bool) $this->findExisting($url);
    }
}
