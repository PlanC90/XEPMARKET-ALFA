<?php
/**
 * Sync plugins from GitHub (PlanC90/plugins) into wp-content/plugins
 * Used by theme Modules tab — "Install from GitHub" action.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XEPMARKET2_GITHUB_PLUGINS_REPO', 'https://github.com/PlanC90/plugins/archive/refs/heads/main.zip');
define('XEPMARKET2_GITHUB_SYNC_NONCE_ACTION', 'xepmarket2_github_plugins_sync');
// Allowlist of plugin folders that can be packaged into a single-plugin zip for TGMPA.
// Keep this limited to known slugs for security; token is required too.
define('XEPMARKET2_PLUGIN_ZIP_SLUGS', [
    'woocommerce',
    'omnixep-woocommerce',
    'classic-editor',
    'bopo-woo-product-bundle-builder',
    'woo-alidropship',
    'product-variations-swatches-for-woocommerce',
    'vargal-additional-variation-gallery-for-woo',
    'woo-orders-tracking',
]);

// Official download URLs for public plugins (bypass GitHub for these)
define('XEPMARKET2_OFFICIAL_PLUGIN_URLS', [
    'woocommerce' => 'https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip',
    'woo-alidropship' => 'https://downloads.wordpress.org/plugin/woo-alidropship.latest-stable.zip',
    'product-variations-swatches-for-woocommerce' => 'https://downloads.wordpress.org/plugin/product-variations-swatches-for-woocommerce.latest-stable.zip',
    'vargal-additional-variation-gallery-for-woo' => 'https://downloads.wordpress.org/plugin/vargal-additional-variation-gallery-for-woo.latest-stable.zip',
    'woo-orders-tracking' => 'https://downloads.wordpress.org/plugin/woo-orders-tracking.latest-stable.zip',
]);

/**
 * Initialize WordPress Filesystem
 */
function xepmarket2_init_filesystem() {
    global $wp_filesystem;
    
    // Ensure all necessary WP includes for filesystem operations are present
    if ( ! function_exists('WP_Filesystem') ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists('download_url') ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists('unzip_file') ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/screen.php';

    if (empty($wp_filesystem)) {
        // Try to initialize. On some servers, this might fail or ask for credentials.
        if ( ! WP_Filesystem() ) {
            error_log("[OmniXEP Sync] WP_Filesystem initialization failed.");
            return false;
        }
    }
    return $wp_filesystem;
}

/**
 * TGMPA / Upgrader: single-plugin zip from GitHub (WordPress expects one root folder).
 * GET admin-ajax.php?action=xepmarket2_plugin_zip&slug=omnixep-woocommerce&token=...
 */
function xepmarket2_plugin_zip_endpoint()
{
    if (!isset($_GET['action']) || $_GET['action'] !== 'xepmarket2_plugin_zip') {
        return;
    }
    $slug = isset($_GET['slug']) ? sanitize_text_field($_GET['slug']) : '';
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    if (!in_array($slug, XEPMARKET2_PLUGIN_ZIP_SLUGS, true) || $token === '') {
        status_header(403);
        exit;
    }
    $stored = get_transient('xepmarket2_plugin_zip_token');
    if ($stored === false || !hash_equals((string) $stored, $token)) {
        status_header(403);
        exit;
    }
    // If user is logged in, require install_plugins so only admins can use the URL in browser
    if (is_user_logged_in() && !current_user_can('install_plugins')) {
        status_header(403);
        exit;
    }

    $temp_dir = get_temp_dir() . 'xepmarket2_zip_' . wp_generate_password(8, false) . '/';
    if (!wp_mkdir_p($temp_dir)) {
        status_header(500);
        echo 'Could not create temporary directory.';
        exit;
    }

    $response = wp_remote_get(XEPMARKET2_GITHUB_PLUGINS_REPO, ['timeout' => 60]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(502);
        echo 'Could not download plugins repository zip.';
        exit;
    }
    $zip_path = $temp_dir . 'repo.zip';
    if (file_put_contents($zip_path, wp_remote_retrieve_body($response)) === false) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        echo 'Could not save downloaded zip.';
        exit;
    }

    if (!class_exists('ZipArchive')) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        echo 'ZipArchive PHP extension is required.';
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        echo 'Could not open downloaded zip.';
        exit;
    }
    $extract_to = $temp_dir . 'e/';
    wp_mkdir_p($extract_to);
    $zip->extractTo($extract_to);
    $zip->close();

    $root_folders = array_values(array_filter(glob($extract_to . '*'), 'is_dir'));
    $repo_root = $root_folders[0] ?? null;
    if (!$repo_root || !is_dir($repo_root)) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        echo 'Invalid zip structure (no root folder).';
        exit;
    }

    // Set source and normalize path
    $source = rtrim(str_replace('\\', '/', $repo_root), '/') . '/' . $slug;
    
    // 🔍 AUTOMATIC ROOT DETECTION:
    // If the slug-based subfolder doesn't exist, check if the plugin file is actually in the repo ROOT.
    // This happens when using 'git subtree push' which flattens the structure.
    if (!is_dir($source)) {
        if (file_exists($repo_root . '/' . $slug . '.php')) {
            $source = rtrim(str_replace('\\', '/', $repo_root), '/');
            error_log("[OmniXEP Sync] Using ROOT-ONLY structure for $slug");
        }
    } else {
        // Handle nested structure inside the subfolder if necessary
        $inner = $source . '/' . $slug;
        if (is_dir($inner) && file_exists($inner . '/' . $slug . '.php')) {
            $source = $inner;
        }
    }
    
    if (!is_dir($source)) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(404);
        echo "Plugin folder '$slug' not found in repo root OR subfolders.";
        error_log("[OmniXEP Sync Error] Folder not found. Tried: $source and repo_root");
        exit;
    }

    $out_zip = $temp_dir . $slug . '.zip';

    // ZIP Creation - ULTIMATE FIX
    $z = new ZipArchive();
    if ($z->open($out_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        exit('Could not create output zip.');
    }

    $source_path = rtrim(realpath($source), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $source_len = strlen($source_path);
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    // Explicitly add the slug folder
    $z->addEmptyDir($slug);

    foreach ($files as $name => $file) {
        if ($file->isDir()) continue;

        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, $source_len); // Path relative to the slug folder
        
        // Skip system/hidden files
        if (strpos($relativePath, '.') === 0 || strpos($relativePath, '.git') !== false || strpos($relativePath, '__MACOSX') !== false) {
            continue;
        }

        // Add file: slugname/rest-of-path
        // Normalize backslashes (Windows) to forward slashes for ZIP standards
        $zipPath = $slug . '/' . str_replace('\\', '/', $relativePath);
        $z->addFile($filePath, $zipPath);
    }
    $z->close();

    if (!file_exists($out_zip) || filesize($out_zip) === 0) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        echo 'Generated zip is empty or missing.';
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $slug . '.zip"');
    header('Content-Length: ' . filesize($out_zip));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($out_zip);
    xepmarket2_github_sync_cleanup($temp_dir);
    exit;
}

