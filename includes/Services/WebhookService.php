<?php
namespace Diprotec\ERP\Services;

class WebhookService
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        register_rest_route('diprotec/v1', '/update-stock', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_stock_update'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(\WP_REST_Request $request)
    {
        $secret = $request->get_header('X-Diprotec-Webhook-Secret');

        if (!$secret || $secret !== DIPROTEC_ERP_WEBHOOK_SECRET) {
            return new \WP_Error('unauthorized', 'Invalid or missing secret key', ['status' => 403]);
        }

        return true;
    }

    public function handle_stock_update($request)
    {
        $params = $request->get_json_params();

        // Expected format: { "sku": "XXX", "stock": 10 }
        $sku = $params['sku'] ?? null;
        $qty = $params['stock'] ?? null;

        if (!$sku) {
            return new \WP_Error('no_sku', 'SKU missing', ['status' => 400]);
        }

        $product_id = wc_get_product_id_by_sku($sku);

        if ($product_id) {
            $product = wc_get_product($product_id);

            $validator = new StockValidator(null); // Client not needed for simple validation
            $cleanStock = $validator->validate($qty);

            $product->set_stock_quantity($cleanStock);
            $product->save();

            return rest_ensure_response([
                'success' => true,
                'id' => $product_id,
                'new_stock' => $cleanStock
            ]);
        }

        return new \WP_Error('not_found', 'Product not found', ['status' => 404]);
    }
}
