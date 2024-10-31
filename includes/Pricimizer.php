<?php

class Pricimizer
{
    private static $option = [];

    public function __construct()
    {
        // Get API key alert
        add_action('admin_notices', [$this, 'emptyApiKeyAlert']);

        // Sidebar menu
        add_action('admin_menu', [$this, 'buildSidebarMenu']);

        // Assets
        add_action('admin_enqueue_scripts', [$this, 'loadAdminAssets']);

        // Global settings ajax
        add_action("wp_ajax_pricimizer_setting_update", [$this, "updateGlobalSettings"]);
        add_action("wp_ajax_nopriv_pricimizer_setting_update", [$this, "updateGlobalSettings"]);
        add_action("wp_ajax_pricimizer_turn_on_off", [$this, "turnOnOff"]);

        // Display Fields
        add_action('woocommerce_product_options_general_product_data', [$this, 'showProductCustomFields']);
        add_filter('manage_product_posts_columns', [$this, 'removeDefaultPriceFromProductList'], 20);
        add_action('manage_product_posts_custom_column', [$this, 'productListCustomColumn'], 10, 2);

        // Save Fields
        add_action('woocommerce_process_product_meta', [$this, 'updateProductPricingFields']);

        // Pricing
        add_action('woocommerce_before_single_product_summary', [$this, 'singleProduct']);
        add_filter('woocommerce_get_price_html', [$this, 'getProductPriceHtml'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'customCartTotalPrice'], 10);
        add_action('woocommerce_update_product', [Pricimizer_Cache::class, 'clearByProductId'], 10);
        add_action('woocommerce_before_shop_loop', [$this, 'shopProductsLoop'], 10);
        add_action('woocommerce_related_products', [$this, 'relatedProductsLoop'], 10, 1);

        add_filter('plugin_action_links_pricimizer/pricimizer.php', [$this, 'pluginsListLinks']);
        add_filter('woocommerce_thankyou', [$this, 'purchased'], 1);
    }

    /**
     * @return void
     */
    public function emptyApiKeyAlert()
    {
        $globalSettings = $this->getOption('pricimizer_global_settings');

        //Woocommerce not installed
        if (!$this->isWoocommerceActive()) {
            echo '<div class="notice notice-error is-dismissible">
                    <p>Price optimizer needs Woocommerce plugin to be active.</p>
                </div>';
            return;
        }

        // Empty api key
        if (empty($globalSettings['api_key'])) {
            $this->autoRegister();
            $globalSettings = get_option('pricimizer_global_settings'); //query db again to see if it was set by autoregister (So don't use getOption() method)
            if (empty($globalSettings['api_key'])) {
                echo '<div class="notice notice-error is-dismissible">
                    <p>You have set no API key for Pricimizer. Please set your key in <a href="' . admin_url() . 'admin.php?page=' . PRICIMIZER_PLUGIN_NAME . '">Pricimizer settings page</a>.</p>
                </div>';
                return;
            }
        }

        // Invalid api key
        if (isset($globalSettings['api_key_validity']) && $globalSettings['api_key_validity'] == 'invalid') {
            echo '<div class="notice notice-error is-dismissible">
                    <p>Please set a valid API key in <a href="' . admin_url() . 'admin.php?page=' . PRICIMIZER_PLUGIN_NAME . '">Pricimizer settings</a>.</p>
                </div>';
            return;
        }

        // No subscription and no trial
        if (isset($globalSettings['no_subscription']) && $globalSettings['no_subscription']) {
            echo '<div class="notice notice-error is-dismissible">
                    <p>You don\'t have any active subscription for <b>Price Optimization</b>. Please <a target="_blank" href="'.(isset($globalSettings['signed_url']) ? $globalSettings['signed_url'] : 'https://pricimizer.com/pricing').'">Activate a subscription</a>, otherwise random prices are used.</p>
                </div>';
            return;
        }

        // Trial is ending
        if (isset($globalSettings['trial_ends_in'])) {
            echo '<div class="notice notice-error is-dismissible">
                    <p>You have only <b>'.esc_html($globalSettings['trial_ends_in']).' trial days</b> left. If you haven\'t subscribed yet, please <a target="_blank" href="'.(isset($globalSettings['signed_url']) ? $globalSettings['signed_url'] : 'https://pricimizer.com/pricing').'">Activate a subscription</a> before the trial ends.</p>
                </div>';
            return;
        }

        // Plugin is off
        if (
            (empty($globalSettings['state']) || $globalSettings['state'] != 1) &&
            (empty($_GET['page']) || (!empty($_GET['page']) && $_GET['page'] != 'Pricimizer'))
        ) {
            echo '<div class="notice error is-dismissible">
                    <p><a href="' . admin_url() . 'admin.php?page=' . PRICIMIZER_PLUGIN_NAME . '">Price optimizer</a> is off. To maximize your profit, turn it on:
                    <div class="onoffswitch">
                        <input type="checkbox" name="state" class="onoffswitch-checkbox" id="header-onoff-pricimizer" value="1">
                        <label class="onoffswitch-label" for="header-onoff-pricimizer">
                            <span class="onoffswitch-inner"></span>
                            <span class="onoffswitch-switch"></span>
                        </label>
                    </div>
                    </p>
                </div>';
            return;
        }
    }

