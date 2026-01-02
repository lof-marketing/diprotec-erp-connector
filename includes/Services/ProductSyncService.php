<?php
namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class ProductSyncService
{

    private $client;
    private $imageHandler;
    private $stockValidator;

    public function __construct(ClientInterface $client, ImageHandler $imageHandler)
    {
        $this->client = $client;
        $this->imageHandler = $imageHandler;
        $this->stockValidator = new StockValidator($client);
    }

    public function syncAllProducts()
    {
        // 1. Obtener datos crudos del ERP
        $erpData = $this->client->getProducts();

        if (empty($erpData)) {
            return ['status' => 'error', 'message' => 'No se recibieron datos del ERP'];
        }

        $processed = 0;
        $errors = 0;
        $log = [];

        // 2. Iterar y procesar
        foreach ($erpData as $item) {
            try {
                $this->processSingleProduct($item);
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $sku = isset($item['PRO_PARTNUMBER']) ? $item['PRO_PARTNUMBER'] : 'UNKNOWN';
                $log[] = "Error en SKU {$sku}: " . $e->getMessage();
            }
        }

        return [
            'status' => 'completed',
            'processed' => $processed,
            'errors' => $errors,
            'details' => $log
        ];
    }

    private function processSingleProduct($item)
    {
        // Mapear datos crudos a estructura de WooCommerce
        $data = $this->mapProductData($item);

        if (!$data['sku']) {
            throw new \Exception("Producto sin SKU válido");
        }

        // Buscar si existe el producto por SKU
        $productId = wc_get_product_id_by_sku($data['sku']);

        if ($productId) {
            $product = wc_get_product($productId);
        } else {
            $product = new \WC_Product_Simple();
        }

        // Asignar propiedades básicas
        $product->set_name($data['name']);
        $product->set_sku($data['sku']);
        $product->set_regular_price($data['price']);

        // Gestión de Inventario
        $product->set_manage_stock(true);
        // Validamos stock negativo o inválido antes de asignar
        $cleanStock = $this->stockValidator->validate($data['stock']);
        $product->set_stock_quantity($cleanStock);

        // Descripción (Usamos la corta si la larga está vacía)
        $product->set_description(!empty($data['description']) ? $data['description'] : $data['short_description']);

        // Manejo de Imágenes
        if (!empty($item['IMAGE_URL'])) {
            // Updated to use the correct method name in refactored ImageHandler
            $imageId = $this->imageHandler->handleImage($product->get_id(), $item['IMAGE_URL']);
            if ($imageId) {
                $product->set_image_id($imageId);
            }
        }

        $product->save();
    }

    /**
     * Mapea los campos del ERP (Basado en PRODUCTOS.xlsx) a WooCommerce.
     * Esta función es CRÍTICA para que funcione el cambio el 15 de Enero.
     */
    private function mapProductData($erpItem)
    {
        // Ajuste defensivo: asegurarnos que las claves existan, si no, poner defaults
        return [
            // Mapeo directo de las columnas del Excel PRODUCTOS.xlsx
            'sku' => $erpItem['PRO_PARTNUMBER'] ?? '',     // Columna F
            'name' => $erpItem['PRO_DESCRIPCION'] ?? 'Producto sin nombre', // Columna I
            'description' => $erpItem['PRO_DESCRIPCION'] ?? '',    // Columna I
            'short_description' => $erpItem['PRO_ATRIBUTOS'] ?? '',      // Columna G (Atributos como desc corta)

            // Lógica de Precios
            'price' => isset($erpItem['PRECIO_VENTA']) ? $erpItem['PRECIO_VENTA'] : 0,

            // Stock
            'stock' => $erpItem['PRO_STOCK'] ?? 0, // Columna N

            // Metadatos adicionales que podríamos guardar para uso futuro
            'meta' => [
                'pro_id' => $erpItem['PRO_ID'] ?? '',
                'category_id' => $erpItem['PC_ID'] ?? '',
                'subcategory_id' => $erpItem['PSC_ID'] ?? ''
            ]
        ];
    }
}
