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
define('DIPROTEC_ERP_API_URL', 'https://commerce.diprotec.cl'); // URL Real Sandbox
define('DIPROTEC_ERP_API_TOKEN', '5edySufUVLiMxZfFX8XzUmlCJ0IpIFJev2k6d4MITADFQdYlh6dKj3J2CSGPvMYL');
define('DIPROTEC_ERP_API_USER', ''); // Solo si se usa Basic Auth
define('DIPROTEC_ERP_API_PASS', ''); // Solo si se usa Basic Auth
define('DIPROTEC_ERP_WEBHOOK_SECRET', 'mc%uM~A;PCzAkA:mwmhZ(#$7//P?a*4i'); // <--- USAR UN VALOR SEGURO EN PRODUCCIÓN
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
require_once DIPROTEC_ERP_PATH . 'includes/Services/WebhookService.php';

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

        // Hooks de Admin (Batch Sync v2.0)
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Handlers AJAX para Batch Sync
        add_action('wp_ajax_diprotec_sync_init', [$this, 'ajax_sync_init']);
        add_action('wp_ajax_diprotec_sync_process_batch', [$this, 'ajax_sync_process_batch']);
        add_action('wp_ajax_diprotec_sync_process_deletions', [$this, 'ajax_sync_process_deletions']);
        add_action('wp_ajax_diprotec_sync_cleanup', [$this, 'ajax_sync_cleanup']);

        // Inicializar integración frontend
        new FrontendIntegration($apiClient);

        // Inicializar Webhook
        new \Diprotec\ERP\Services\WebhookService();

        // Programar CRON (Desactivado por ahora hasta validar en Staging)
        // add_action('diprotec_hourly_sync', [$this->syncService, 'syncAllProducts']);
    }

    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'toplevel_page_diprotec-connector') {
            return;
        }

        wp_enqueue_script(
            'diprotec-admin-sync',
            DIPROTEC_ERP_URL . 'assets/js/admin-sync.js',
            ['jquery'],
            DIPROTEC_ERP_VERSION,
            true
        );

        wp_localize_script('diprotec-admin-sync', 'diprotec_sync_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('diprotec_sync_nonce')
        ]);

        // Estilos básicos para la barra de progreso
        wp_add_inline_style('common', '
            .diprotec-progress-wrapper { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px; display: none; }
            .diprotec-progress-bar { height: 20px; background: #f0f0f1; border-radius: 10px; overflow: hidden; margin-bottom: 15px; }
            .diprotec-progress-fill { height: 100%; background: #2271b1; width: 0%; transition: width 0.3s ease; }
            #diprotec-sync-logs { background: #1d2327; color: #f0f0f1; font-family: monospace; padding: 15px; height: 250px; overflow-y: scroll; font-size: 12px; }
            .log-item { margin-bottom: 4px; }
            .log-error { color: #f86368; }
            .log-success { color: #68de7d; }
        ');
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
            <p>Utilice este panel para iniciar la sincronización por lotes.</p>

            <div class="diprotec-sync-actions">
                <button id="diprotec-start-sync" class="button button-primary button-large">
                    <span class="dashicons dashicons-update" style="margin-top: 4px; margin-right: 5px;"></span>
                    Iniciar Sincronización Completa
                </button>
            </div>

            <div class="diprotec-progress-wrapper" id="sync-progress-container">
                <h3>Sincronizando Productos...</h3>
                <div class="diprotec-progress-bar">
                    <div class="diprotec-progress-fill" id="sync-progress-fill"></div>
                </div>
                <div class="progress-stats">
                    <strong>Progreso:</strong> <span id="sync-count">0</span> / <span id="sync-total">?</span>
                    (<span id="sync-percent">0</span>%)
                </div>

                <hr>
                <h4>Detalle del Proceso</h4>
                <div id="diprotec-sync-logs">
                    <div class="log-item">Esperando inicio...</div>
                </div>
            </div>

            <hr>
            <h2>Logs de la última ejecución</h2>
            <textarea style="width:100%; height: 150px; background: #f0f0f1; font-family: monospace;"
                readonly><?php echo esc_textarea($this->get_logs()); ?></textarea>
        </div>
        <?php
    }

    // ==========================================================================
    // AJAX HANDLERS
    // ==========================================================================

    public function ajax_sync_init()
    {
        check_ajax_referer('diprotec_sync_nonce', 'nonce');

        try {
            $count = $this->syncService->downloadAndCache();
            wp_send_json_success(['total' => $count]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_sync_process_batch()
    {
        check_ajax_referer('diprotec_sync_nonce', 'nonce');

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = 25; // Tamaño del lote ajustable

        try {
            $result = $this->syncService->syncBatch($offset, $limit);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_sync_process_deletions()
    {
        check_ajax_referer('diprotec_sync_nonce', 'nonce');

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = 50; // Tamaño del lote para eliminaciones

        try {
            $result = $this->syncService->processDeletions($offset, $limit);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_sync_cleanup()
    {
        check_ajax_referer('diprotec_sync_nonce', 'nonce');
        $this->syncService->cleanup();
        wp_send_json_success();
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
