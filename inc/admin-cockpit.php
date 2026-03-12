<?php
/**
 * XEPMARKET Cockpit - Modern Admin Dashboard (Final Polish & Status Summary)
 */

if (!defined('ABSPATH')) exit;

/**
 * Register Cockpit Menu
 */
add_action('admin_menu', 'xepmarket2_register_cockpit_menu', 5);
function xepmarket2_register_cockpit_menu() {
    add_submenu_page(
        'index.php',
        'XEPMARKET Cockpit',
        'XEPMARKET Cockpit',
        'manage_options',
        'xep-cockpit',
        'xepmarket2_render_cockpit'
    );
}

/**
 * AJAX Handler for Cockpit Data (Filtered)
 */
add_action('wp_ajax_xep_cockpit_get_data', 'xepmarket2_ajax_get_cockpit_data');
function xepmarket2_ajax_get_cockpit_data() {
    if (!current_user_can('manage_options')) wp_send_json_error('Forbidden');

    $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : 'month';
    $stats = xepmarket2_get_cockpit_stats($range);
    
    // Fix Currency Entity Issue
    $currency_symbol = get_woocommerce_currency_symbol();
    $decoded_currency = html_entity_decode($currency_symbol, ENT_QUOTES, 'UTF-8');
    
    // Format values for JSON
    $data = array(
        'range_label'   => strtoupper($range),
        'period_orders' => number_format($stats['period']->count),
        'period_rev'    => $decoded_currency . ' ' . number_format($stats['period']->revenue ?: 0, 2),
        'total_members' => number_format($stats['total_users']),
        'avg_order'     => $decoded_currency . ' ' . number_format($stats['period']->count > 0 ? ($stats['period']->revenue / $stats['period']->count) : 0, 2),
        'chart_labels'  => array_map(function($d){ return date('d M', strtotime($d)); }, array_keys($stats['chart_data'])),
        'chart_values'  => array_values($stats['chart_data']),
        'countries'     => array(),
        'status_counts' => $stats['status_counts']
    );

    foreach ($stats['top_countries'] as $c) {
        $c_code = strtoupper($c['country']);
        $c_name = WC()->countries->countries[$c_code] ?: $c_code;
        $data['countries'][] = array(
            'code'  => strtolower($c_code),
            'name'  => $c_name,
            'count' => $c['count']
        );
    }

    wp_send_json_success($data);
}

/**
 * Fetch Stats based on Range
 */
