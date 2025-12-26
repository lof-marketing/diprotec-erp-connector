<?php

namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class ProductSyncService
{

    private $client;
    private $image_handler;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        $this->image_handler = new ImageHandler();

        // Register Admin Menu for manual sync
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public function register_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            'Sincronizar ERP',
            'Sincronizar ERP',
            'manage_options',
            'diprotec-erp-sync',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        if (isset($_POST['diprotec_run_sync']) && check_admin_referer('diprotec_sync_action', 'diprotec_sync_nonce')) {
            $this->sync_products();
            echo '<div class="notice notice-success"><p>Sincronización completada.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Sincronización ERP Diprotec</h1>
            <p>Haga clic en el botón para sincronizar productos desde el ERP (Simulado).</p>
            <form method="post">
                <?php wp_nonce_field('diprotec_sync_action', 'diprotec_sync_nonce'); ?>
                <input type="hidden" name="diprotec_run_sync" value="1">
                <?php submit_button('Sincronizar Productos'); ?>
            </form>
        </div>
        <?php
    }

    public function sync_products()
    {
        $products = $this->client->getProducts();

        foreach ($products as $erp_product) {
            $this->process_product($erp_product);
        }
    }

    private function process_product($erp_product)
    {
        $sku = $erp_product['sku'];
        $product_id = wc_get_product_id_by_sku($sku);

        if ($product_id) {
            $product = wc_get_product($product_id);
        } else {
            $product = new \WC_Product_Simple();
            $product->set_sku($sku);
        }

        // Basic Fields
        $product->set_name($erp_product['name']);
        $product->set_description($erp_product['description']);

        // Prices
        $product->set_regular_price($erp_product['prices']['web_price']);

        $offer_price = $erp_product['prices']['offer_price'];
        if ($offer_price > 0 && $offer_price < $erp_product['prices']['web_price']) {
            $product->set_sale_price($offer_price);
        } else {
            $product->set_sale_price('');
        }

        // Stock
        $product->set_manage_stock(true);
        $product->set_stock_quantity($erp_product['stock']['quantity']);

        // Category (Simple logic: create if not exists)
        if (isset($erp_product['category'])) {
            $cat_name = $erp_product['category']['name'];
            $term = term_exists($cat_name, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($cat_name, 'product_cat');
            }
            if (!is_wp_error($term) && isset($term['term_id'])) {
                $product->set_category_ids([$term['term_id']]);
            }
        }

        // Attributes
        if (isset($erp_product['attributes']) && is_array($erp_product['attributes'])) {
            $attributes = [];
            foreach ($erp_product['attributes'] as $attr) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name($attr['name']);
                $attribute->set_options([$attr['value']]);
                $attribute->set_position(0);
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }
            $product->set_attributes($attributes);
        }

        // Status
        $product->set_status($erp_product['active'] ? 'publish' : 'draft');

        $product->save();

        // Image
        if (isset($erp_product['image_filename'])) {
            $this->image_handler->handle_image($product->get_id(), $erp_product['image_filename']);
        }
    }
}