add_action('wp_ajax_xepmarket2_plugin_zip', 'xepmarket2_plugin_zip_endpoint');
add_action('wp_ajax_nopriv_xepmarket2_plugin_zip', 'xepmarket2_plugin_zip_endpoint');

/**
 * URL for TGMPA to download a single plugin from GitHub (zip with one root folder).
 */
function xepmarket2_get_github_plugin_zip_url($slug)
{
    // If it's an official plugin, return the direct download URL
    if (isset(XEPMARKET2_OFFICIAL_PLUGIN_URLS[$slug])) {
        return XEPMARKET2_OFFICIAL_PLUGIN_URLS[$slug];
    }
    
    if (!in_array($slug, XEPMARKET2_PLUGIN_ZIP_SLUGS, true)) {
        return null;
    }
    $token = get_transient('xepmarket2_plugin_zip_token');
    if ($token === false) {
        $token = wp_generate_password(32, false);
        set_transient('xepmarket2_plugin_zip_token', $token, 600);
    }
    return add_query_arg([
        'action' => 'xepmarket2_plugin_zip',
        'slug'   => $slug,
        'token'  => $token,
    ], admin_url('admin-ajax.php'));
}

/**
 * AJAX: Sync plugins from GitHub
 */
function xepmarket2_ajax_github_plugins_sync()
{
    check_ajax_referer(XEPMARKET2_GITHUB_SYNC_NONCE_ACTION, 'nonce');
    if (!current_user_can('install_plugins')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $result = xepmarket2_sync_plugins_from_github();
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message(), 'data' => $result->get_error_data()]);
    }
    wp_send_json_success($result);
}

add_action('wp_ajax_xepmarket2_github_plugins_sync', 'xepmarket2_ajax_github_plugins_sync');

/**
 * AJAX Step 1: Prepare — Return the list of plugins to install.
 * NO big download here — each plugin is downloaded individually in Step 2.
 * This prevents the 10% hang caused by downloading the entire GitHub repo at once.
 */
