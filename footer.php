</div><!-- #content -->

<footer id="colophon" class="site-footer">
    <div class="container">
        <div class="footer-content" style="text-align: center; padding: 40px 0;">
            <div class="footer-brand" style="margin-bottom: 40px;">
                <?php
                $f_logo_type = xepmarket2_get_option_fast('xepmarket2_footer_logo_type', 'text');
                $f_logo_img = xepmarket2_get_option_fast('xepmarket2_footer_logo_img');
                $f_logo_text1 = xepmarket2_get_option_fast('xepmarket2_footer_logo_text_1', 'XEP');
                $f_logo_text2 = xepmarket2_get_option_fast('xepmarket2_footer_logo_text_2', 'MARKET');
                ?>
                <span class="logo-text" style="font-size: 32px;">
                    <?php if ($f_logo_type === 'image' && $f_logo_img): ?>
                        <img src="<?php echo esc_url($f_logo_img); ?>" alt="<?php bloginfo('name'); ?>"
                            style="max-height: 60px; width: auto; margin: 0 auto; display: block;">
                    <?php else: ?>
                        <?php echo esc_html($f_logo_text1); ?><span
                            class="logo-accent"><?php echo esc_html($f_logo_text2); ?></span>
                    <?php endif; ?>
                </span>
                <p class="footer-desc"
                    style="margin-top: 20px; color: var(--text-muted); max-width: 650px; margin-left: auto; margin-right: auto; line-height: 1.8;">
                    <?php echo xepmarket2_get_option_fast('xepmarket2_footer_desc', 'Your premium destination for Web3 lifestyle gear. From hardware security to exclusive crypto apparel, we bring the blockchain to your doorstep.'); ?>
                </p>
                <div class="footer-social" style="margin-top: 25px; display: flex; justify-content: center; gap: 15px;">
                    <?php if ($tg = xepmarket2_get_option_fast('xepmarket2_social_telegram')): ?>
                        <a href="<?php echo esc_url($tg); ?>" target="_blank" class="social-link" title="Telegram"><i
                                class="fab fa-telegram-plane"></i></a>
                    <?php endif; ?>
                    <?php if ($ds = xepmarket2_get_option_fast('xepmarket2_social_discord')): ?>
                        <a href="<?php echo esc_url($ds); ?>" target="_blank" class="social-link" title="Discord"><i
                                class="fab fa-discord"></i></a>
                    <?php endif; ?>
                    <?php if ($tw = xepmarket2_get_option_fast('xepmarket2_social_twitter')): ?>
                        <a href="<?php echo esc_url($tw); ?>" target="_blank" class="social-link" title="X (Twitter)"><i
                                class="fab fa-twitter"></i></a>
                    <?php endif; ?>
                    <?php if ($ig = xepmarket2_get_option_fast('xepmarket2_social_instagram')): ?>
                        <a href="<?php echo esc_url($ig); ?>" target="_blank" class="social-link" title="Instagram"><i
                                class="fab fa-instagram"></i></a>
                    <?php endif; ?>
                    <?php if ($yt = xepmarket2_get_option_fast('xepmarket2_social_youtube')): ?>
                        <a href="<?php echo esc_url($yt); ?>" target="_blank" class="social-link" title="YouTube"><i
                                class="fab fa-youtube"></i></a>
                    <?php endif; ?>
                    <?php if ($pn = xepmarket2_get_option_fast('xepmarket2_social_pinterest')): ?>
                        <a href="<?php echo esc_url($pn); ?>" target="_blank" class="social-link" title="Pinterest"><i
                                class="fab fa-pinterest"></i></a>
                    <?php endif; ?>
                    <?php if ($tk = xepmarket2_get_option_fast('xepmarket2_social_tiktok')): ?>
                        <a href="<?php echo esc_url($tk); ?>" target="_blank" class="social-link" title="TikTok"><i
                                class="fab fa-tiktok"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="footer-info" style="border-top: 1px solid var(--border-glass); padding-top: 30px;">
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                    &copy; <?php echo date('Y'); ?> XEPmarket.com. Built on
                    <a href="https://electraprotocol.com/" target="_blank"
                        style="color: var(--primary); text-decoration: none; font-weight: 600;">Electra Protocol</a>
                </p>
                <div class="payment-methods"
                    style="display: flex; justify-content: center; gap: 15px; align-items: center;">
                    <?php
                    for ($i = 1; $i <= 4; $i++) {
                        $t_name = xepmarket2_get_option_fast('xepmarket2_token_name_' . $i);
                        $t_status = xepmarket2_get_option_fast('xepmarket2_token_status_' . $i, 'hidden');

                        if (empty($t_name) || $t_status === 'hidden')
                            continue;

                        if ($t_status === 'live') {
                            echo '<span class="payment-badge" style="background: rgba(0, 255, 0, 0.1); color: #00ff00; border: 1px solid rgba(0, 255, 0, 0.2); padding: 5px 15px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase;">' . esc_html($t_name) . ' LIVE</span>';
                        } else {
                            echo '<span class="payment-badge" style="background: rgba(255, 255, 255, 0.05); color: var(--text-muted); padding: 5px 15px; border-radius: 50px; font-size: 11px; font-weight: 600; opacity: 0.6; text-transform: uppercase;">' . esc_html($t_name) . ' COMING SOON</span>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- Customer Feedback Form                                             -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div class="xep-feedback-section" style="background: rgba(255,255,255,0.02); border-top: 1px solid var(--border-glass); padding: 30px 0;">
    <div class="container" style="max-width: 800px; margin: 0 auto; text-align: center;">
        <button type="button" class="xep-feedback-toggle" onclick="xepToggleFeedback()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-muted); padding: 12px 24px; border-radius: 12px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-comment-dots"></i>
            Report an Issue to ElectraPay
        </button>
        
        <div id="xep-feedback-form-wrap" class="xep-feedback-form-wrap" style="display: none; margin-top: 30px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 30px; text-align: left;">
            <form id="xep-feedback-form" class="xep-feedback-form">
                <h4 style="margin: 0 0 10px 0; font-size: 20px; color: #ffffff;">Report an Issue to ElectraPay</h4>
                <p class="xep-feedback-desc" style="margin: 0 0 25px 0; color: var(--text-muted); font-size: 14px;">
                    This report will be sent to <strong style="color: var(--primary, #00f2ff);">ElectraPay Payment Gateway</strong>. 
                    Help us improve by reporting any issues with your order or payment experience.
                </p>
                
                <div style="background: rgba(0,242,255,0.1); border-left: 3px solid var(--primary, #00f2ff); padding: 12px 16px; border-radius: 8px; margin-bottom: 25px;">
                    <p style="margin: 0; font-size: 13px; color: rgba(255,255,255,0.8); line-height: 1.6;">
                        <strong style="color: var(--primary, #00f2ff);">ℹ️ Important:</strong> 
                        This form is for reporting issues with the <strong>ElectraPay payment gateway</strong> only. 
                        For merchant/store-related issues, please contact the store directly.
                    </p>
                </div>
                
                <!-- Honeypot field (hidden from users, bots will fill it) -->
                <input type="text" name="website" value="" style="position: absolute; left: -9999px; width: 1px; height: 1px;" tabindex="-1" autocomplete="off">
                
                <!-- Bot check timestamp -->
                <input type="hidden" name="form_loaded_at" id="xep-form-loaded-at" value="">
                
                <div class="xep-form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #ffffff; font-size: 14px; font-weight: 600;">Order ID (Optional)</label>
                    <input type="text" name="order_id" placeholder="e.g., 12345" style="width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: #ffffff; font-size: 14px; outline: none; transition: all 0.3s ease; box-sizing: border-box;">
                </div>
                
                <div class="xep-form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #ffffff; font-size: 14px; font-weight: 600;">Issue Category <span style="color: #ff6b6b;">*</span></label>
                    <select name="category" required style="width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: #ffffff; font-size: 14px; outline: none; cursor: pointer; transition: all 0.3s ease; box-sizing: border-box; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27white%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px; padding-right: 40px;">
                        <option value="" style="background: #1a1a1a; color: #999;">Select a category</option>
                        <option value="product_not_shipped" style="background: #1a1a1a; color: #ffffff;">Product Not Shipped</option>
                        <option value="refund_not_processed" style="background: #1a1a1a; color: #ffffff;">Refund Not Processed</option>
                        <option value="illegal_product" style="background: #1a1a1a; color: #ffffff;">Illegal Product Sale</option>
                        <option value="ip_violation" style="background: #1a1a1a; color: #ffffff;">Intellectual Property Violation</option>
                        <option value="counterfeit" style="background: #1a1a1a; color: #ffffff;">Counterfeit Product</option>
                        <option value="false_advertising" style="background: #1a1a1a; color: #ffffff;">False Advertising</option>
                        <option value="poor_quality" style="background: #1a1a1a; color: #ffffff;">Poor Quality</option>
                        <option value="damaged_product" style="background: #1a1a1a; color: #ffffff;">Damaged Product</option>
                        <option value="wrong_item" style="background: #1a1a1a; color: #ffffff;">Wrong Item Received</option>
                        <option value="other" style="background: #1a1a1a; color: #ffffff;">Other</option>
                    </select>
                </div>
                
                <div class="xep-form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #ffffff; font-size: 14px; font-weight: 600;">Description <span style="color: #ff6b6b;">*</span></label>
                    <textarea name="description" rows="4" placeholder="Please describe the issue in detail..." required style="width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: #ffffff; font-size: 14px; outline: none; resize: vertical; transition: all 0.3s ease; font-family: inherit; box-sizing: border-box;"></textarea>
                </div>
                
                <div class="xep-form-group" style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 8px; color: #ffffff; font-size: 14px; font-weight: 600;">Email (Optional, for follow-up)</label>
                    <input type="email" name="email" placeholder="your@email.com" style="width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: #ffffff; font-size: 14px; outline: none; transition: all 0.3s ease; box-sizing: border-box;">
                </div>
                
                <div class="xep-form-actions" style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="xep-btn-cancel" onclick="xepToggleFeedback()" style="padding: 12px 24px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-muted); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease;">Cancel</button>
                    <button type="submit" class="xep-btn-submit" style="padding: 12px 24px; background: var(--primary, #00f2ff); border: none; color: #000; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 700; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,242,255,0.3);">
                        <span class="xep-submit-text">Submit Report</span>
                        <span class="xep-submit-loading" style="display: none;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Sending...
                        </span>
                    </button>
                </div>
                
                <div id="xep-feedback-message" class="xep-feedback-message" style="margin-top: 20px; padding: 15px; border-radius: 10px; display: none; font-size: 14px;"></div>
            </form>
        </div>
    </div>
</div>

<style>
    .xep-feedback-toggle:hover {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.2);
        color: #ffffff;
        transform: translateY(-2px);
    }
    
    .xep-form-group input,
    .xep-form-group select,
    .xep-form-group textarea {
        box-sizing: border-box;
    }
    
    .xep-form-group input::placeholder,
    .xep-form-group textarea::placeholder {
        color: rgba(255,255,255,0.4);
    }
    
    .xep-form-group input:focus,
    .xep-form-group select:focus,
    .xep-form-group textarea:focus {
        border-color: var(--primary, #00f2ff);
        background: rgba(0,0,0,0.5);
        box-shadow: 0 0 0 3px rgba(0,242,255,0.1);
    }
    
    .xep-form-group select option {
        background: #1a1a1a;
        color: #ffffff;
        padding: 10px;
    }
    
    .xep-btn-cancel:hover {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.2);
        color: #ffffff;
    }
    
    .xep-btn-submit:hover {
        background: #fff;
        box-shadow: 0 6px 20px rgba(255,255,255,0.4);
        transform: translateY(-2px);
    }
    
    .xep-btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .xep-feedback-message.success {
        background: rgba(63,185,80,0.15);
        border: 1px solid rgba(63,185,80,0.3);
        color: #3fb950;
    }
    
    .xep-feedback-message.error {
        background: rgba(248,81,73,0.15);
        border: 1px solid rgba(248,81,73,0.3);
        color: #f85149;
    }
</style>

<script>
    function xepToggleFeedback() {
        var wrap = document.getElementById('xep-feedback-form-wrap');
        if (wrap.style.display === 'none') {
            wrap.style.display = 'block';
            // Set form loaded timestamp for bot detection
            document.getElementById('xep-form-loaded-at').value = Date.now();
            // Scroll to form
            setTimeout(function() {
                wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        } else {
            wrap.style.display = 'none';
            // Reset form
            document.getElementById('xep-feedback-form').reset();
            document.getElementById('xep-feedback-message').style.display = 'none';
        }
    }
    
    // Handle form submission
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('xep-feedback-form');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var submitBtn = form.querySelector('.xep-btn-submit');
            var submitText = submitBtn.querySelector('.xep-submit-text');
            var submitLoading = submitBtn.querySelector('.xep-submit-loading');
            var messageDiv = document.getElementById('xep-feedback-message');
            
            // Bot detection checks
            var honeypot = form.querySelector('input[name="website"]');
            if (honeypot && honeypot.value !== '') {
                // Bot detected (filled honeypot field)
                console.log('Bot detected: honeypot filled');
                return false;
            }
            
            var formLoadedAt = parseInt(form.querySelector('input[name="form_loaded_at"]').value);
            var timeDiff = Date.now() - formLoadedAt;
            if (timeDiff < 3000) {
                // Submitted too fast (less than 3 seconds)
                messageDiv.style.display = 'block';
                messageDiv.className = 'xep-feedback-message error';
                messageDiv.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Please take a moment to review your submission.';
                return false;
            }
            
            // Disable submit button
            submitBtn.disabled = true;
            submitText.style.display = 'none';
            submitLoading.style.display = 'inline';
            messageDiv.style.display = 'none';
            
            // Prepare form data
            var formData = new FormData(form);
            formData.append('action', 'omnixep_submit_feedback');
            formData.append('nonce', omnixep_feedback.nonce);
            
            // Send AJAX request
            fetch(omnixep_feedback.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitText.style.display = 'inline';
                submitLoading.style.display = 'none';
                
                // Show message
                messageDiv.style.display = 'block';
                
                if (data.success) {
                    messageDiv.className = 'xep-feedback-message success';
                    messageDiv.innerHTML = '<i class="fa-solid fa-check-circle"></i> ' + 
                        '<strong>Thank you!</strong> Your report has been submitted to ElectraPay successfully. ' +
                        'We will review your complaint and take appropriate action. ' +
                        'Reference: <strong>' + (data.reference_number || 'N/A') + '</strong>';
                    
                    // Reset form after 5 seconds
                    setTimeout(function() {
                        form.reset();
                        messageDiv.style.display = 'none';
                        xepToggleFeedback();
                    }, 5000);
                } else {
                    messageDiv.className = 'xep-feedback-message error';
                    var errorMsg = data.message || data.error || 'An error occurred. Please try again.';
                    messageDiv.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> ' + errorMsg;
                }
            })
            .catch(function(error) {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitText.style.display = 'inline';
                submitLoading.style.display = 'none';
                
                // Show error message
                messageDiv.style.display = 'block';
                messageDiv.className = 'xep-feedback-message error';
                messageDiv.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Connection error. Please try again.';
                console.error('Feedback submission error:', error);
            });
        });
    });
