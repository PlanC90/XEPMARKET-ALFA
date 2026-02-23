<?php
/**
 * XepMarket2 Checkout Hardened Validation & Privacy Modal
 */

// 1. Hook to add the Checkbox before Submit Button
add_action('woocommerce_review_order_before_submit', 'xepmarket2_add_checkout_privacy_policy_with_checkbox', 5);
function xepmarket2_add_checkout_privacy_policy_with_checkbox()
{
    $privacy_url = get_privacy_policy_url() ?: '#';
    // Link class used for Modal trigger
    $privacy_link = '<a href="' . esc_url($privacy_url) . '" class="xep-privacy-modal-trigger" style="color: #00f2ff !important; text-decoration: underline !important; font-weight: 700 !important; margin-left: 5px; display: inline; position: relative; z-index: 10;">' . __('Privacy Policy', 'xepmarket2') . '</a>';

    $text = __('By completing your order, you agree to our:', 'xepmarket2') . ' ' . $privacy_link;

    echo '<div class="xep-privacy-policy-wrap" style="margin: 25px 0; width: 100%; border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; background: rgba(255,255,255,0.02); overflow: hidden;">';
    echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" style="display: flex !important; align-items: center; justify-content: flex-start; gap: 15px; cursor: pointer; font-size: 15px; color: rgba(255, 255, 255, 0.85); width: 100% !important; margin: 0 !important; padding: 25px; box-sizing: border-box; min-height: 80px;">';
    echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="xep_privacy_policy_acceptance" id="xep_privacy_policy_acceptance" value="1" style="margin: 0 !important; transform: scale(1.1); flex-shrink: 0;" /> ';
    echo '<span class="woocommerce-privacy-policy-text" style="line-height: normal; display: block; flex: 1; margin: 0 !important; padding: 0 !important;">' . $text . ' <abbr class="required" style="color: #ff453a; margin-left: 5px; border: none; text-decoration: none; font-size: 18px; vertical-align: middle; line-height: 0;" title="required">*</abbr></span>';
    echo '</label></div>';

    // Disable default WooCommerce behavior for this block
    echo '<style>.woocommerce-privacy-policy-text:not(.xep-privacy-policy-wrap .woocommerce-privacy-policy-text) { display: none !important; }</style>';
}

