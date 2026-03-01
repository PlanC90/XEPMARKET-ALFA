<?php
/**
 * Live Search AJAX Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_xep_live_search', 'xep_live_search_callback');
add_action('wp_ajax_nopriv_xep_live_search', 'xep_live_search_callback');

function xep_live_search_callback()
{
    // SECURITY: Rate limiting check
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $rate_limit_key = 'xep_search_rate_' . md5($ip);
    $request_count = get_transient($rate_limit_key);
    
    if ($request_count && $request_count > 20) {
        wp_send_json_error(array('message' => 'Too many requests. Please wait.'));
    }
    
    set_transient($rate_limit_key, ($request_count ? $request_count + 1 : 1), 60);
    
    // SECURITY: Validate and sanitize input
    $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($search_query) || strlen($search_query) < 2) {
        wp_send_json_success(array('html' => ''));
    }
    
    // SECURITY: Limit search query length
    if (strlen($search_query) > 100) {
        wp_send_json_error(array('message' => 'Search query too long'));
    }

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        's' => $search_query,
        'posts_per_page' => 5,
    );

    $query = new WP_Query($args);
    $html = '';

    if ($query->have_posts()) {
        $html .= '<div class="live-search-results-list">';
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            $price = $product->get_price_html();
            $image = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
            $title = get_the_title();
            $permalink = get_permalink();

            // XSS: escape all dynamic output (title in content + alt; permalink in href; price whitelist)
            $html .= '<a href="' . esc_url($permalink) . '" class="live-search-item">';
            if ($image) {
                $html .= '<div class="live-search-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '"></div>';
            }
            $html .= '<div class="live-search-info">';
            $html .= '    <div class="live-search-title">' . esc_html($title) . '</div>';
            $html .= '    <div class="live-search-price">' . wp_kses_post($price) . '</div>';
            $html .= '</div>';
            $html .= '</a>';
        }
        $html .= '</div>';
        $html .= '<div class="live-search-footer">';
        $view_all_url = add_query_arg(array('s' => $search_query, 'post_type' => 'product'), home_url('/'));
        $html .= '    <a href="' . esc_url($view_all_url) . '" class="view-all-results">' . esc_html(sprintf(__('View all results for "%s"', 'xepmarket2'), $search_query)) . '</a>';
        $html .= '</div>';
    } else {
        $html .= '<div class="live-search-no-results">' . esc_html(__('No products found.', 'xepmarket2')) . '</div>';
    }

    wp_reset_postdata();
    wp_send_json_success(array('html' => $html));
}