</script>

</div><!-- #page -->


<!-- Scroll to Top Button -->
<button id="scroll-to-top" class="scroll-to-top" aria-label="Scroll to Top">
    <i class="dashicons dashicons-arrow-up-alt2"></i>
</button>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- Premium Full-Screen Search Overlay                                 -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div id="global-search-overlay">
    <div class="xep-search-backdrop" onclick="xepCloseSearch()"></div>
    <div class="xep-search-container">
        <!-- Close Button -->
        <button class="xep-search-close" onclick="xepCloseSearch()" aria-label="Close search">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>

        <!-- Search Header -->
        <div class="xep-search-header">
            <div class="xep-search-icon-pulse">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <h2 class="xep-search-title">What are you looking for?</h2>
            <p class="xep-search-subtitle">Search through our entire product catalog</p>
        </div>

        <!-- Search Input -->
        <form role="search" method="get" class="xep-search-form" action="<?php echo esc_url(home_url('/')); ?>"
            onsubmit="return xepSearchSubmit(event)">
            <div class="xep-search-input-wrap">
                <i class="fa-solid fa-magnifying-glass xep-input-icon"></i>
                <input type="search" id="xep-search-input" class="xep-search-input"
                    placeholder="Type to search products..." name="s" autocomplete="off" autocorrect="off"
                    autocapitalize="off" spellcheck="false" />
                <input type="hidden" name="post_type" value="product" />
                <div class="xep-search-spinner" id="xep-search-spinner">
                    <div class="xep-spinner-ring"></div>
                </div>
                <kbd class="xep-search-kbd">ESC</kbd>
            </div>
        </form>

        <!-- Live Results -->
        <div id="xep-search-results" class="xep-search-results"></div>
    </div>
