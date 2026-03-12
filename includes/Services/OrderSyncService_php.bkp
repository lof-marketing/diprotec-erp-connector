<?php
namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class OrderSyncService
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;

        // 1. En lugar de enviar la orden inmediatamente, programamos un evento asíncrono (pago exitoso)
        add_action('woocommerce_order_status_processing', [$this, 'schedule_order_to_erp'], 10, 1);

        // 2. Enganchamos nuestra función real de envío al evento programado
        add_action('diprotec_async_send_order', [$this, 'send_order_to_erp'], 10, 1);

        // Opcional: Permitir envío manual desde acciones del pedido (para pruebas)
        add_action('woocommerce_order_actions', [$this, 'add_manual_sync_action']);
        add_action('woocommerce_order_action_diprotec_send_order', [$this, 'send_order_to_erp']);
    }

    public function schedule_order_to_erp($order_id)
    {
        // Programamos el evento para que se ejecute inmediatamente en el próximo ciclo de wp-cron,
        // liberando así el proceso de carga de la página del cliente.
        wp_schedule_single_event(time(), 'diprotec_async_send_order', [$order_id]);
    }

    public function add_manual_sync_action($actions)
    {
        $actions['diprotec_send_order'] = 'Enviar a ERP Diprotec';
        return $actions;
    }

    public function send_order_to_erp($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Evitar doble envío (verificamos si ya tiene un ID de ERP exitoso)
        if ($order->get_meta('_diprotec_erp_order_id')) {
            return;
        }

        // Instanciar el Logger de WooCommerce para depuración
        $logger = wc_get_logger();
        $log_context = ['source' => 'diprotec-erp-connector'];

        $logger->info("Iniciando proceso de envío para la Orden #{$order_id}", $log_context);

        // ==========================================
        // 1. PREPARACIÓN Y FORMATEO DE DATOS
        // ==========================================

        // Helper para forzar mayúsculas y evitar duplicados en el ERP
        $format_text = function ($text) {
            return mb_strtoupper(trim($text), 'UTF-8');
        };

        // Obtenemos RUT
        $raw_rut = $order->get_meta('_billing_rut') ?: '66666666-6';
        $clean_rut = preg_replace('/[^0-9kK]/', '', strtoupper($raw_rut));
        if (strlen($clean_rut) > 1) {
            $rut = substr($clean_rut, 0, -1) . '-' . substr($clean_rut, -1);
        } else {
            $rut = $clean_rut;
        }

        // Consultamos la Banda del cliente en el ERP
        $banda = "";
        if (method_exists($this->client, 'getCustomerByRut')) {
            $cliente_erp = $this->client->getCustomerByRut($rut);
            if ($cliente_erp && !empty($cliente_erp['Banda'])) {
                $banda = $cliente_erp['Banda'];
            }
        }

        // Formateo de Nombre y Giro
        $nombre_empresa = $order->get_billing_company() ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $nombre_empresa = $format_text($nombre_empresa);
        $giro = $format_text($order->get_meta('_billing_giro') ?: 'PARTICULAR');

        // ==========================================
        // 2. MAPEO DE REGIONES Y DIRECCIONES
        // ==========================================

        // Helper para traducir códigos ISO de WooCommerce al formato estricto del ERP
        $map_region = function ($state_code) {
            $code = str_replace('CL-', '', strtoupper($state_code));
            $map = [
                'AP' => 'ARICA Y PARINACOTA',
                'TA' => 'TARAPACA',
                'AN' => 'ANTOFAGASTA',
                'AT' => 'ATACAMA',
                'CO' => 'COQUIMBO',
                'VS' => 'VALPARAISO',
                'RM' => 'METROPOLITANA',
                'LI' => 'OHIGGINS',
                'ML' => 'MAULE',
                'NB' => 'ÑUBLE',
                'BI' => 'BIOBIO',
                'AR' => 'ARAUCANIA',
                'LR' => 'LOS RIOS',
                'LL' => 'LOS LAGOS',
                'AI' => 'AYSEN',
                'MA' => 'MAGALLANES'
            ];
            return isset($map[$code]) ? $map[$code] : $code;
        };

        // Facturación
        $region_factura = $map_region($order->get_billing_state());
        $comuna_factura = $format_text($order->get_billing_city());
        $direccion_factura = $format_text($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());

        // Despacho (Si no existe, cae de vuelta a la de facturación)
        $region_despacho = $order->get_shipping_state() ? $map_region($order->get_shipping_state()) : $region_factura;
        $comuna_despacho = $order->get_shipping_city() ? $format_text($order->get_shipping_city()) : $comuna_factura;
        $direccion_despacho = $format_text($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2()) ?: $direccion_factura;

        $nombre_despacho = $order->get_shipping_first_name() ? $format_text($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()) : $nombre_empresa;

        // ==========================================
        // 3. PRODUCTOS Y CÁLCULOS
        // ==========================================
        $items_erp = [];
        $neto_total_calculado = 0;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $erpId = $product->get_meta('_diprotec_pro_id');
            $id_producto = $erpId ? $erpId : $product->get_sku();

            $cantidad = $item->get_quantity();
            $total_linea = $item->get_total(); // Total sin impuestos de la línea
            $precio_unitario = $total_linea / $cantidad;

            $items_erp[] = [
                "Id" => (string) $id_producto,
                "Cantidad" => (int) $cantidad,
                "PrecioUnitario" => (float) round($precio_unitario),
                "PrecioTotal" => (float) round($total_linea)
            ];

            $neto_total_calculado += $total_linea;
        }

        $total_order = $order->get_total();
        $iva_factor = 19;
        $iva_monto = $order->get_total_tax();

        if ($iva_monto == 0) {
            $neto = round($total_order / 1.19);
            $iva_monto = $total_order - $neto;
        } else {
            $neto = $total_order - $iva_monto;
        }

        // ==========================================
        // 4. CONSTRUIR PAYLOAD 
        // ==========================================
        $payload = [
            "Nuevo" => 0,
            "RUT" => substr($rut, 0, 12),
            "Nombre" => substr($nombre_empresa, 0, 100),
            "Giro" => substr($giro, 0, 50),
            "Banda" => $banda,

            // Datos Facturación
            "FacturaDireccion" => substr($direccion_factura, 0, 100),
            "FacturaTelefono" => substr($order->get_billing_phone(), 0, 20),
            "FacturaRegion" => $region_factura,
            "FacturaProvincia" => $comuna_factura,
            "FacturaComuna" => $comuna_factura,

            // Pago
            "FormaPago" => $format_text($order->get_payment_method_title()),
            "FormaPagoVcto" => 0,

            // Despacho
            "DespachoForma" => "DESPACHO",
            "DespachoFormaNombre" => $format_text($order->get_shipping_method()),
            "DespachoTransporte" => "PROPIO",
            "DespachoDireccion" => substr($direccion_despacho, 0, 100),
            "DespachoRegion" => $region_despacho,
            "DespachoProvincia" => $comuna_despacho,
            "DespachoComuna" => $comuna_despacho,
            "DespachoContacto" => substr($nombre_despacho, 0, 100),
            "DespachoContactoRut" => $rut,
            "DespachoContactoTelefono" => substr($order->get_billing_phone(), 0, 20),
            "DespachoContactoEmail" => strtolower($order->get_billing_email()),

            "Comentarios" => $order->get_customer_note() ?: "PEDIDO WEB #" . $order->get_id(),

            // Totales
            "IvaFactor" => $iva_factor,
            "Neto" => (float) $neto,
            "IVA" => (float) $iva_monto,
            "Total" => (float) $total_order,

            // Detalle
            "Productos" => $items_erp
        ];

        // GUARDAR EL LOG DEL PAYLOAD GENERADO
        $logger->info("Payload a enviar al ERP (JSON): \n" . wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $log_context);

        // ==========================================
        // 5. ENVIAR AL ERP Y PROCESAR RESPUESTA
        // ==========================================
        $response = $this->client->createOrder($payload);

        // GUARDAR EL LOG DE LA RESPUESTA
        $logger->info("Respuesta recibida del ERP: \n" . wp_json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $log_context);

        if (isset($response['Respuesta']) && $response['Respuesta'] === 'TRANSACCION_OK') {
            $erp_identifier = isset($response['Data']['Identificador']) ? $response['Data']['Identificador'] : 'ENVIADO';

            $order->update_meta_data('_diprotec_erp_order_id', $erp_identifier);
            $order->add_order_note("Pedido enviado exitosamente a ERP Diprotec. ID Respuesta: " . $erp_identifier);
            $order->save();

            $logger->info("Orden #{$order_id} sincronizada exitosamente con el ID del ERP: {$erp_identifier}", $log_context);
        } else {
            $error_msg = isset($response['CodigoError']) ? $response['CodigoError'] : 'Error desconocido';
            $order->add_order_note("Error al enviar a ERP Diprotec: " . $error_msg);

            $logger->error("Fallo al sincronizar Orden #{$order_id}. Error: {$error_msg}", $log_context);
        }
    }
}