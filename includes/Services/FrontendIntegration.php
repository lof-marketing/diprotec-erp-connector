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

        // Display Attributes (Frontend Display)
        add_action('woocommerce_single_product_summary', [$this, 'display_custom_attributes'], 25);

        // v2.0 Checkout AJAX & Scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);
        add_action('wp_ajax_diprotec_get_customer', [$this, 'ajax_get_customer']);
        add_action('wp_ajax_nopriv_diprotec_get_customer', [$this, 'ajax_get_customer']);
    }

    public function enqueue_checkout_scripts()
    {
        if (is_checkout() && !is_order_received_page()) {
            // Inline script for simplicity, or could serve a separate file.
            // Using inline for "Code in one file" preference unless complex.
            wp_register_script('diprotec-checkout', false);
            wp_enqueue_script('diprotec-checkout');

            $js_code = "
            jQuery(document).ready(function($) {
                // Ensure the event handler is attached
                $(document).on('blur', '#billing_rut', function() {
                    var rut = $(this).val();
                    if (!rut) return;

                    // Indicator logic if you have one, or just simple UI blocking
                    // $('.diprotec-loader').show(); 

                    $.ajax({
                        url: '" . admin_url('admin-ajax.php') . "',
                        type: 'POST',
                        data: {
                            action: 'diprotec_get_customer',
                            rut: rut
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                // Assume Data format from RestClient which wraps it or direct
                                // If using MockClient example: { success: true, data: { business_name: ... } }
                                // If response itself is success wrapper from wp_send_json_success ($data)
                                // data would be the array returned by getCustomerByRut
                                
                                var customerArgs = response.data.data; // Because wp_send_json_success wraps our array in 'data'
                                if (!customerArgs) customerArgs = response.data; // Fallback

                                if (customerArgs.business_name) {
                                    $('#billing_company').val(customerArgs.business_name);
                                }
                                // More fields ...
                            }
                        },
                        complete: function() {
                             // $('.diprotec-loader').hide();
                        }
                    });
                });
            });
            ";
            wp_add_inline_script('diprotec-checkout', $js_code);
        }
    }

    public function ajax_get_customer()
    {
        $rut = isset($_POST['rut']) ? sanitize_text_field($_POST['rut']) : '';

        if (empty($rut)) {
            wp_send_json_error(['message' => 'RUT vacío']);
        }

        if (method_exists($this->client, 'getCustomerByRut')) {
            $data = $this->client->getCustomerByRut($rut);

            // Allow MockClient to return full structure or just data
            // If it returns ['success' => true, 'data' => ...], extract data
            // If it returns just array of fields, use it.

            if ($data) {
                wp_send_json_success($data);
            } else {
                wp_send_json_error(['message' => 'Cliente no encontrado']);
            }
        } else {
            wp_send_json_error(['message' => 'Método no soportado']);
        }
    }

    public function custom_availability_text($availability, $product)
    {
        $sku = $product->get_sku();
        if (empty($sku)) {
            return $availability;
        }

        $stock_data = $this->client->getStock($sku);
        $qty = $stock_data['available_qty'] ?? 0;
        $backorder = $stock_data['allow_backorder'] ?? false;

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

        echo '<div class="diprotec-custom-attributes" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">';
        echo '<h4>Especificaciones:</h4>';
        echo '<ul>';

        // Taxonomy 'pa_especificaciones'
        $specs = wc_get_product_terms($product->get_id(), 'pa_especificaciones', array('fields' => 'names'));
        if (!empty($specs) && !is_wp_error($specs)) {
            foreach ($specs as $spec) {
                echo '<li>' . esc_html($spec) . '</li>';
            }
        }

        // Fallback or other attributes
        $attributes = $product->get_attributes();
        foreach ($attributes as $attribute) {
            if ($attribute->get_name() === 'pa_especificaciones')
                continue;

            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
                if (!is_wp_error($terms)) {
                    echo '<li><strong>' . wc_attribute_label($attribute->get_name()) . ':</strong> ' . implode(', ', $terms) . '</li>';
                }
            } else {
                echo '<li><strong>' . $attribute->get_name() . ':</strong> ' . implode(', ', $attribute->get_options()) . '</li>';
            }
        }
        echo '</ul>';
        echo '</div>';
    }
}