</div>

<style>
    /* ── Search Overlay Base ─────────────────────────────────────────── */
    #global-search-overlay {
        position: fixed;
        inset: 0;
        z-index: 999999;
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding-top: 8vh;
        opacity: 0;
        transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #global-search-overlay.active {
        display: flex;
        opacity: 0;
        /* will be set to 1 via JS */
    }

    /* Backdrop */
    .xep-search-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(5, 6, 10, 0.92);
        backdrop-filter: blur(40px) saturate(180%);
        -webkit-backdrop-filter: blur(40px) saturate(180%);
        cursor: pointer;
    }

    /* Container */
    .xep-search-container {
        position: relative;
        width: 94%;
        max-width: 720px;
        z-index: 2;
        transform: translateY(-30px) scale(0.97);
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
        opacity: 0;
    }

    #global-search-overlay.visible .xep-search-container {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    /* Close Button */
    .xep-search-close {
        position: absolute;
        top: -50px;
        right: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.06);
        color: rgba(255, 255, 255, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.25s ease;
        padding: 0;
    }

    .xep-search-close:hover {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        transform: rotate(90deg);
        border-color: rgba(255, 255, 255, 0.25);
    }

    /* Search Header */
    .xep-search-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .xep-search-icon-pulse {
        width: 60px;
        height: 60px;
        margin: 0 auto 18px;
        background: linear-gradient(135deg, rgba(0, 242, 255, 0.15), rgba(112, 0, 255, 0.15));
        border: 1px solid rgba(0, 242, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: var(--primary, #00f2ff);
        animation: xepPulse 2.5s ease-in-out infinite;
    }

    @keyframes xepPulse {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(0, 242, 255, 0.3);
        }

        50% {
            box-shadow: 0 0 0 15px rgba(0, 242, 255, 0);
        }
    }

    .xep-search-title {
        font-family: 'Outfit', sans-serif;
        font-size: 28px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 6px;
        letter-spacing: -0.5px;
    }

    .xep-search-subtitle {
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.4);
        margin: 0;
        letter-spacing: 0.3px;
    }

    /* Search Input */
    .xep-search-form {
        margin: 0;
    }

    .xep-search-input-wrap {
        position: relative;
        width: 100%;
    }

    .xep-input-icon {
        position: absolute;
        left: 22px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
        color: rgba(255, 255, 255, 0.3);
        pointer-events: none;
        transition: color 0.3s ease;
    }

    .xep-search-input-wrap:focus-within .xep-input-icon {
        color: var(--primary, #00f2ff);
    }

    .xep-search-input {
        width: 100%;
        background: rgba(255, 255, 255, 0.04);
        border: 1.5px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 20px 100px 20px 56px;
        font-size: 18px;
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        color: #fff;
        outline: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        box-sizing: border-box;
    }

    .xep-search-input::placeholder {
        color: rgba(255, 255, 255, 0.25);
        font-weight: 400;
    }

    .xep-search-input:focus {
        border-color: rgba(0, 242, 255, 0.35);
        background: rgba(255, 255, 255, 0.06);
        box-shadow: 0 4px 40px rgba(0, 242, 255, 0.08), 0 4px 30px rgba(0, 0, 0, 0.3);
    }

    /* Spinner */
    .xep-search-spinner {
        position: absolute;
        right: 65px;
        top: 50%;
        transform: translateY(-50%);
        display: none;
    }

    .xep-search-spinner.active {
        display: block;
    }

    .xep-spinner-ring {
        width: 22px;
        height: 22px;
        border: 2.5px solid rgba(255, 255, 255, 0.1);
        border-top-color: var(--primary, #00f2ff);
        border-radius: 50%;
        animation: xepSpin 0.7s linear infinite;
    }

    @keyframes xepSpin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ESC Badge */
    .xep-search-kbd {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        font-family: 'Inter', sans-serif;
        font-size: 11px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        padding: 4px 8px;
        pointer-events: none;
        letter-spacing: 0.5px;
    }

    /* ── Search Results ──────────────────────────────────────────────── */
    .xep-search-results {
        margin-top: 16px;
        max-height: 55vh;
        overflow-y: auto;
        overscroll-behavior: contain;
        border-radius: 16px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
    }

    .xep-search-results::-webkit-scrollbar {
        width: 6px;
    }

    .xep-search-results::-webkit-scrollbar-track {
        background: transparent;
    }

    .xep-search-results::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }

    /* Individual Result Item */
    .live-search-results-list {
        display: flex;
        flex-direction: column;
        gap: 2px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 16px;
        overflow: hidden;
    }

    .live-search-item {
        display: flex !important;
        align-items: center;
        gap: 16px;
        padding: 14px 20px;
        text-decoration: none !important;
        color: #fff !important;
        transition: all 0.2s ease;
        background: transparent;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .live-search-item:last-child {
        border-bottom: none;
    }

    .live-search-item:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .live-search-image {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        overflow: hidden;
        flex-shrink: 0;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.06);
    }

    .live-search-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .live-search-info {
        flex: 1;
        min-width: 0;
    }

    .live-search-title {
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        font-weight: 600;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 4px;
    }

    .live-search-price {
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        color: var(--primary, #00f2ff);
        font-weight: 600;
    }

    .live-search-price del {
        color: rgba(255, 255, 255, 0.3);
        font-weight: 400;
        margin-right: 6px;
    }

    .live-search-price ins {
        text-decoration: none;
        color: var(--primary, #00f2ff);
    }

    /* Footer inside results */
    .live-search-footer {
        padding: 14px 20px;
        text-align: center;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .view-all-results {
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: var(--primary, #00f2ff) !important;
        text-decoration: none !important;
        transition: opacity 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .view-all-results:hover {
        opacity: 0.8;
    }

    /* No results */
    .live-search-no-results {
        text-align: center;
        padding: 40px 20px;
        color: rgba(255, 255, 255, 0.35);
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 16px;
    }

    /* Loading shimmer */
    .xep-search-shimmer {
        display: flex;
        flex-direction: column;
        gap: 2px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 16px;
        overflow: hidden;
    }

    .xep-shimmer-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 20px;
    }

    .xep-shimmer-img {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0.04) 25%, rgba(255, 255, 255, 0.08) 50%, rgba(255, 255, 255, 0.04) 75%);
        background-size: 200% 100%;
        animation: xepShimmer 1.5s infinite;
        flex-shrink: 0;
    }

    .xep-shimmer-lines {
        flex: 1;
    }

    .xep-shimmer-line {
        height: 12px;
        border-radius: 6px;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0.04) 25%, rgba(255, 255, 255, 0.08) 50%, rgba(255, 255, 255, 0.04) 75%);
        background-size: 200% 100%;
        animation: xepShimmer 1.5s infinite;
        margin-bottom: 8px;
    }

    .xep-shimmer-line:last-child {
        width: 40%;
        margin-bottom: 0;
    }

    @keyframes xepShimmer {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        #global-search-overlay {
            padding-top: 4vh;
        }

        .xep-search-container {
            width: 96%;
        }

        .xep-search-title {
            font-size: 22px;
        }

        .xep-search-input {
            font-size: 16px;
            padding: 17px 85px 17px 50px;
        }

        .xep-search-icon-pulse {
            width: 50px;
            height: 50px;
            font-size: 18px;
        }

        .xep-search-close {
            top: -45px;
        }

        .live-search-image {
            width: 48px;
            height: 48px;
        }
    }
