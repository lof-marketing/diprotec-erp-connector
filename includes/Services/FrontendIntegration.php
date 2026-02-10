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

        // Checkout Billing Fields
        add_filter('woocommerce_billing_fields', [$this, 'add_billing_fields'], 20);
    }

    public function enqueue_checkout_scripts()
    {
        if (is_checkout() && !is_order_received_page()) {
            wp_register_script('diprotec-checkout', false);
            wp_enqueue_script('diprotec-checkout');

            // Pasamos la URL de admin-ajax al script
            wp_localize_script('diprotec-checkout', 'diprotec_vars', [
                'ajax_url' => admin_url('admin-ajax.php')
            ]);

            $js_code = "
            jQuery(document).ready(function($) {
                $(document).on('change', '#billing_rut', function() {
                    var rut = $(this).val();
                    if (rut.length < 8) return;

                    // Bloquear UI
                    $('.woocommerce-checkout').addClass('processing');
                    $('#billing_company').attr('placeholder', 'Buscando...');

                    $.ajax({
                        url: diprotec_vars.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'diprotec_get_customer',
                            rut: rut
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                var data = response.data;
                                
                                // 1. Datos Básicos
                                if (data.Nombre) $('#billing_company').val(data.Nombre);
                                if (data.Giro) $('#billing_giro').val(data.Giro);
                                
                                // 2. Dirección (Tomamos la primera disponible)
                                if (data.DireccionFacturacion && data.DireccionFacturacion.length > 0) {
                                    var dir = data.DireccionFacturacion[0];
                                    if (dir.Direccion) $('#billing_address_1').val(dir.Direccion);
                                    if (dir.ComunaNombre) $('#billing_city').val(dir.ComunaNombre);
                                    if (dir.Telefono) $('#billing_phone').val(dir.Telefono);
                                }

                                // 3. Contacto (Email)
                                if (data.Contacto && data.Contacto.length > 0) {
                                    if (data.Contacto[0].Email) $('#billing_email').val(data.Contacto[0].Email);
                                }
                                
                                // Actualizar el checkout para recalcular envíos si cambió la dirección
                                $('body').trigger('update_checkout');
                            } else {
                                console.log('Cliente no encontrado o error en API');
                                // Opcional: Limpiar campos si no se encuentra
                            }
                        },
                        complete: function() {
                            $('.woocommerce-checkout').removeClass('processing');
                            $('#billing_company').attr('placeholder', '');
                        }
                    });
                });
            });
            ";
            wp_add_inline_script('diprotec-checkout', $js_code);
        }
    }

    public function add_billing_fields($fields)
    {
        // 1. Campo RUT
        $fields['billing_rut'] = [
            'type' => 'text',
            'label' => 'RUT Empresa',
            'placeholder' => '12.345.678-9',
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => 10,
            'clear' => true
        ];

        // 2. Campo Giro (Nuevo)
        $fields['billing_giro'] = [
            'type' => 'text',
            'label' => 'Giro Comercial',
            'placeholder' => 'Ej: Venta de Hardware',
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => 30, // Justo después de la empresa
            'clear' => true
        ];

        // Ajustar Razón Social
        if (isset($fields['billing_company'])) {
            $fields['billing_company']['required'] = true;
            $fields['billing_company']['priority'] = 20;
        }

        return $fields;
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
