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
     * Loads from mock-data/products_catalog.json.
     *
     * @param string|null $modified_after Timestamp to filter products.
     * @return array
     */
    public function getProducts(?string $modified_after = null): array
    {
        $file_path = $this->mock_data_path . 'products_catalog.json';
        if (!file_exists($file_path)) {
            return [
                "Estado" => 500,
                "Respuesta" => "MOCK_FILE_NOT_FOUND",
                "Data" => [],
                "CodigoError" => "File not found: " . $file_path
            ];
        }

        $json_content = file_get_contents($file_path);
        return json_decode($json_content, true) ?: [];
    }

    /**
     * Get stock for a specific SKU (Mock).
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
     */
    public function createOrder(array $order_payload): array
    {
        $file_path = $this->mock_data_path . 'order_responses.json';
        if (!file_exists($file_path)) {
            return [
                "Estado" => 500,
                "Respuesta" => "MOCK_FILE_NOT_FOUND",
                "Data" => null
            ];
        }

        $json_content = file_get_contents($file_path);
        $templates = json_decode($json_content, true);

        // Return success by default, simulate error if certain RUT is used
        if (isset($order_payload['RUT']) && strpos($order_payload['RUT'], 'ERROR') !== false) {
            return $templates['error'] ?? [];
        }

        return $templates['success'] ?? [];
    }

    /**
     * Obtiene datos del cliente por RUT (Mock).
     */
    public function getCustomerByRut(string $rut): ?array
    {
        $file_path = $this->mock_data_path . 'customer_info.json';
        if (!file_exists($file_path)) {
            return null;
        }

        $json_content = file_get_contents($file_path);
        $customers = json_decode($json_content, true);

        $response = $customers[$rut] ?? null;

        if ($response && isset($response['Data'][0])) {
            return $response['Data'][0];
        }

        return null;
    }
}