</style>

<script>
    (function () {
        'use strict';

        var overlay = document.getElementById('global-search-overlay');
        var searchInput = document.getElementById('xep-search-input');
        var spinner = document.getElementById('xep-search-spinner');
        var results = document.getElementById('xep-search-results');
        var debounceTimer = null;
        var currentXHR = null;

        // ── Open Search ─────────────────────────────────────────────
        window.xepOpenSearch = function (e) {
            if (e) e.preventDefault();
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Force reflow then animate
            void overlay.offsetWidth;
            overlay.style.opacity = '1';
            overlay.classList.add('active');
            requestAnimationFrame(function () {
                overlay.classList.add('visible');
            });
            setTimeout(function () {
                searchInput.value = '';
                results.innerHTML = '';
                searchInput.focus();
            }, 100);
        };

        // ── Close Search ────────────────────────────────────────────
        window.xepCloseSearch = function () {
            overlay.classList.remove('visible');
            overlay.style.opacity = '0';
            document.body.style.overflow = '';
            if (currentXHR) { currentXHR.abort(); currentXHR = null; }
            setTimeout(function () {
                overlay.classList.remove('active');
                overlay.style.display = 'none';
                results.innerHTML = '';
                searchInput.value = '';
            }, 400);
        };

        // ── Search Submit ──────────────────────────────────────────
        window.xepSearchSubmit = function (e) {
            var q = searchInput.value.trim();
            if (q.length < 1) { e.preventDefault(); return false; }
            return true;
        };

        // ── ESC Key ─────────────────────────────────────────────────
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('active')) {
                xepCloseSearch();
            }
        });

        // ── Header Search Button ────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('header-search-open');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    xepOpenSearch();
                });
                btn.addEventListener('mouseenter', function () {
                    this.style.background = 'rgba(255,255,255,0.12)';
                    this.style.borderColor = 'rgba(255,255,255,0.25)';
                    this.style.transform = 'translateY(-2px)';
                });
                btn.addEventListener('mouseleave', function () {
                    this.style.background = 'rgba(255,255,255,0.05)';
                    this.style.borderColor = 'rgba(255,255,255,0.1)';
                    this.style.transform = 'translateY(0)';
                });
            }
        });

        // ── Live Search (per keystroke) ─────────────────────────────
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var query = this.value.trim();

                if (debounceTimer) clearTimeout(debounceTimer);
                if (currentXHR) { currentXHR.abort(); currentXHR = null; }

                if (query.length < 2) {
                    results.innerHTML = '';
                    spinner.classList.remove('active');
                    return;
                }

                // Show shimmer loading
                spinner.classList.add('active');
                results.innerHTML = buildShimmer();

                debounceTimer = setTimeout(function () {
                    doSearch(query);
                }, 250);
            });
        }

        function doSearch(query) {
            var formData = new FormData();
            formData.append('action', 'xep_live_search');
            formData.append('query', query);
            formData.append('nonce', (typeof xep_live_search !== 'undefined') ? xep_live_search.nonce : '');

            var ajaxUrl = (typeof xep_live_search !== 'undefined') ? xep_live_search.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>';

            var xhr = new XMLHttpRequest();
            currentXHR = xhr;
            xhr.open('POST', ajaxUrl, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    spinner.classList.remove('active');
                    currentXHR = null;
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.success && data.data && data.data.html) {
                                results.innerHTML = data.data.html;
                            } else {
                                results.innerHTML = '<div class="live-search-no-results">No products found.</div>';
                            }
                        } catch (e) {
                            results.innerHTML = '<div class="live-search-no-results">Something went wrong.</div>';
                        }
                    }
                }
            };
            xhr.send(formData);
        }

        function buildShimmer() {
            var h = '<div class="xep-search-shimmer">';
            for (var i = 0; i < 4; i++) {
                h += '<div class="xep-shimmer-item">' +
                    '<div class="xep-shimmer-img"></div>' +
                    '<div class="xep-shimmer-lines"><div class="xep-shimmer-line"></div><div class="xep-shimmer-line"></div></div>' +
                    '</div>';
            }
            h += '</div>';
            return h;
        }
    })();
