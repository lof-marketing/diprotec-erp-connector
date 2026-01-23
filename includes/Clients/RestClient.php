<?php
namespace Diprotec\ERP\Clients;

use Diprotec\ERP\Interfaces\ClientInterface;

/**
 * Cliente REST real para conectar con el ERP de Diprotec.
 * Versión 2.0 con CorrelationLogging y Cliente Lookup.
 */
class RestClient implements ClientInterface
{

    private $baseUrl;
    private $token;

    public function __construct($baseUrl, $token)
    {
        $this->baseUrl = untrailingslashit($baseUrl);
        $this->token = $token;
    }

    /**
     * Obtiene el listado de productos desde el ERP.
     */
    public function getProducts(?string $modified_after = null): array
    {
        // Endpoint real Sandbox v1
        $endpoint = '/api/v1/productos/GetProductos';
        return $this->request($endpoint);
    }

    /**
     * Obtiene el stock de un producto específico o masivo.
     */
    public function getStock(string $sku): array
    {
        // En v1 usualmente se obtiene del listado completo o un endpoint específico si existe
        $endpoint = '/api/v1/productos/GetStock?sku=' . urlencode($sku);
        return $this->request($endpoint);
    }

    /**
     * Obtiene datos del cliente por RUT (v2.0)
     */
    public function getCustomerByRut(string $rut): array
    {
        // Placeholder hasta que el endpoint esté disponible
        return [];
    }

    /**
     * Crear pedido en ERP
     */
    public function createOrder(array $order_payload): array
    {
        $endpoint = '/api/v1/notaventa/PutNotaVenta';
        return $this->request($endpoint, 'POST', $order_payload);
    }

    /**
     * Método centralizado para realizar peticiones HTTP.
     */
    private function request($endpoint, $method = 'GET', $body = [])
    {
        // Limpiar URL base y endpoint para evitar dobles slashes si es necesario
        $url = $this->baseUrl . $endpoint;

        $args = [
            'method' => $method,
            'timeout' => 45,
            'headers' => [
                'X-API-KEY' => $this->token, // Cambio v1: X-API-KEY en lugar de Authorization Bearer
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($body)) {
            $args['body'] = json_encode($body);
        }

        // Realizar la petición
        $response = wp_remote_request($url, $args);

        // Header de Correlación
        $correlationId = wp_remote_retrieve_header($response, 'CorrelationId');

        if (is_wp_error($response)) {
            $this->logError("Diprotec ERP Connection Error: " . $response->get_error_message(), $correlationId);
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        $resBody = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            $this->logError("Diprotec ERP HTTP Error [$code]: " . $resBody, $correlationId);
            return [];
        }

        $data = json_decode($resBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Diprotec ERP JSON Error: " . json_last_error_msg() . " | Raw: " . substr($resBody, 0, 500), $correlationId);
            return [];
        }

        return $data;
    }

    private function logError($message, $correlationId = null)
    {
        $logMsg = "[ERROR API] " . $message;
        if ($correlationId) {
            $logMsg .= " Server CorrelationId: {" . $correlationId . "}";
        }

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($logMsg, ['source' => 'diprotec-erp-connector']);
        } else {
            error_log($logMsg);
        }
    }
}
