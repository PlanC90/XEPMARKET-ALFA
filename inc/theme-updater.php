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
        // You can change the GitHub token or repo user/name from options if you want
        $this->github_url = "https://api.github.com/repos/{$this->repo_user}/{$this->repo_name}/releases/latest";

        add_filter('pre_set_site_transient_update_themes', array($this, 'check_update'));
        add_action('admin_post_xepmarket_force_update_check', array($this, 'manual_check'));
        add_filter('upgrader_source_selection', array($this, 'rename_github_folder'), 10, 4);
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
                        style="margin-top: 30px; padding: 20px; background: rgba(0,242,255,0.05); border: 1px solid rgba(0,242,255,0.2); border-radius: 12px;">
                        <h4 style="margin-top:0; color:var(--admin-primary); margin-bottom: 15px;">How to update?</h4>
                        <p style="font-size: 14px; opacity: 0.8; margin-bottom: 20px;">Go to <strong>Dashboard &gt; Updates</strong>
                            or <strong>Appearance &gt; Themes</strong> to install the latest version.</p>
                        <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="xep-save-btn"
                            style="width: auto !important; display: inline-block; background: transparent !important; color: var(--text) !important; border: 1px solid var(--admin-border) !important;">Go
                            to Updates Page</a>
                    </div>
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

            // Fetch from GitHub
            $response = wp_remote_get($this->github_url, $args);

            if (is_wp_error($response)) {
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $release = json_decode($body);

            if (!empty($release) && isset($release->tag_name)) {
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
