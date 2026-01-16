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
        // 1. Obtener datos crudos del ERP (Nuevo formato JSON)
        $response = $this->client->getProducts();

        // Validar estructura base de respuesta
        if (empty($response) || !isset($response['Data'])) {
            // Fallback para estructura simple si no viene con "Data" wrapper (por si acaso Mock antiguo)
            if (is_array($response) && !isset($response['Data'])) {
                $erpData = $response;
            } else {
                return ['status' => 'error', 'message' => 'Estructura de datos inválida'];
            }
        } else {
            $erpData = $response['Data'];
        }

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
                $id = isset($item['Id']) ? $item['Id'] : (isset($item['Partnumber']) ? $item['Partnumber'] : 'UNKNOWN');
                $log[] = "Error en ID {$id}: " . $e->getMessage();
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
        $erpId = $item['Id'] ?? null;
        $sku = $item['Partnumber'] ?? '';

        if (!$erpId) {
            throw new \Exception("Producto sin ID Diprotec (Id)");
        }

        // 1. Estrategia de Búsqueda: Meta ID -> SKU -> Nuevo
        $productId = $this->findProduct($erpId, $sku);

        if ($productId) {
            $product = wc_get_product($productId);
        } else {
            $product = new \WC_Product_Simple();
        }

        // Asignar ID Interno para futuras syncs
        $product->update_meta_data('_diprotec_pro_id', $erpId);

        // 2. Campos Básicos
        $product->set_sku($sku);
        $product->set_name($item['Descripcion'] ?? 'Producto sin nombre');
        $product->set_description($item['Descripcion'] ?? ''); // Descripción larga
        // $product->set_short_description($item['Descripcion'] ?? ''); 

        // 3. Precios
        $precioLista = isset($item['PrecioLista']) ? floatval($item['PrecioLista']) : 0;
        $precioOferta = isset($item['PrecioOferta']) ? floatval($item['PrecioOferta']) : 0;

        $product->set_regular_price($precioLista);
        if ($precioOferta > 0 && $precioOferta < $precioLista) {
            $product->set_sale_price($precioOferta);
        } else {
            $product->set_sale_price('');
        }

        // 4. Stock
        $product->set_manage_stock(true);
        $cleanStock = $this->stockValidator->validate($item['Stock'] ?? 0);
        $product->set_stock_quantity($cleanStock);

        // 5. Categorías (Jerarquía)
        $this->assignCategories($product, $item);

        // 6. Atributos (Especificaciones) + Marca
        $this->assignAttributes($product, $item);

        $product->save();

        // 7. Imágenes (v2.0 Multi-imagen con validación de performance)
        // Pasamos el ID del producto guardado
        $this->imageHandler->handleImagesV2($product->get_id(), $item);
    }

    private function findProduct($erpId, $sku)
    {
        // A. Buscar por Meta ID (Prioridad máxima)
        $args = [
            'post_type' => 'product',
            'meta_key' => '_diprotec_pro_id',
            'meta_value' => $erpId,
            'fields' => 'ids',
            'limit' => 1
        ];
        $query = get_posts($args);

        if (!empty($query)) {
            return $query[0];
        }

        // B. Buscar por SKU (Migración)
        if ($sku) {
            $idBySku = wc_get_product_id_by_sku($sku);
            if ($idBySku) {
                return $idBySku;
            }
        }

        return false;
    }

    private function assignCategories($product, $item)
    {
        // CategoriaId, CategoriaNombre, SubCategoriaId, SubCategoriaNombre
        // Priorizamos Nombre para buscar/crear
        $catName = $item['CategoriaNombre'] ?? null;
        $subCatName = $item['SubCategoriaNombre'] ?? null;

        if (!$catName)
            return;

        // 1. Padre
        $parentId = $this->getOrCreateTerm($catName, 'product_cat');
        $categoryIds = [$parentId];

        // 2. Hijo
        if ($subCatName) {
            $childId = $this->getOrCreateTerm($subCatName, 'product_cat', $parentId);
            $categoryIds[] = $childId;
        }

        $product->set_category_ids($categoryIds);
    }

    private function assignAttributes($product, $item)
    {
        $attributes = [];

        // A. Marca (pa_marca)
        if (!empty($item['MarcaNombre'])) {
            $this->getOrCreateTerm($item['MarcaNombre'], 'pa_marca'); // Asegurar existencia

            $attrMarca = new \WC_Product_Attribute();
            $attrMarca->set_name('pa_marca');
            $attrMarca->set_options([$item['MarcaNombre']]);
            $attrMarca->set_visible(true);
            $attrMarca->set_variation(false);
            $attributes[] = $attrMarca;
        }

        // B. Especificaciones (pa_especificaciones) - Explode '/'
        if (!empty($item['Atributos'])) {
            $specs = explode('/', $item['Atributos']);
            $specs = array_map('trim', $specs);
            $specs = array_filter($specs);

            if (!empty($specs)) {
                // Crear términos si no existen
                foreach ($specs as $spec) {
                    $this->getOrCreateTerm($spec, 'pa_especificaciones');
                }

                $attrSpecs = new \WC_Product_Attribute();
                $attrSpecs->set_name('pa_especificaciones');
                $attrSpecs->set_options($specs);
                $attrSpecs->set_visible(true);
                $attrSpecs->set_variation(false);
                $attributes[] = $attrSpecs;
            }
        }

        $product->set_attributes($attributes);
    }

    private function getOrCreateTerm($termName, $taxonomy, $parentId = 0)
    {
        if (empty($termName))
            return 0;

        $term = term_exists($termName, $taxonomy, $parentId);

        if ($term) {
            return is_array($term) ? $term['term_id'] : $term;
        }

        $args = [];
        if ($parentId > 0) {
            $args['parent'] = $parentId;
        }

        $newTerm = wp_insert_term($termName, $taxonomy, $args);

        if (!is_wp_error($newTerm)) {
            return $newTerm['term_id'];
        }

        return 0;
    }
}
