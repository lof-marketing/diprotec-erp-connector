<?php

namespace Diprotec\ERP\Clients;

use Diprotec\ERP\Interfaces\ClientInterface;

class RestClient implements ClientInterface
{

    public function __construct($credentials = [])
    {
        // Initialize Guzzle or HTTP client here
    }

    public function getProducts(?string $modified_after = null): array
    {
        // Implement REST API call
        return [];
    }

    public function getStock(string $sku): array
    {
        // Implement REST API call
        return ['available_qty' => 0, 'allow_backorder' => false];
    }

    public function createOrder(array $order_payload): array
    {
        // Implement REST API call
        return ['status' => 'error', 'message' => 'Not implemented'];
    }
}
