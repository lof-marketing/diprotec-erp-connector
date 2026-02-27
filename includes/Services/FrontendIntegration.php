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

        // Display Documents (Brochure y Manual)
        add_action('woocommerce_single_product_summary', [$this, 'display_documents'], 30);

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

            // Mapeo de Regiones: ID ERP => Código WooCommerce (ISO 3166-2:CL)
            $region_map = [
                'REG0001' => 'CL-TA', // Tarapacá
                'REG0002' => 'CL-AN', // Antofagasta
                'REG0003' => 'CL-AT', // Atacama
                'REG0004' => 'CL-CO', // Coquimbo
                'REG0005' => 'CL-VS', // Valparaíso
                'REG0006' => 'CL-LI', // O'Higgins
                'REG0007' => 'CL-ML', // Maule
                'REG0008' => 'CL-BI', // Biobío
                'REG0009' => 'CL-AR', // Araucanía
                'REG0010' => 'CL-LL', // Los Lagos
                'REG0011' => 'CL-AI', // Aysén
                'REG0012' => 'CL-MA', // Magallanes
                'REG0013' => 'CL-RM', // Metropolitana
                'REG0014' => 'CL-LR', // Los Ríos
                'REG0015' => 'CL-AP', // Arica y Parinacota
                'REG0016' => 'CL-NB', // Ñuble
            ];

            wp_localize_script('diprotec-checkout', 'diprotec_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'region_map' => $region_map
            ]);

            $js_code = "
            jQuery(document).ready(function($) {
                
                // Función auxiliar para separar nombres
                function splitName(fullName) {
                    if (!fullName) return { first: '', last: '' };
                    var parts = fullName.trim().split(' ');
                    var first = parts[0];
                    var last = parts.length > 1 ? parts.slice(1).join(' ') : parts[0]; 
                    return { first: first, last: last };
                }

                $(document).on('change', '#billing_rut', function() {
                    var rut = $(this).val();
                    if (rut.length < 8) return;

                    // Bloquear UI visualmente
                    $('.woocommerce-checkout').addClass('processing');
                    $('#billing_company').attr('placeholder', 'Buscando en ERP...');

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
                                
                                // 1. Empresa y Giro
                                if (data.Nombre) $('#billing_company').val(data.Nombre);
                                if (data.Giro) $('#billing_giro').val(data.Giro);
                                
                                // 2. Manejo de Direcciones (Array)
                                var erpAddress = null;
                                if (data.DireccionFacturacion && data.DireccionFacturacion.length > 0) {
                                    erpAddress = data.DireccionFacturacion.find(addr => addr.Direccion && addr.Direccion.length > 3);
                                    if (!erpAddress) erpAddress = data.DireccionFacturacion[0];
                                }

                                if (erpAddress) {
                                    if (erpAddress.Direccion) $('#billing_address_1').val(erpAddress.Direccion);
                                    if (erpAddress.ComunaNombre) $('#billing_city').val(erpAddress.ComunaNombre);
                                    if (erpAddress.Telefono && erpAddress.Telefono !== '0') $('#billing_phone').val(erpAddress.Telefono);

                                    var regionCodeWoo = diprotec_vars.region_map[erpAddress.RegionId];
                                    if (regionCodeWoo) {
                                        $('#billing_state').val(regionCodeWoo);
                                        $('#billing_state').trigger('change');
                                    }
                                }

                                // 3. Manejo de Contactos (Array) para Nombre y Apellido
                                var erpContact = null;
                                if (data.Contacto && data.Contacto.length > 0) {
                                    erpContact = data.Contacto.find(c => c.Nombre && c.Nombre.length > 1);
                                    if (!erpContact) erpContact = data.Contacto[0];
                                }

                                if (erpContact) {
                                    if (erpContact.Email) $('#billing_email').val(erpContact.Email);
                                    
                                    var nameObj = splitName(erpContact.Nombre);
                                    $('#billing_first_name').val(nameObj.first);
                                    $('#billing_last_name').val(nameObj.last);
                                }
                                
                                // Actualizar el checkout para recalcular envíos si cambió la dirección
                                $('body').trigger('update_checkout');

                            } else {
                                console.log('Cliente no encontrado en ERP, permitir llenado manual.');
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
            'class' => ['form-row-wide', 'diprotec-rut-field'],
            'priority' => 5, // Antes que el Nombre (que suele ser 10)
            'clear' => true
        ];

        // 2. Campo Giro (Nuevo)
        $fields['billing_giro'] = [
            'type' => 'text',
            'label' => 'Giro Comercial',
            'placeholder' => 'Ej: Venta de Hardware',
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => 25, // Justo después de la empresa
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
        // 1. Obtener el Identificador correcto (ID del ERP si existe, sino SKU)
        $erpId = $product->get_meta('_diprotec_pro_id');

        // Si estamos en MOCK usa SKU, si es PRODUCCIÓN usa el ID del ERP
        // Nota: Asegúrate de tener acceso a la constante o usa la lógica inversa si prefieres
        // Aquí replicamos la lógica robusta del StockValidator:
        $identifier = (!defined('DIPROTEC_ERP_USE_MOCK') || !DIPROTEC_ERP_USE_MOCK) && $erpId ? $erpId : $product->get_sku();

        if (empty($identifier)) {
            return $availability;
        }

        // 2. Consultar al ERP
        $stock_data = $this->client->getStock($identifier);

        // 3. Lógica de Fallback (Plan B)
        // Si el ERP responde que no tiene info (o conexión falló), usamos el stock local de WC
        // Asumimos que si available_qty es 0 y no permite reserva, podría ser un fallo de API si el producto local dice tener stock.

        $wc_stock = $product->get_stock_quantity();
        $qty = $stock_data['available_qty'] ?? 0;

        // Si la API devuelve 0, verificamos si fue un error de conexión o producto no encontrado
        // (Esto depende de cómo tu RestClient maneje los errores, pero por seguridad:)
        if ($qty === 0 && $wc_stock > 0) {
            // Opcional: Podrías confiar en el local si la API falla. 
            // Para ser estrictos con el ERP, mantenemos la lógica, pero corregir el ID (paso 1) debería solucionar el 90% de los casos.
        }

        $backorder = $stock_data['allow_backorder'] ?? false;

        // Lógica de visualización
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

    /**
     * Muestra botones para descargar el Brochure y/o el Manual si existen.
     */
    public function display_documents()
    {
        global $product;

        $brochure = $product->get_meta('_diprotec_brochure_url');
        $manual = $product->get_meta('_diprotec_manual_url');

        if ($brochure || $manual) {
            echo '<div class="diprotec-documents" style="margin-top: 20px; margin-bottom: 20px;">';
            echo '<h4>Documentos:</h4>';
            echo '<ul style="list-style: none; padding: 0; display: flex; gap: 10px; flex-wrap: wrap;">';

            if ($brochure) {
                // Generamos un botón visual para el Brochure
                echo '<li><a href="' . esc_url($brochure) . '" target="_blank" class="button alt" style="text-decoration: none;">📄 Descargar Brochure</a></li>';
            }

            if ($manual) {
                // Generamos un botón visual para el Manual
                echo '<li><a href="' . esc_url($manual) . '" target="_blank" class="button alt" style="text-decoration: none;">📄 Descargar Manual</a></li>';
            }

            echo '</ul>';
            echo '</div>';
        }
    }
}
