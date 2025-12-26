<?php

namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class StockValidator
{

    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        add_action('woocommerce_check_cart_items', [$this, 'validate_cart_stock']);
    }

    public function validate_cart_stock()
    {
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

            $stock_data = $this->client->getStock($sku);

            // Logic:
            // If available_qty >= requested qty -> OK
            // If available_qty < requested qty AND allow_backorder is TRUE -> OK
            // Else -> Error

            $available = $stock_data['available_qty'];
            $backorder = $stock_data['allow_backorder'];

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
