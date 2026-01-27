(function ($) {
    'use strict';

    $(function () {
        const $btn = $('#diprotec-start-sync');
        const $wrapper = $('#sync-progress-container');
        const $fill = $('#sync-progress-fill');
        const $count = $('#sync-count');
        const $total = $('#sync-total');
        const $percent = $('#sync-percent');
        const $logs = $('#diprotec-sync-logs');

        let isRunning = false;
        let totalProducts = 0;
        let currentOffset = 0;
        const limit = 25;

        $btn.on('click', function (e) {
            e.preventDefault();
            if (isRunning) return;

            if (!confirm('¿Desea iniciar la sincronización completa? Este proceso puede tardar unos minutos.')) {
                return;
            }

            startSync();
        });

        function startSync() {
            isRunning = true;
            $btn.prop('disabled', true).addClass('updating-message');
            $wrapper.fadeIn();
            $logs.html('<div class="log-item">Iniciando conexión con el ERP...</div>');

            // 1. INIT
            $.post(diprotec_sync_params.ajax_url, {
                action: 'diprotec_sync_init',
                nonce: diprotec_sync_params.nonce
            }, function (response) {
                if (response.success) {
                    totalProducts = response.data.total;
                    $total.text(totalProducts);
                    addLog('Conexión exitosa. Se encontraron ' + totalProducts + ' productos.', 'success');
                    processNextBatch();
                } else {
                    addLog('Error al conectar: ' + response.data.message, 'error');
                    stopSync();
                }
            }).fail(function () {
                addLog('Error crítico de red al iniciar.', 'error');
                stopSync();
            });
        }

        function processNextBatch() {
            addLog('Procesando lote (Offset: ' + currentOffset + ')...');

            $.post(diprotec_sync_params.ajax_url, {
                action: 'diprotec_sync_process_batch',
                nonce: diprotec_sync_params.nonce,
                offset: currentOffset
            }, function (response) {
                if (response.success) {
                    const data = response.data;
                    currentOffset += data.processed + data.errors;

                    updateProgress(data.current_progress, data.total);
                    addLog('Lote completado: ' + data.processed + ' procesados, ' + data.errors + ' errores.');

                    if (data.details && data.details.length > 0) {
                        data.details.forEach(err => addLog(err, 'error'));
                    }

                    if (data.is_finished) {
                        finishSync();
                    } else {
                        processNextBatch();
                    }
                } else {
                    addLog('Error en el lote: ' + response.data.message, 'error');
                    stopSync();
                }
            }).fail(function () {
                addLog('Error de red durante el procesamiento. Reintentando en 3 segundos...', 'error');
                setTimeout(processNextBatch, 3000);
            });
        }

        function finishSync() {
            addLog('Procesando productos obsoletos (Mark and Sweep)...', 'success');
            processDeletionBatch(0);
        }

        function processDeletionBatch(offset) {
            $.post(diprotec_sync_params.ajax_url, {
                action: 'diprotec_sync_process_deletions',
                nonce: diprotec_sync_params.nonce,
                offset: offset
            }, function (response) {
                if (response.success) {
                    const data = response.data;
                    addLog('Productos obsoletos procesados lote: ' + data.processed + ' de ' + data.total);

                    if (!data.is_finished) {
                        processDeletionBatch(offset + data.processed);
                    } else {
                        cleanupSync();
                    }
                } else {
                    addLog('Error al procesar obsolescencia: ' + response.data.message, 'error');
                    cleanupSync();
                }
            }).fail(function () {
                addLog('Error de red al procesar obsolescencia.', 'error');
                stopSync();
            });
        }

        function cleanupSync() {
            addLog('Limpiando archivos temporales...', 'success');
            $.post(diprotec_sync_params.ajax_url, {
                action: 'diprotec_sync_cleanup',
                nonce: diprotec_sync_params.nonce
            }, function () {
                addLog('Sincronización FINALIZADA con éxito.', 'success');
                $btn.prop('disabled', false).text('Sincronización Completada');
                isRunning = false;
            });
        }

        function stopSync() {
            isRunning = false;
            $btn.prop('disabled', false).text('Reintentar Sincronización');
            addLog('Sincronización DETENIDA por errores.', 'error');
        }

        function updateProgress(current, total) {
            const perc = Math.round((current / total) * 100);
            $fill.css('width', perc + '%');
            $count.text(current);
            $percent.text(perc);
        }

        function addLog(msg, type = '') {
            const time = new Date().toLocaleTimeString();
            const cssClass = type ? 'log-' + type : '';
            $logs.prepend('<div class="log-item ' + cssClass + '">[' + time + '] ' + msg + '</div>');
        }
    });

})(jQuery);