    public function autoRegister()
    {
        $args = [
            'body' => [],
            'timeout'     => '15',
            'redirection' => '5',
            'blocking'    => true,
            'sslverify' => false,
            'headers'     => [
                'Accept' => 'application/json',
                'Referer' => home_url()
            ],
        ];
        $response = wp_remote_post('https://pricimizer.com/api/v1/register', $args);
        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);

        if (isset($response['success']) && $response['success']) {
            // successful
            if (!empty($response['data']['key'])) {
                update_option('pricimizer_global_settings', [
                    'api_key_validity' => 'valid',
                    'api_key' => $response['data']['key'],
                    'optimize_by' => [],
                ]);
            }
        }
    }

    public function getOptimizeByKey($by = null)
    {
        switch ($by) {
            case 'country':
                $result = Pricimizer_Helper::getLocationInfo(Pricimizer_Helper::getUserIP(), 'country');
                break;
            case 'os':
                $result = Pricimizer_Helper::detectOs();
                break;
            case 'weekday':
                $result = date('l');
                break;
            case 'month':
                $result = date('F');
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * Set dynamic price for them if they don't have
     * @return void
     */
    public function shopProductsLoop()
    {
        global $wp_query;

        if (!$this->enqueueScriptIfValidApiKey()) {
            return;
        }

        $variations = [];
        while ($wp_query->have_posts()) : the_post();
            $productId = get_the_ID();
            $meta = get_post_meta($productId, 'pricimizer_meta', true);
            if (empty($meta) || $meta['pricing_type'] == 'fixed_price') {
                // Ignore fixed price products
                continue;
            }

            $variations[] = $this->getVariation($productId);
        endwhile;

        // Get cached products
        $productIds = array_column($variations, 'id');
        $data = $this->getCachedData($productIds);

        $cachedProductIds = array_column($data, 'product_id');
        $differences = array_diff($productIds, $cachedProductIds);

        // If some products are not in cache, request api and cache prices
        if (empty($data) || !empty($differences)) {
            // Send only not cached product ids to get their price
            $diffVariations = [];
            foreach ($variations as $variation) {
                if (isset($variation['id']) && in_array($variation['id'], $differences)) {
                    $diffVariations[] = $variation;
                }
            }
            $this->requestApi($diffVariations);
        }
    }

    /**
     * Get prices from API if they are dynamic and didn't receive any price yet
     * @param $relatedProducts
     * @return mixed
     */
    public function relatedProductsLoop($relatedProducts)
    {
        if (!$this->enqueueScriptIfValidApiKey()) {
            return $relatedProducts;
        }

        $variations = [];
        $ids = array_column(array_filter(array_map('wc_get_product', $relatedProducts), 'wc_products_array_filter_visible'), 'id');

        foreach ($ids as $relatedProductId) {
            $meta = get_post_meta($relatedProductId, 'pricimizer_meta', true);
            if (empty($meta) || $meta['pricing_type'] == 'fixed_price') {
                // Ignore fixed price products
                continue;
            }
            $variations[] = $this->getVariation($relatedProductId);
        }

        // Get cached products
        $productIds = array_column($variations, 'id');
        $data = $this->getCachedData($productIds);

        $cachedProductIds = array_column($data, 'product_id');
        $differences = array_diff($productIds, $cachedProductIds);

        // If some products are not in cache, request api and cache prices
        if (empty($data) || !empty($differences)) {
            // Send only not cached product ids to get their price
            $diffVariations = [];
            foreach ($variations as $variation) {
                if (in_array($variation['id'], $differences)) {
                    $diffVariations[] = $variation;
                }
            }
            $this->requestApi($diffVariations);
        }

        return $relatedProducts;
    }

    /**
     * @param $productId
     * @return void
     */
    public function updateProductPricingFields($productId)
    {
        $pricingType = isset($_POST['price_input_radio']) ? sanitize_text_field($_POST['price_input_radio']) : null;

        if (empty($pricingType)) {
            return;
        }

        switch ($pricingType) {
            case 'custom_price':
                $explodedPrices = [];
                if (isset($_POST['custom_price'])) {
                    $explodedPrices = sanitize_text_field($_POST['custom_price']);
                    $explodedPrices = explode(',', $explodedPrices);
                    $explodedPrices = array_map('sanitize_text_field', $explodedPrices);
                    // Validate
                     if (!array_filter($explodedPrices, 'is_numeric')) {
                        return;
                    }
                }
                $priceData = implode(',', $explodedPrices);
                break;
            case 'range':
                // Validate
                if (!is_numeric($_POST['min']) || !is_numeric($_POST['max']) || !is_numeric($_POST['step'])) {
                    return;
                }
                $priceData = [
                    'min' => floatval($_POST['min']),
                    'max' => floatval($_POST['max']),
                    'step' => floatval($_POST['step']),
                ];
                break;
            case 'automatic':
                // Validate
                if (!is_numeric($_POST['cost'])) {
                    return;
                }
                $priceData = [
                    'cost' => floatval($_POST['cost']),
                ];
                break;
            case 'fixed_price':
                // Validate
                if (
                    (!empty($_POST['_sale_price']) && !is_numeric($_POST['_sale_price'])) ||
                    (!empty($_POST['_regular_price']) && !is_numeric($_POST['_regular_price']))
                ) {
                    return;
                }
                $priceData = !empty($_POST['_sale_price']) ? floatval($_POST['_sale_price']) : floatval($_POST['_regular_price']);
                break;
            default:
                $priceData = '';
        }

        update_post_meta($productId, 'pricimizer_meta', [
            'pricing_type' => $pricingType,
            'price_data' => $priceData,
        ]);
    }

    // Design, frontend and assets

    /**
     * @return void
     */
    public function loadAdminAssets()
    {
        // Styles
        wp_enqueue_style('pricimizer_font-awesome', PRICIMIZER_URL . 'admin/assets/css/font-awesome.min.css');
        wp_enqueue_style('pricimizer_css', PRICIMIZER_URL . 'admin/assets/css/admin.min.css?v=' . PRICIMIZER_VERSION);

        // Jquery validator
        wp_enqueue_script('pricimizer_validator', PRICIMIZER_URL . 'admin/assets/js/jquery.validate.min.js');

        //Sweet Alert
        wp_enqueue_script('pricimizer_sweeralert', PRICIMIZER_URL . 'admin/assets/js/sweetalert.min.js');

        // pricimizer_script.js
        wp_enqueue_script('pricimizer_script', PRICIMIZER_URL . 'admin/assets/js/pricimizer_script.js?v=' . PRICIMIZER_VERSION);
        wp_localize_script('pricimizer_script', 'pricimizer_admin', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    /**
     * @return void
     */
    public function buildSidebarMenu()
    {
        add_menu_page(PRICIMIZER_PLUGIN_NAME,
            'Pricimizer',
            'manage_options',
            PRICIMIZER_PLUGIN_NAME,
            [$this, 'loadGlobalSettingsPage'],
            'dashicons-admin-site-alt3'
        );
    }

    /**
     * @return void
     */
    public function showProductCustomFields()
    {
        include_once PRICIMIZER_PATH . 'admin/templates/product_settings.php';
    }

    /**
     * @return string
     */
    public function getProductPriceHtml($price, $product)
    {
        global $woocommerce_loop;

        $productId = get_the_id();
        $product = wc_get_product($productId);

        // Fixed price

        // No valid api key or product using fixed price? return fixed price
        $meta = get_post_meta($productId, 'pricimizer_meta', true);
        $globalSettings = $this->getOption('pricimizer_global_settings');
        if (
            (isset($globalSettings['api_key_validity']) && $globalSettings['api_key_validity'] == 'invalid')
            || (!empty($meta) && $meta['pricing_type'] == 'fixed_price')
            || empty($globalSettings['state'])
            || $globalSettings['state'] != 1
        ) {
            return $this->getDefaultPriceHtml($product);
        }

        // Dynamic price

        $cache = $this->getCachedData($productId);
        // If there is any other product with not fixed price that doesn't have any dynamic price, request API (normal products request API at first and not at this stage)
        // !empty($woocommerce_loop['name']) means the product is not in the main list. For example, it is in related list or something else.
        if (is_product() && empty($cache[0]) && !empty($woocommerce_loop['name'])) {
            $this->requestApi([$this->getVariation($productId)]);
            $cache = $this->getCachedData($productId);
        }

        if (empty($cache[0])) {
            $this->requestApi([$this->getVariation($productId)]);
            $cache = $this->getCachedData($productId);
        }

        if (!empty($cache[0])) {
            // Print tracking code for the price
            $this->printTrackingCodeForProduct($cache[0]->product_id, $cache[0]->price);
            return '<span class="amount">' . wc_price(wc_get_price_including_tax($product, ['price' => $cache[0]->price])) . '</span>';
        }

        // Default fixed price
        return $this->getDefaultPriceHtml($product);
    }

    /**
     * @param $links
     * @return mixed
     */
    public function pluginsListLinks($links)
    {
        // Build and escape the URL.
        $url = esc_url(add_query_arg('page', 'Pricimizer', get_admin_url() . 'admin.php'));

        // Create the link.
        $links[] = '<a href="' . $url . '">' . __('Settings') . '</a>';

        return $links;
    }

    /**
     * @param $orderId
     * @return void
     */
    public function purchased($orderId)
    {
        if (!$orderId) {
            return;
        }

        // Execute only once
        if (!get_post_meta($orderId, '_thankyou_action_done', true)) {
            $order = wc_get_order($orderId);

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();

                // If the product is using dynamic pricing
                $meta = get_post_meta($product->get_id(), 'pricimizer_meta', true);
                if (!empty($meta) && !empty($meta['pricing_type']) && $meta['pricing_type'] != 'fixed_price') {
                    // Track
                    $this->printTrackingCodeForProduct($product->get_id(), $product->get_price(), 'purchase');
                }
            }
        }
    }

    // Global Settings

    /**
     * @return void
     */
    public function loadGlobalSettingsPage()
    {
        include_once PRICIMIZER_PATH . 'admin/templates/global_settings.php';
    }

    /**
     * @return void
     */
    public function updateGlobalSettings()
    {
        $optimizeByArray = [];
        if (isset($_POST['optimize_by'])) {
            if (!is_array($_POST['optimize_by'])) {
                return;
            }
            foreach ($_POST['optimize_by'] as $optimizeBy) {
                if (!in_array($optimizeBy, ['month', 'weekday', 'country', 'os'])) {
                    return;
                }
                $optimizeByArray[] = sanitize_text_field($optimizeBy);
            }
        }

        // don't set api_key_validity key, we set it after api requests
        $globalSettings = [
            'state' => isset($_POST['state']) && $_POST['state'] == 1 ? 1 : 0,
            'api_key' => isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '',
            'profit_margin' => isset($_POST['profit_margin']) ? intval(sanitize_text_field($_POST['profit_margin'])) : '',
            'optimize_by' => $optimizeByArray,
        ];

        update_option('pricimizer_global_settings', $globalSettings);

        Pricimizer_Cache::clearAll();

        echo json_encode([
            'code' => 200,
            'message' => 'Successfully saved.',
        ]);
        exit;
    }

    public function turnOnOff()
    {
        $globalSettings = $this->getOption('pricimizer_global_settings');

        $oldState = false;
        if (!empty($globalSettings['state']) && $globalSettings['state'] == 1) {
            $oldState = true;
        }
        $globalSettings['state'] = (int)!$oldState;

        update_option('pricimizer_global_settings', $globalSettings);

        echo json_encode([
            'code' => 200,
            'message' => 'Successfully saved.',
        ]);
        exit;
    }

    /**
     * @param $cart
     * @return mixed|void
     */
    public function customCartTotalPrice($cart)
    {
        $globalSettings = $this->getOption('pricimizer_global_settings');

        if (isset($globalSettings['api_key_validity']) && $globalSettings['api_key_validity'] == 'invalid') {
            return $cart;
        }

        $productIds = array_column($cart->get_cart(), 'product_id');

        $data = $this->getCachedData($productIds);

        foreach ($data as $cachedProduct) {
            foreach ($cart->get_cart() as $item) {
                if ($cachedProduct->product_id == $item['product_id']) {
                    $item['data']->set_price($cachedProduct->price);
                }
            }
        }
    }

    /**
     * @return void
     */
    public function singleProduct()
    {
        global $product;

        $ids = [];
        $ids = array_merge($ids, [$product->get_id()]);
        $ids = array_merge($ids, $product->get_upsell_ids());
        $ids = array_merge($ids, $product->get_cross_sell_ids());
        $ids = array_merge($ids, wc_get_featured_product_ids());
        // We fetch related products in another loop which is specifically for related products
        $ids = array_unique($ids);

        // Get visible ones
        $visibleProductsOnPage = array_filter(array_map('wc_get_product', $ids), 'wc_products_array_filter_visible');
        foreach($visibleProductsOnPage as $item) {
            $ids[] = $item->get_id();
        }

        $cache = $this->getCachedData($ids);
        $differences = array_diff($ids, array_column($cache, 'product_id'));

        if (!empty($differences)) {
            $variations = [];
            foreach ($differences as $id) {
                $variations[] = $this->getVariation($id);
            }
            $this->requestApi($variations);
        }
    }

    /**
     * @param $columns
     * @return array
     */
    public function removeDefaultPriceFromProductList($columns) {
        $reorderedColumns = [];

        $globalSettings = $this->getOption('pricimizer_global_settings');
        if (empty($globalSettings) || $globalSettings['state'] != 1) {
            return $columns;
        }

        foreach($columns as $key => $column) {
            $reorderedColumns[$key] = $column;
            if ($key == 'price') {
                $reorderedColumns['pricimizer_price'] = __('Price', 'woocommerce');
            }
        }
        unset($reorderedColumns['price']);

        return $reorderedColumns;
    }

    /**
     * @param $column
     * @param $productId
     * @return void
     */
    public function productListCustomColumn($column, $productId) {
        if ($column == 'pricimizer_price') {
            $meta = get_post_meta($productId, 'pricimizer_meta', true);
            if (!$meta || empty($meta['pricing_type'])) {
                if (!$meta) {
                    $meta = [];
                }
                $meta['pricing_type'] = 'automatic';
            }
            $globalSettings = $this->getOption('pricimizer_global_settings');
            if (!empty($globalSettings['state']) && $globalSettings['state'] == 1 && (empty($meta['pricing_type']) || $meta['pricing_type'] != 'fixed_price')) {
                $titleText = '';
                $displayPrices = '';
                if ($meta['pricing_type'] == 'custom_price') {
                    $meta['price_data'] = explode(',', $meta['price_data']);
                    $titleText = 'Custom prices'
                        . ' ('
                        . implode(', ', array_slice($meta['price_data'], 0, 10))
                        . (count($meta['price_data']) > 10 ? ',...' : '')
                        . ')';

                    $displayPrices = 'Custom: ' . implode(', ', array_slice($meta['price_data'], 0, 4))
                        . (count($meta['price_data']) > 4 ? ',...' : '');
                } elseif ($meta['pricing_type'] == 'range') {
                    $titleText = 'Range prices'
                        . ' (Min: ' . $meta['price_data']['min']
                        . ', Step: ' . $meta['price_data']['step']
                        . ', Max: ' . $meta['price_data']['max'] . ')';

                    $displayPrices = 'Range: ' . $meta['price_data']['min'] . ' - ' . $meta['price_data']['max'];
                } elseif (empty($meta['pricing_type']) || $meta['pricing_type'] == 'automatic') {
                    $titleText = 'Automatic prices';
                } elseif ($meta['pricing_type'] == 'read_from_global_settings') {
                    $displayPrices = 'Pricimizer defaults';
                }

                echo '<div title="' . esc_html($titleText) . '">';
                echo '    <div><a class="text-price-success" href="https://pricimizer.com/projects/api_key/'.esc_html($globalSettings['api_key']).'/resources?id='.$productId.'" target="_blank"><b><u>Dynamic</u></b></a></div>';
                echo '    <div>' . esc_html($displayPrices) . '</div>';
                echo '</div>';
            } else {
                echo wc_get_product($productId)->get_price_html();
            }
        }
    }

    /**
     * @param $id
     * @param $price
     * @return array
     */
    private function getFixedPriceVariationArray($id, $price)
    {
        return [
            'product_id' => $id,
            'price' => $price,
        ];
    }

    /**
     * @param $id
     * @param $price
     * @return array
     */
    private function getAutomaticPriceVariationArray($id, $priceData, array $variations)
    {
        $result = [
            'id' => $id,
            'prices' => [
                'type' => 'automatic',
                'data' => $priceData,
            ],
        ];

        foreach ($variations as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param $id
     * @param $priceData
     * @param array $variations
     * @return array
     */
    private function getCustomPriceVariationArray($id, $priceData, array $variations)
    {
        $result = [
            'id' => $id,
            'prices' => [
                'type' => 'custom',
                'data' => array_map('floatval', explode(',', $priceData)),
            ],
        ];

        foreach ($variations as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param $id
     * @param $priceData
     * @param array $variations
     * @return array
     */
    private function getRangePriceVariationArray($id, $priceData, array $variations)
    {
        $result = [
            'id' => $id,
            'prices' => [
                'type' => 'range',
                'data' => (object)[
                    'min' => $priceData['min'],
                    'max' => $priceData['max'],
                    'step' => $priceData['step'],
                ],
            ],
        ];

        foreach ($variations as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param $id
     * @param $variations
     * @return array
     */
    private function getGlobalSettingsVariationArray($id, $variations)
    {
        $options = $this->getOption('pricimizer_global_settings');
        if (empty($options['pricing_model'])) {
            return [];
        }

        switch ($options['pricing_model']) {
            case 'automatic':
                $result = $this->getAutomaticPriceVariationArray($id, [
                    'cost' => $options['pricing_automatic_cost'],
                    'init' => $options['pricing_automatic_init'],
                    'step' => $options['pricing_automatic_step'],
                ], $variations);
                break;
            case 'custom_price':
                $result = $this->getCustomPriceVariationArray($id, $options['pricing_custom'], $variations);
                break;
            case 'range':
                $result = $this->getRangePriceVariationArray($id, [
                    'min' => $options['pricing_range_min'],
                    'max' => $options['pricing_range_max'],
                    'step' => $options['pricing_range_step'],
                ], $variations);
                break;
            default:
                $result = [];
        }

        return $result;
    }

    /**
     * @param $productId
     * @return array
     */
    private function getVariation($productId)
    {
        $postMeta = get_post_meta($productId, 'pricimizer_meta', true);

        // Optimize by
        $globalSettings = $this->getOption('pricimizer_global_settings');
        $optimizeBy = $globalSettings['optimize_by'];
        $otherVariations = [];
        foreach ($optimizeBy as $key) {
            $value = $this->getOptimizeByKey($key);
            if (!is_null($value)) {
                $otherVariations[$key] = $this->getOptimizeByKey($key);
            }
        }

        // Fixed price (No pricimizer meta)
        if (empty($postMeta['pricing_type']) || empty($postMeta['price_data'])) {
            $product = wc_get_product($productId);
            $price = wc_get_price_to_display($product);
            return $this->getAutomaticPriceVariationArray(
                $productId,
                [
                    'init' => floatval($price),
                    'cost' => $price * (1 - ($globalSettings['profit_margin']/100))
                ],
                $otherVariations
            );
        }

        switch ($postMeta['pricing_type']) {
            case 'automatic':
                $variation = $this->getAutomaticPriceVariationArray(
                    $productId,
                    $postMeta['price_data'],
                    $otherVariations
                );
                break;
            case 'custom_price':
                $variation = $this->getCustomPriceVariationArray(
                    $productId,
                    $postMeta['price_data'],
                    $otherVariations
                );
                break;
            case 'range':
                $variation = $this->getRangePriceVariationArray(
                    $productId,
                    $postMeta['price_data'],
                    $otherVariations
                );
                break;
            case 'read_from_global_settings':
                $variation = $this->getGlobalSettingsVariationArray(
                    $productId,
                    $otherVariations
                );
                break;
            case 'fixed_price':
            default:
                $product = wc_get_product($productId);
                $variation = $this->getFixedPriceVariationArray(
                    $productId,
                    wc_get_price_to_display($product)
                );
                break;
        }

        return $variation;
    }

    /**
     * @param $productIds
     * @return array|stdClass
     */
    private function getCachedData($productIds) {
        if (is_user_logged_in()) {
            $cache = Pricimizer_Cache::get(get_current_user_id(), null, $productIds);
        } else {
            $cache = Pricimizer_Cache::get(null, Pricimizer_Helper::getUserIP(), $productIds);
        }

        return $cache;
    }

    /**
     * @param $product
     * @return string
     */
    private function getDefaultPriceHtml($product) {
        return '<span class="amount">' . wc_price(wc_get_price_to_display($product)) . '</span>';
    }

    /**
     * Just print tracking code for view or purchase. We don't check if product is using dynamic price here,
     * because we check that before calling this method
     * @param $id
     * @param $price
     * @param string $action
     * @return void
     */
    private function printTrackingCodeForProduct($id, $price, $action = 'view')
    {
        // Validate
        if (!in_array($action, ['view', 'purchase'])) {
            return;
        }

        $globalSettings = $this->getOption('pricimizer_global_settings');
        // No action if invalid api key
        if (isset($globalSettings['api_key_validity']) && $globalSettings['api_key_validity'] == 'invalid') {
            return;
        }
        // No action if empty api key
        if (empty($globalSettings['api_key']) || empty($globalSettings['state'] || $globalSettings['state'] != 1)) {
            return;
        }

        wp_register_script('pricimizer_api', 'https://pricimizer.com/api/v1/optimize.js?key=' . $globalSettings['api_key']);
        wp_enqueue_script('pricimizer_api');

        $variations = [];
        foreach ($globalSettings['optimize_by'] as $key) {
            $value = $this->getOptimizeByKey($key);
            if (!is_null($value)) {
                $variations[$key] = $this->getOptimizeByKey($key);
            }
        }

        // Check if the product is using dynamic pricing
        $productName = get_the_title($id);
        $json = [
            'id' => $id,
            'name' => $productName,
            'price' => $price,
        ];
        foreach ($variations as $key => $value) {
            $json[$key] = $value;
        }

        echo '<script type="text/javascript">
            window.onload = function() {
                pricimizer_' . esc_html($action) . '(' . json_encode([$json]) . ');
            };
        </script>';
    }

    /**
     * @return bool
     */
    private function enqueueScriptIfValidApiKey()
    {
        $options = $this->getOption('pricimizer_global_settings');
        $apiKey = isset($options['api_key']) ? $options['api_key'] : '';
        if (empty($apiKey)) {
            return false;
        }
        wp_register_script('pricimizer_api', 'https://pricimizer.com/api/v1/optimize.js?key=' . $apiKey);
        wp_enqueue_script('pricimizer_api');

        return true;
    }

    /**
     * @param $variations
     * @return void
     */
    private function requestApi($variations)
    {
        if (!$variations) {
            return;
        }

        $globalSettings = $this->getOption('pricimizer_global_settings');
        if (empty($globalSettings['api_key']) || empty($globalSettings['state']) || $globalSettings['state'] != 1) {
            return;
        }

        // Request API
        $args = [
            'body'        => ['variations' => json_encode($variations)],
            'timeout'     => '10',
            'redirection' => '5',
            'blocking'    => true,
            'headers'     => [
                'Authorization' => 'Bearer ' . $globalSettings['api_key'],
                'Accept' => 'application/json',
            ],
        ];
        $response = wp_remote_post( 'https://pricimizer.com/api/v1/prices', $args);
        $response = wp_remote_retrieve_body($response);

        $response = json_decode($response);
        if (empty($response) || !$response->success) {
            // Not successful
            if ($response->message == 'Wrong API Key') {
                $globalSettings['api_key_validity'] = 'invalid';
                update_option('pricimizer_global_settings', $globalSettings);
            }
            return;
        }

        // Successful? then the Api key is valid
        $globalSettings['api_key_validity'] = 'valid';

        // Does response have trial_ends_at or no_subscription key?
        if (!empty($response->trial_ends_in)) {
            $globalSettings['trial_ends_in'] = $response->trial_ends_in;
        } else {
            unset($globalSettings['trial_ends_in']);
        }
        // No subscription?
        if (!empty($response->no_subscription)) {
            $globalSettings['no_subscription'] = $response->no_subscription;
        } else {
            unset($globalSettings['no_subscription']);
        }
        // Any signed url?
        if (!empty($response->signed_url)) {
            $globalSettings['signed_url'] = $response->signed_url;
        } else {
            unset($globalSettings['signed_url']);
        }
        update_option('pricimizer_global_settings', $globalSettings);

        $loggedIn = is_user_logged_in();
        $userId = get_current_user_id();
        foreach ($response->data as $id => $price) {
            Pricimizer_Cache::remember($id, $price, $loggedIn ? intval($userId) : null, !$loggedIn ? Pricimizer_Helper::getUserIP() : null);
        }
    }

    private function getOption($key, $default = false)
    {
        if (isset(static::$option[$key])) {
            return static::$option[$key];
        }

        static::$option[$key] = get_option($key, $default);

        return static::$option[$key];
    }

    private function isWoocommerceActive()
    {
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            return true;
        }

        return false;
    }
}
