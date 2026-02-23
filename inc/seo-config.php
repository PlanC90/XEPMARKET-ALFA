<?php
/**
 * XepMarket Elite SEO & AI SEO Configuration
 * 
 * Fully customizable from admin panel. All texts are editable.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// DEFAULT VALUES — Users can override all of these from admin panel
// ═══════════════════════════════════════════════════════════════════
function xepmarket2_seo_defaults()
{
    return [
        'title_suffix'   => ' | XEPMarket — World\'s First Blockchain E-Commerce Store',
        'description'    => 'XEPMarket is the world\'s first blockchain e-commerce store. Pay with XEP and MMX (MemexAI) tokens. Open-source theme and payment module built by the MemexAI team to demonstrate the real potential of blockchain technology. Be your own boss — receive payments directly without intermediaries.',
        'keywords'       => 'XEPMarket, XEP, MMX, MemexAI, blockchain e-commerce, cryptocurrency payments, decentralized store, open source ecommerce, Web3 shopping, Electra Protocol, crypto marketplace, blockchain store, be your own boss',
        'business_name'  => 'XEPMarket',
        'slogan'         => 'Be your own boss — receive payments directly without intermediaries',
        'about_text'     => 'XEPMarket is the world\'s first blockchain e-commerce store, accepting XEP and MMX (MemexAI) tokens. Open-source theme and payment module built by the MemexAI team to showcase the real potential of blockchain technology.',
        'founder_name'   => 'MemexAI',
        'founder_url'    => 'https://memexai.com',
        'payment_methods' => 'XEP Token (Electra Protocol), MMX Token (MemexAI)',
        'ai_topics'      => 'Blockchain E-Commerce, Cryptocurrency Payments, Decentralized Shopping, XEP Token, MMX Token, MemexAI, Open Source E-Commerce, Web3 Retail',
    ];
}

// Helper to get an SEO option with fallback to defaults
function xepmarket2_seo_get($key, $option_name = '')
{
    $defaults = xepmarket2_seo_defaults();
    $default = $defaults[$key] ?? '';
    if (!$option_name) {
        $option_name = 'xepmarket2_seo_' . $key;
    }
    return get_option($option_name, $default);
}

/**
 * Register SEO Settings
 */
function xepmarket2_register_seo_settings()
{
    $settings = [
        'xepmarket2_seo_title_suffix',
        'xepmarket2_seo_description',
        'xepmarket2_seo_keywords',
        'xepmarket2_seo_og_image',
        'xepmarket2_seo_google_verify',
        'xepmarket2_seo_analytics_id',
        'xepmarket2_seo_ai_business_name',
        'xepmarket2_seo_ai_logo_url',
        'xepmarket2_seo_ai_crawler_allow',
        // New fields
        'xepmarket2_seo_slogan',
        'xepmarket2_seo_about_text',
        'xepmarket2_seo_founder_name',
        'xepmarket2_seo_founder_url',
        'xepmarket2_seo_payment_methods',
        'xepmarket2_seo_ai_topics',
    ];

    foreach ($settings as $setting) {
        register_setting('xepmarket2_settings_group', $setting);
    }
}
add_action('admin_init', 'xepmarket2_register_seo_settings');

/**
 * ═══════════════════════════════════════════════════════════════════
 * SEO ADMIN TAB CONTENT
 * ═══════════════════════════════════════════════════════════════════
 */