</script>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- Premium Add to Cart Modal                                          -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<div id="xep-add-to-cart-modal" class="xep-atc-overlay">
    <div class="xep-atc-backdrop" onclick="xepCloseAtcModal()"></div>
    <div class="xep-atc-container">
        <div class="xep-atc-icon-wrapper">
            <i class="fa-solid fa-check"></i>
        </div>
        <h3 class="xep-atc-title">Added to Cart!</h3>
        <p class="xep-atc-subtitle">What would you like to do next?</p>
        <div class="xep-atc-actions">
            <button class="xep-atc-btn-secondary" onclick="xepCloseAtcModal()">Continue Shopping</button>
            <a href="<?php echo function_exists('wc_get_cart_url') ? wc_get_cart_url() : '/cart'; ?>"
                class="xep-atc-btn-primary">Go to Cart</a>
        </div>
    </div>
</div>

<style>
    /* ── Add to Cart Modal ─────────────────────────────────────────── */
    #xep-add-to-cart-modal {
        position: fixed;
        inset: 0;
        z-index: 999999;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #xep-add-to-cart-modal.active {
        display: flex;
    }

    .xep-atc-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(5, 6, 10, 0.85);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        cursor: pointer;
    }

    .xep-atc-container {
        position: relative;
        width: 90%;
        max-width: 420px;
        background: rgba(20, 22, 30, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        padding: 40px 30px;
        text-align: center;
        z-index: 2;
        transform: translateY(30px) scale(0.95);
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
        opacity: 0;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(0, 242, 255, 0.1);
    }

    #xep-add-to-cart-modal.visible .xep-atc-container {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    .xep-atc-icon-wrapper {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, rgba(0, 242, 255, 0.15), rgba(112, 0, 255, 0.15));
        border: 1px solid rgba(0, 242, 255, 0.3);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: var(--primary, #00f2ff);
        box-shadow: 0 0 20px rgba(0, 242, 255, 0.2);
    }

    .xep-atc-title {
        font-family: 'Outfit', sans-serif;
        font-size: 26px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 10px;
        letter-spacing: -0.5px;
    }

    .xep-atc-subtitle {
        font-family: 'Inter', sans-serif;
        font-size: 15px;
        color: rgba(255, 255, 255, 0.5);
        margin: 0 0 30px;
    }

    .xep-atc-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .xep-atc-btn-secondary,
    .xep-atc-btn-primary {
        padding: 14px 24px;
        border-radius: 12px;
        font-family: 'Inter', sans-serif;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 1;
        min-width: 140px;
    }

    .xep-atc-btn-secondary {
        background: rgba(255, 255, 255, 0.05);
        color: #fff !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .xep-atc-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
        color: #fff !important;
    }

    .xep-atc-btn-primary {
        background: var(--primary, #00f2ff);
        color: #000 !important;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 242, 255, 0.3);
    }

    .xep-atc-btn-primary:hover {
        background: #fff;
        box-shadow: 0 6px 20px rgba(255, 255, 255, 0.4);
        transform: translateY(-2px);
        color: #000 !important;
    }
</style>

<script>
    function xepOpenAtcModal() {
        var modal = document.getElementById('xep-add-to-cart-modal');
        if (!modal) return;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetWidth; // trigger reflow
        modal.style.opacity = '1';
        modal.classList.add('active');
        requestAnimationFrame(function () {
            modal.classList.add('visible');
        });
    }

    function xepCloseAtcModal() {
        var modal = document.getElementById('xep-add-to-cart-modal');
        if (!modal) return;
        modal.classList.remove('visible');
        modal.style.opacity = '0';
        document.body.style.overflow = '';
        setTimeout(function () {
            modal.classList.remove('active');
            modal.style.display = 'none';
        }, 350);
    }

    // Hook into WooCommerce AJAX add to cart
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('added_to_cart', function (event, fragments, cart_hash, $button) {
                xepOpenAtcModal();
            });
        }
    });
</script>

<?php wp_footer(); ?>
</body>

</html>