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
     * Handle images v2.0 (Multi-image + Performance check)
     */
    public function handleImagesV2($product_id, $itemData)
    {
        $galleryIds = [];
        $mainImageSet = false;

        // Lista de campos de imagen
        $imageFields = ['ImagenPpal', 'Imagen01', 'Imagen02', 'Imagen03', 'Imagen04', 'Imagen05', 'Imagen06'];

        foreach ($imageFields as $field) {
            if (empty($itemData[$field]))
                continue;

            $filename = $itemData[$field];
            $attachmentId = $this->getOrUploadImage($product_id, $filename);

            if ($attachmentId) {
                if (!$mainImageSet && $field === 'ImagenPpal') {
                    set_post_thumbnail($product_id, $attachmentId);
                    $mainImageSet = true;
                } else {
                    $galleryIds[] = $attachmentId;
                }
            }
        }

        // Set gallery
        if (!empty($galleryIds)) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_gallery_image_ids($galleryIds);
                $product->save();
            }
        }
    }

    /**
     * Busca la imagen en la librería por nombre, si no existe la sube.
     */
    private function getOrUploadImage($product_id, $filename)
    {
        // 1. Performance Check: Buscar por título/nombre en BD
        // El título suele ser el filename sin extensión
        $title = preg_replace('/\.[^.]+$/', '', $filename);

        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'title' => $title,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ];

        $query = get_posts($args);
        if (!empty($query)) {
            return $query[0]; // Retorna ID existente
        }

        // 2. Si no existe, subir
        return $this->handleImage($product_id, $filename);
    }

    /**
     * Legacy method adjusted for v2 reuse
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

        // Note: Duplicate check logic moved to getOrUploadImage, but kept simple here for direct calls
        $upload_file = wp_upload_bits($filename, null, file_get_contents($source_file));

        if (!$upload_file['error']) {
            return $this->attach_image_to_product($product_id, $upload_file['file'], $filename);
        }

        return false;
    }

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

    private function attach_image_to_product($product_id, $file_path, $filename)
    {
        $wp_filetype = wp_check_filetype($filename, null);

        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        // Attach to product (parent)
        $attachment_id = wp_insert_attachment($attachment, $file_path, $product_id);

        if (!is_wp_error($attachment_id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);
            return $attachment_id;
        }

        return false;
    }
}
