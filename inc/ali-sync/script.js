jQuery(document).ready(function ($) {
    let productIds = [];
    let currentIndex = 0;
    let isSyncing = false;
    let retryQueue = [];
    let retryDone = false;
    let successCount = 0;
    let failCount = 0;

    // Get total products initially
    $.post(aliSyncData.ajax_url, {
        action: 'ali_sync_get_products',
        nonce: aliSyncData.nonce
    }, function (response) {
        if (response.success) {
            $('#total-ald-count').text(response.data.count);
            productIds = response.data.ids;
        }
    });

    $('#start-sync').on('click', function () {
        if (isSyncing) return;

        if (productIds.length === 0) {
            alert('No products found to sync.');
            return;
        }

        isSyncing = true;
        currentIndex = 0;
        retryQueue = [];
        retryDone = false;
        successCount = 0;
        failCount = 0;
        $(this).prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Syncing...');
        $('#sync-progress-box').fadeIn();
        $('#sync-log').empty();

        updateProgress(0);
        processNext();
    });

    function processNext() {
        if (currentIndex >= productIds.length) {
            if (retryQueue.length > 0 && !retryDone) {
                retryDone = true;
                addLog(`\n⏳ ${retryQueue.length} products will be retried (waiting 15 sec)...`, 'info');
                setTimeout(function () {
                    productIds = retryQueue;
                    retryQueue = [];
                    currentIndex = 0;
                    processNext();
                }, 15000);
                return;
            }
            finishSync();
            return;
        }

        const productId = productIds[currentIndex];
        const doPrice = $('#sync-price').is(':checked');
        const doStock = $('#sync-stock').is(':checked');
        const doTitle = $('#sync-title').is(':checked');
        const doDesc = $('#sync-desc').is(':checked');

        addLog(`[${currentIndex + 1}/${productIds.length}] Product ID: ${productId} is being checked...`, 'info');

        $.post(aliSyncData.ajax_url, {
            action: 'ali_sync_single_product',
            product_id: productId,
            do_price: doPrice,
            do_stock: doStock,
            do_title: doTitle,
            do_desc: doDesc,
            nonce: aliSyncData.nonce
        }, function (response) {
            if (response.success) {
                const data = response.data;
                const changeMsg = data.changed ? '<span class="log-success">Updated</span>' : 'No changes';
                addLog(`✓ ${data.title}: ${changeMsg}`, 'info');
                successCount++;
            } else {
                addLog(`✗ Error (ID ${productId}): ${response.data}`, 'error');
                if (!retryDone) {
                    retryQueue.push(productId);
                } else {
                    failCount++;
                }
            }

            currentIndex++;
            updateProgress((currentIndex / productIds.length) * 100);

            setTimeout(processNext, 10000);
        }).fail(function () {
            addLog(`✗ Server error (ID ${productId})`, 'error');
            if (!retryDone) {
                retryQueue.push(productId);
            } else {
                failCount++;
            }
            currentIndex++;
            setTimeout(processNext, 7000);
        });
    }

    function updateProgress(percent) {
        percent = Math.round(percent);
        $('.progress-bar-fill').css('width', percent + '%');
        $('#progress-percentage').text(percent + '%');
        $('#progress-status').text(`Processing: ${currentIndex} / ${productIds.length}`);
    }

    function addLog(message, type) {
        const logBox = $('#sync-log');
        const entry = $('<div class="log-entry"></div>').addClass('log-' + type).html(message);
        logBox.append(entry);
        logBox.scrollTop(logBox[0].scrollHeight);
    }

    function finishSync() {
        isSyncing = false;
        $('#start-sync').prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Start Syncing');
        addLog(`\n--- SYNC COMPLETED ---`, 'success');
        addLog(`📊 Result: ✅ ${successCount} successful, ❌ ${failCount} failed`, 'info');
        $('#progress-status').text('Completed!');
    }

    // Fix SKUs
    $('#fix-skus').on('click', function () {
        const btn = $(this);
        if (!confirm('This will remove all _sync_tmp_ suffixes from your SKUs. Proceed?')) return;

        btn.prop('disabled', true).text('Fixing...');
        $.post(aliSyncData.ajax_url, {
            action: 'ali_sync_fix_skus',
            nonce: aliSyncData.nonce
        }, function (response) {
            btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> Fix Broken SKUs');
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

    // Show Broken SKUs is now a PHP link



    // Diagnostics Test
    $('#run-debug').on('click', function () {
        const btn = $(this);
        const output = $('#debug-output');
        btn.prop('disabled', true).text('Test Running...');
        output.show().text('Running connection tests, please wait...\n');

        const specificId = $('#debug-product-id').val();
        let testProductId = '';
        if (specificId) {
            testProductId = specificId;
        } else if (productIds.length > 0) {
            testProductId = productIds[0];
        }

        $.post(aliSyncData.ajax_url, {
            action: 'ali_sync_debug',
            test_product_id: testProductId,
            nonce: aliSyncData.nonce
        }, function (response) {
            btn.prop('disabled', false).text('Run Connection Test');
            if (response.success) {
                output.text(JSON.stringify(response.data, null, 2));
            } else {
                output.text('Error: ' + JSON.stringify(response));
            }
        }).fail(function (xhr) {
            btn.prop('disabled', false).text('Run Connection Test');
            output.text('Server error: HTTP ' + xhr.status + '\n' + xhr.responseText.substring(0, 500));
        });
    });
});
