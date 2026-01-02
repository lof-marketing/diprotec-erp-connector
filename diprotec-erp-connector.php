<?php
/**
 * Plugin Name: Diprotec ERP Connector
 * Plugin URI: https://diprotec.cl
 * Description: Conector oficial para sincronización de productos e inventario entre ERP Diprotec y WooCommerce.
 * Version: 1.0.0-alpha2
 * Author: LOF Marketing
 * Author URI: https://lofmarketing.com
 * Text Domain: diprotec-erp-connector
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==============================================================================
// CONFIGURACIÓN DEL ERP (CAMBIAR ESTOS VALORES EL 15 DE ENERO)
// ==============================================================================
// Define si usamos datos simulados (true) o conexión real (false)
define('DIPROTEC_ERP_USE_MOCK', true); // <--- CAMBIAR A FALSE CUANDO TENGAS LAS CREDENCIALES

// Credenciales (Se llenarán el 15 de Enero)
define('DIPROTEC_ERP_API_URL', 'https://api.erp-diprotec.cl/v1'); // URL Placeholder
define('DIPROTEC_ERP_API_TOKEN', 'tu_api_token_aqui');
define('DIPROTEC_ERP_API_USER', ''); // Solo si se usa Basic Auth
define('DIPROTEC_ERP_API_PASS', ''); // Solo si se usa Basic Auth
// ==============================================================================

// Path constants for consistency
define('DIPROTEC_ERP_VERSION', '1.0.0-alpha2');
define('DIPROTEC_ERP_PATH', plugin_dir_path(__FILE__));
define('DIPROTEC_ERP_URL', plugin_dir_url(__FILE__));

// Autoload de clases (Manual requirements as per provided structure)
require_once DIPROTEC_ERP_PATH . 'includes/Interfaces/ClientInterface.php';
require_once DIPROTEC_ERP_PATH . 'includes/Clients/RestClient.php';
require_once DIPROTEC_ERP_PATH . 'includes/Clients/MockClient.php';
require_once DIPROTEC_ERP_PATH . 'includes/Services/ProductSyncService.php';
require_once DIPROTEC_ERP_PATH . 'includes/Services/StockValidator.php';
require_once DIPROTEC_ERP_PATH . 'includes/Services/ImageHandler.php';
require_once DIPROTEC_ERP_PATH . 'includes/Services/FrontendIntegration.php';

use Diprotec\ERP\Services\ProductSyncService;
use Diprotec\ERP\Services\ImageHandler;
use Diprotec\ERP\Services\FrontendIntegration;
use Diprotec\ERP\Clients\RestClient;
use Diprotec\ERP\Clients\MockClient;

class DiprotecConnector
{

    private $syncService;

    public function __construct()
    {
        // Inicializar servicios
        $imageHandler = new ImageHandler();

        // Inyección de Dependencia: Elegimos el cliente según la constante
        if (DIPROTEC_ERP_USE_MOCK) {
            $apiClient = new MockClient();
        } else {
            // El 15 de Enero, al cambiar la constante, se activará este cliente automáticamente
            $apiClient = new RestClient(
                DIPROTEC_ERP_API_URL,
                DIPROTEC_ERP_API_TOKEN
            );
        }

        $this->syncService = new ProductSyncService($apiClient, $imageHandler);

        // Hooks de Admin (Manual Sync para pruebas)
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_diprotec_manual_sync', [$this, 'handle_manual_sync']);

        // Inicializar integración frontend
        new FrontendIntegration($apiClient);

        // Programar CRON (Desactivado por ahora hasta validar en Staging)
        // add_action('diprotec_hourly_sync', [$this->syncService, 'syncAllProducts']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Diprotec ERP',
            'Diprotec ERP',
            'manage_options',
            'diprotec-connector',
            [$this, 'render_admin_page'],
            'dashicons-database-import',
            56
        );
    }

    public function render_admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Conector ERP Diprotec (Estado: <?php echo DIPROTEC_ERP_USE_MOCK ? 'MODO SIMULACIÓN' : 'MODO PRODUCCIÓN'; ?>)
            </h1>
            <p>Utilice este panel para forzar una sincronización manual de productos.</p>

            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                <input type="hidden" name="action" value="diprotec_manual_sync">
                <?php wp_nonce_field('diprotec_sync_action', 'diprotec_sync_nonce'); ?>
                <button type="submit" class="button button-primary">Sincronizar Ahora</button>
            </form>

            <hr>
            <h2>Logs Recientes</h2>
            <textarea style="width:100%; height: 300px; background: #f0f0f1; font-family: monospace;" readonly>
                                <?php echo esc_textarea($this->get_logs()); ?>
                                            </textarea>
        </div>
        <?php
    }

    public function handle_manual_sync()
    {
        if (!current_user_can('manage_options'))
            return;
        check_admin_referer('diprotec_sync_action', 'diprotec_sync_nonce');

        $results = $this->syncService->syncAllProducts();

        // Guardar log simple (Mejorar esto con un sistema de logs real)
        update_option('diprotec_last_sync_log', json_encode($results, JSON_PRETTY_PRINT));

        wp_redirect(admin_url('admin.php?page=diprotec-connector&status=success'));
        exit;
    }

    private function get_logs()
    {
        return get_option('diprotec_last_sync_log', 'No hay logs disponibles.');
    }
}

// Iniciar Plugin
add_action('plugins_loaded', function () {
    new DiprotecConnector();
});
