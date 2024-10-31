<?php

global $post;
$pricingType = '';
$custom_price = '';
$min = '';
$max = '';
$step = '';

$cost = '';
$init = '';

$meta = get_post_meta($post->ID, 'pricimizer_meta', true);
$pricingType = 'automatic';
if (!empty($meta)) {
    $pricingType = $meta['pricing_type'];

    if ($meta['pricing_type'] == 'range') {
        $min = $meta['price_data']['min'];
        $max = $meta['price_data']['max'];
        $step = $meta['price_data']['step'];
    } elseif ($meta['pricing_type'] == 'automatic') {
        $init = isset($meta['price_data']['init']) ? $meta['price_data']['init'] : '';
        $step = isset($meta['price_data']['step']) ? $meta['price_data']['step'] : '';
        $cost = isset($meta['price_data']['cost']) ? $meta['price_data']['cost'] : '';
    } elseif ($meta['pricing_type'] == 'custom_price') {
        $custom_price = $meta['price_data'];
    }
}

echo '<div class="product_custom_field"><h3>Dynamic Pricing</h3>';
woocommerce_wp_hidden_input([
    'id' => 'price_input_type',
    'label' => __('price_input_type ', 'woocommerce'),
    'value' => $pricingType,
]);
woocommerce_wp_radio([
    'id' => 'price_input_radio',
    'label' => '',
    'options' => [
        'automatic' => __('Automatic price'),
        'fixed_price' => __('Fixed price'),
        'custom_price' => __('Custom price'),
        'range' => __('Range'),
    ],
    'default' => $pricingType,
]);

echo '<div id="show_automatic" class="price_set">';
woocommerce_wp_text_input([
    'id' => 'cost',
    'label' => __('Cost', 'woocommerce') . ':',
    'type' => 'text',
    'value' => $cost,
]);
echo '<div><small>(How much did this product cost you? if you bought it for $80 and selling for $100, enter 80)</small></div>';
echo '</div><div id="show_custom_price" class="price_set" style="display: none;">
<p style="padding-top:0">
Imagine you want to test these prices: <b>10$, 15$, 20$, 25$, 30$, 35$</b><br />
In this case you can enter <b>10,15,20,25,30,35</b> (comma separated without currency sign) and it will test all of these prices for the product to find the most profitable one.
</p>';
woocommerce_wp_text_input([
    'id' => 'custom_price',
    'placeholder' => 'Enter custom prices',
    'label' => __('Custom price', 'woocommerce') . '<br /><small>(Comma separated)</small>',
    'value' => $custom_price,
]);
/////////////////////
echo '<div id="show_range" class="price_set" style="display: none;" >
<p style="padding-top:0">Imagine you want to test these prices for your product to find the most profitable one: <b>10$, 15$, 20$, 25$, 30$, 35$</b><br />
In this case you can enter these values to try all those prices: <b>Min = 10</b> | <b>Step = 5</b> | <b>Max = 35</b>
</p>';
woocommerce_wp_text_input([
    'id' => 'min',
    'placeholder' => 'Enter Minimum value ',
    'label' => __('Min', 'woocommerce') . ':',
    'type' => 'text',
    'value' => $min,
]);
woocommerce_wp_text_input([
    'id' => 'step',
    'placeholder' => 'Enter Step ',
    'label' => __('Step', 'woocommerce'). ':',
    'type' => 'text',
    'value' => $step,
]);
woocommerce_wp_text_input([
    'id' => 'max',
    'placeholder' => 'Enter Max value ',
    'label' => __('Max', 'woocommerce') . ':',
    'type' => 'text',
    'value' => $max,
]);
echo '</div><div id="show_custom_price" class="price_set" style="display: none;">
<p style="padding-top:0">
Imagine you want to test these prices: <b>10$, 15$, 20$, 25$, 30$, 35$</b><br />
In this case you can enter <b>10,15,20,25,30,35</b> (comma separated without currency sign) and it will test all of these prices for the product to find the most profitable one.
</p>';
woocommerce_wp_text_input([
    'id' => 'custom_price',
    'placeholder' => 'Enter custom prices',
    'label' => __('Custom price', 'woocommerce') . '<br /><small>(Comma separated)</small>',
    'value' => $custom_price,
]);
echo '</div>';
echo '</div>';