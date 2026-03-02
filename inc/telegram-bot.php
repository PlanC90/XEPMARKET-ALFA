<?php
/**
 * Telegram Order Notifications – theme-integrated (same options as standalone plugin).
 * Settings are in XEPMARKET theme options → Telegram Bot tab.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('xepmarket2_telegram_send_message')) {
    function xepmarket2_telegram_send_message($message)
    {
        if (get_option('xep_tg_bot_enabled') !== 'yes') {
            return;
        }
        $token = get_option('xep_tg_bot_token');
        $chat_id = get_option('xep_tg_bot_chat_id');
        if (empty($token) || empty($chat_id)) {
            return;
        }
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = array(
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        );
        wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => json_encode($data)
        ));
    }
}

if (!function_exists('xepmarket2_telegram_replace_vars')) {
    function xepmarket2_telegram_replace_vars($template, $order)
    {
        if (!$order) {
            return $template;
        }
        $items_str = '';
        foreach ($order->get_items() as $item_id => $item) {
            $qty = $item->get_quantity();
            $name = $item->get_name();
            $total = html_entity_decode(wp_strip_all_tags(wc_price($item->get_total(), array('currency' => $order->get_currency()))));
            $items_str .= "- {$qty}x {$name} ({$total})\n";
        }
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        if (empty(trim($customer_name))) {
            $customer_name = 'Guest';
        }
        $telegram_username = get_post_meta($order->get_id(), '_billing_telegram', true);
        if (empty($telegram_username)) {
            $telegram_username = get_post_meta($order->get_id(), 'billing_telegram', true);
        }
        $telegram_str = __('Not provided', 'xepmarket2');
        if (!empty($telegram_username)) {
            $clean_username = ltrim($telegram_username, '@');
            $telegram_str = "<a href='https://t.me/" . esc_attr($clean_username) . "'>@" . esc_html($clean_username) . "</a>";
        }
        $vars = array(
            '{order_id}' => $order->get_order_number(),
            '{status}' => wc_get_order_status_name($order->get_status()),
            '{total}' => html_entity_decode(wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency())))),
            '{customer_name}' => $customer_name,
            '{telegram_username}' => $telegram_str,
            '{items}' => $items_str
        );
        return strtr($template, $vars);
    }
}

add_action('woocommerce_checkout_order_processed', 'xepmarket2_telegram_on_new_order', 10, 3);
function xepmarket2_telegram_on_new_order($order_id, $posted_data, $order)
{
    $template = get_option('xep_tg_bot_msg_new_order');
    if (empty($template)) {
        return;
    }
    $message = xepmarket2_telegram_replace_vars($template, $order);
    xepmarket2_telegram_send_message($message);
}

add_action('woocommerce_order_status_changed', 'xepmarket2_telegram_on_status_changed', 10, 4);
function xepmarket2_telegram_on_status_changed($order_id, $old_status, $new_status, $order)
{
    if ($new_status === 'pending' || $old_status === 'pending') {
        return;
    }
    $template = get_option('xep_tg_bot_msg_status_changed');
    if (empty($template)) {
        return;
    }
    $message = xepmarket2_telegram_replace_vars($template, $order);
    xepmarket2_telegram_send_message($message);
}
