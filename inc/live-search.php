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
    $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

    if (empty($search_query) || strlen($search_query) < 2) {
        wp_send_json_success(array('html' => ''));
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

            $html .= '<a href="' . get_permalink() . '" class="live-search-item">';
            if ($image) {
                $html .= '<div class="live-search-image"><img src="' . esc_url($image) . '" alt="' . get_the_title() . '"></div>';
            }
            $html .= '<div class="live-search-info">';
            $html .= '    <div class="live-search-title">' . get_the_title() . '</div>';
            $html .= '    <div class="live-search-price">' . $price . '</div>';
            $html .= '</div>';
            $html .= '</a>';
        }
        $html .= '</div>';
        $html .= '<div class="live-search-footer">';
        $html .= '    <a href="' . esc_url(home_url('/?s=' . $search_query . '&post_type=product')) . '" class="view-all-results">' . sprintf(__('View all results for "%s"', 'xepmarket2'), $search_query) . '</a>';
        $html .= '</div>';
    } else {
        $html .= '<div class="live-search-no-results">' . __('No products found.', 'xepmarket2') . '</div>';
    }

    wp_reset_postdata();
    wp_send_json_success(array('html' => $html));
}
