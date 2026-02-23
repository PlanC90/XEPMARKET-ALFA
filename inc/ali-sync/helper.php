<?php
/**
 * AliExpress Sync Helper
 * Author: XEPMARKET
 */

if (!defined('ABSPATH')) {
    exit;
}

class XEPMarket_Ali_Sync_Helper
{

    private static $instance = null;
    private $slug = 'ali-sync-helper';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_ali_sync_get_products', array($this, 'ajax_get_products'));
        add_action('wp_ajax_ali_sync_single_product', array($this, 'ajax_sync_single'));
        add_action('wp_ajax_ali_sync_debug', array($this, 'ajax_debug'));
        add_action('wp_ajax_ali_sync_fix_skus', array($this, 'ajax_fix_skus'));
        add_action('wp_ajax_ali_sync_delete_broken', array($this, 'ajax_delete_broken'));
        add_action('wp_ajax_ali_sync_list_broken', array($this, 'ajax_list_broken'));

        // Terminal & Fallback Trigger
        add_action('init', array($this, 'handle_manual_cleanup'));

        // Register WP-CLI Command
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('xep delete-broken', array($this, 'cli_delete_broken'));
        }
    }

    /**
     * Handle cleanup via URL parameter (cPanel fallback)
     * Usage: site.com/wp-admin/?xep_action=delete_broken
     */
    public function handle_manual_cleanup()
    {
        // URL-based cleanup: ?xep_action=delete_broken
        if (isset($_GET['xep_action']) && $_GET['xep_action'] === 'delete_broken') {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized.');
            }

            $count = $this->run_cleanup_all();
            wp_die("<h3>Cleanup Completed</h3><p>Successfully deleted <strong>$count</strong> broken products/variations.</p><a href='" . admin_url('admin.php?page=' . $this->slug) . "'>&larr; Back to Dashboard</a>");
        }

        // PHP Form-based delete (POST from admin page)
        if (isset($_POST['xep_broken_action']) && $_POST['xep_broken_action'] === 'delete_selected') {
            if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_xep_broken_nonce'], 'xep_broken_delete')) {
                return;
            }

            $ids = isset($_POST['broken_ids']) ? array_map('intval', (array) $_POST['broken_ids']) : array();
            $deleted = 0;

            foreach ($ids as $id) {
                if (wp_delete_post($id, true)) {
                    $deleted++;
                }
            }

            set_transient('xep_broken_notice', $deleted, 30);
            wp_safe_redirect(admin_url('admin.php?page=' . $this->slug . '&show_broken=1'));
            exit;
        }

        // PHP Form-based delete ALL
        if (isset($_POST['xep_broken_action']) && $_POST['xep_broken_action'] === 'delete_all') {
            if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_xep_broken_nonce'], 'xep_broken_delete')) {
                return;
            }

            $deleted = $this->run_cleanup_all();
            set_transient('xep_broken_notice', $deleted, 30);
            wp_safe_redirect(admin_url('admin.php?page=' . $this->slug . '&show_broken=1'));
            exit;
        }
    }

    /**
     * WP-CLI command handler
     */
    public function cli_delete_broken($args, $assoc_args)
    {
        WP_CLI::log('Searching for broken products...');
        $count = $this->run_cleanup_all();
        WP_CLI::success("Successfully deleted $count broken products/variations.");
    }

    /**
     * Core cleanup logic used by CLI and Manual Trigger
     */
    private function run_cleanup_all()
    {
        $items = $this->get_broken_products();
        $count = 0;

        if (!empty($items)) {
            foreach ($items as $item) {
                if (wp_delete_post($item['id'], true)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get broken products list (shared between AJAX and PHP render)
     * Detects: 1) Temp SKUs (_tmp_)  2) Duplicate SKUs  3) Empty SKU products
     */
    private function get_broken_products()
    {
        $items = array();
        $found_ids = array();

        // === 1. Find products with _tmp_ in SKU ===
        $tmp_args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_sku',
                    'value' => '_tmp_',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        );

        $tmp_ids = get_posts($tmp_args);
        foreach ($tmp_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $found_ids[$id] = true;
                $items[] = array(
                    'id' => $id,
                    'title' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'type' => $product->get_type(),
                    'issue' => '🔴 Temporary SKU (_tmp_)',
                );
            }
        }

        // === 2. Find products with duplicate SKUs ===
        global $wpdb;
        $duplicates = $wpdb->get_results("
            SELECT meta_value as sku, GROUP_CONCAT(post_id ORDER BY post_id ASC) as ids, COUNT(*) as cnt
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_sku'
            AND pm.meta_value != ''
            AND p.post_type IN ('product', 'product_variation')
            AND p.post_status IN ('publish', 'draft', 'pending', 'private')
            GROUP BY pm.meta_value
            HAVING cnt > 1
            ORDER BY cnt DESC
        ");

        if (!empty($duplicates)) {
            foreach ($duplicates as $dup) {
                $dup_ids = array_map('intval', explode(',', $dup->ids));
                // Keep the first one (original), flag the rest as duplicates
                $original_id = array_shift($dup_ids);
                foreach ($dup_ids as $dup_id) {
                    if (isset($found_ids[$dup_id]))
                        continue; // Already in list
                    $product = wc_get_product($dup_id);
                    if ($product) {
                        $found_ids[$dup_id] = true;
                        $items[] = array(
                            'id' => $dup_id,
                            'title' => $product->get_name(),
                            'sku' => $product->get_sku(),
                            'type' => $product->get_type(),
                            'issue' => '🟠 Duplicate SKU (original: ID ' . $original_id . ')',
                        );
                    }
                }
            }
        }

        // === 3. Find products with _ali_sync_failed meta ===
        $failed_args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_ali_sync_failed',
                    'compare' => 'EXISTS',
                ),
            ),
            'fields' => 'ids',
        );

        $failed_ids = get_posts($failed_args);
        foreach ($failed_ids as $id) {
            if (isset($found_ids[$id]))
                continue;
            $product = wc_get_product($id);
            if ($product) {
                $found_ids[$id] = true;
                $reason = get_post_meta($id, '_ali_sync_failed', true);
                if (is_array($reason))
                    $reason = implode(' ', $reason);
                $items[] = array(
                    'id' => $id,
                    'title' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'type' => $product->get_type(),
                    'issue' => '❌ Sync Failed: ' . ($reason ? wp_trim_words((string) $reason, 10, '...') : 'Unknown Error'),
                );
            }
        }

        return $items;
    }

    public function ajax_fix_skus()
    {
        check_ajax_referer('ali_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized.');
        }

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_sku',
                    'value' => '_tmp_',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        );

        $product_ids = get_posts($args);
        $count = 0;

        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $current_sku = $product->get_sku();
                $clean_sku = preg_replace('/(_sync_tmp_|_vtmp_).*$/', '', $current_sku);

                if ($clean_sku !== $current_sku) {
                    $product->set_sku($clean_sku);
                    $product->save();
                    $count++;
                }
            }
        }

        wp_send_json_success("Successfully cleaned up $count SKUs.");
    }

    public function ajax_list_broken()
    {
        check_ajax_referer('ali_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized.');
        }

        $items = $this->get_broken_products();
        wp_send_json_success(array('items' => $items, 'count' => count($items)));
    }

    public function ajax_delete_broken()
    {
        check_ajax_referer('ali_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized.');
        }

        $ids_to_delete = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : array();

        if (empty($ids_to_delete)) {
            wp_send_json_error('No products selected.');
        }

        $count = 0;
        foreach ($ids_to_delete as $id) {
            $product = wc_get_product($id);
            if ($product) {
                $res = wp_delete_post($id, true);
                if ($res) {
                    $count++;
                }
            }
        }

        wp_send_json_success("Successfully deleted $count broken products/variations.");
    }

    public function ajax_debug()
    {
        check_ajax_referer('ali_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized.');
        }

        $results = array();
        $wc_product_id = isset($_POST['test_product_id']) ? intval($_POST['test_product_id']) : 0;

        if (!$wc_product_id) {
            $sync_products = get_posts(array(
                'post_type' => 'product',
                'posts_per_page' => 1,
                'meta_query' => array(array('key' => '_vi_wad_aliexpress_product_id', 'compare' => 'EXISTS')),
                'fields' => 'ids'
            ));
            if (!empty($sync_products))
                $wc_product_id = $sync_products[0];
        }

        $ali_id = $wc_product_id ? get_post_meta($wc_product_id, '_vi_wad_aliexpress_product_id', true) : '';
        $results['testing_wc_id'] = $wc_product_id;
        $results['testing_ali_id'] = $ali_id ?: 'Not found';

        if ($ali_id && class_exists('VI_WOO_ALIDROPSHIP_DATA')) {
            $product_args = array(
                'product_id' => $ali_id,
                'target_currency' => 'USD',
                'ship_to_country' => 'TR',
                'target_language' => 'en',
                'locale' => 'en_US',
                'domain' => get_site_url(),
                'action' => 'import',
            );

            $response = VI_WOO_ALIDROPSHIP_DATA::ali_request(
                [],
                wp_json_encode($product_args),
                [],
                'https://aldapi.vinext.net/get_product'
            );

            $results['proxy_response'] = array(
                'status' => isset($response['status']) ? $response['status'] : 'none',
                'message' => isset($response['message']) ? $response['message'] : 'none',
                'has_data' => isset($response['data']) ? 'Yes' : 'No',
                'raw_full' => $response
            );
        }

        wp_send_json_success($results);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'AliSync Helper',
            'AliSync Helper',
            'manage_options',
            $this->slug,
            array($this, 'render_admin_page'),
            'dashicons-update-alt',
            58
        );
    }

    public function enqueue_assets($hook)
    {
        if ('toplevel_page_' . $this->slug !== $hook) {
            return;
        }

        wp_enqueue_style('ali-sync-admin', get_template_directory_uri() . '/inc/ali-sync/style.css', array(), '1.4.0');
        wp_enqueue_script('ali-sync-admin', get_template_directory_uri() . '/inc/ali-sync/script.js', array('jquery'), '1.4.0', true);

        wp_localize_script('ali-sync-admin', 'aliSyncData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ali_sync_nonce'),
        ));
    }

    public function render_admin_page()
    {
        // Check for deletion notice
        $notice = get_transient('xep_broken_notice');
        if ($notice !== false) {
            delete_transient('xep_broken_notice');
        }

        // Check if we should show broken products panel
        $show_broken = isset($_GET['show_broken']) && $_GET['show_broken'] === '1';

        // Get broken products for PHP rendering
        $broken_items = $show_broken ? $this->get_broken_products() : array();
        ?>
        <div class="wrap ali-sync-wrap">

            <?php if ($notice !== false): ?>
                <div class="notice notice-success is-dismissible"
                    style="margin-bottom:15px; padding:12px 15px; border-left-color:#00a32a; color:#1d2327; background:#fff;">
                    <p style="margin:0;"><strong>✅ Successfully deleted <?php echo intval($notice); ?> broken
                            products/variations.</strong></p>
                </div>
            <?php endif; ?>

            <div class="sync-header">
                <div class="header-content">
                    <h1>AliExpress Sync Helper</h1>
                    <p>Sync your products with AliExpress and keep stock/price information up to date.</p>
                </div>
                <div class="header-stats">
                    <div class="stat-card">
                        <span class="stat-label">Total ALD Products</span>
                        <span class="stat-value" id="total-ald-count">-</span>
                    </div>
                </div>
            </div>

            <div class="sync-container">
                <div
                    style="background-color: #fff3cd; border: 1px solid #ffe69c; color: #664d03; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px;">
                    <strong>⚠️ Note for Manual Products:</strong> If you add a product manually, please ensure its SKU code
                    starts with <code>M-</code> (e.g., M-101). This module only searches and updates products connected to
                    AliExpress. If a manually added product's SKU does not start with <code>M-</code>, the sync process will try
                    to fetch it from AliExpress and might result in an error.
                </div>
                <div class="sync-controls">
                    <button id="start-sync" class="button button-primary premium-btn">
                        <span class="dashicons dashicons-update"></span> Start Syncing
                    </button>
                    <button id="fix-skus" class="button button-secondary">
                        <span class="dashicons dashicons-admin-tools"></span> Fix Broken SKUs
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->slug . '&show_broken=1')); ?>"
                        class="button button-link-delete" target="_blank"
                        style="color: #d63638; text-decoration:none; border:1px solid #d63638;">
                        <span class="dashicons dashicons-trash" style="margin-top:4px;"></span> Delete Broken Products
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->slug . '&show_broken=1')); ?>"
                        class="button button-secondary" style="margin-left:5px; text-decoration:none;" target="_blank">
                        <span class="dashicons dashicons-visibility" style="margin-top:4px;"></span> Show Broken SKUs
                    </a>
                    <div class="sync-options">
                        <label><input type="checkbox" id="sync-price" checked> Price</label>
                        <label><input type="checkbox" id="sync-stock" checked> Stock</label>
                        <label><input type="checkbox" id="sync-title"> Title</label>
                        <label><input type="checkbox" id="sync-desc"> Description</label>
                    </div>
                </div>

                <div id="broken-skus-container"
                    style="display:none; margin-top:15px; padding:15px; border:1px solid #ccd0d4; border-radius:8px; background:#fff; max-height: 400px; overflow-y: auto;">
                    <h4 style="margin-top:0;">Broken SKUs List</h4>
                    <p style="font-size:13px; color:#666; margin-bottom:10px;">You can click the links below to manually check
                        and edit the broken products.</p>
                    <div id="broken-skus-list-content"></div>
                </div>
            </div>

            <!-- ========== PHP-RENDERED BROKEN PRODUCTS (NO JS REQUIRED) ========== -->
            <?php if ($show_broken): ?>
                <div
                    style="margin-top:15px; background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #e2e4e7; padding-bottom:12px;">
                        <h3 style="margin:0; color:#1d2327; font-size:16px;">🔍 Broken Products (Failed Syncs + Temp SKUs +
                            Duplicate SKUs) — PHP
                            Mode</h3>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->slug)); ?>" class="button"
                            style="min-height:30px;">&times; Close</a>
                    </div>

                    <?php if (!empty($broken_items)): ?>
                        <form method="post" action="" style="margin:0;">
                            <?php // We post to init hook via handle_manual_cleanup ?>
                            <input type="hidden" name="xep_broken_action" value="delete_selected">
                            <?php wp_nonce_field('xep_broken_delete', '_xep_broken_nonce'); ?>

                            <div
                                style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <p style="margin:0; font-weight:600;">Found <?php echo count($broken_items); ?> broken item(s)</p>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button type="submit" class="button button-primary"
                                        style="background:#d63638; border-color:#d63638;"
                                        onclick="return confirm('⚠️ Delete SELECTED broken products? This cannot be undone.');">
                                        <span class="dashicons dashicons-trash" style="margin-top:3px;"></span> Delete Selected
                                    </button>
                                </div>
                            </div>

                            <div style="max-height:500px; overflow-y:auto; border:1px solid #e2e4e7; border-radius:6px;">
                                <table class="widefat striped" style="margin:0;">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;"><input type="checkbox" id="php-select-all"
                                                    onclick="var c=this.checked;document.querySelectorAll('.php-broken-check').forEach(function(e){e.checked=c;});">
                                            </th>
                                            <th>Type</th>
                                            <th>Name</th>
                                            <th>SKU</th>
                                            <th>ID</th>
                                            <th>Issue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($broken_items as $item): ?>
                                            <tr>
                                                <td><input type="checkbox" name="broken_ids[]" value="<?php echo esc_attr($item['id']); ?>"
                                                        class="php-broken-check"></td>
                                                <td><?php echo $item['type'] === 'variation' ? '🔹 Variation' : '📦 Product'; ?></td>
                                                <td><strong><?php echo esc_html($item['title']); ?></strong></td>
                                                <td><code
                                                        style="background:#f0f0f1; padding:2px 6px; border-radius:3px; font-size:11px; color:#d63638;"><?php echo esc_html($item['sku']); ?></code>
                                                </td>
                                                <td><?php echo esc_html($item['id']); ?></td>
                                                <td><?php echo isset($item['issue']) ? esc_html($item['issue']) : '—'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>

                        <hr style="margin:15px 0;">

                        <!-- Delete ALL button (separate form) -->
                        <form method="post" action="" style="margin:0; text-align:center;">
                            <input type="hidden" name="xep_broken_action" value="delete_all">
                            <?php wp_nonce_field('xep_broken_delete', '_xep_broken_nonce'); ?>
                            <button type="submit" class="button"
                                style="background:#d63638; border-color:#d63638; color:#fff; font-size:14px; padding:6px 20px;"
                                onclick="return confirm('⚠️ This will PERMANENTLY DELETE ALL <?php echo count($broken_items); ?> broken items. Are you sure?');">
                                🗑️ Delete ALL <?php echo count($broken_items); ?> Broken Items
                            </button>
                        </form>

                    <?php else: ?>
                        <div style="text-align:center; padding:30px; color:#50575e;">
                            <span class="dashicons dashicons-yes-alt" style="font-size:40px; color:#00a32a;"></span>
                            <p style="font-size:14px; margin-top:10px;">No broken products found. Everything looks clean! ✨</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


            <div id="sync-progress-box" class="sync-progress-box" style="display:none;">
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: 0%;"></div>
                </div>
                <div class="progress-info">
                    <span id="progress-status">Preparing...</span>
                    <span id="progress-percentage">0%</span>
                </div>
                <div id="sync-log" class="sync-log"></div>
            </div>

            <hr style="margin:20px 0;">
            <h3>🔧 Connection Diagnostics</h3>
            <p>Run the test below to detect AliExpress access issues.</p>
            <div style="margin-bottom:10px; background:#f0f0f0; padding:10px; border-radius:5px;">
                <label for="debug-product-id">Test specific WooCommerce Product ID (Optional):</label>
                <input type="number" id="debug-product-id" placeholder="e.g. 1497" style="width:100px;">
                <button id="run-debug" class="button button-secondary">
                    <span class="dashicons dashicons-sos"></span> Run Connection Test
                </button>
            </div>
            <pre id="debug-output"
                style="background:#1a1a2e;color:#0f0;padding:15px;border-radius:8px;max-height:400px;overflow:auto;display:none;font-size:13px;"></pre>
        </div>
        <?php
    }

    public function ajax_get_products()
    {
        check_ajax_referer('ali_sync_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access.');
        }

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_vi_wad_aliexpress_product_id',
                    'compare' => 'EXISTS',
                ),
            ),
            'fields' => 'ids',
        );

        $products = get_posts($args);

        wp_send_json_success(array(
            'ids' => $products,
            'count' => count($products),
        ));
    }

    public function ajax_sync_single()
    {
        try {
            check_ajax_referer('ali_sync_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized access.');
            }

            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            if (!$product_id) {
                wp_send_json_error('Product ID not found.');
            }

            $ali_id = get_post_meta($product_id, '_vi_wad_aliexpress_product_id', true);
            if (!$ali_id) {
                wp_send_json_error('AliExpress ID not found.');
            }

            $result = $this->sync_product($product_id, $ali_id);

            if (is_wp_error($result)) {
                update_post_meta($product_id, '_ali_sync_failed', $result->get_error_message());
                wp_send_json_error($result->get_error_message());
            }

            delete_post_meta($product_id, '_ali_sync_failed');
            wp_send_json_success($result);
        } catch (Exception $e) {
            if (isset($product_id) && $product_id) {
                update_post_meta($product_id, '_ali_sync_failed', 'System Error: ' . $e->getMessage());
            }
            wp_send_json_error('System Error: ' . $e->getMessage());
        } catch (Error $e) {
            if (isset($product_id) && $product_id) {
                update_post_meta($product_id, '_ali_sync_failed', 'Fatal Error: ' . $e->getMessage());
            }
            wp_send_json_error('Fatal Error: ' . $e->getMessage());
        }
    }

    private function sync_product($product_id, $ali_id)
    {
        if (!class_exists('VI_WOO_ALIDROPSHIP_DATA')) {
            return new WP_Error('no_ald', 'ALD plugin is not installed or active.');
        }

        $ald_data = VI_WOO_ALIDROPSHIP_DATA::get_instance();
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('not_found', 'Product not found in database.');
        }

        $original_sku = $product->get_sku();

        // Skip manually managed products starting with M-
        if ($original_sku && strpos(strtoupper($original_sku), 'M-') === 0) {
            return array('id' => $product_id, 'title' => $product->get_name() . ' (Manual Product)', 'changed' => true);
        }

        $variation_backups = array();

        try {
            if ($original_sku) {
                $base_sku = preg_replace('/(_sync_tmp_|_vtmp_).*$/', '', $original_sku);
                $temp_sku = $base_sku . '_sync_tmp_' . time() . '_' . wp_generate_password(4, false);

                while (wc_get_product_id_by_sku($temp_sku)) {
                    $temp_sku = $base_sku . '_sync_tmp_' . time() . '_' . wp_generate_password(4, false);
                }

                $product->set_sku($temp_sku);
                $product->save();
            }

            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $var_id) {
                    $v_p = wc_get_product($var_id);
                    if (!$v_p)
                        continue;
                    $v_sku = $v_p->get_sku();
                    if ($v_sku) {
                        $v_base = preg_replace('/(_sync_tmp_|_vtmp_).*$/', '', $v_sku);
                        $variation_backups[$var_id] = $v_base;
                        $v_temp = $v_base . '_vtmp_' . time() . '_' . wp_generate_password(4, false);

                        while (wc_get_product_id_by_sku($v_temp)) {
                            $v_temp = $v_base . '_vtmp_' . time() . '_' . wp_generate_password(4, false);
                        }

                        $v_p->set_sku($v_temp);
                        $v_p->save();
                    }
                }
            }

            $product_args = array(
                'product_id' => $ali_id,
                'target_currency' => 'USD',
                'ship_to_country' => 'TR',
                'target_language' => 'en',
                'locale' => 'en_US',
                'domain' => get_site_url(),
                'action' => 'import',
            );

            // Attempt 1: Default Proxy (TR)
            $response = VI_WOO_ALIDROPSHIP_DATA::get_data('', [], 'viwad_init_data_before', true, $product_args);

            // Attempt 2: Fallback Proxy (US) if TR fails
            if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
                sleep(2); // Small delay to avoid burst detection
                $product_args['ship_to_country'] = 'US';
                $response = VI_WOO_ALIDROPSHIP_DATA::get_data('', [], 'viwad_init_data_before', true, $product_args);
            }

            // Attempt 3: Direct Scrape with improved headers
            if (!$response || !isset($response['status']) || $response['status'] !== 'success' || (isset($response['message']) && strpos($response['message'], 'forEach') !== false)) {
                $ali_url = 'https://www.aliexpress.com/item/' . $ali_id . '.html';
                $uas = array(
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
                );
                $ua = $uas[array_rand($uas)];
                $direct_response = VI_WOO_ALIDROPSHIP_DATA::get_data($ali_url, array(
                    'user-agent' => $ua,
                    'referer' => 'https://www.aliexpress.com/',
                    'timeout' => 45,
                ));
                if ($direct_response && isset($direct_response['status']) && $direct_response['status'] === 'success') {
                    $response = $direct_response;
                }
            }

            // Attempt 4: Mobile URL Fallback (Stubborn Choice products)
            if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
                $mobile_url = 'https://m.aliexpress.com/item/' . $ali_id . '.html';
                $direct_mobile = VI_WOO_ALIDROPSHIP_DATA::get_data($mobile_url, array(
                    'user-agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Mobile/15E148 Safari/604.1',
                    'timeout' => 45,
                ));
                if ($direct_mobile && isset($direct_mobile['status']) && $direct_mobile['status'] === 'success') {
                    $response = $direct_mobile;
                }
            }

            if (!$response || !isset($response['status']) || $response['status'] !== 'success' || empty($response['data'])) {
                $msg = !empty($response['message']) ? $response['message'] : '';
                if (is_array($msg))
                    $msg = implode(' ', $msg);
                $detail = $msg ? $msg : 'Product data could not be fetched (Proxy/Direct failed).';
                return new WP_Error('api_error', '[AliExpress ID: ' . $ali_id . '] ' . $detail);
            }

            $ali_data = $response['data'];

            if (isset($_POST['do_title']) && $_POST['do_title'] === 'true' && !empty($ali_data['name'])) {
                $product->set_name($ali_data['name']);
            }

            if (isset($_POST['do_desc']) && $_POST['do_desc'] === 'true' && !empty($ali_data['description'])) {
                $product->set_description($ali_data['description']);
            }

            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $var_id) {
                    $v_product = wc_get_product($var_id);
                    $v_ali_attr = get_post_meta($var_id, '_vi_wad_aliexpress_variation_attr', true);

                    if ($v_ali_attr && isset($ali_data['variations'])) {
                        foreach ($ali_data['variations'] as $ali_v) {
                            if (isset($ali_v['skuAttr']) && $ali_v['skuAttr'] === $v_ali_attr) {
                                if (isset($_POST['do_price']) && $_POST['do_price'] === 'true' && isset($ali_v['skuVal'])) {
                                    $sale = isset($ali_v['skuVal']['actSkuCalPrice']) ? floatval($ali_v['skuVal']['actSkuCalPrice']) : 0;
                                    $reg = isset($ali_v['skuVal']['skuCalPrice']) ? floatval($ali_v['skuVal']['skuCalPrice']) : 0;
                                    $raw = $sale > 0 ? $sale : $reg;
                                    if ($raw > 0) {
                                        $new_p = $ald_data->process_exchange_price($ald_data->process_price($raw));
                                        $v_product->set_regular_price($new_p);
                                        $v_product->set_price($new_p);
                                    }
                                }
                                if (isset($_POST['do_stock']) && $_POST['do_stock'] === 'true' && isset($ali_v['skuVal'])) {
                                    $stk = isset($ali_v['skuVal']['availQuantity']) ? intval($ali_v['skuVal']['availQuantity']) : 0;
                                    $v_product->set_manage_stock(true);
                                    $v_product->set_stock_quantity($stk);
                                    $v_product->set_stock_status($stk > 0 ? 'instock' : 'outofstock');
                                }
                                $v_product->save();
                                break;
                            }
                        }
                    }
                }
            } else {
                if (isset($_POST['do_price']) && $_POST['do_price'] === 'true' && isset($ali_data['variations'][0]['skuVal'])) {
                    $sv = $ali_data['variations'][0]['skuVal'];
                    $raw = (isset($sv['actSkuCalPrice']) && $sv['actSkuCalPrice'] > 0) ? $sv['actSkuCalPrice'] : ($sv['skuCalPrice'] ?? 0);
                    if ($raw > 0) {
                        $new_p = $ald_data->process_exchange_price($ald_data->process_price($raw));
                        $product->set_regular_price($new_p);
                        $product->set_price($new_p);
                    }
                }
                if (isset($_POST['do_stock']) && $_POST['do_stock'] === 'true' && isset($ali_data['variations'][0]['skuVal']['availQuantity'])) {
                    $stk = intval($ali_data['variations'][0]['skuVal']['availQuantity']);
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($stk);
                    $product->set_stock_status($stk > 0 ? 'instock' : 'outofstock');
                }
            }

            $product->save();
            return array('id' => $product_id, 'title' => $product->get_name(), 'changed' => true);

        } finally {
            try {
                $clean_main = preg_replace('/(_sync_tmp_|_vtmp_).*$/', '', $original_sku);

                // Check if base SKU is taken by another product to avoid fatal error on save
                $existing_id = wc_get_product_id_by_sku($clean_main);
                if ($existing_id && (int) $existing_id !== (int) $product_id) {
                    $other_p = wc_get_product($existing_id);
                    if ($other_p && (strpos($other_p->get_sku(), '_sync_tmp_') !== false || strpos($other_p->get_sku(), '_vtmp_') !== false)) {
                        $other_p->set_sku($clean_main . '_conflict_' . time());
                        $other_p->save();
                    } else if ($clean_main !== '') {
                        $clean_main = $clean_main . '_dup_' . time();
                    }
                }

                $product->set_sku($clean_main);
                $product->save();

                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $var_id) {
                        $v_p = wc_get_product($var_id);
                        if ($v_p) {
                            $v_sku = $v_p->get_sku();
                            $v_clean = preg_replace('/(_sync_tmp_|_vtmp_).*$/', '', $v_sku);

                            $v_existing_id = wc_get_product_id_by_sku($v_clean);
                            if ($v_existing_id && (int) $v_existing_id !== (int) $var_id) {
                                $other_v = wc_get_product($v_existing_id);
                                if ($other_v && (strpos($other_v->get_sku(), '_sync_tmp_') !== false || strpos($other_v->get_sku(), '_vtmp_') !== false)) {
                                    $other_v->set_sku($v_clean . '_vconflict_' . time());
                                    $other_v->save();
                                } else if ($v_clean !== '') {
                                    $v_clean = $v_clean . '_vdup_' . time();
                                }
                            }

                            $v_p->set_sku($v_clean);
                            $v_p->save();
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore SKU restoration errors to prevent them from showing as sync failure
            } catch (Error $e) {
                // Ignore fatal errors in SKU restoration
            }
        }
    }
}

XEPMarket_Ali_Sync_Helper::get_instance();
