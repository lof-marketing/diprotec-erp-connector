<?php

namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class StockValidator
{

    private $client;

    public function __construct(ClientInterface $client = null)
    {
        $this->client = $client;

        // Only add action if client is provided (for frontend/checkout validation)
        if ($this->client) {
            add_action('woocommerce_check_cart_items', [$this, 'validate_cart_stock']);
        }
    }

    /**
     * Valida y limpia el valor de stock.
     * Asegura que no sea negativo o inválido.
     */
    public function validate($stock)
    {
        $clean = intval($stock);
        return $clean >= 0 ? $clean : 0;
    }

    public function validate_cart_stock()
    {
        if (!$this->client)
            return;

        if (WC()->cart->is_empty()) {
            return;
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $sku = $product->get_sku();
            $qty = $cart_item['quantity'];

            if (empty($sku)) {
                continue;
            }

            $erpId = $product->get_meta('_diprotec_pro_id');

            // Si estamos en modo Producción y tenemos ID, lo usamos. Si no, SKU.
            $identifier = (!DIPROTEC_ERP_USE_MOCK && $erpId) ? $erpId : $sku;

            $stock_data = $this->client->getStock($identifier);

            $available = isset($stock_data['available_qty']) ? $stock_data['available_qty'] : 0;
            $backorder = isset($stock_data['allow_backorder']) ? $stock_data['allow_backorder'] : false;

            if ($available < $qty && !$backorder) {
                wc_add_notice(
                    sprintf(
                        'El producto "%s" no tiene stock suficiente y no permite reserva. Stock disponible: %d.',
                        $product->get_name(),
                        $available
                    ),
                    'error'
                );
            }
        }
    }
}
