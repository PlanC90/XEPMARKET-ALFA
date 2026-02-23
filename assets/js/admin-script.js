jQuery(document).ready(function ($) {
    // Tab switching logic
    $('.xep-nav-item').on('click', function () {
        var tab_id = $(this).attr('data-tab');
        $('.xep-nav-item').removeClass('active');
        $('.xep-tab-content').removeClass('active');
        $(this).addClass('active');
        $('#' + tab_id).addClass('active');
        localStorage.setItem('xep_active_tab', tab_id);
    });

    // Restore active tab
    var stored_tab = localStorage.getItem('xep_active_tab');
    var hash_tab = window.location.hash.replace('#', '');
    if (hash_tab && $('#' + hash_tab).length && hash_tab.startsWith('tab-')) {
        stored_tab = hash_tab;
        localStorage.setItem('xep_active_tab', hash_tab);
    }

    if (stored_tab && $('#' + stored_tab).length) {
        $('.xep-nav-item').removeClass('active');
        $('.xep-tab-content').removeClass('active');
        $('.xep-nav-item[data-tab="' + stored_tab + '"]').addClass('active');
        $('#' + stored_tab).addClass('active');
    }

    // Modern Save Logic - FIXED COLLISION
    $('.xep-trigger-save').on('click', function (e) {
        e.preventDefault();
        $('#xep-settings-form').submit();
    });

    // Handle Form Submit Event for Feedback
    $('#xep-settings-form').on('submit', function () {
        // Only target primary save buttons, not browse buttons
        var $btns = $('.xep-save-btn, .xep-trigger-save');
        $btns.html('<i class="fas fa-spinner fa-spin"></i> SAVING...').css({
            'opacity': '0.7',
            'pointer-events': 'none'
        });
    });

    // Handle "Saved" state after page reload
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('settings-updated') === 'true') {
        $('.xep-save-btn, .xep-trigger-save').each(function () {
            var $btn = $(this);
            var isMain = $btn.hasClass('xep-trigger-save');

            $btn.html('<i class="fas fa-check"></i> SAVED!').css({
                'background': 'linear-gradient(135deg, #2ecc71, #27ae60)',
                'color': '#fff',
                'opacity': '1'
            });

            setTimeout(function () {
                $btn.html(isMain ? 'Quick Save' : 'Save All Changes').attr('style', '');
            }, 3000);
        });
    }

    // Media Library Upload Logic
    $('.xep-browse-btn').on('click', function (e) {
        e.preventDefault();
        var button = $(this);
        var targetId = button.data('target');
        var customMedia = wp.media({
            title: 'Select Image',
            button: {
                text: 'Use Image'
            },
            multiple: false
        }).on('select', function () {
            var attachment = customMedia.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.url);

            // Live Preview Update
            var $preview = $('#preview_' + targetId);
            if ($preview.length) {
                $preview.attr('src', attachment.url);
            } else {
                // If preview container doesn't exist (newly added images), show it
                var previewHtml = '<div class="xep-image-preview" style="margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid var(--admin-border); background: #000; max-width: 300px;">' +
                    '<img src="' + attachment.url + '" style="width: 100%; display: block;" id="preview_' + targetId + '" />' +
                    '</div>';
                $('#' + targetId).closest('.xep-form-group').append(previewHtml);
            }
        }).open();
    });

    // Toggle Slider logic
    $('input[name="xepmarket2_slider_enable"]').on('change', function () {
        if ($(this).is(':checked')) {
            $('.slider-settings-group').slideDown();
            $('.static-hero-group').slideUp();
        } else {
            $('.slider-settings-group').slideUp();
            $('.static-hero-group').slideDown();
        }
    }).change();

    // ALPHA-IMPORTER: Demo Data Installation Handler
    $('#xep-run-demo-import').on('click', function () {
        if (!confirm('BE CAREFUL: Are you sure you want to install demo content? This will create core pages (Home, Shop, Swap), configure menus, and apply premium theme settings.')) return;

        const $btn = $(this);
        const $status = $('#xep-demo-status');
        const $progressWrap = $('#xep-demo-progress');
        const $progressBar = $('.xep-progress-bar');

        // Reset UI
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> INITIALIZING...');
        $status.text('Connecting to database...').css('color', 'var(--admin-primary)');
        $progressWrap.fadeIn();

        let progress = 0;
        const progressInt = setInterval(() => {
            if (progress < 90) {
                progress += Math.random() * 8;
                $progressBar.css('width', progress + '%');

                if (progress > 30 && progress < 60) {
                    $status.text('Generating core pages & structures...');
                } else if (progress >= 60) {
                    $status.text('Finalizing theme configurations...');
                }
            }
        }, 600);

        $.ajax({
            url: xep_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xep_import_demo',
                nonce: xep_admin.nonce
            },
            success: function (response) {
                clearInterval(progressInt);
                $progressBar.css('width', '100%');

                if (response.success) {
                    $status.text('DEMO INSTALLED SUCCESSFULLY!').css('color', '#32d74b');
                    $btn.html('<i class="fas fa-check"></i> REDIRECTING...').css('background', '#2ecc71');

                    setTimeout(() => {
                        window.location.href = window.location.origin + '?settings-updated=true';
                    }, 2000);
                } else {
                    $status.text('ERROR: ' + response.data).css('color', '#ff453a');
                    $btn.prop('disabled', false).text('RETRY INSTALLATION');
                }
            },
            error: function () {
                clearInterval(progressInt);
                $status.text('System error occurred. Please try again.').css('color', '#ff453a');
                $btn.prop('disabled', false).text('RETRY INSTALLATION');
            }
        });
    });

    // ALPHA-IMPORTER: Factory Reset Handler
    $('#xep-factory-reset').on('click', function () {
        if (!confirm('DANGER: This will delete ALL your theme settings, custom logos, and menus. Are you absolutely sure?')) return;
        if (!confirm('FINAL WARNING: This action is destructive and irreversible. Continue?')) return;

        const $btn = $(this);
        const $status = $('#xep-demo-status');
        const $progressWrap = $('#xep-demo-progress');
        const $progressBar = $('.xep-progress-bar');

        $btn.prop('disabled', true).html('<i class="fas fa-trash-alt fa-spin"></i> WIPING DATA...');
        $status.text('Cleaning database & theme options...').css('color', '#ff453a');
        $progressWrap.fadeIn();
        $progressBar.css({ 'width': '30%', 'background': '#ff453a' });

        $.ajax({
            url: xep_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'xep_factory_reset',
                nonce: xep_admin.nonce
            },
            success: function (response) {
                $progressBar.css('width', '100%');
                if (response.success) {
                    $status.text('SYSTEM RESET COMPLETE! REBOOTING...').css('color', '#32d74b');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    $status.text('RESET FAILED: ' + response.data).css('color', '#ff453a');
                    $btn.prop('disabled', false).text('RETRY RESET');
                }
            },
            error: function () {
                $status.text('System error during reset.').css('color', '#ff453a');
                $btn.prop('disabled', false).text('RETRY RESET');
            }
        });
    });
});
