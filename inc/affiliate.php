<?php
/**
 * XEPMARKET Affiliate – theme-integrated (same options/meta as standalone plugin).
 * Settings: XEPMARKET theme options → Affiliate tab.
 * Full management: WooCommerce → Affiliates.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    add_rewrite_endpoint('affiliate', EP_ROOT | EP_PAGES);
}, 5);
add_filter('query_vars', function ($v) { $v[] = 'affiliate'; return $v; }, 0);

add_action('init', function () {
    if (get_option('xepmarket2_affiliate_flush_rules')) {
        flush_rewrite_rules();
        delete_option('xepmarket2_affiliate_flush_rules');
    }
}, 99);

add_action('init', function () {
    if (is_admin() || !isset($_GET['ref'])) return;
    $ref_id = absint($_GET['ref']);
    if ($ref_id > 0) {
        $days = absint(get_option('omnixep_affiliate_cookie_days', 30));
        setcookie('omnixep_affiliate_ref', $ref_id, time() + ($days * DAY_IN_SECONDS), '/');
    }
}, 20);

add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    if (empty($_COOKIE['omnixep_affiliate_ref'])) return;
    $ref_id = absint($_COOKIE['omnixep_affiliate_ref']);
    if ($ref_id > 0 && (!is_user_logged_in() || get_current_user_id() !== $ref_id))
        update_post_meta($order_id, '_omnixep_affiliate_id', $ref_id);
});

add_action('woocommerce_order_status_completed', function ($order_id) {
    $ref_user_id = get_post_meta($order_id, '_omnixep_affiliate_id', true);
    if (!$ref_user_id || get_post_meta($order_id, '_omnixep_commission_paid', true)) return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    $total = $order->get_subtotal();
    $rate = floatval(get_option('omnixep_affiliate_rate', 10));
    $commission = ($total * $rate) / 100;
    if ($commission <= 0) return;
    $current_balance = floatval(get_user_meta($ref_user_id, 'omnixep_affiliate_balance', true));
    update_user_meta($ref_user_id, 'omnixep_affiliate_balance', $current_balance + $commission);
    update_post_meta($order_id, '_omnixep_commission_paid', 'yes');
    update_post_meta($order_id, '_omnixep_commission_amount', $commission);
    $history = get_user_meta($ref_user_id, 'omnixep_affiliate_history', true);
    if (!is_array($history)) $history = array();
    $history[] = array('order_id' => $order_id, 'amount' => $commission, 'date' => current_time('mysql'), 'type' => 'earned');
    update_user_meta($ref_user_id, 'omnixep_affiliate_history', $history);
});

add_action('woocommerce_order_status_refunded', 'xepmarket2_affiliate_revert');
add_action('woocommerce_order_status_cancelled', 'xepmarket2_affiliate_revert');
function xepmarket2_affiliate_revert($order_id) {
    $ref_user_id = get_post_meta($order_id, '_omnixep_affiliate_id', true);
    if (!$ref_user_id || !get_post_meta($order_id, '_omnixep_commission_paid', true) || get_post_meta($order_id, '_omnixep_commission_reverted', true)) return;
    $commission = floatval(get_post_meta($order_id, '_omnixep_commission_amount', true));
    if ($commission <= 0) return;
    $current_balance = floatval(get_user_meta($ref_user_id, 'omnixep_affiliate_balance', true));
    update_user_meta($ref_user_id, 'omnixep_affiliate_balance', max(0, $current_balance - $commission));
    update_post_meta($order_id, '_omnixep_commission_reverted', 'yes');
    $history = get_user_meta($ref_user_id, 'omnixep_affiliate_history', true);
    if (!is_array($history)) $history = array();
    $history[] = array('order_id' => $order_id, 'amount' => -$commission, 'date' => current_time('mysql'), 'type' => 'reverted');
    update_user_meta($ref_user_id, 'omnixep_affiliate_history', $history);
}

add_filter('woocommerce_account_menu_items', function ($items) {
    $new = array();
    foreach ($items as $key => $label) {
        $new[$key] = $label;
        if ($key === 'orders') $new['affiliate'] = __('Affiliate Dashboard', 'xepmarket2');
    }
    return $new;
});

add_action('woocommerce_account_affiliate_endpoint', 'xepmarket2_affiliate_endpoint_content');
function xepmarket2_affiliate_endpoint_content() {
    $user_id = get_current_user_id();
    if (isset($_POST['save_xep_wallet'], $_POST['omnixep_aff_wallet']) && wp_verify_nonce($_POST['xep_wallet_nonce'], 'save_wallet')) {
        update_user_meta($user_id, 'omnixep_aff_wallet', sanitize_text_field($_POST['omnixep_aff_wallet']));
        echo '<div class="woocommerce-message" role="alert">' . esc_html__('Your XEP wallet address has been updated.', 'xepmarket2') . '</div>';
    }
    $rate = get_option('omnixep_affiliate_rate', 10);
    $affiliate_link = add_query_arg('ref', $user_id, site_url('/'));
    $balance = floatval(get_user_meta($user_id, 'omnixep_affiliate_balance', true));
    $paid_balance = floatval(get_user_meta($user_id, 'omnixep_affiliate_paid_balance', true));
    $wallet_address = get_user_meta($user_id, 'omnixep_aff_wallet', true);
    $history = get_user_meta($user_id, 'omnixep_affiliate_history', true);
    if (!is_array($history)) $history = array();
    usort($history, function ($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
    ?>
    <h3><?php esc_html_e('My Affiliate Dashboard', 'xepmarket2'); ?></h3>
    <p><?php printf(esc_html__('Share your referral link to earn %s%% commission on completed sales. Payouts in XEP.', 'xepmarket2'), esc_html($rate)); ?></p>
    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); padding: 15px 20px; border-radius: 12px; margin-bottom: 30px;">
        <form method="post"><input type="hidden" name="xep_wallet_nonce" value="<?php echo wp_create_nonce('save_wallet'); ?>">
            <label style="display:block; margin-bottom: 8px; font-weight: 600;"><?php esc_html_e('XEP Payout Wallet Address', 'xepmarket2'); ?></label>
            <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="omnixep_aff_wallet" value="<?php echo esc_attr($wallet_address); ?>" placeholder="X..." style="flex: 1; min-width: 250px; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: #fff;">
                <button type="submit" name="save_xep_wallet" class="button"><?php esc_html_e('Save Wallet', 'xepmarket2'); ?></button>
            </div>
        </form>
    </div>
    <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 20px; border-radius: 12px; margin-bottom: 30px;">
        <label style="display:block; margin-bottom: 8px; font-weight: 600;"><?php esc_html_e('Your referral link', 'xepmarket2'); ?></label>
        <div style="display:flex; gap: 10px;">
            <input type="text" id="xep-aff-link" value="<?php echo esc_url($affiliate_link); ?>" readonly style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.2); color: #fff;">
            <button type="button" class="button" onclick="var i=document.getElementById('xep-aff-link'); i.select(); navigator.clipboard.writeText(i.value); this.textContent='<?php echo esc_js(__('Copied!', 'xepmarket2')); ?>';"><?php esc_html_e('Copy', 'xepmarket2'); ?></button>
        </div>
    </div>
    <div style="display:flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; padding: 25px; background: linear-gradient(135deg, rgba(0,242,255,0.1), rgba(112,0,255,0.1)); border: 1px solid rgba(0,242,255,0.2); border-radius: 12px;">
        <div><h4 style="margin: 0; font-size: 13px; text-transform: uppercase; opacity: 0.8;"><?php esc_html_e('Unpaid balance', 'xepmarket2'); ?></h4><div style="font-size: 32px; font-weight: 700; color: var(--primary, #00f2ff);"><?php echo number_format($balance, 2); ?> XEP</div></div>
        <div style="border-left: 1px solid rgba(255,255,255,0.1); padding-left: 20px;"><h4 style="margin: 0; font-size: 13px; text-transform: uppercase; opacity: 0.8;"><?php esc_html_e('Total paid', 'xepmarket2'); ?></h4><div style="font-size: 32px; font-weight: 700; color: #00a32a;"><?php echo number_format($paid_balance, 2); ?> XEP</div></div>
    </div>
    <h4><?php esc_html_e('Commission history', 'xepmarket2'); ?></h4>
    <?php if (empty($history)): ?><p style="opacity: 0.7;"><?php esc_html_e('No commissions yet. Share your link to earn!', 'xepmarket2'); ?></p>
    <?php else: ?>
    <table class="woocommerce-orders-table shop_table" style="width:100%; border-collapse: collapse;">
        <thead><tr><th><?php esc_html_e('Order', 'xepmarket2'); ?></th><th><?php esc_html_e('Date', 'xepmarket2'); ?></th><th><?php esc_html_e('Commission', 'xepmarket2'); ?></th><th><?php esc_html_e('Status', 'xepmarket2'); ?></th></tr></thead>
        <tbody><?php foreach ($history as $log): ?>
            <tr>
                <td><a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $log['order_id'], wc_get_page_permalink('myaccount'))); ?>">#<?php echo esc_html($log['order_id']); ?></a></td>
                <td><?php echo esc_html(wp_date(get_option('date_format'), strtotime($log['date']))); ?></td>
                <td><strong><?php echo number_format($log['amount'], 2); ?> XEP</strong></td>
                <td><?php echo (isset($log['type']) && $log['type'] === 'reverted') ? '<span style="color:#ff4b4b;">' . esc_html__('Reverted', 'xepmarket2') . '</span>' : '<span style="color:#00a32a;">' . esc_html__('Earned', 'xepmarket2') . '</span>'; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table>
    <?php endif;
}

add_action('admin_menu', function () {
    add_submenu_page('woocommerce', __('Affiliate Settings', 'xepmarket2'), __('Affiliates', 'xepmarket2'), 'manage_woocommerce', 'xepmarket2-affiliate', 'xepmarket2_affiliate_admin_page');
}, 20);

function xepmarket2_affiliate_admin_page() {
    if (isset($_POST['mark_paid'], $_POST['user_id']) && current_user_can('manage_woocommerce')) {
        $u_id = absint($_POST['user_id']);
        $current_unpaid = floatval(get_user_meta($u_id, 'omnixep_affiliate_balance', true));
        if ($current_unpaid > 0) {
            $current_paid = floatval(get_user_meta($u_id, 'omnixep_affiliate_paid_balance', true));
            update_user_meta($u_id, 'omnixep_affiliate_paid_balance', $current_paid + $current_unpaid);
            update_user_meta($u_id, 'omnixep_affiliate_balance', 0);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Balance marked as paid.', 'xepmarket2') . '</p></div>';
        }
    }
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'total_earned';
    $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
    $paged = max(1, absint($_GET['paged'] ?? 1));
    $per_page = 50;
    $args = array('meta_query' => array('relation' => 'OR', array('key' => 'omnixep_affiliate_balance', 'compare' => 'EXISTS'), array('key' => 'omnixep_affiliate_history', 'compare' => 'EXISTS'), array('key' => 'omnixep_aff_wallet', 'compare' => 'EXISTS')));
    $all_users = get_users($args);
    $stat_affiliates = $stat_sales = 0; $stat_paid = $stat_unpaid = 0.0; $rows = array();
    foreach ($all_users as $u) {
        $balance = floatval(get_user_meta($u->ID, 'omnixep_affiliate_balance', true));
        $paid_balance = floatval(get_user_meta($u->ID, 'omnixep_affiliate_paid_balance', true));
        $wallet = get_user_meta($u->ID, 'omnixep_aff_wallet', true);
        $history = get_user_meta($u->ID, 'omnixep_affiliate_history', true);
        $deals = is_array($history) ? count($history) : 0;
        $total_earned = $balance + $paid_balance;
        $stat_affiliates++; $stat_sales += $deals; $stat_paid += $paid_balance; $stat_unpaid += $balance;
        if ($search) { $q = strtolower($search); if (strpos(strtolower($u->display_name), $q) === false && strpos(strtolower($u->user_email), $q) === false && strpos(strtolower((string)$wallet), $q) === false) continue; }
        $rows[] = array('user' => $u, 'balance' => $balance, 'paid_balance' => $paid_balance, 'total_earned' => $total_earned, 'wallet' => $wallet, 'deals' => $deals);
    }
    $v = array('unpaid_balance' => 'balance', 'paid_commissions' => 'paid_balance', 'deals' => 'deals', 'total_earned' => 'total_earned');
    usort($rows, function ($a, $b) use ($orderby, $order, $v) { $key = $v[$orderby] ?? 'total_earned'; $va = $a[$key]; $vb = $b[$key]; if ($va == $vb) return 0; $c = ($va < $vb) ? -1 : 1; return $order === 'asc' ? $c : -$c; });
    $total_items = count($rows); $total_pages = max(1, (int)ceil($total_items / $per_page)); $offset = ($paged - 1) * $per_page;
    $page_rows = array_slice($rows, $offset, $per_page);
    $base_url = add_query_arg(array('page' => 'xepmarket2-affiliate', 'orderby' => $orderby, 'order' => $order), admin_url('admin.php'));
    if ($search) $base_url = add_query_arg('s', urlencode($search), $base_url);
    ?>
    <div class="wrap"><h1><?php esc_html_e('Affiliate Management', 'xepmarket2'); ?></h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=xepmarket2-settings#tab-affiliate')); ?>"><?php esc_html_e('Configure commission rate and cookie duration in XEPMARKET Settings → Affiliate.', 'xepmarket2'); ?></a></p>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:15px;"><div style="font-size:12px; color:#646970;"><?php esc_html_e('Total Affiliates', 'xepmarket2'); ?></div><div style="font-size:24px; font-weight:700;"><?php echo number_format($stat_affiliates); ?></div></div>
        <div style="background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:15px;"><div style="font-size:12px; color:#646970;"><?php esc_html_e('Total Deals', 'xepmarket2'); ?></div><div style="font-size:24px; font-weight:700;"><?php echo number_format($stat_sales); ?></div></div>
        <div style="background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:15px;"><div style="font-size:12px; color:#646970;"><?php esc_html_e('Total Paid', 'xepmarket2'); ?></div><div style="font-size:24px; font-weight:700; color:#00a32a;"><?php echo number_format($stat_paid, 2); ?> XEP</div></div>
        <div style="background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:15px;"><div style="font-size:12px; color:#646970;"><?php esc_html_e('Total Unpaid', 'xepmarket2'); ?></div><div style="font-size:24px; font-weight:700; color:#d63638;"><?php echo number_format($stat_unpaid, 2); ?> XEP</div></div>
    </div>
    <form method="get" style="margin-bottom:15px;"><input type="hidden" name="page" value="xepmarket2-affiliate"><input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>"><input type="hidden" name="order" value="<?php echo esc_attr($order); ?>"><input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search...', 'xepmarket2'); ?>"><button type="submit" class="button"><?php esc_html_e('Search', 'xepmarket2'); ?></button></form>
    <table class="wp-list-table widefat fixed striped">
        <thead><tr><th><?php esc_html_e('User & Wallet', 'xepmarket2'); ?></th><th><?php esc_html_e('Referral link', 'xepmarket2'); ?></th><th><a href="<?php echo esc_url(add_query_arg(array('orderby' => 'total_earned', 'order' => ($orderby === 'total_earned' && $order === 'asc') ? 'desc' : 'asc'), $base_url)); ?>"><?php esc_html_e('Total earned', 'xepmarket2'); ?></a></th><th><?php esc_html_e('Paid', 'xepmarket2'); ?></th><th><?php esc_html_e('Unpaid', 'xepmarket2'); ?></th><th><?php esc_html_e('Actions', 'xepmarket2'); ?></th></tr></thead>
        <tbody><?php if (empty($page_rows)): ?><tr><td colspan="6" style="text-align:center; padding:30px;"><?php esc_html_e('No affiliates found.', 'xepmarket2'); ?></td></tr>
        <?php else: foreach ($page_rows as $r): $u = $r['user']; $ref_link = add_query_arg('ref', $u->ID, site_url('/')); ?>
            <tr>
                <td><strong><?php echo esc_html($u->display_name); ?></strong> (ID: <?php echo $u->ID; ?>)<br><a href="mailto:<?php echo esc_attr($u->user_email); ?>"><?php echo esc_html($u->user_email); ?></a><br><?php if (!empty($r['wallet'])): ?><code style="font-size:11px;"><?php echo esc_html($r['wallet']); ?></code><?php else: ?><em><?php esc_html_e('No wallet', 'xepmarket2'); ?></em><?php endif; ?></td>
                <td><input type="text" value="<?php echo esc_url($ref_link); ?>" readonly style="width:100%; max-width:200px; font-size:11px;" onclick="this.select();"></td>
                <td><?php echo number_format($r['total_earned'], 2); ?> XEP (<?php echo $r['deals']; ?> <?php esc_html_e('deals', 'xepmarket2'); ?>)</td>
                <td style="color:#00a32a;"><?php echo number_format($r['paid_balance'], 2); ?> XEP</td>
                <td><?php echo number_format($r['balance'], 2); ?> XEP</td>
                <td><?php if ($r['balance'] > 0): ?><form method="post" style="margin:0;" onsubmit="return confirm('<?php echo esc_js(__('Mark this balance as paid?', 'xepmarket2')); ?>');"><input type="hidden" name="user_id" value="<?php echo $u->ID; ?>"><button type="submit" name="mark_paid" class="button button-small"><?php esc_html_e('Mark as paid', 'xepmarket2'); ?></button></form><?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?></tbody>
    </table>
    <?php if ($total_pages > 1): ?><p style="margin-top:15px;"><?php if ($paged > 1): ?><a href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $base_url)); ?>" class="button">&laquo; <?php esc_html_e('Prev', 'xepmarket2'); ?></a><?php endif; ?> <span style="margin:0 15px;"><?php printf(esc_html__('Page %1$d of %2$d', 'xepmarket2'), $paged, $total_pages); ?></span> <?php if ($paged < $total_pages): ?><a href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $base_url)); ?>" class="button"><?php esc_html_e('Next', 'xepmarket2'); ?> &raquo;</a><?php endif; ?></p><?php endif; ?>
    </div><?php
}
