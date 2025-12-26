<?php
/**
 * Plugin Name: Diprotec ERP Connector
 * Description: Conector Middleware WooCommerce <-> ERP Propietario (Mock First Strategy)
 * Version: 1.0.0
 * Author: Diprotec Dev Team
 * Text Domain: diprotec-erp-connector
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('DIPROTEC_ERP_VERSION', '1.0.0');
define('DIPROTEC_ERP_PATH', plugin_dir_path(__FILE__));
define('DIPROTEC_ERP_URL', plugin_dir_url(__FILE__));
define('DIPROTEC_DEV_MODE', true); // TRUE = MockClient, FALSE = RestClient

// Autoloader (Simple implementation for now, or could use composer if available, but sticking to manual for simplicity as per guide)
spl_autoload_register(function ($class) {
    $prefix = 'Diprotec\\ERP\\';
    $base_dir = DIPROTEC_ERP_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize Plugin
function diprotec_erp_init()
{
    // Container / Dependency Injection Setup
    $client = DIPROTEC_DEV_MODE
        ? new \Diprotec\ERP\Clients\MockClient()
        : new \Diprotec\ERP\Clients\RestClient([]); // Credentials would go here

    // Initialize Services
    new \Diprotec\ERP\Services\ProductSyncService($client);
    new \Diprotec\ERP\Services\StockValidator($client);
    new \Diprotec\ERP\Services\FrontendIntegration($client);

    // Image Handler might be used inside ProductSyncService or standalone
    // For now, let's assume it's a helper used by SyncService, or we can init it if it has hooks.
    // Based on guide, it seems to be a utility used during sync.
}

add_action('plugins_loaded', 'diprotec_erp_init');