function xepmarket2_seo_settings_tab_content()
{
    $d = xepmarket2_seo_defaults();
    ?>
    <div id="tab-seo" class="xep-tab-content">

        <!-- SECTION 1: Basic SEO -->
        <div class="xep-section-card">
            <h3><i class="fas fa-search"></i> Search Engine Optimization</h3>
            <p class="description">Configure how your site appears in Google, Bing and other search engines.</p>

            <div class="xep-form-group">
                <label>Site Title Suffix</label>
                <input type="text" name="xepmarket2_seo_title_suffix"
                    value="<?php echo esc_attr(get_option('xepmarket2_seo_title_suffix', $d['title_suffix'])); ?>"
                    placeholder="e.g. | My Blockchain Store">
                <p class="description">Appended to every page title. Example: "Shop <strong><?php echo esc_html(get_option('xepmarket2_seo_title_suffix', $d['title_suffix'])); ?></strong>"</p>
            </div>

            <div class="xep-form-group">
                <label>Global Meta Description</label>
                <textarea name="xepmarket2_seo_description"
                    rows="3" style="min-height: 80px;"><?php echo esc_textarea(get_option('xepmarket2_seo_description', $d['description'])); ?></textarea>
                <p class="description">Default description shown in search results. 150-160 characters recommended.</p>
            </div>

            <div class="xep-form-group">
                <label>Keywords (Comma separated)</label>
                <input type="text" name="xepmarket2_seo_keywords"
                    value="<?php echo esc_attr(get_option('xepmarket2_seo_keywords', $d['keywords'])); ?>">
            </div>
        </div>

        <!-- SECTION 2: Social & Analytics -->
        <div class="xep-section-card">
            <h3><i class="fas fa-share-alt"></i> Social & Analytics</h3>
            <div class="xep-form-group">
                <label>Social Share Image (OG Image)</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="xepmarket2_seo_og_image" id="xep_seo_og_image"
                        value="<?php echo esc_url(get_option('xepmarket2_seo_og_image')); ?>"
                        placeholder="https://yoursite.com/share-image.jpg">
                    <button type="button" class="xep-browse-btn" data-target="xep_seo_og_image"
                        style="padding: 10px 20px; font-size: 13px; width: auto; background: var(--admin-surface); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; cursor: pointer;">Browse</button>
                </div>
                <p class="description">Image shown when your link is shared on social media (1200×630px recommended).</p>
            </div>

            <div class="xep-grid-2">
                <div class="xep-form-group">
                    <label>Google Console Verification</label>
                    <input type="text" name="xepmarket2_seo_google_verify"
                        value="<?php echo esc_attr(get_option('xepmarket2_seo_google_verify')); ?>"
                        placeholder="Verification code">
                </div>
                <div class="xep-form-group">
                    <label>Google Analytics ID</label>
                    <input type="text" name="xepmarket2_seo_analytics_id"
                        value="<?php echo esc_attr(get_option('xepmarket2_seo_analytics_id')); ?>" placeholder="GT-XXXXXX">
                </div>
            </div>
        </div>

        <!-- SECTION 3: AI & Structured Data -->
        <div class="xep-section-card">
            <h3><i class="fas fa-robot"></i> AI SEO & Structured Data</h3>
            <p class="description">Configure how AI assistants (ChatGPT, Gemini, Perplexity, Copilot) understand and present your store. All fields below are used in JSON-LD schema, FAQ schema, and llms.txt file.</p>

            <div class="xep-grid-2">
                <div class="xep-form-group">
                    <label>Business Name</label>
                    <input type="text" name="xepmarket2_seo_ai_business_name"
                        value="<?php echo esc_attr(get_option('xepmarket2_seo_ai_business_name', $d['business_name'])); ?>">
                </div>
                <div class="xep-form-group">
                    <label>Slogan / Tagline</label>
                    <input type="text" name="xepmarket2_seo_slogan"
                        value="<?php echo esc_attr(get_option('xepmarket2_seo_slogan', $d['slogan'])); ?>"
                        placeholder="Your store's main slogan">
                </div>
            </div>

            <div class="xep-form-group">
                <label>About Your Store (AI Description)</label>
                <textarea name="xepmarket2_seo_about_text" rows="3"
                    style="min-height: 80px;"><?php echo esc_textarea(get_option('xepmarket2_seo_about_text', $d['about_text'])); ?></textarea>
                <p class="description">This text is used in JSON-LD Organization schema, AI content blocks, and llms.txt. Write a clear, factual description of your store for AI models.</p>
            </div>

            <div class="xep-grid-2">
                <div class="xep-form-group">
                    <label>Founded By (Team/Company)</label>
                    <input type="text" name="xepmarket2_seo_founder_name"
                        value="<?php echo esc_attr(get_option('xepmarket2_seo_founder_name', $d['founder_name'])); ?>">
                </div>
                <div class="xep-form-group">
                    <label>Founder Website URL</label>
                    <input type="text" name="xepmarket2_seo_founder_url"
                        value="<?php echo esc_attr(get_option('xepmarket2_seo_founder_url', $d['founder_url'])); ?>"
                        placeholder="https://...">
                </div>
            </div>

            <div class="xep-form-group">
                <label>Accepted Payment Methods</label>
                <input type="text" name="xepmarket2_seo_payment_methods"
                    value="<?php echo esc_attr(get_option('xepmarket2_seo_payment_methods', $d['payment_methods'])); ?>">
                <p class="description">Comma separated. These appear in product schema and store schema.</p>
            </div>

            <div class="xep-form-group">
                <label>AI Knowledge Topics (comma separated)</label>
                <input type="text" name="xepmarket2_seo_ai_topics"
                    value="<?php echo esc_attr(get_option('xepmarket2_seo_ai_topics', $d['ai_topics'])); ?>">
                <p class="description">Topics your store is known for. Used in <code>knowsAbout</code> schema.</p>
            </div>

            <div class="xep-form-group">
                <label>Organization Logo URL</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="xepmarket2_seo_ai_logo_url" id="xep_seo_ai_logo_url"
                        value="<?php echo esc_url(get_option('xepmarket2_seo_ai_logo_url')); ?>"
                        placeholder="https://yoursite.com/logo.png">
                    <button type="button" class="xep-browse-btn" data-target="xep_seo_ai_logo_url"
                        style="padding: 10px 20px; font-size: 13px; width: auto; background: var(--admin-surface); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; cursor: pointer;">Browse</button>
                </div>
            </div>

            <div class="xep-form-group">
                <label>AI Crawler Access</label>
                <select name="xepmarket2_seo_ai_crawler_allow">
                    <option value="allow" <?php selected(get_option('xepmarket2_seo_ai_crawler_allow', 'allow'), 'allow'); ?>>Allow AI Indexing (Recommended)</option>
                    <option value="disallow" <?php selected(get_option('xepmarket2_seo_ai_crawler_allow', 'allow'), 'disallow'); ?>>Restrict AI Crawling</option>
                </select>
            </div>
        </div>

    </div>
    <?php
}

