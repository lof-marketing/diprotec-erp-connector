<?php

namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class FrontendIntegration
{

    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;

        // Availability Text
        add_filter('woocommerce_get_availability', [$this, 'custom_availability_text'], 10, 2);

        // Display Attributes
        add_action('woocommerce_single_product_summary', [$this, 'display_custom_attributes'], 25);
    }

    public function custom_availability_text($availability, $product)
    {
        $sku = $product->get_sku();
        if (empty($sku)) {
            return $availability;
        }

        // We need to fetch real-time stock from ERP (Mock) because WC stock might be out of sync 
        // or we want to show specific messages based on backorder flag from ERP.
        // However, for performance, usually we rely on WC data synced. 
        // But the guide says: "Si stock == 0 Y allow_backorder == true: Mostrar 'Disponible para reserva'".
        // WC has backorder support, but let's assume we want to override based on ERP response if needed,
        // or just use the data we synced to WC product.

        // Let's use the data from the product object first as it was synced.
        // If we want real-time check, we would call $this->client->getStock($sku).
        // Given the requirements "Si stock == 0 Y allow_backorder == true", let's check the ERP data directly 
        // to be sure we match the "mock-data" logic dynamically if the sync hasn't run recently?
        // Actually, the sync service sets _manage_stock and quantity. 
        // But "allow_backorder" logic in WC is usually "backorders allowed".
        // If we didn't set backorders allowed in WC during sync, WC would just say "Out of stock".

        // Let's fetch from client to be 100% accurate to the guide's "Logic" section which implies checking the flags.
        $stock_data = $this->client->getStock($sku);
        $qty = $stock_data['available_qty'];
        $backorder = $stock_data['allow_backorder'];

        if ($qty > 0) {
            $availability['availability'] = 'Disponible';
            $availability['class'] = 'in-stock';
        } elseif ($qty <= 0 && $backorder) {
            $availability['availability'] = 'Disponible para reserva';
            $availability['class'] = 'available-on-backorder';
        } elseif ($qty <= 0 && !$backorder) {
            $availability['availability'] = 'Agotado';
            $availability['class'] = 'out-of-stock';
        }

        return $availability;
    }

    public function display_custom_attributes()
    {
        global $product;
        $attributes = $product->get_attributes();

        if (empty($attributes)) {
            return;
        }

        echo '<div class="diprotec-custom-attributes" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">';
        echo '<h4>Especificaciones:</h4>';
        echo '<ul>';
        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                // Skip taxonomy attributes if handled by theme, or display them here too?
                // Guide says "Leer atributos... y renderizarlos".
                // Let's display all.
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
                echo '<li><strong>' . wc_attribute_label($attribute->get_name()) . ':</strong> ' . implode(', ', $terms) . '</li>';
            } else {
                echo '<li><strong>' . $attribute->get_name() . ':</strong> ' . implode(', ', $attribute->get_options()) . '</li>';
            }
        }
        echo '</ul>';
        echo '</div>';
    }
}
