<?php
if (!defined('ABSPATH')) {
    exit;
}

class XepMarket_Theme_Updater
{
    public $repo_user;
    public $repo_name;
    public $theme_slug;
    public $github_url;

    public function __construct()
    {
        $this->theme_slug = 'XEPMARKET-ALFA';
        $this->repo_user = 'PlanC90';
        $this->repo_name = 'XEPMARKET-ALFA';

        // We use tags endpoint as it's more reliable when formal releases aren't created
        $this->github_url = "https://api.github.com/repos/{$this->repo_user}/{$this->repo_name}/tags";

        add_filter('pre_set_site_transient_update_themes', array($this, 'check_update'));
        add_action('admin_post_xepmarket_force_update_check', array($this, 'manual_check'));
        add_filter('upgrader_source_selection', array($this, 'rename_github_folder'), 10, 4);
        add_action('wp_ajax_xep_update_theme', array($this, 'ajax_update_theme'));
    }

    public function updater_page_html()
    {
        $theme = wp_get_theme($this->theme_slug);
        $current_version = $theme->get('Version');

        $release = $this->get_latest_release(true); // force check for the page display

        $new_version_available = false;
        $latest_version_str = 'Unknown';

        if ($release && isset($release->tag_name)) {
            $latest_version_str = ltrim($release->tag_name, 'v');
            if (version_compare($current_version, $latest_version_str, '<')) {
                $new_version_available = true;
            }
        }
        ?>
        <div class="xep-section-card">
            <h3 style="color: var(--admin-primary);"><i class="fas fa-sync-alt"></i> GitHub Auto-Updater</h3>
            <p class="description" style="margin-bottom: 25px;">Automatically check for theme updates from GitHub.</p>

            <div
                style="background: rgba(255,255,255,0.02); padding: 30px; border-radius: 12px; border: 1px solid var(--admin-border); max-width: 600px;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label>Current Version:</label></th>
                        <td><strong style="font-size: 16px;">
                                <?php echo esc_html($current_version); ?>
                            </strong></td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Latest GitHub Version:</label></th>
                        <td>
                            <strong style="font-size: 16px;">
                                <?php echo esc_html($latest_version_str); ?>
                            </strong>
                            <?php if ($new_version_available): ?>
                                <span
                                    style="background: rgba(255, 69, 58, 0.1); color: #ff453a; padding: 4px 8px; border-radius: 6px; font-weight: bold; margin-left: 10px; font-size: 11px; border: 1px solid rgba(255, 69, 58, 0.2);">UPDATE
                                    AVAILABLE!</span>
                            <?php else: ?>
                                <span
                                    style="background: rgba(50, 215, 75, 0.1); color: #32d74b; padding: 4px 8px; border-radius: 6px; font-weight: bold; margin-left: 10px; font-size: 11px; border: 1px solid rgba(50, 215, 75, 0.2);">UP
                                    TO DATE</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 30px;">
                    <?php
                    $update_url = add_query_arg(array(
                        'action' => 'xepmarket_force_update_check',
                        '_wpnonce' => wp_create_nonce('xepmarket_force_update_check'),
                        'redirect_to' => 'xepmarket2-settings'
                    ), admin_url('admin-post.php'));
                    ?>
                    <a href="<?php echo esc_url($update_url); ?>" class="xep-save-btn"
                        style="width: auto !important; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3); display: inline-block; text-align: center; text-decoration: none;">
                        <i class="fas fa-search"></i> Check for Updates Now
                    </a>
                    <p class="description" style="margin-top: 15px;">The system automatically checks for updates once a day. You
                        can click this button to force a check right now.</p>
                </div>

                <?php if ($new_version_available): ?>
                    <div
                        style="margin-top: 30px; padding: 30px; background: rgba(0,242,255,0.03); border: 1px dashed rgba(0,242,255,0.2); border-radius: 12px; text-align: center;">
                        <h4 style="margin-top:0; color:var(--admin-primary); margin-bottom: 5px; font-size: 18px;">New Version
                            Available!</h4>
                        <p style="font-size: 14px; opacity: 0.8; margin-bottom: 25px;">Version
                            <strong><?php echo esc_html($latest_version_str); ?></strong> is ready to be installed on your store.
                        </p>

                        <button type="button" id="xep-run-theme-update" class="xep-save-btn"
                            data-nonce="<?php echo wp_create_nonce('xepmarket_force_update_check'); ?>"
                            style="padding: 12px 30px; font-size: 14px; width: auto !important; background: linear-gradient(135deg, var(--admin-primary), #00d2ff) !important; box-shadow: 0 10px 25px rgba(0, 242, 255, 0.2) !important;">
                            <i class="fas fa-cloud-download-alt"></i> INSTALL UPDATE NOW
                        </button>

                        <div id="xep-update-status"
                            style="margin-top: 25px; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;">
                        </div>
                        <div id="xep-update-progress"
                            style="display: none; width: 100%; height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; margin: 15px auto 0; overflow: hidden;">
                            <div class="xep-update-progress-bar"
                                style="width: 0%; height: 100%; background: var(--admin-primary); transition: width 0.3s ease;">
                            </div>
                        </div>
                    </div>

                    <script>
                        jQuery(document).ready(function ($) {
                            $('#xep-run-theme-update').on('click', function () {
                                if (!confirm('Are you sure you want to update the theme? Downloading and replacing files will take some time.')) return;

                                const $btn = $(this);
                                const $status = $('#xep-update-status');
                                const $progressWrap = $('#xep-update-progress');
                                const $progressBar = $('.xep-update-progress-bar');

                                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> DOWNLOADING UPDATE...');
                                $status.text('Connecting to repository...').css('color', 'var(--admin-primary)');
                                $progressWrap.fadeIn();

                                let progress = 0;
                                const progressInt = setInterval(() => {
                                    if (progress < 90) {
                                        progress += Math.random() * 8;
                                        $progressBar.css('width', progress + '%');

                                        if (progress > 30 && progress < 60) {
                                            $status.text('Downloading package...');
                                        } else if (progress >= 60) {
                                            $status.text('Unpacking and replacing files...');
                                        }
                                    }
                                }, 800);

                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'xep_update_theme',
                                        nonce: $btn.data('nonce')
                                    },
                                    success: function (response) {
                                        clearInterval(progressInt);
                                        $progressBar.css('width', '100%');

                                        if (response.success) {
                                            $status.text('UPDATE SUCCESSFUL! REFRESHING...').css('color', '#32d74b');
                                            $btn.html('<i class="fas fa-check"></i> UPDATED').css('background', '#2ecc71');
                                            setTimeout(() => window.location.reload(), 2000);
                                        } else {
                                            $status.text('ERROR: ' + (response.data || 'Failed')).css('color', '#ff453a');
                                            $btn.prop('disabled', false).html('<i class="fas fa-redo"></i> RETRY UPDATE');
                                        }
                                    },
                                    error: function () {
                                        clearInterval(progressInt);
                                        $status.text('System error during update. Check connection.').css('color', '#ff453a');
                                        $btn.prop('disabled', false).html('<i class="fas fa-redo"></i> RETRY UPDATE');
                                    }
                                });
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function manual_check()
    {
        if (!current_user_can('manage_options'))
            return;

        check_admin_referer('xepmarket_force_update_check');

        delete_site_transient('update_themes');
        delete_transient('xepmarket2_github_release');

        // Fetch to reset transient immediately
        $this->get_latest_release(true);

        $redirect_to = isset($_REQUEST['redirect_to']) ? sanitize_text_field($_REQUEST['redirect_to']) : 'xepmarket2-settings';

        wp_safe_redirect(admin_url('admin.php?page=' . $redirect_to . '&updater_check_done=1#tab-updater'));
        exit;
    }

    public function ajax_update_theme()
    {
        check_ajax_referer('xepmarket_force_update_check', 'nonce');

        if (!current_user_can('update_themes')) {
            wp_send_json_error('Permission denied. You do not have the right to update themes.');
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        // Force check updates so package info is refreshed in transient
        delete_site_transient('update_themes');
        $this->get_latest_release(true);
        wp_update_themes();

        try {
            // Using Automatic_Upgrader_Skin to keep it quiet
            $skin = new Automatic_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);

            // Log start of update
            error_log("XEP Update: Starting update for " . $this->theme_slug);

            $result = $upgrader->upgrade($this->theme_slug);

            if (is_wp_error($result)) {
                error_log("XEP Update Error (WP_Error): " . $result->get_error_message());
                wp_send_json_error($result->get_error_message());
            } elseif ($result === false) {
                error_log("XEP Update Failed: Upgrader returned false. Possible missing package or version mismatch.");
                wp_send_json_error('System rejected the update. This usually happens if the update package is missing or folder permissions (FS_METHOD) are restricted. Try updating via Appearance > Themes.');
            }

            error_log("XEP Update Success!");
            wp_send_json_success('Theme updated successfully to latest version.');
        } catch (Exception $e) {
            error_log("XEP Update Exception: " . $e->getMessage());
            wp_send_json_error('Update failed: ' . $e->getMessage());
        }
    }

    public function get_latest_release($force = false)
    {
        $transient_key = 'xepmarket2_github_release';
        $release = get_transient($transient_key);

        if (false === $release || $force) {
            $args = array(
                'headers' => array(
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                    'Accept' => 'application/vnd.github.v3+json'
                ),
                'timeout' => 15,
            );

            // Fetch tags from GitHub
            $response = wp_remote_get($this->github_url, $args);

            if (is_wp_error($response)) {
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $tags = json_decode($body);

            if (!empty($tags) && is_array($tags) && isset($tags[0]->name)) {
                $latest_tag = $tags[0];
                $release = (object) array(
                    'tag_name' => $latest_tag->name,
                    'zipball_url' => $latest_tag->zipball_url,
                    'html_url' => "https://github.com/{$this->repo_user}/{$this->repo_name}/tree/{$latest_tag->name}"
                );
                set_transient($transient_key, $release, DAY_IN_SECONDS);
            } else {
                return false;
            }
        }
        return $release;
    }

    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $theme = wp_get_theme($this->theme_slug);
        $current_version = $theme->get('Version');

        $release = $this->get_latest_release();

        if ($release && isset($release->tag_name) && version_compare($current_version, ltrim($release->tag_name, 'v'), '<')) {
            $update = array(
                'theme' => $this->theme_slug,
                'new_version' => ltrim($release->tag_name, 'v'),
                'url' => $release->html_url,
                'package' => $release->zipball_url,
            );
            $transient->response[$this->theme_slug] = $update;
        }

        return $transient;
    }

    public function rename_github_folder($source, $remote_source, $upgrader, $hook_extra)
    {
        global $wp_filesystem;

        // Check if the update is for our theme
        if (isset($hook_extra['action']) && $hook_extra['action'] === 'update' && isset($hook_extra['theme']) && $hook_extra['theme'] === $this->theme_slug) {
            $corrected_source = trailingslashit($remote_source) . $this->theme_slug . '/';

            if ($wp_filesystem->move($source, $corrected_source, true)) {
                return $corrected_source;
            }
        }
        return $source;
    }
}
global $xepmarket_updater;
$xepmarket_updater = new XepMarket_Theme_Updater();
