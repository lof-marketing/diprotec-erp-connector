<?php
namespace Diprotec\ERP\Services;

use Diprotec\ERP\Interfaces\ClientInterface;

class OrderSyncService
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;

        // Hook para enviar el pedido cuando pasa a "Procesando" (pago exitoso)
        add_action('woocommerce_order_status_processing', [$this, 'send_order_to_erp'], 10, 1);

        // Opcional: Permitir envío manual desde acciones del pedido (para pruebas)
        add_action('woocommerce_order_actions', [$this, 'add_manual_sync_action']);
        add_action('woocommerce_order_action_diprotec_send_order', [$this, 'send_order_to_erp']);
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

        // 1. Preparar Datos del Cliente
        // Obtenemos RUT y Giro desde los meta fields guardados por FrontendIntegration
        $raw_rut = $order->get_meta('_billing_rut') ?: '66666666-6'; // Fallback si no hay RUT

        // Formatear RUT: Solo números y K, añadir guión antes del dígito verificador
        $clean_rut = preg_replace('/[^0-9kK]/', '', strtoupper($raw_rut));
        if (strlen($clean_rut) > 1) {
            $rut = substr($clean_rut, 0, -1) . '-' . substr($clean_rut, -1);
        } else {
            $rut = $clean_rut;
        }

        $giro = $order->get_meta('_billing_giro') ?: 'Particular';

        // 2. Preparar Dirección (Mapeo básico WC -> ERP)
        $region = $order->get_billing_state(); // WC guarda códigos de región CL
        $comuna = $order->get_billing_city();
        $direccion = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();

        // 3. Preparar Productos
        $items_erp = [];
        $neto_total_calculado = 0;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            // Lógica crítica: Usar el ID del ERP (_diprotec_pro_id), no el SKU, según lo corregido antes
            $erpId = $product->get_meta('_diprotec_pro_id');

            // Fallback: Si no tiene ID mapeado, tratamos de usar SKU, pero esto podría fallar en el ERP
            $id_producto = $erpId ? $erpId : $product->get_sku();

            $cantidad = $item->get_quantity();
            $total_linea = $item->get_total(); // Total sin impuestos de la línea

            // Calcular precio unitario neto aproximado
            $precio_unitario = $total_linea / $cantidad;

            $items_erp[] = [
                "Id" => (string) $id_producto,
                "Cantidad" => (int) $cantidad,
                "PrecioUnitario" => (float) round($precio_unitario),
                "PrecioTotal" => (float) round($total_linea)
            ];

            $neto_total_calculado += $total_linea;
        }

        // 4. Cálculos Totales
        // Asumimos IVA 19% Chile. El ERP pide Neto, IVA y Total por separado
        $total_order = $order->get_total();
        $iva_factor = 19;
        $iva_monto = $order->get_total_tax();

        // Si WC no tiene impuesto configurado, calculamos inverso
        if ($iva_monto == 0) {
            $neto = round($total_order / 1.19);
            $iva_monto = $total_order - $neto;
        } else {
            $neto = $total_order - $iva_monto;
        }

        // 5. Construir Payload según documentación Swagger
        $payload = [
            "Nuevo" => 0, // Asumimos 0 por defecto para nueva nota
            "RUT" => substr($rut, 0, 12), // Truncar por seguridad
            "Nombre" => $order->get_billing_company() ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            "Giro" => substr($giro, 0, 50),

            // Datos Facturación
            "FacturaDireccion" => substr($direccion, 0, 100),
            "FacturaTelefono" => substr($order->get_billing_phone(), 0, 20),
            "FacturaRegion" => $region,
            "FacturaProvincia" => $comuna, // WC no maneja provincias bien, usamos comuna como fallback
            "FacturaComuna" => $comuna,

            // Pago
            "FormaPago" => $order->get_payment_method_title(),
            "FormaPagoVcto" => 0,

            // Despacho
            "DespachoForma" => "DESPACHO", // Podrías lógica para "RETIRO" si detectas Local Pickup
            "DespachoFormaNombre" => $order->get_shipping_method(),
            "DespachoTransporte" => "PROPIO",
            "DespachoDireccion" => $order->get_shipping_address_1() ?: $direccion,
            "DespachoRegion" => $order->get_shipping_state() ?: $region,
            "DespachoProvincia" => $order->get_shipping_city() ?: $comuna,
            "DespachoComuna" => $order->get_shipping_city() ?: $comuna,
            "DespachoContacto" => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            "DespachoContactoRut" => $rut,
            "DespachoContactoTelefono" => $order->get_billing_phone(),
            "DespachoContactoEmail" => $order->get_billing_email(),

            "Comentarios" => $order->get_customer_note() ?: "Pedido Web #" . $order->get_id(),

            // Totales
            "IvaFactor" => $iva_factor,
            "Neto" => (float) $neto,
            "IVA" => (float) $iva_monto,
            "Total" => (float) $total_order,

            // Detalle
            "Productos" => $items_erp
        ];

        // 6. Enviar al ERP
        $response = $this->client->createOrder($payload);

        // 7. Procesar Respuesta
        // Según documentación, Estado 200 y "TRANSACCION_OK" es éxito.
        if (isset($response['Respuesta']) && $response['Respuesta'] === 'TRANSACCION_OK') {
            // Guardar ID del ERP si viene en la respuesta (Data suele traer info)
            // Según tu ejemplo de respuesta OK: "Data": { "Identificador": "0", "Retorno": 0 }
            $erp_identifier = isset($response['Data']['Identificador']) ? $response['Data']['Identificador'] : 'ENVIADO';

            $order->update_meta_data('_diprotec_erp_order_id', $erp_identifier);
            $order->add_order_note("Pedido enviado exitosamente a ERP Diprotec. ID Respuesta: " . $erp_identifier);
            $order->save();
        } else {
            $error_msg = isset($response['CodigoError']) ? $response['CodigoError'] : 'Error desconocido';
            $order->add_order_note("Error al enviar a ERP Diprotec: " . $error_msg);
        }
    }
}
