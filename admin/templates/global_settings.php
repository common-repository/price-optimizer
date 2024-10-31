<?php
$data = get_option('pricimizer_global_settings');

$state = isset($data['state']) ? (bool)$data['state'] : false;
$api_key = isset($data['api_key']) ? $data['api_key'] : '';
$profit_margin = isset($data['profit_margin']) ? $data['profit_margin'] : 20;
$optimize_by = isset($data['optimize_by']) ? $data['optimize_by'] : [];
?>

<h1>Pricimizer - Global Settings</h1>

<section>
    <form method="POST" id="cart_sync">
        <div class="import_left_section">
        <table class="widefat fixed siteblox-table" style="margin-bottom:10px">
            <tbody>
                <tr>
                    <th><span><i class="fa fa-info-circle"></i> <b>State:</b></span></th>
                </tr>
                <tr class="alternate inner_link">
                    <td style="padding-left:40px">
                        <div class="onoffswitch">
                            <input type="checkbox" name="state" class="onoffswitch-checkbox" id="header-onoff-pricimizer" value="1" <?php echo $state ? 'checked' : ''?>>
                            <label class="onoffswitch-label" for="header-onoff-pricimizer">
                                <span class="onoffswitch-inner"></span>
                                <span class="onoffswitch-switch"></span>
                            </label>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
        <table class="widefat fixed siteblox-table">
            <tbody>
                <tr>
                    <th><span title="To obtain another API key, simply register on Pricimizer website and create a project."><i class="fa fa-info-circle"></i> <label for="pricimizer-api-key"><b>API Key:</b></label></span></th>
                </tr>
                <tr class="alternate inner_link">
                    <td style="padding-left:40px">
                        <input type="text" name="api_key" id="pricimizer-api-key" placeholder="API Key" value="<?php echo esc_html($api_key); ?>" required>
                        <div><small>If you want to change your API key, you can register and create another project on <a href="https://pricimizer.com/register?for=wordpress" target="_blank">Pricimizer</a> website.</small></div>
                    </td>
                </tr>
                <tr>
                    <th><span><i class="fa fa-info-circle"></i> <b>Price settings:</b></span></th>
                </tr>
                <tr class="alternate inner_link">
                    <td style="padding-left:40px">
                        <label for="pricimizer-profit-margin">Default profit margin (%):</label>
                        <input type="text" name="profit_margin" id="pricimizer-profit-margin" value="<?php echo esc_html($profit_margin); ?>" required max="100" min="1">
                        <div><small>Specify how much profit you make on each product by average, e.g. you buy &dollar;80 and sell &dollar;100 that will be 20&percnt;. You can customize it in each product page.</small></div>
                    </td>
                </tr>
                <tr>
                    <th><span title="To optimize effectively, please clarify the specific factors you wish to consider. You can also leave them unchecked."><i class="fa fa-info-circle"></i> <b>Optimize by:</b></span></th>
                </tr>
                <tr class="alternate checkboxes">
                    <td style="padding-left:40px">
                        <label for="optimize-by-country">
                            <input type="checkbox" name="optimize_by[]" id="optimize-by-country" placeholder="Min" value="country" <?php echo in_array("country", $optimize_by) ? 'checked' : ''?>>
                            <span>Country</span>
                        </label>

                        <label for="optimize-by-month">
                            <input type="checkbox" name="optimize_by[]" id="optimize-by-month" value="month" <?php echo in_array("month", $optimize_by) ? 'checked' : ''?>>
                            <span>Month</span>
                        </label>

                        <label for="optimize-by-weekday">
                            <input type="checkbox" name="optimize_by[]" id="optimize-by-weekday" value="weekday" <?php echo in_array("weekday", $optimize_by) ? 'checked' : ''?>>
                            <span>Weekday</span>
                        </label>

                        <label for="optimize-by-os" title="People who own relatively more expensive operating systems like Apple, can be OK with higher prices. Check this option to see if it has any effect on your sales to find the most profitable prices for each operating system.">
                            <input type="checkbox" name="optimize_by[]" id="optimize-by-os" value="os" <?php echo in_array('os', $optimize_by) ? 'checked' : ''?>>
                            <span>OS</span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 40px 0 40px">
                        <ul>
                            <li><i class="fa fa-info-circle"></i> <b>Country (by IP)</b>: Sometimes people in countries with a good economy are able to pay more for your products. To find the most profitable prices by country check this option.</li>
                            <li><i class="fa fa-info-circle"></i> <b>Month</b>: Sometimes people are willing to pay more to buy some products on certain months of the year. If you want to find the most profitable prices by month, check this option.</li>
                            <li><i class="fa fa-info-circle"></i> <b>Weekday</b>: People are willing to pay more for some products on specific weekdays (For example for fast food on working days). If you want to find the most profitable prices by weekday (Monday, Tuesday,...) check this option.</li>
                            <li><i class="fa fa-info-circle"></i> <b>OS</b>: People who own expensive operating systems like Apple, possibly can pay more. Check this option to find the most profitable prices for each operating system.</li>
                            <li><u><b>Notice</b></u>: the more optimize items you check, the longer time it takes to optimize.</li>
                        </ul>
                    </td>
                </tr>
                <tr style="text-align:right">
                    <td>
                    <input type="hidden" id="action_feature" name="action" value="pricimizer_setting_update">
                    <button type="submit" id="cartsync_btn" class="button button-pro">Save Settings</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <p><b>You can set custom values in each <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')) ?>">product page</a>.</b></p>
    </form>
</section>