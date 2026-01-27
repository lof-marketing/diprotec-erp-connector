<?php
namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class ProductSyncService
{

    private $client;
    private $imageHandler;
    private $stockValidator;
    private $temp_dir;
    private $temp_file;

    public function __construct(ClientInterface $client, ImageHandler $imageHandler)
    {
        $this->client = $client;
        $this->imageHandler = $imageHandler;
        $this->stockValidator = new StockValidator($client);

        $upload_dir = wp_upload_dir();
        $this->temp_dir = trailingslashit($upload_dir['basedir']) . 'diprotec-sync';
        $this->temp_file = $this->temp_dir . '/erp_data_cache.json';

        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }
    }

    /**
     * PASO 1: Descargar todo el JSON y guardarlo en cache local
     */
    public function downloadAndCache()
    {
        $response = $this->client->getProducts();

        if (empty($response) || !isset($response['Data'])) {
            throw new \Exception("Estructura de API inválida o sin datos.");
        }

        $erpData = $response['Data'];

        if (empty($erpData)) {
            throw new \Exception("El ERP retornó una lista vacía.");
        }

        // Generar un ID único para este proceso completo (timestamp)
        $current_sync_id = time();
        update_option('diprotec_current_sync_id', $current_sync_id);

        // Guardar a archivo temporal
        $success = file_put_contents($this->temp_file, json_encode($erpData));

        if ($success === false) {
            throw new \Exception("No se pudo escribir el archivo temporal en {$this->temp_file}");
        }

        return count($erpData);
    }

    /**
     * PASO 2: Procesar un lote específico del archivo cacheado
     */
    public function syncBatch($offset = 0, $limit = 25)
    {
        if (!file_exists($this->temp_file)) {
            throw new \Exception("Archivo de cache no encontrado. Reinicie la sincronización.");
        }

        $json = file_get_contents($this->temp_file);
        $erpData = json_decode($json, true);

        if (!$erpData) {
            throw new \Exception("Error al decodificar el cache JSON.");
        }

        $total = count($erpData);
        $batch = array_slice($erpData, $offset, $limit);

        $processed = 0;
        $errors = 0;
        $log = [];

        foreach ($batch as $item) {
            try {
                $this->processSingleProduct($item);
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $id = $item['Id'] ?? ($item['Partnumber'] ?? 'UNKNOWN');
                $log[] = "Error en ID {$id}: " . $e->getMessage();
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
            'details' => $log,
            'is_finished' => ($offset + $limit) >= $total,
            'current_progress' => min($offset + $limit, $total),
            'total' => $total
        ];
    }

    /**
     * PASO 3: Limpiar archivos temporales
     */
    public function cleanup()
    {
        if (file_exists($this->temp_file)) {
            unlink($this->temp_file);
        }
        return true;
    }

    /**
     * Legacy method (kept for potential CLI usage)
     */
    public function syncAllProducts()
    {
        // ... (Mantener por compatibilidad si es necesario, pero el flow ahora es via AJAX)
        $count = $this->downloadAndCache();
        return $this->syncBatch(0, $count);
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

        // MARCAR: Guardamos el ID de esta sincronización para saber que este producto está "vivo"
        $currentSyncId = get_option('diprotec_current_sync_id');
        $product->update_meta_data('_diprotec_last_sync_id', $currentSyncId);

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

    /**
     * PASO 4: Desactivar productos que no vinieron en la última sincronización.
     */
    public function processDeletions($offset = 0, $limit = 50)
    {
        $current_sync_id = get_option('diprotec_current_sync_id');

        if (!$current_sync_id)
            return 0;

        // Buscamos productos que TIENEN ID de Diprotec, pero su last_sync_id es DIFERENTE al actual
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'fields' => 'ids',
            'post_status' => ['publish', 'private'], // Buscar en publicados y privados
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_diprotec_pro_id', // Solo productos del ERP
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => '_diprotec_last_sync_id',
                    'value' => $current_sync_id,
                    'compare' => '!=' // <--- La clave: No se actualizaron en esta vuelta
                ]
            ]
        ];

        // Necesitamos saber el total para el JS
        $total_args = $args;
        $total_args['posts_per_page'] = -1;
        $total_args['offset'] = 0;
        $total_args['fields'] = 'ids';
        $all_to_remove = get_posts($total_args);
        $total_to_remove = count($all_to_remove);

        $products_to_remove = get_posts($args);
        $count = 0;

        foreach ($products_to_remove as $post_id) {
            $product = wc_get_product($post_id);
            if ($product) {
                // Poner en borrador y stock 0
                $product->set_status('draft');
                $product->set_stock_quantity(0);
                $product->save();
                $count++;
            }
        }

        return [
            'processed' => $count,
            'total' => $total_to_remove,
            'is_finished' => ($offset + $limit) >= $total_to_remove
        ];
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
