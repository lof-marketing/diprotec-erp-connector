<?php
// Mock WordPress/WooCommerce functions and classes for testing
if (!function_exists('add_action')) {
    function add_action($tag, $callback)
    {
    }
}
if (!function_exists('add_filter')) {
    function add_filter($tag, $callback)
    {
    }
}
if (!function_exists('wc_get_order')) {
    function wc_get_order($id)
    {
        return new WC_Order_Mock($id);
    }
}
if (!function_exists('wc_get_logger')) {
    function wc_get_logger()
    {
        return new WC_Logger_Mock();
    }
}
if (!function_exists('untrailingslashit')) {
    function untrailingslashit($url)
    {
        return rtrim($url, '/');
    }
}
if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args)
    {
        return [];
    }
}
if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($r, $h)
    {
        return '';
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($t)
    {
        return false;
    }
}

define('DIPROTEC_ERP_PATH', __DIR__ . '/../');

// Include necessary files
require_once DIPROTEC_ERP_PATH . 'includes/Interfaces/ClientInterface.php';
require_once DIPROTEC_ERP_PATH . 'includes/Services/OrderSyncService.php';
require_once DIPROTEC_ERP_PATH . 'includes/Clients/MockClient.php';

// Mock Classes
class WC_Logger_Mock
{
    public function error($msg, $context = [])
    {
        echo "[LOG ERROR] $msg\n";
    }
}

class WC_Product_Mock
{
    public function get_meta($key)
    {
        if ($key === '_diprotec_pro_id')
            return 'PRO-MOCK-123';
        return null;
    }
    public function get_sku()
    {
        return 'SKU-MOCK-123';
    }
}

class WC_Order_Item_Mock
{
    public function get_product()
    {
        return new WC_Product_Mock();
    }
    public function get_quantity()
    {
        return 2;
    }
    public function get_total()
    {
        return 10000;
    } // Total line mock
}

class WC_Order_Mock
{
    private $id;
    private $meta = [];

    public function __construct($id)
    {
        $this->id = $id;
    }
    public function get_id()
    {
        return $this->id;
    }

    public function get_meta($key)
    {
        if ($key === '_billing_rut')
            return '12345678-9';
        if ($key === '_billing_giro')
            return 'Giro Test';
        return isset($this->meta[$key]) ? $this->meta[$key] : null;
    }

    public function update_meta_data($key, $val)
    {
        $this->meta[$key] = $val;
        echo "[ORDER UPDATE] Meta '$key' set to '$val'\n";
    }

    public function add_order_note($note)
    {
        echo "[ORDER NOTE] $note\n";
    }
    public function save()
    {
        echo "[ORDER SAVE] Order saved.\n";
    }

    public function get_billing_first_name()
    {
        return 'Juan';
    }
    public function get_billing_last_name()
    {
        return 'Perez';
    }
    public function get_billing_company()
    {
        return 'Empresa Test';
    }
    public function get_billing_address_1()
    {
        return 'Calle Falsa 123';
    }
    public function get_billing_address_2()
    {
        return '';
    }
    public function get_billing_city()
    {
        return 'Santiago';
    }
    public function get_billing_state()
    {
        return 'RM';
    }
    public function get_billing_phone()
    {
        return '555-1234';
    }
    public function get_billing_email()
    {
        return 'juan@test.com';
    }

    public function get_shipping_first_name()
    {
        return 'Juan';
    }
    public function get_shipping_last_name()
    {
        return 'Perez';
    }
    public function get_shipping_address_1()
    {
        return 'Calle Falsa 123';
    }
    public function get_shipping_city()
    {
        return 'Santiago';
    }
    public function get_shipping_state()
    {
        return 'RM';
    }

    public function get_payment_method_title()
    {
        return 'WebPay';
    }
    public function get_shipping_method()
    {
        return 'Despacho a Domicilio';
    }
    public function get_customer_note()
    {
        return 'Nota del cliente';
    }

    public function get_items()
    {
        return [new WC_Order_Item_Mock()];
    }

    public function get_total()
    {
        return 11900;
    }
    public function get_total_tax()
    {
        return 1900;
    }
}

// TEST EXECUTION
echo "Starting Order Sync Test...\n";

$client = new \Diprotec\ERP\Clients\MockClient();
$service = new \Diprotec\ERP\Services\OrderSyncService($client);

// Simulate Order ID 101 processing
echo "Simulating order 101 processing...\n";
$service->send_order_to_erp(101);

echo "Test Complete.\n";
