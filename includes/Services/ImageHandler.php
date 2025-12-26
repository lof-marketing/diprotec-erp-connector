<?php

namespace Diprotec\ERP\Services;

class ImageHandler
{

    private $image_source_path;

    public function __construct($source_path = null)
    {
        // Default to a folder in uploads for testing if not specified
        // In production this might be an absolute path on the server
        $upload_dir = wp_upload_dir();
        $this->image_source_path = $source_path ?: $upload_dir['basedir'] . '/erp-images/';

        // Ensure directory exists for testing
        if (!file_exists($this->image_source_path)) {
            wp_mkdir_p($this->image_source_path);
        }
    }

    /**
     * Handle image import for a product.
     *
     * @param int    $product_id WC Product ID.
     * @param string $filename   Image filename.
     * @return int|bool Attachment ID on success, false on failure.
     */
    public function handle_image($product_id, $filename)
    {
        if (empty($filename)) {
            return false;
        }

        $source_file = $this->image_source_path . $filename;

        if (!file_exists($source_file)) {
            // Try to find it in the plugin assets for demo purposes if not found in source
            // This is just a fallback for the mock phase so we don't need to manually upload files to test
            $fallback_source = DIPROTEC_ERP_PATH . 'assets/' . $filename;
            if (file_exists($fallback_source)) {
                $source_file = $fallback_source;
            } else {
                return false;
            }
        }

        // Check if image is already attached to product to avoid duplicates
        // This is a simple check, could be more robust by checking filename in media library
        $existing_image_id = get_post_thumbnail_id($product_id);
        if ($existing_image_id) {
            // For now, assume if it has an image, it's fine. 
            // Real logic might check if filename matches.
            return $existing_image_id;
        }

        // Copy file to uploads directory
        $upload_file = wp_upload_bits($filename, null, file_get_contents($source_file));

        if (!$upload_file['error']) {
            $wp_filetype = wp_check_filetype($filename, null);

            $attachment = [
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit',
            ];

            $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $product_id);

            if (!is_wp_error($attachment_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
                wp_update_attachment_metadata($attachment_id, $attach_data);

                set_post_thumbnail($product_id, $attachment_id);

                return $attachment_id;
            }
        }

        return false;
    }
}
