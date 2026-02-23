<?php
/**
 * My Account navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

$nav_icons = array(
    'dashboard' => 'fas fa-th-large',
    'orders' => 'fas fa-shopping-bag',
    'downloads' => 'fas fa-download',
    'edit-address' => 'fas fa-map-marker-alt',
    'edit-account' => 'fas fa-user-edit',
    'customer-logout' => 'fas fa-sign-out-alt',
);

do_action('woocommerce_before_account_navigation');
?>

<nav class="woocommerce-MyAccount-navigation">
    <ul>
        <?php foreach (wc_get_account_menu_items() as $endpoint => $label): ?>
            <li class="<?php echo wc_get_account_menu_item_classes($endpoint); ?>">
                <a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>">
                    <i
                        class="<?php echo isset($nav_icons[$endpoint]) ? $nav_icons[$endpoint] : 'fas fa-chevron-right'; ?>"></i>
                    <span>
                        <?php echo esc_html($label); ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php do_action('woocommerce_after_account_navigation'); ?>