function xepmarket2_get_cockpit_stats($range = 'month') {
    if (!class_exists('WooCommerce')) return array();

    $stats = array(
        'period' => (object) array('count' => 0, 'revenue' => 0),
        'total_users' => 0,
        'chart_data' => array(),
        'top_countries' => array(),
        'status_counts' => array()
    );

    $start_date = '';
    switch ($range) {
        case 'day': $start_date = date('Y-m-d'); break;
        case 'week': $start_date = date('Y-m-d', strtotime('-7 days')); break;
        case 'month': $start_date = date('Y-m-01'); break;
        case 'year': $start_date = date('Y-01-01'); break;
        case 'all': $start_date = '2000-01-01'; break;
    }

    // 1. Period Stats using official WC API
    $orders = wc_get_orders(array(
        'date_created' => '>=' . $start_date,
        'status'       => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-cancelled', 'wc-refunded', 'wc-failed'),
        'limit'        => -1,
    ));
    
    foreach ($orders as $order) {
        $st = $order->get_status();
        $stats['status_counts'][$st] = ($stats['status_counts'][$st] ?? 0) + 1;
        
        // Only sum revenue for "Completed" and "Processing" for the top cards
        if (in_array($st, array('completed', 'processing'))) {
            $stats['period']->count++;
            $stats['period']->revenue += $order->get_total();
        }
    }

    // 2. Members
    $user_counts = count_users();
    $stats['total_users'] = $user_counts['total_users'];

    // 3. Chart Data
    $loop_count = 7;
    for ($i = $loop_count - 1; $i >= 0; $i--) {
        $d_start = date('Y-m-d', strtotime("-$i days"));
        $d_end = $d_start . ' 23:59:59';
        
        $day_orders = wc_get_orders(array('date_created' => $d_start . '...' . $d_end, 'status' => array('wc-completed', 'wc-processing'), 'limit' => -1));
        $rev = 0;
        foreach ($day_orders as $o) { $rev += $o->get_total(); }
        $stats['chart_data'][$d_start] = $rev;
    }

    // 4. Countries (Robust Query)
    global $wpdb;
    $is_hpos = get_option('woocommerce_custom_orders_table_enabled') === 'yes';

    if ($is_hpos) {
        $query = $wpdb->prepare("
            SELECT a.country, COUNT(o.id) as count
            FROM {$wpdb->prefix}wc_orders o
            JOIN {$wpdb->prefix}wc_order_addresses a ON o.id = a.order_id
            WHERE o.status IN ('wc-completed', 'wc-processing')
            AND a.address_type = 'billing'
            AND o.date_created_gmt >= %s
            GROUP BY a.country
            ORDER BY count DESC LIMIT 5
        ", $start_date);
    } else {
        $query = $wpdb->prepare("
            SELECT pm.meta_value as country, COUNT(p.ID) as count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_billing_country'
            AND p.post_date >= %s
            GROUP BY pm.meta_value
            ORDER BY count DESC LIMIT 5
        ", $start_date . ' 00:00:00');
    }
    
    $stats['top_countries'] = $wpdb->get_results($query, ARRAY_A) ?: array();

    return $stats;
}

/**
 * Render Cockpit
 */
function xepmarket2_render_cockpit() {
    $primary_color = xepmarket2_get_option_fast('xepmarket2_color_primary', '#00f2ff');
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --cockpit-bg: #05060a;
            --cockpit-card: rgba(255, 255, 255, 0.03);
            --cockpit-border: rgba(255, 255, 255, 0.08);
            --cockpit-text: #ffffff;
            --cockpit-muted: #a0a0b8;
            --cockpit-primary: <?php echo $primary_color; ?>;
            --status-completed: #00ff88;
            --status-processing: #0088ff;
            --status-hold: #ffaa00;
            --status-cancelled: #ff4444;
        }
        #wpbody-content { background: var(--cockpit-bg) !important; color: var(--cockpit-text); }
        .wrap.xep-cockpit-wrap { margin: 20px 20px 0 0; font-family: 'Inter', sans-serif; position: relative; }
        
        .cockpit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 25px; background: linear-gradient(90deg, rgba(0,242,255,0.05) 0%, transparent 100%); border-left: 4px solid var(--cockpit-primary); border-radius: 4px; }
        .cockpit-header h1 { margin: 0; font-size: 28px; font-weight: 800; color: #fff; letter-spacing: -0.5px; }

        .cockpit-filters { display: flex; gap: 8px; margin-bottom: 30px; background: rgba(255,255,255,0.03); padding: 6px; border-radius: 40px; border: 1px solid var(--cockpit-border); width: fit-content; }
        .filter-btn { background: transparent; border: none; color: var(--cockpit-muted); padding: 8px 20px; border-radius: 30px; cursor: pointer; font-size: 13px; font-weight: 700; transition: 0.3s; }
        .filter-btn.active { background: var(--cockpit-primary); color: #000; box-shadow: 0 4px 15px rgba(0,242,255,0.3); }

        .cockpit-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--cockpit-card); border: 1px solid var(--cockpit-border); border-radius: 20px; padding: 35px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .stat-label { font-size: 11px; color: var(--cockpit-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
        .stat-value { font-size: 34px; font-weight: 900; color: #fff; line-height: 1.1; }
        
        .cockpit-row-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start; margin-bottom: 30px; }
        .chart-card { background: var(--cockpit-card); border: 1px solid var(--cockpit-border); border-radius: 20px; padding: 30px; height: 480px; display: flex; flex-direction: column; }
        
        .status-summary-title { margin: 40px 0 20px 0; font-size: 20px; font-weight: 800; color: #fff; border-bottom: 1px solid var(--cockpit-border); padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .status-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 40px; }
        .status-card { background: rgba(255,255,255,0.02); border: 1px solid var(--cockpit-border); border-radius: 15px; padding: 20px; text-align: center; border-bottom: 3px solid transparent; transition: 0.3s; }
        .status-card:hover { transform: translateY(-5px); background: rgba(255,255,255,0.04); }
        .status-card.completed { border-bottom-color: var(--status-completed); }
        .status-card.processing { border-bottom-color: var(--status-processing); }
        .status-card.on-hold { border-bottom-color: var(--status-hold); }
        .status-card.cancelled { border-bottom-color: var(--status-cancelled); }
        .status-card.other { border-bottom-color: var(--cockpit-muted); }
        
        .status-name { font-size: 11px; font-weight: 800; color: var(--cockpit-muted); text-transform: uppercase; margin-bottom: 8px; }
        .status-count { font-size: 24px; font-weight: 900; color: #fff; }

        .refresh-btn { 
            position: absolute; right: 25px; top: 115px; 
            background: rgba(0, 242, 255, 0.1); border: 1px solid var(--cockpit-primary); 
            color: var(--cockpit-primary); padding: 10px 20px; border-radius: 30px; 
            cursor: pointer; display: flex; align-items: center; justify-content: center; 
            transition: 0.3s; z-index: 10; font-size: 13px; font-weight: 800; gap: 10px;
        }
        .refresh-btn:hover { background: var(--cockpit-primary); color: #000; box-shadow: 0 0 20px rgba(0,242,255,0.4); }
        .refresh-btn.loading i { animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .country-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--cockpit-border); }
        .country-name { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 700; }
        .country-count { background: rgba(0, 242, 255, 0.15); color: var(--cockpit-primary); padding: 5px 12px; border-radius: 10px; font-size: 11px; font-weight: 900; }

        .loading-shimmer { background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 6px; height: 30px; width: 60%; margin: 0 auto; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>

    <div class="wrap xep-cockpit-wrap">
        <div class="cockpit-header">
            <div><h1>XEPMARKET COCKPIT</h1><span>Real-time shop intelligence</span></div>
        </div>

        <div class="cockpit-filters">
            <button class="filter-btn" data-range="day">DAY</button>
            <button class="filter-btn" data-range="week">WEEK</button>
            <button class="filter-btn active" data-range="month">MONTH</button>
            <button class="filter-btn" data-range="year">YEAR</button>
            <button class="filter-btn" data-range="all">ALL TIME</button>
        </div>

        <button class="refresh-btn" id="refresh-cockpit" title="Refresh Dashboard"><i class="fas fa-sync-alt"></i> REFRESH DATA</button>

        <div class="cockpit-grid">
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-shopping-bag"></i> <span id="label_orders">MONTH</span> ORDERS</div>
                <div class="stat-value" id="val_orders"><div class="loading-shimmer"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-hand-holding-usd"></i> <span id="label_rev">MONTH</span> REVENUE</div>
                <div class="stat-value" id="val_rev"><div class="loading-shimmer"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-chart-pie"></i> AVG ORDER</div>
                <div class="stat-value" id="val_avg"><div class="loading-shimmer"></div></div>
            </div>
            <div class="stat-card" style="border-bottom: 3px solid var(--cockpit-primary);">
                <div class="stat-label" style="color: var(--cockpit-primary);"><i class="fas fa-user-check"></i> TOTAL MEMBERS</div>
                <div class="stat-value" id="val_members"><div class="loading-shimmer"></div></div>
            </div>
        </div>

        <div class="cockpit-row-2">
            <div class="chart-card">
                <h3><i class="fas fa-wave-square"></i> SALES PERFORMANCE</h3>
                <div style="flex-grow:1; position:relative;"><canvas id="mainChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-globe"></i> TOP COUNTRIES</h3>
                <div id="country_list"></div>
                <div style="height:150px; margin-top:auto;"><canvas id="geoChart"></canvas></div>
            </div>
        </div>

        <h3 class="status-summary-title"><i class="fas fa-list-ul"></i> ORDER STATUS SUMMARY</h3>
        <div class="status-grid" id="status_grid">
             <!-- Status cards will be injected here -->
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentRange = 'month';
        let mainChart = null;
        let geoChart = null;

        function fetchStats(range) {
            currentRange = range;
            const btn = document.getElementById('refresh-cockpit');
            btn.classList.add('loading');
            
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b.dataset.range === range));
            ['val_orders', 'val_rev', 'val_avg', 'val_members'].forEach(id => {
                document.getElementById(id).innerHTML = '<div class="loading-shimmer"></div>';
            });
            document.getElementById('status_grid').innerHTML = '<div class="loading-shimmer" style="grid-column: 1/6; width:100%"></div>';

            jQuery.post(ajaxurl, { action: 'xep_cockpit_get_data', range: range }, function(res) {
                if(res.success) {
                    const d = res.data;
                    document.getElementById('label_orders').innerText = d.range_label;
                    document.getElementById('label_rev').innerText = d.range_label;
                    document.getElementById('val_orders').innerText = d.period_orders;
                    document.getElementById('val_rev').innerText = d.period_rev;
                    document.getElementById('val_avg').innerText = d.avg_order;
                    document.getElementById('val_members').innerText = d.total_members;

                    // Status Grid
                    let sHtml = '';
                    const stMap = { 'completed':'COMPLETED', 'processing':'PROCESSING', 'on-hold':'ON HOLD', 'cancelled':'CANCELLED', 'failed':'FAILED' };
                    Object.keys(stMap).forEach(key => {
                        const count = d.status_counts[key] || 0;
                        const css = key === 'on-hold' ? 'on-hold' : key;
                        sHtml += `<div class="status-card ${stMap[key] ? css : 'other'}">
                                    <div class="status-name">${stMap[key] || key}</div>
                                    <div class="status-count">${count}</div>
                                  </div>`;
                    });
                    document.getElementById('status_grid').innerHTML = sHtml;

                    // Countries list
                    let cHtml = '';
                    d.countries.forEach(c => {
                        cHtml += `<div class="country-item">
                                    <span class="country-name"><img src="https://flagcdn.com/w20/${c.code}.png" style="border-radius:2px"> ${c.name}</span>
                                    <span class="country-count">${c.count} SALES</span>
                                 </div>`;
                    });
                    document.getElementById('country_list').innerHTML = cHtml || '<div style="opacity:0.4;text-align:center;padding:20px">NO GEOGRAPHIC DATA</div>';
                    
                    renderCharts(d);
                }
                btn.classList.remove('loading');
            });
        }

        function renderCharts(d) {
            const primary = '<?php echo $primary_color; ?>';
            if(mainChart) mainChart.destroy();
            mainChart = new Chart(document.getElementById('mainChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: d.chart_labels,
                    datasets: [{
                        data: d.chart_values, borderColor: primary, borderWidth: 4,
                        backgroundColor: 'rgba(0, 242, 255, 0.05)', fill: true, tension: 0.45, pointRadius: 0
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { legend: { display: false } },
                    scales: { y: { grid: { color: 'rgba(255,255,255,0.02)' }, ticks: { color: '#444' } }, x: { grid: { display: false }, ticks: { color: '#444' } } }
                }
            });

            if(geoChart) geoChart.destroy();
            geoChart = new Chart(document.getElementById('geoChart'), {
                type: 'doughnut',
                data: {
                    labels: d.countries.map(c => c.name),
                    datasets: [{
                        data: d.countries.map(c => c.count),
                        backgroundColor: [primary, '#7000ff', '#ff007a', '#f0ff00', '#00ff73'],
                        borderWidth: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '85%' }
            });
        }

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => fetchStats(btn.dataset.range));
        });
        document.getElementById('refresh-cockpit').addEventListener('click', () => fetchStats(currentRange));
        fetchStats('month');
    });
    </script>
    <?php
}