function xepmarket2_ajax_prepare_plugins_sync() {
    ob_start();
    check_ajax_referer(XEPMARKET2_GITHUB_SYNC_NONCE_ACTION, 'nonce');
    if (!current_user_can('install_plugins')) {
        ob_end_clean();
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    // Plugin installation order:
    // 1. WooCommerce (WordPress.org) — MUST be first
    // 2. Other WordPress.org plugins
    // 3. OmniXEP Gateway (GitHub) — last, requires WooCommerce
    $slugs = [
        'woocommerce',
        'woo-alidropship',
        'product-variations-swatches-for-woocommerce',
        'vargal-additional-variation-gallery-for-woo',
        'woo-orders-tracking',
        'omnixep-woocommerce',
    ];

    ob_end_clean();
    wp_send_json_success(['slugs' => $slugs]);
}
add_action('wp_ajax_xep_prepare_plugins_sync', 'xepmarket2_ajax_prepare_plugins_sync');

/**
 * AJAX Step 2: Install a Single Plugin
 * Downloads each plugin individually from the correct source:
 *   - omnixep-woocommerce → GitHub (PlanC90/plugins repo)
 *   - All others → WordPress.org official repository
 */
function xepmarket2_ajax_install_plugin_step() {
    ob_start();
    check_ajax_referer(XEPMARKET2_GITHUB_SYNC_NONCE_ACTION, 'nonce');
    if (!current_user_can('install_plugins')) {
        ob_end_clean();
        wp_send_json_error(['message' => 'Denied']);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    @ini_set('memory_limit', '512M');

    if (!defined('FS_METHOD')) {
        define('FS_METHOD', 'direct');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $wp_filesystem = xepmarket2_init_filesystem();
    if (!$wp_filesystem) {
        ob_end_clean();
        wp_send_json_error(['message' => 'Filesystem init failed.']);
    }

    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    if (!$slug) {
        ob_end_clean();
        wp_send_json_error(['message' => 'No plugin slug provided.']);
    }

    // Disable SSL verification for compatibility
    add_filter('http_request_args', function($args, $url) {
        if (strpos($url, 'github.com') !== false || strpos($url, 'downloads.wordpress.org') !== false) {
            $args['sslverify'] = false;
        }
        return $args;
    }, 10, 2);

    // ─── GITHUB: omnixep-woocommerce only ───
    if ($slug === 'omnixep-woocommerce') {
        $temp_zip = download_url(XEPMARKET2_GITHUB_PLUGINS_REPO, 300);
        if (is_wp_error($temp_zip)) {
            ob_end_clean();
            wp_send_json_error(['message' => 'GitHub download failed: ' . $temp_zip->get_error_message()]);
        }

        $temp_dir = get_temp_dir() . 'xep_gh_' . wp_generate_password(8, false) . '/';
        $wp_filesystem->mkdir($temp_dir);
        $extract_to = $temp_dir . 'extract/';
        $wp_filesystem->mkdir($extract_to);

        $unzip = unzip_file($temp_zip, $extract_to);
        @unlink($temp_zip);

        if (is_wp_error($unzip)) {
            xepmarket2_github_sync_cleanup($temp_dir);
            ob_end_clean();
            wp_send_json_error(['message' => 'GitHub unzip failed: ' . $unzip->get_error_message()]);
        }

        // Find repo root (plugins-main/)
        $root_folders = array_values(array_filter(glob($extract_to . '*'), 'is_dir'));
        $repo_root = $root_folders[0] ?? null;
        if (!$repo_root) {
            xepmarket2_github_sync_cleanup($temp_dir);
            ob_end_clean();
            wp_send_json_error(['message' => 'Invalid GitHub repo structure.']);
        }

        // Find omnixep-woocommerce folder inside the repo
        $source = $repo_root . DIRECTORY_SEPARATOR . $slug;
        $inner = $source . DIRECTORY_SEPARATOR . $slug;
        if (is_dir($inner) && file_exists($inner . DIRECTORY_SEPARATOR . $slug . '.php')) {
            $source = $inner;
        }

        if (!is_dir($source)) {
            xepmarket2_github_sync_cleanup($temp_dir);
            ob_end_clean();
            wp_send_json_error(['message' => 'omnixep-woocommerce folder not found in GitHub repo.']);
        }

        $dest = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $slug;

        // CRITICAL FIX: If the plugin is active, we MUST deactivate it before deleting
        // Otherwise WordPress or the server may block the deletion/replacement.
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_file = $slug . '/' . $slug . '.php';
        // Check for common variant folder names if direct match fails
        if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
            $variant = 'omnixep-woocommerce-payment-gateway/omnixep-woocommerce.php';
            if ($slug === 'omnixep-woocommerce' && file_exists(WP_PLUGIN_DIR . '/' . $variant)) {
                $plugin_file = $variant;
                $dest = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'omnixep-woocommerce-payment-gateway';
            }
        }

        if (is_plugin_active($plugin_file)) {
            deactivate_plugins($plugin_file, true);
        }

        if ($wp_filesystem->is_dir($dest)) {
            $wp_filesystem->delete($dest, true);
        }

        if ($wp_filesystem->move($source, $dest, true)) {
            xepmarket2_github_sync_cleanup($temp_dir);
            ob_end_clean();
            wp_send_json_success(['slug' => $slug, 'source' => 'github']);
        } else {
            $errors = [];
            $success = xepmarket2_copy_plugin_dir($source, $dest, $errors);
            xepmarket2_github_sync_cleanup($temp_dir);
            ob_end_clean();
            if ($success) {
                wp_send_json_success(['slug' => $slug, 'source' => 'github']);
            } else {
                wp_send_json_error(['message' => 'Copy failed: ' . implode('; ', $errors)]);
            }
        }
        return;
    }

    // ─── WORDPRESS.ORG: WooCommerce and all other plugins ───
    $download_url = isset(XEPMARKET2_OFFICIAL_PLUGIN_URLS[$slug])
        ? XEPMARKET2_OFFICIAL_PLUGIN_URLS[$slug]
        : 'https://downloads.wordpress.org/plugin/' . $slug . '.latest-stable.zip';

    $temp_zip = download_url($download_url, 300);
    if (is_wp_error($temp_zip)) {
        ob_end_clean();
        wp_send_json_error(['message' => 'Download failed for ' . $slug . ': ' . $temp_zip->get_error_message()]);
    }

    $dest = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $slug;
    if ($wp_filesystem->is_dir($dest)) {
        $wp_filesystem->delete($dest, true);
    }

    $unzip = unzip_file($temp_zip, WP_PLUGIN_DIR);
    @unlink($temp_zip);

    if (is_wp_error($unzip)) {
        ob_end_clean();
        wp_send_json_error(['message' => 'Unzip failed for ' . $slug . ': ' . $unzip->get_error_message()]);
    }

    ob_end_clean();
    wp_send_json_success(['slug' => $slug, 'source' => 'wordpress.org']);
}
add_action('wp_ajax_xep_install_plugin_step', 'xepmarket2_ajax_install_plugin_step');

/**
 * AJAX Step 3: Finalize (Cleanup)
 */
function xepmarket2_ajax_finalize_plugins_sync() {
    check_ajax_referer(XEPMARKET2_GITHUB_SYNC_NONCE_ACTION, 'nonce');
    delete_transient('xep_sync_repo_root');
    delete_transient('xep_sync_temp_dir');
    wp_send_json_success();
}
add_action('wp_ajax_xep_finalize_plugins_sync', 'xepmarket2_ajax_finalize_plugins_sync');

/**
 * Download repo ZIP, extract, copy each plugin folder into wp-content/plugins.
 *
 * @return array|WP_Error { 'updated' => string[], 'installed' => string[], 'skipped' => string[], 'errors' => string[] }
 */
function xepmarket2_sync_plugins_from_github()
{
    $plugins_dir = WP_PLUGIN_DIR;
    $temp_dir   = get_temp_dir() . 'xepmarket2_plugins_sync_' . wp_generate_password(8, false) . '/';
    $zip_path   = $temp_dir . 'main.zip';

    if (!wp_mkdir_p($temp_dir)) {
        return new WP_Error('temp_dir', __('Could not create temporary directory.', 'xepmarket2'));
    }

    $response = wp_remote_get(XEPMARKET2_GITHUB_PLUGINS_REPO, [
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        xepmarket2_github_sync_cleanup($temp_dir);
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200 || empty($body)) {
        xepmarket2_github_sync_cleanup($temp_dir);
        return new WP_Error('download', sprintf(__('GitHub returned HTTP %d or empty body.', 'xepmarket2'), $code));
    }

    if (file_put_contents($zip_path, $body) === false) {
        xepmarket2_github_sync_cleanup($temp_dir);
        return new WP_Error('temp_dir', __('Could not save downloaded ZIP.', 'xepmarket2'));
    }

    if (!class_exists('ZipArchive')) {
        xepmarket2_github_sync_cleanup($temp_dir);
        return new WP_Error('zip', __('ZipArchive PHP extension is required.', 'xepmarket2'));
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        xepmarket2_github_sync_cleanup($temp_dir);
        return new WP_Error('zip', __('Could not open downloaded ZIP file.', 'xepmarket2'));
    }

    $extract_to = $temp_dir . 'extract/';
    if (!wp_mkdir_p($extract_to)) {
        $zip->close();
        xepmarket2_github_sync_cleanup($temp_dir);
        return new WP_Error('temp_dir', __('Could not create extract directory.', 'xepmarket2'));
    }

    $zip->extractTo($extract_to);
    $zip->close();

    // GitHub archive has a single root folder like "plugins-main"
    $root_folders = array_values(array_filter(glob($extract_to . '*'), 'is_dir'));
    $repo_root = $root_folders[0] ?? null;
    if (!$repo_root || !is_dir($repo_root)) {
        xepmarket2_github_sync_cleanup($temp_dir);
        return new WP_Error('structure', __('Invalid archive structure (no root folder).', 'xepmarket2'));
    }

    $out = ['updated' => [], 'installed' => [], 'skipped' => [], 'errors' => []];
    $skip_names = ['.git', '.gitignore', 'index.php', 'README.md', '.github', 'xepmarket-telegram-bot-2', 'xepmarket-telegram-bot', 'mailin', 'woocommerce-sendinblue-newsletter-subscription'];

    foreach (scandir($repo_root) as $name) {
        if ($name === '.' || $name === '..' || in_array($name, $skip_names, true)) {
            continue;
        }
        // Telegram Bot: tema içinde entegre (inc/telegram-bot.php). İsim ne olursa olsun Telegram eklentisi kurulmasın.
        if (stripos($name, 'telegram') !== false && stripos($name, 'bot') !== false) {
            $out['skipped'][] = $name;
            continue;
        }
        $source = $repo_root . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($source)) {
            continue;
        }

        // Nested plugin: xepmarket-telegram-bot/xepmarket-telegram-bot → install as xepmarket-telegram-bot
        $plugin_slug = $name;
        $copy_source = $source;
        $inner = $source . DIRECTORY_SEPARATOR . $name;
        if (is_dir($inner) && file_exists($inner . DIRECTORY_SEPARATOR . $name . '.php')) {
            $copy_source = $inner;
        }

        $dest = $plugins_dir . DIRECTORY_SEPARATOR . $plugin_slug;
        $existed = is_dir($dest);

        if (!xepmarket2_copy_plugin_dir($copy_source, $dest, $out['errors'])) {
            $out['skipped'][] = $plugin_slug;
            continue;
        }

        if ($existed) {
            $out['updated'][] = $plugin_slug;
        } else {
            $out['installed'][] = $plugin_slug;
        }
    }

    xepmarket2_github_sync_cleanup($temp_dir);
    return $out;
}

/**
 * Recursively copy a plugin directory to destination using WP_Filesystem.
 */
function xepmarket2_copy_plugin_dir($source, $dest, &$errors)
{
    global $wp_filesystem;
    $fs = xepmarket2_init_filesystem();

    if (!$fs->is_dir($source)) {
        return false;
    }

    if (!$fs->is_dir($dest)) {
        if (!$fs->mkdir($dest, FS_CHMOD_DIR)) {
            $errors[] = sprintf(__('Could not create folder: %s', 'xepmarket2'), $dest);
            return false;
        }
    }

    $file_list = $fs->dirlist($source);
    if (!$file_list) return true;

    foreach ($file_list as $file) {
        $src_path = str_replace('\\', '/', $source . '/' . $file['name']);
        $dst_path = str_replace('\\', '/', $dest . '/' . $file['name']);

        if ($file['type'] === 'd') {
            if (!xepmarket2_copy_plugin_dir($src_path, $dst_path, $errors)) {
                return false;
            }
        } else {
            if (!$fs->copy($src_path, $dst_path, true, FS_CHMOD_FILE)) {
                $errors[] = sprintf(__('Could not copy: %s', 'xepmarket2'), $file['name']);
            }
        }
    }
    return true;
}

function xepmarket2_github_sync_cleanup($temp_dir)
{
    if (!is_dir($temp_dir)) {
        return;
    }
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($file->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($temp_dir);
    } catch (Exception $e) {
        // Ignore permission denied or iteration exceptions to prevent fatal error parsing JSON
    }
}