// 2. Hook to add Validation Logic & Modal in Footer
add_action('wp_footer', 'xepmarket2_checkout_validation_js', 51);
function xepmarket2_checkout_validation_js()
{
    if (!is_checkout() || is_wc_endpoint_url('order-received'))
        return;
    ?>
    <!-- Modal HTML -->
    <div id="xepPrivacyModal" class="xep-privacy-overlay" style="display:none;">
        <div class="xep-modal-container">
            <div class="xep-modal-header">
                <h2><?php _e('Privacy Policy', 'xepmarket2'); ?></h2>
                <button type="button" class="xep-modal-close-btn">&times;</button>
            </div>
            <div class="xep-modal-body">
                <?php
                $policy_page_id = get_option('wp_page_for_privacy_policy');
                if ($policy_page_id) {
                    $post = get_post($policy_page_id);
                    if ($post)
                        echo apply_filters('the_content', $post->post_content);
                } else {
                    echo '<p style="text-align:center; padding: 50px 0;">' . __('Privacy policy page not set.', 'xepmarket2') . '</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .xep-privacy-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            z-index: 100000;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .xep-modal-container {
            background: #0d0f14;
            max-width: 800px;
            width: 100%;
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            animation: xepModalFadeIn 0.3s ease;
        }

        @keyframes xepModalFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .xep-modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .xep-modal-header h2 {
            margin: 0;
            font-size: 22px;
            color: #00f2ff;
        }

        .xep-modal-close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 32px;
            cursor: pointer;
            opacity: 0.5;
            transition: 0.2s;
        }

        .xep-modal-close-btn:hover {
            opacity: 1;
        }

        .xep-modal-body {
            padding: 30px;
            overflow-y: auto;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.7;
            font-size: 15px;
            scrollbar-width: thin;
        }

        /* Validation Styles */
        #place_order.xep-btn-disabled {
            opacity: 0.45 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            filter: grayscale(1) !important;
            background: #2a2a2a !important;
            color: #888 !important;
            box-shadow: none !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        .xep-checkout-warning {
            background: rgba(255, 159, 10, 0.08);
            border: 1px solid rgba(255, 159, 10, 0.25);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #ff9f0a;
            font-weight: 600;
        }

        .xep-checkout-warning.xep-hidden {
            display: none;
        }


        .xep-privacy-policy-wrap .woocommerce-privacy-policy-text {
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.4 !important;
            display: inline-block !important;
        }

        @keyframes xepShake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }
    </style>

    <script>

        (function ($) {
            // -- Modal Logic --
            $(document).on('click', '.xep-privacy-modal-trigger', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $('#xepPrivacyModal').css('display', 'flex').hide().fadeIn(200);
                $('body').css('overflow', 'hidden');
            });

            $(document).on('click', '.xep-modal-close-btn, .xep-privacy-overlay', function (e) {
                if ($(e.target).closest('.xep-modal-container').length && !$(e.target).hasClass('xep-modal-close-btn')) return;
                $('#xepPrivacyModal').fadeOut(200);
                $('body').css('overflow', 'auto');
            });

            $(document).on('keydown', function (e) {
                if (e.key === "Escape") {
                    $('#xepPrivacyModal').fadeOut(200);
                    $('body').css('overflow', 'auto');
                }
            });

            // -- Validation Logic --
            function doValidation() {
                var $btn = $('#place_order');
                if (!$btn.length) return;

                // Optimization: Don't interfere if the button is currently in a "processing" state from WooCommerce or OmniXEP
                if ($btn.hasClass('processing') || $btn.prop('disabled') === true && !$btn.hasClass('xep-btn-disabled')) {
                    return;
                }

                var $form = $btn.closest('form');
                if (!$form.length) $form = $('form.checkout');

                var $warning = $('#xep-checkout-warning');
                if (!$warning.length) {
                    $btn.before('<div id="xep-checkout-warning" class="xep-checkout-warning xep-hidden"><i class="fas fa-exclamation-triangle"></i><span id="xep-checkout-warning-text"></span></div>');
                    $warning = $('#xep-checkout-warning');
                }

                var issues = [];

                // A. Required Fields check
                $form.find('.validate-required:visible').each(function () {
                    var $row = $(this);
                    // Skip if it's a checkbox we handle specifically below
                    if ($row.find('input[type="checkbox"]').length > 0) return;

                    var $input = $row.find('input:not([type="hidden"]), select, textarea');
                    if ($input.length && (!$input.val() || $input.val().trim() === "")) {
                        var label = $row.find('label').text().replace(/\*/g, '').trim();
                        if (label) issues.push(label);
                    }
                });

                // B. Privacy Policy & Terms (Strict)
                var xepAccepted = $('#xep_privacy_policy_acceptance').prop('checked');
                var wcTerms = $('#terms').length ? $('#terms').prop('checked') : true; // If standard terms exist, they must be checked too

                if (!xepAccepted) {
                    issues.push('Privacy Policy');
                } else if (!wcTerms) {
                    issues.push('Terms and Conditions');
                }

                // C. Wallet PC Check
                var isPC = !/iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
                var wallet = window.omnixep || window.omniXep;
                if (isPC && !wallet) {
                    var method = $('input[name="payment_method"]:checked').val();
                    if (method === 'omnixep' || $('input[name="payment_method"]').length <= 1) {
                        issues.push('OmniXEP Wallet Extension');
                    }
                }

                // 3. Apply State
                if (issues.length > 0) {
                    $btn.addClass('xep-btn-disabled');
                    // We don't use .prop('disabled', true) to avoid interfering with WooCommerce's own blockUI/unblockUI

                    var msg = issues.indexOf('OmniXEP Wallet Extension') !== -1
                        ? 'OmniXEP Wallet extension not found. Please install it and refresh the page.'
                        : 'Please fill: ' + issues.join(', ');

                    $warning.find('#xep-checkout-warning-text').text(msg);
                    $warning.removeClass('xep-hidden');
                } else {
                    $btn.removeClass('xep-btn-disabled');
                    $warning.addClass('xep-hidden');
                }
            }

            // Universal Click Blocker for disabled state
            $(document.body).on('click', '#place_order', function (e) {
                if ($(this).hasClass('xep-btn-disabled')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    // Shake effect on warning
                    var $w = $('#xep-checkout-warning');
                    $w.css('animation', 'none');
                    setTimeout(function () { $w.css('animation', 'xepShake 0.4s'); }, 10);
                    return false;
                }
            });

            // Listen for changes
            $(document).on('change input', 'form.checkout input, form.checkout select, form.checkout textarea', function () {
                setTimeout(doValidation, 50);
            });

            $(document.body).on('updated_checkout checkout_error updated_shipping_method', function () {
                setTimeout(doValidation, 100);
            });

            // Initial run
            $(document).ready(function () {
                setTimeout(doValidation, 1000); // Small delay for extensions to inject
            });

            // Re-run frequently but not every 300ms
            var validationCounter = 0;
            var validationInterval = setInterval(function () {
                doValidation();
                validationCounter++;
                if (validationCounter > 20) clearInterval(validationInterval); // Stop after 10 seconds of page load
            }, 500);

        })(jQuery);
    </script>
    <?php
}

