<?php

namespace Diprotec\ERP\Services;

class ImageHandler
{

    private $image_source_path;

    public function __construct($source_path = null)
    {
        $upload_dir = wp_upload_dir();
        $this->image_source_path = $source_path ?: $upload_dir['basedir'] . '/erp-images/';

        if (!file_exists($this->image_source_path)) {
            wp_mkdir_p($this->image_source_path);
        }
    }

    /**
     * Handle image import for a product.
     *
     * @param int    $product_id WC Product ID.
     * @param string $filename   Image filename or URL.
     * @return int|bool Attachment ID on success, false on failure.
     */
    public function handleImage($product_id, $filename)
    {
        if (empty($filename)) {
            return false;
        }

        // Check if it's a URL or a local filename
        if (filter_var($filename, FILTER_VALIDATE_URL)) {
            return $this->handle_remote_image($product_id, $filename);
        }

        $source_file = $this->image_source_path . $filename;

        if (!file_exists($source_file)) {
            $fallback_source = DIPROTEC_ERP_PATH . 'assets/' . $filename;
            if (file_exists($fallback_source)) {
                $source_file = $fallback_source;
            } else {
                return false;
            }
        }

        $existing_image_id = get_post_thumbnail_id($product_id);
        if ($existing_image_id) {
            return $existing_image_id;
        }

        $upload_file = wp_upload_bits($filename, null, file_get_contents($source_file));

        if (!$upload_file['error']) {
            return $this->attach_image_to_product($product_id, $upload_file['file'], $filename);
        }

        return false;
    }

    /**
     * Handle remote image import.
     */
    private function handle_remote_image($product_id, $url)
    {
        $filename = basename($url);
        $response = wp_remote_get($url);

        if (is_wp_error($response))
            return false;

        $body = wp_remote_retrieve_body($response);
        $upload_file = wp_upload_bits($filename, null, $body);

        if (!$upload_file['error']) {
            return $this->attach_image_to_product($product_id, $upload_file['file'], $filename);
        }

        return false;
    }

    /**
     * Helper to attach image to product and generate metadata.
     */
    private function attach_image_to_product($product_id, $file_path, $filename)
    {
        $wp_filetype = wp_check_filetype($filename, null);

        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path, $product_id);

        if (!is_wp_error($attachment_id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);
            set_post_thumbnail($product_id, $attachment_id);
            return $attachment_id;
        }

        return false;
    }
}
