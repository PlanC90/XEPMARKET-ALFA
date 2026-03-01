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
define('XEPMARKET2_PLUGIN_ZIP_SLUGS', ['omnixep-woocommerce', 'xepmarket-telegram-bot']);

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

    $temp_dir = get_temp_dir() . 'xepmarket2_zip_' . wp_generate_password(8, false) . '/';
    if (!wp_mkdir_p($temp_dir)) {
        status_header(500);
        exit;
    }

    $response = wp_remote_get(XEPMARKET2_GITHUB_PLUGINS_REPO, ['timeout' => 60]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(502);
        exit;
    }
    $zip_path = $temp_dir . 'repo.zip';
    if (file_put_contents($zip_path, wp_remote_retrieve_body($response)) === false) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
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
        exit;
    }

    $source = $repo_root . DIRECTORY_SEPARATOR . $slug;
    $inner = $source . DIRECTORY_SEPARATOR . $slug;
    if (is_dir($inner) && file_exists($inner . DIRECTORY_SEPARATOR . $slug . '.php')) {
        $source = $inner;
    }
    if (!is_dir($source)) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(404);
        exit;
    }

    $out_zip = $temp_dir . $slug . '.zip';
    $z = new ZipArchive();
    if ($z->open($out_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        xepmarket2_github_sync_cleanup($temp_dir);
        status_header(500);
        exit;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        $path = $file->getRealPath();
        $relative = substr($path, strlen($source) + 1);
        $z->addFile($path, $slug . '/' . str_replace('\\', '/', $relative));
    }
    $z->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $slug . '.zip"');
    header('Content-Length: ' . filesize($out_zip));
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
    $skip_names = ['.git', '.gitignore', 'index.php', 'README.md', '.github', 'xepmarket-telegram-bot-2'];

    foreach (scandir($repo_root) as $name) {
        if ($name === '.' || $name === '..' || in_array($name, $skip_names, true)) {
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
 * Recursively copy a plugin directory to destination. Merge/replace.
 */
function xepmarket2_copy_plugin_dir($source, $dest, &$errors)
{
    if (!is_dir($source)) {
        return false;
    }
    if (!wp_mkdir_p($dest)) {
        $errors[] = sprintf(__('Could not create folder: %s', 'xepmarket2'), $dest);
        return false;
    }

    $dir = dir($source);
    while (false !== ($entry = $dir->read())) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $src_path = $source . DIRECTORY_SEPARATOR . $entry;
        $dst_path = $dest . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($src_path)) {
            if (!xepmarket2_copy_plugin_dir($src_path, $dst_path, $errors)) {
                $dir->close();
                return false;
            }
        } else {
            if (!copy($src_path, $dst_path)) {
                $errors[] = sprintf(__('Could not copy: %s', 'xepmarket2'), $entry);
            }
        }
    }
    $dir->close();
    return true;
}

function xepmarket2_github_sync_cleanup($temp_dir)
{
    if (!is_dir($temp_dir)) {
        return;
    }
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
}
