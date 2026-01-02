<?php

namespace Diprotec\ERP\Clients;

use Diprotec\ERP\Interfaces\ClientInterface;

class MockClient implements ClientInterface
{

    private $mock_data_path;

    public function __construct()
    {
        $this->mock_data_path = DIPROTEC_ERP_PATH . 'mock-data/';
    }

    /**
     * Get products from ERP (Mock).
     * Updated to return data with Excel-style keys for testing.
     *
     * @param string|null $modified_after Timestamp to filter products.
     * @return array
     */
    public function getProducts(?string $modified_after = null): array
    {
        $file_path = $this->mock_data_path . 'products_catalog.json';

        if (!file_exists($file_path)) {
            return [];
        }

        $json_content = file_get_contents($file_path);
        $data = json_decode($json_content, true);

        if (!isset($data['status']) || $data['status'] !== 'success') {
            return [];
        }

        $products = $data['data'] ?? [];

        // Transform the mock data to match the new mapping (PRO_PARTNUMBER, etc.)
        // This simulates the real ERP response based on the provide Excel structure.
        return array_map(function ($p) {
            return [
                'PRO_PARTNUMBER' => $p['sku'],
                'PRO_DESCRIPCION' => $p['name'],
                'PRO_ATRIBUTOS' => isset($p['attributes']) ? json_encode($p['attributes']) : '',
                'PRECIO_VENTA' => $p['prices']['offer_price'] > 0 ? $p['prices']['offer_price'] : $p['prices']['web_price'],
                'PRO_STOCK' => $p['stock']['quantity'],
                'IMAGE_URL' => $p['image_filename'], // Local filename for now
                'PRO_ID' => 'MOCK-' . $p['sku'],
                'PC_ID' => $p['category']['id'] ?? '',
                'PSC_ID' => ''
            ];
        }, $products);
    }

    /**
     * Get stock for a specific SKU (Mock).
     *
     * @param string $sku Product SKU.
     * @return array
     */
    public function getStock(string $sku): array
    {
        $file_path = $this->mock_data_path . 'stock_responses.json';

        if (!file_exists($file_path)) {
            return ['available_qty' => 0, 'allow_backorder' => false];
        }

        $json_content = file_get_contents($file_path);
        $data = json_decode($json_content, true);

        return $data[$sku] ?? ['available_qty' => 0, 'allow_backorder' => false];
    }

    /**
     * Create an order in ERP (Mock).
     *
     * @param array $order_payload Order data.
     * @return array
     */
    public function createOrder(array $order_payload): array
    {
        // Log the payload
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->info('Mock Order Created: ' . print_r($order_payload, true), ['source' => 'diprotec-erp-mock']);
        } else {
            error_log('Mock Order Created: ' . print_r($order_payload, true));
        }

        return [
            'status' => 'success',
            'erp_id' => 'MOCK-' . rand(1000, 9999),
        ];
    }
}
