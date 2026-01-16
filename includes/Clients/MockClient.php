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
     * Updated to return new JSON v2 structure.
     *
     * @param string|null $modified_after Timestamp to filter products.
     * @return array
     */
    public function getProducts(?string $modified_after = null): array
    {
        // v2.0 Mock Data hardcoded or loaded from file to match JSON_Consulta_15-01.json
        // For simplicity and robustness, I'll return the array structure directly here matching the guide examples

        return [
            "Estado" => 200,
            "Respuesta" => "TRANSACCION_OK",
            "Data" => [
                [
                    "CategoriaId" => "PC018",
                    "CategoriaNombre" => "BIOMETRIA",
                    "SubCategoriaId" => "PSC0005",
                    "SubCategoriaNombre" => "ACCESORIOS",
                    "Id" => "PRO-0006996",
                    "MarcaNombre" => "ACER",
                    "Partnumber" => "123456789",
                    "Atributos" => "ATR1/ATR2/ATR3",
                    "Descripcion" => "IMPRESORA ZEBRA MOCK",
                    "ImagenPpal" => "P4D-0UB10000-00.jpg", // Reusing existing assets
                    "Imagen01" => "CABLE-UTP-CAT6.jpg",
                    "PrecioLista" => 163249,
                    "PrecioOferta" => 0,
                    "Stock" => 15
                ],
                [
                    "CategoriaId" => "PC018",
                    "CategoriaNombre" => "CONECTIVIDAD",
                    "SubCategoriaId" => "PSC0005",
                    "SubCategoriaNombre" => "CABLES",
                    "Id" => "PRO-0006995",
                    "MarcaNombre" => "DIPRONET",
                    "Partnumber" => "CABLE-UTP-CAT6",
                    "Atributos" => "CAT6/GRIS/300M",
                    "Descripcion" => "BOBINA CABLE UTP",
                    "ImagenPpal" => "CABLE-UTP-CAT6.jpg",
                    "PrecioLista" => 95000,
                    "PrecioOferta" => 85000,
                    "Stock" => 0
                ],
                [
                    "CategoriaId" => "PC021",
                    "CategoriaNombre" => "LECTORES",
                    "SubCategoriaId" => "PSC0099",
                    "SubCategoriaNombre" => "LECTORES DE BARRA",
                    "Id" => "PRO-0006994",
                    "MarcaNombre" => "HONEYWELL",
                    "Partnumber" => "HON-1900GSR-2",
                    "Atributos" => "USB/NEGRO",
                    "Descripcion" => "LECTOR HONEYWELL",
                    "ImagenPpal" => "HON-1900GSR-2.jpg",
                    "PrecioLista" => 1484080,
                    "PrecioOferta" => 0,
                    "Stock" => 0
                ]
            ],
            "CodigoError" => null,
            "CorrelationId" => null
        ];
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

    public function createOrder(array $order_payload): array
    {
        return ['status' => 'success', 'erp_id' => 'MOCK-' . rand(1000, 9999)];
    }

    // Stub for v2 compatible interface check if called
    public function getCustomerByRut($rut)
    {
        return [
            "success" => true,
            "data" => [
                "business_name" => "Empresa Mock S.A.",
                "giro" => "Venta de Tecnología",
                "addresses" => [
                    ["id" => "DIR1", "address" => "Av. Mock 123", "type" => "billing"],
                    ["id" => "DIR2", "address" => "Bodega Mock 456", "type" => "shipping"]
                ]
            ]
        ];
    }
}