/**
 * ═══════════════════════════════════════════════════════════════════
 * OUTPUT SEO TAGS IN HEAD — All values from admin settings
 * ═══════════════════════════════════════════════════════════════════
 */
function xepmarket2_output_seo_tags()
{
    $d = xepmarket2_seo_defaults();

    // Read all settings from admin
    $suffix          = get_option('xepmarket2_seo_title_suffix', $d['title_suffix']);
    $global_desc     = get_option('xepmarket2_seo_description', $d['description']);
    $keywords        = get_option('xepmarket2_seo_keywords', $d['keywords']);
    $og_image        = get_option('xepmarket2_seo_og_image');
    $google_verify   = get_option('xepmarket2_seo_google_verify');
    $ga_id           = get_option('xepmarket2_seo_analytics_id');
    $biz_name        = get_option('xepmarket2_seo_ai_business_name', $d['business_name']);
    $biz_logo        = get_option('xepmarket2_seo_ai_logo_url');
    $ai_allow        = get_option('xepmarket2_seo_ai_crawler_allow', 'allow');
    $slogan          = get_option('xepmarket2_seo_slogan', $d['slogan']);
    $about_text      = get_option('xepmarket2_seo_about_text', $d['about_text']);
    $founder_name    = get_option('xepmarket2_seo_founder_name', $d['founder_name']);
    $founder_url     = get_option('xepmarket2_seo_founder_url', $d['founder_url']);
    $payment_methods = get_option('xepmarket2_seo_payment_methods', $d['payment_methods']);
    $ai_topics       = get_option('xepmarket2_seo_ai_topics', $d['ai_topics']);
    $site_url        = home_url();

    // Build page-specific description
    $page_desc = $global_desc;

    if (is_singular('product') && function_exists('wc_get_product')) {
        global $post;
        $product = wc_get_product($post->ID);
        if ($product) {
            $page_desc = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());
            $page_desc = mb_substr($page_desc, 0, 160);
            if ($product_img = wp_get_attachment_url($product->get_image_id())) {
                $og_image = $og_image ?: $product_img;
            }
        }
    } elseif (is_product_category()) {
        $term = get_queried_object();
        if ($term) {
            $page_desc = $term->description ?: 'Shop ' . $term->name . ' on ' . $biz_name . '. ' . $slogan;
        }
    } elseif (function_exists('is_shop') && is_shop()) {
        $page_desc = 'Browse all products on ' . $biz_name . '. ' . $slogan;
    }

    // Filter Page Title
    add_filter('pre_get_document_title', function ($title) use ($suffix) {
        if (is_front_page()) {
            return get_bloginfo('name') . $suffix;
        }
        return $title . $suffix;
    }, 999);

    // ─── STANDARD META TAGS ───────────────────────────────────────
    echo "\n<!-- " . esc_html($biz_name) . " SEO & AI Optimizer -->\n";
    echo '<meta name="description" content="' . esc_attr($page_desc) . '">' . "\n";
    echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
    if ($founder_name) {
        echo '<meta name="author" content="' . esc_attr($founder_name) . ' Team">' . "\n";
    }
    echo '<link rel="canonical" href="' . esc_url(xepmarket2_get_canonical_url()) . '">' . "\n";

    if ($google_verify) {
        echo '<meta name="google-site-verification" content="' . esc_attr($google_verify) . '">' . "\n";
    }

    // ─── OPEN GRAPH ───────────────────────────────────────────────
    $page_title = wp_get_document_title();
    echo '<meta property="og:site_name" content="' . esc_attr($biz_name) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($page_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($page_desc) . '">' . "\n";
    echo '<meta property="og:type" content="' . (is_singular('product') ? 'product' : 'website') . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url(xepmarket2_get_canonical_url()) . '">' . "\n";
    echo '<meta property="og:locale" content="en_US">' . "\n";
    if ($og_image) {
        echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        echo '<meta property="og:image:width" content="1200">' . "\n";
        echo '<meta property="og:image:height" content="630">' . "\n";
    }

    // Product-specific OG
    if (is_singular('product') && function_exists('wc_get_product')) {
        global $post;
        $product = wc_get_product($post->ID);
        if ($product) {
            echo '<meta property="product:price:amount" content="' . esc_attr($product->get_price()) . '">' . "\n";
            echo '<meta property="product:price:currency" content="' . esc_attr(get_woocommerce_currency()) . '">' . "\n";
            echo '<meta property="product:availability" content="' . ($product->is_in_stock() ? 'in stock' : 'out of stock') . '">' . "\n";
        }
    }

    // ─── TWITTER CARD ──────────────────────────────────────────────
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($page_title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($page_desc) . '">' . "\n";
    if ($og_image) {
        echo '<meta name="twitter:image" content="' . esc_url($og_image) . '">' . "\n";
    }

    // ─── GOOGLE ANALYTICS ──────────────────────────────────────────
    if ($ga_id) {
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '<?php echo esc_attr($ga_id); ?>');
        </script>
        <?php
    }

    // ═══════════════════════════════════════════════════════════════
    // JSON-LD STRUCTURED DATA — All from admin settings
    // ═══════════════════════════════════════════════════════════════

    $social_links = array_values(array_filter([
        get_option('xepmarket2_social_twitter'),
        get_option('xepmarket2_social_instagram'),
        get_option('xepmarket2_social_telegram'),
        get_option('xepmarket2_social_discord'),
        get_option('xepmarket2_social_youtube'),
        get_option('xepmarket2_social_tiktok'),
    ]));

    $topics_array = array_map('trim', explode(',', $ai_topics));

    // 1) Organization
    $org_schema = [
        "@context" => "https://schema.org",
        "@type" => "Organization",
        "@id" => $site_url . "/#organization",
        "name" => $biz_name,
        "url" => $site_url,
        "description" => $about_text,
        "slogan" => $slogan,
        "knowsAbout" => $topics_array,
    ];
    if ($founder_name) {
        $org_schema["founder"] = [
            "@type" => "Organization",
            "name" => $founder_name,
        ];
        if ($founder_url) {
            $org_schema["founder"]["url"] = $founder_url;
        }
    }
    if ($biz_logo) {
        $org_schema["logo"] = ["@type" => "ImageObject", "url" => $biz_logo];
        $org_schema["image"] = $biz_logo;
    }
    if (!empty($social_links)) {
        $org_schema["sameAs"] = $social_links;
    }
    echo '<script type="application/ld+json">' . json_encode($org_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

    // 2) WebSite with SearchAction
    $website_schema = [
        "@context" => "https://schema.org",
        "@type" => "WebSite",
        "@id" => $site_url . "/#website",
        "name" => $biz_name,
        "url" => $site_url,
        "description" => $global_desc,
        "publisher" => ["@id" => $site_url . "/#organization"],
        "potentialAction" => [
            "@type" => "SearchAction",
            "target" => ["@type" => "EntryPoint", "urlTemplate" => $site_url . "/?s={search_term_string}&post_type=product"],
            "query-input" => "required name=search_term_string"
        ],
        "inLanguage" => "en-US"
    ];
    echo '<script type="application/ld+json">' . json_encode($website_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

    // 3) OnlineStore
    $payment_list = array_map('trim', explode(',', $payment_methods));
    $store_schema = [
        "@context" => "https://schema.org",
        "@type" => "OnlineStore",
        "@id" => $site_url . "/#store",
        "name" => $biz_name,
        "url" => $site_url,
        "description" => $about_text,
        "paymentAccepted" => implode(', ', $payment_list),
        "priceRange" => "$",
    ];
    if ($biz_logo) {
        $store_schema["logo"] = $biz_logo;
    }
    echo '<script type="application/ld+json">' . json_encode($store_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

    // 4) Product Schema (WooCommerce single product)
    if (is_singular('product') && function_exists('wc_get_product')) {
        global $post;
        $product = wc_get_product($post->ID);
        if ($product) {
            $pay_schema = [];
            foreach ($payment_list as $pm) {
                $pay_schema[] = ["@type" => "PaymentMethod", "name" => $pm];
            }
            $product_schema = [
                "@context" => "https://schema.org",
                "@type" => "Product",
                "name" => $product->get_name(),
                "description" => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
                "url" => get_permalink($post->ID),
                "sku" => $product->get_sku() ?: $post->ID,
                "brand" => ["@type" => "Brand", "name" => $biz_name],
                "offers" => [
                    "@type" => "Offer",
                    "url" => get_permalink($post->ID),
                    "priceCurrency" => get_woocommerce_currency(),
                    "price" => $product->get_price(),
                    "availability" => $product->is_in_stock() ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
                    "seller" => ["@id" => $site_url . "/#organization"],
                    "acceptedPaymentMethod" => $pay_schema,
                ]
            ];
            if ($img_id = $product->get_image_id()) {
                $product_schema["image"] = wp_get_attachment_url($img_id);
            }
            if ($product->get_review_count() > 0) {
                $product_schema["aggregateRating"] = [
                    "@type" => "AggregateRating",
                    "ratingValue" => $product->get_average_rating(),
                    "reviewCount" => $product->get_review_count()
                ];
            }
            echo '<script type="application/ld+json">' . json_encode($product_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        }
    }

    // 5) Breadcrumbs
    $breadcrumbs = xepmarket2_build_breadcrumbs();
    if (!empty($breadcrumbs)) {
        echo '<script type="application/ld+json">' . json_encode(["@context" => "https://schema.org", "@type" => "BreadcrumbList", "itemListElement" => $breadcrumbs], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    // ─── AI BOT CONTROL ────────────────────────────────────────────
    if ($ai_allow === 'disallow') {
        echo '<meta name="robots" content="noai, noimageai">' . "\n";
        echo '<meta name="GPTBot" content="none">' . "\n";
    } else {
        echo '<meta name="ai-content" content="index, follow">' . "\n";
        echo '<meta name="GPTBot" content="index, follow">' . "\n";
        echo '<meta name="PerplexityBot" content="index, follow">' . "\n";
        echo '<meta name="Google-Extended" content="index, follow">' . "\n";
        echo '<link rel="alternate" type="text/plain" title="LLM-friendly content" href="' . esc_url(home_url('llms.txt')) . '">' . "\n";
    }

    echo "<!-- End " . esc_html($biz_name) . " SEO Optimizer -->\n\n";
}
add_action('wp_head', 'xepmarket2_output_seo_tags', 1);

/**
 * ═══════════════════════════════════════════════════════════════════
 * AI SEMANTIC CONTENT (Homepage only, hidden from users)
 * All text pulled from admin settings
 * ═══════════════════════════════════════════════════════════════════
 */
function xepmarket2_ai_semantic_content()
{
    if (!is_front_page()) return;

    $d = xepmarket2_seo_defaults();
    $biz_name        = get_option('xepmarket2_seo_ai_business_name', $d['business_name']);
    $about_text      = get_option('xepmarket2_seo_about_text', $d['about_text']);
    $slogan          = get_option('xepmarket2_seo_slogan', $d['slogan']);
    $founder_name    = get_option('xepmarket2_seo_founder_name', $d['founder_name']);
    $payment_methods = get_option('xepmarket2_seo_payment_methods', $d['payment_methods']);
    $keywords        = get_option('xepmarket2_seo_keywords', $d['keywords']);
    ?>
    <div class="sr-only" aria-hidden="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">
        <article itemscope itemtype="https://schema.org/Article">
            <h2 itemprop="headline">About <?php echo esc_html($biz_name); ?></h2>
            <div itemprop="articleBody">
                <p><?php echo esc_html($about_text); ?></p>
                <p>Vision: <?php echo esc_html($slogan); ?></p>
                <p>Accepted payment methods: <?php echo esc_html($payment_methods); ?></p>
            </div>
            <span itemprop="author" itemscope itemtype="https://schema.org/Organization">
                <meta itemprop="name" content="<?php echo esc_attr($founder_name); ?>">
            </span>
            <meta itemprop="keywords" content="<?php echo esc_attr($keywords); ?>">
        </article>
        <div itemscope itemtype="https://schema.org/FAQPage">
            <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                <h3 itemprop="name">What is <?php echo esc_html($biz_name); ?>?</h3>
                <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p itemprop="text"><?php echo esc_html($about_text); ?></p>
                </div>
            </div>
            <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                <h3 itemprop="name">What payment methods does <?php echo esc_html($biz_name); ?> accept?</h3>
                <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p itemprop="text"><?php echo esc_html($biz_name); ?> accepts: <?php echo esc_html($payment_methods); ?>. All transactions are verified directly on the blockchain.</p>
                </div>
            </div>
            <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                <h3 itemprop="name">Who created <?php echo esc_html($biz_name); ?>?</h3>
                <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <p itemprop="text"><?php echo esc_html($biz_name); ?> was created by <?php echo esc_html($founder_name); ?>. <?php echo esc_html($slogan); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'xepmarket2_ai_semantic_content', 5);

/**
 * ═══════════════════════════════════════════════════════════════════
 * HELPER FUNCTIONS
 * ═══════════════════════════════════════════════════════════════════
 */
function xepmarket2_get_canonical_url()
{
    if (is_front_page()) return home_url('/');
    if (is_singular()) return get_permalink();
    if (is_tax() || is_category() || is_tag()) return get_term_link(get_queried_object());
    if (function_exists('is_shop') && is_shop()) return get_permalink(wc_get_page_id('shop'));
    return home_url(add_query_arg([], $GLOBALS['wp']->request));
}

function xepmarket2_build_breadcrumbs()
{
    $items = [];
    $pos = 1;
    $items[] = ["@type" => "ListItem", "position" => $pos++, "name" => "Home", "item" => home_url('/')];

    if (function_exists('is_shop') && is_shop()) {
        $items[] = ["@type" => "ListItem", "position" => $pos++, "name" => "Shop", "item" => get_permalink(wc_get_page_id('shop'))];
    } elseif (is_product_category()) {
        $items[] = ["@type" => "ListItem", "position" => $pos++, "name" => "Shop", "item" => get_permalink(wc_get_page_id('shop'))];
        $term = get_queried_object();
        if ($term) $items[] = ["@type" => "ListItem", "position" => $pos++, "name" => $term->name, "item" => get_term_link($term)];
    } elseif (is_singular('product')) {
        global $post;
        $items[] = ["@type" => "ListItem", "position" => $pos++, "name" => "Shop", "item" => get_permalink(wc_get_page_id('shop'))];
        $terms = get_the_terms($post->ID, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $items[] = ["@type" => "ListItem", "position" => $pos++, "name" => $terms[0]->name, "item" => get_term_link($terms[0])];
        }
        $items[] = ["@type" => "ListItem", "position" => $pos++, "name" => get_the_title(), "item" => get_permalink()];
    }
    return count($items) > 1 ? $items : [];
}

/**
 * SECURITY: Disable Users Sitemap
 */
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    return $name === 'users' ? false : $provider;
}, 10, 2);

/**
 * AI Bot permissions in robots.txt
 */
function xepmarket2_ai_robots_txt($output, $public)
{
    if (get_option('xepmarket2_seo_ai_crawler_allow', 'allow') === 'allow') {
        $output .= "\n# AI SEO Optimizations\n";
        foreach (['GPTBot', 'ChatGPT-User', 'Google-Extended', 'Claude-Web', 'OAI-SearchBot', 'PerplexityBot', 'Applebot-Extended', 'cohere-ai'] as $bot) {
            $output .= "User-agent: {$bot}\nAllow: /\n\n";
        }
    }
    return $output;
}
add_filter('robots_txt', 'xepmarket2_ai_robots_txt', 100, 2);

/**
 * llms.txt — AI-friendly text file (all from admin settings)
 */
function xepmarket2_llms_txt()
{
    if (!isset($_SERVER['REQUEST_URI'])) return;
    $req = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($req !== 'llms.txt') return;

    $d = xepmarket2_seo_defaults();
    $biz_name        = get_option('xepmarket2_seo_ai_business_name', $d['business_name']);
    $about_text      = get_option('xepmarket2_seo_about_text', $d['about_text']);
    $slogan          = get_option('xepmarket2_seo_slogan', $d['slogan']);
    $founder_name    = get_option('xepmarket2_seo_founder_name', $d['founder_name']);
    $payment_methods = get_option('xepmarket2_seo_payment_methods', $d['payment_methods']);

    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: index, follow');

    echo "# {$biz_name}\n\n";
    echo "## About\n{$about_text}\n\n";
    echo "## Vision\n{$slogan}\n\n";
    echo "## Key Facts\n";
    echo "- Founded by: {$founder_name}\n";
    echo "- Accepted payments: {$payment_methods}\n";
    echo "- Website: " . home_url() . "\n";
    echo "- Shop: " . home_url('/shop/') . "\n\n";

    echo "## Social Links\n";
    $social = ['Telegram' => 'xepmarket2_social_telegram', 'Twitter/X' => 'xepmarket2_social_twitter', 'Discord' => 'xepmarket2_social_discord', 'Instagram' => 'xepmarket2_social_instagram', 'YouTube' => 'xepmarket2_social_youtube'];
    foreach ($social as $name => $key) {
        $url = get_option($key);
        if ($url) echo "- {$name}: {$url}\n";
    }

    exit;
}
add_action('template_redirect', 'xepmarket2_llms_txt', 1);
