jQuery(document).ready(function ($) {
    // Initialize color picker
    $('.xep-color-picker').wpColorPicker();

    // Tab switching logic
    function switchTab(tabId) {
        $('.xep-tab-link').removeClass('active');
        $(`.xep-tab-link[data-tab="${tabId}"]`).addClass('active');
        $('.xep-tab-content').removeClass('active');
        $('#tab-' + tabId).addClass('active');
    }

    $('.xep-tab-link').on('click', function () {
        const tabId = $(this).data('tab');
        switchTab(tabId);
    });

    // Handle URL tab parameter
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab) {
        switchTab(activeTab);
    }

    // Toast Notification Logic
    function showToast(message, icon = 'dashicons-yes') {
        const toast = $('<div class="xep-toast"><i class="dashicons ' + icon + '"></i><span>' + message + '</span></div>');
        $('body').append(toast);
        setTimeout(() => toast.addClass('show'), 100);
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    }

    // Detect Save/Reset/Module and show toast using PHP-passed data
    if (typeof xepSettings !== 'undefined') {
        if (xepSettings.isSaved) {
            showToast(xepSettings.saveMessage);
            $('.updated, .notice-success, #setting-error-settings_updated, .settings-error').hide();
        }
        if (xepSettings.isReset) {
            showToast('Reset to Factory Defaults!', 'dashicons-warning');
            $('.updated, .notice-success').hide();
        }
    }

    // Main Save Button Trigger (Since it's now outside the form for layout)
    $('#xep-main-save-trigger').on('click', function () {
        $('form[action="options.php"]').submit();
    });

    // Button Feedback on Submit (Main Settings)
    $('#xep-main-save-trigger, .xep-submit-btn').on('click', function () {
        const form = $(this).closest('form').length ? $(this).closest('form') : $('form[action="options.php"]');
        if (form.length && form[0].checkValidity && !form[0].checkValidity()) return;
        $(this).html('<i class="dashicons dashicons-update spin"></i> Saving...').css('opacity', '0.8');
    });

    // Module Action Feedback
    $('.xep-module-btn, .xep-modules-grid .xep-browse-btn').on('click', function () {
        $(this).html('<i class="dashicons dashicons-update spin"></i> Processing...').css('opacity', '0.8');
    });

    // Factory Reset Confirmation
    $('#xep-factory-reset-trigger').on('click', function (e) {
        e.preventDefault();
        if (confirm('WARNING: This will reset ALL theme settings (colors, logo, slider, social links) to their original factory defaults. This action cannot be undone. Are you sure?')) {
            $('#xep-reset-form').submit();
        }
    });

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
        }).open();
    });
});
