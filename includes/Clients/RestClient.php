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
        $endpoint = '/products';
        return $this->request($endpoint);
    }

    /**
     * Obtiene el stock de un producto específico o masivo.
     */
    public function getStock(string $sku): array
    {
        $endpoint = '/stock/' . $sku;
        return $this->request($endpoint);
    }

    /**
     * Obtiene datos del cliente por RUT (v2.0)
     */
    public function getCustomerByRut(string $rut): array
    {
        // Endpoint hipotético
        $endpoint = '/customers/' . urlencode($rut);
        return $this->request($endpoint);
    }

    /**
     * Crear pedido en ERP
     */
    public function createOrder(array $order_payload): array
    {
        $endpoint = '/orders';
        return $this->request($endpoint, 'POST', $order_payload);
    }

    /**
     * Método centralizado para realizar peticiones HTTP.
     */
    private function request($endpoint, $method = 'GET', $body = [])
    {
        $url = $this->baseUrl . $endpoint;

        $args = [
            'method' => $method,
            'timeout' => 45, // Tiempo alto para cargas masivas
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($body)) {
            $args['body'] = json_encode($body);
        }

        // Realizar la petición
        $response = wp_remote_request($url, $args);

        // Header de Correlación (Trazabilidad v2.0)
        $correlationId = wp_remote_retrieve_header($response, 'CorrelationId');
        // Nota: A veces los headers son case-insensitive, wp_remote_retrieve_header lo maneja.

        // Manejo de errores de conexión (WP_Error)
        if (is_wp_error($response)) {
            $this->logError("Diprotec ERP Connection Error: " . $response->get_error_message(), $correlationId);
            return []; // Retornar array vacío para no romper el flujo
        }

        // Manejo de códigos HTTP
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $bodyMsg = wp_remote_retrieve_body($response);
            $this->logError("Diprotec ERP HTTP Error [$code]: " . $bodyMsg, $correlationId);
            return [];
        }

        // Decodificar respuesta
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Validar JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Diprotec ERP JSON Error: " . json_last_error_msg(), $correlationId);
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
