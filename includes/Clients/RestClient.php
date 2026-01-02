<?php
namespace Diprotec\ERP\Clients;

use Diprotec\ERP\Interfaces\ClientInterface;

/**
 * Cliente REST real para conectar con el ERP de Diprotec.
 * Utiliza wp_remote_get/post nativo de WordPress.
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
        // Endpoint hipotético basado en estándares. 
        // Se ajustará cuando llegue la doc el 15/01.
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
     * Crear pedido en ERP (No implementado en el archivo de referencia pero requerido por Interface)
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

        // Manejo de errores de conexión (WP_Error)
        if (is_wp_error($response)) {
            error_log('Diprotec ERP Error: ' . $response->get_error_message());
            return []; // Retornar array vacío para no romper el flujo
        }

        // Manejo de códigos HTTP
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log("Diprotec ERP HTTP Error [$code]: " . wp_remote_retrieve_body($response));
            return [];
        }

        // Decodificar respuesta
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Validar JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Diprotec ERP JSON Error: ' . json_last_error_msg());
            return [];
        }

        return $data;
    }
}
