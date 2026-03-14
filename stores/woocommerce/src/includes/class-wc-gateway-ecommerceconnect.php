<?php

class WC_Gateway_eCommerceConnect extends WC_Payment_Gateway
{
    public $version;

    protected $data_to_send = array();

    protected $merchant_id;

    protected $terminal_id;

    protected $url;

    protected $currency;

    protected $lang;

    protected $response_url;

    protected $available_currencies;

    protected $private_key;

    protected $private_key_test;

    protected $work_crt;

    protected $test_crt;

    protected $is_pre_autorization;

    protected $skip_form;

    protected $custom_success_status;

    protected $enable_alt_currency;

    protected $eurRate;

    protected $usdRare;

    public function __construct()
    {
        $this->version = WC_GATEWAY_ECOMMERCECONNECT_VERSION;
        $this->id = 'ecommerceconnect';
        $this->method_title = __('eCommerceConnect', 'woocommerce-gateway-ecommerceconnect');

        // translators: %1$s and %2$s are opening and closing <strong> tags.
        $this->method_description = sprintf(__('Accept card payments on your website via %1$seCommerceConnect%2$s payment gateway', 'woocommerce-gateway-ecommerceconnect'), '<a href="https://ecconnect.upc.ua" target="_blank">', '</a>');
        $this->icon = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__DIR__)) . '/assets/images/icon.svg';
        $this->available_currencies = (array) apply_filters('woocommerce_gateway_ecommerceconnect_available_currencies', array(
            'USD' => '840',
            'EUR' => '978',
            'UAH' => '980',
            'BAM' => '977',
            'HUF' => '348',
            'BGN' => '975',
            'RSD' => '941',
            'ALL' => '008',
        ));

        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();
        $this->normalize_translatable_default_settings();
        $this->sync_masked_secret_previews_to_settings();

        $this->merchant_id = $this->get_option('merchant_id');
        $this->terminal_id = $this->get_option('terminal_id');
        $this->private_key = get_option($this->id . '_private_key');
        $this->private_key_test = get_option($this->id . '_private_key_test');
        $this->work_crt = get_option($this->id . '_work_crt');
        $this->test_crt = get_option($this->id . '_test_crt');
        $this->url = $this->get_option('url');
        $this->title = $this->get_option('title');
        $this->response_url = add_query_arg('wc-api', strtolower(get_class($this)), home_url('/'));
        $this->description = $this->get_option('description');
        $this->enabled = 'yes' === $this->get_option('enabled') ? 'yes' : 'no';
        $this->lang = $this->get_option('lang');
        $this->currency = $this->get_option('currency');
        $this->eurRate = $this->get_option('eur_conversion', 1);
        $this->usdRare = $this->get_option('usd_conversion', 1);
        $this->skip_form = 'yes' === $this->get_option('skip_form') ? 'yes' : 'no';
        $this->is_pre_autorization = 'yes' === $this->get_option('is_pre_autorization') ? 'yes' : 'no';
        $this->custom_success_status = str_replace('wc-', '', $this->get_option('custom_success_status'));
        $this->enable_alt_currency = 'yes' === $this->get_option('enable_alt_currency', 'no') ? 'yes' : 'no';

        if ('yes' === $this->get_option('testmode')) {
            $this->add_testmode_admin_settings_notice();
        }

        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'callbackAction'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_ecommerceconnect', array($this, 'receipt_page'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render_admin_capture_form']);

        add_filter('nocache_headers', array($this, 'no_store_cache_headers'));
    }

    /**
     * Keep default text settings translatable across site language switches.
     *
     * WooCommerce stores admin values in DB, so default text can get stuck in
     * its original language. If the saved value is empty or still equal to the
     * source string, expose it in the currently active locale.
     */
    protected function normalize_translatable_default_settings()
    {
        $default_checkout_description = 'Pay with eCommerceConnect (Debit/Credit Cards)';

        if (!isset($this->settings['description'])) {
            $this->settings['description'] = __($default_checkout_description, 'woocommerce-gateway-ecommerceconnect');

            return;
        }

        $saved_description = trim((string) $this->settings['description']);

        if ('' === $saved_description || $default_checkout_description === $saved_description) {
            $this->settings['description'] = __($default_checkout_description, 'woocommerce-gateway-ecommerceconnect');
        }
    }

    public function no_store_cache_headers($headers)
    {
        if (!is_wc_endpoint_url('order-pay')) {
            return $headers;
        }

        $headers['Cache-Control'] = 'no-cache, must-revalidate, max-age=0, no-store, private';

        return $headers;
    }

    public function init_form_fields()
    {
        $currency_options = [];
        foreach ($this->available_currencies as $code => $num) {
            $currency_options[$num] = $code . ' – ' . $num;
        }

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-gateway-ecommerceconnect'),
                'label' => __('Enable eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'checkbox',
                'description' => __('This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-ecommerceconnect'),
                'default' => 'no',
                'desc_tip' => false,
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-ecommerceconnect'),
                'default' => __('eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'),
                'desc_tip' => false,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout', 'woocommerce-gateway-ecommerceconnect'),
                'default' => __('Pay with eCommerceConnect (Debit/Credit Cards)', 'woocommerce-gateway-ecommerceconnect'),
                'desc_tip' => false,
            ),
            'url' => array(
                'title' => __('Payment Gateway Action URL', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'text',
                'placeholder' => __('Enter a Gateway URL https://...', 'woocommerce-gateway-ecommerceconnect'),
                'description' => __('This is the URL of the payment gateway where the payment form will be submitted. For test use <b>https://ecg.test.upc.ua</b>, for production use <b>https://secure.upc.ua</b>', 'woocommerce-gateway-ecommerceconnect'),
            ),
            'testmode' => array(
                'title' => __('eCommerceConnect Sandbox', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode', 'woocommerce-gateway-ecommerceconnect'),
                'default' => 'yes',
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'text',
                'description' => __('This is the Merchant ID, received from eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'),
                'default' => '',
            ),
            'terminal_id' => array(
                'title' => __('Terminal ID', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'text',
                'description' => __('This is the Terminal ID, received from eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'),
                'default' => '',
            ),
            'currency' => array(
                'title' => __('Transaction Currency', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'select',
                'description' => sprintf(
                    __('Select the currency to be used for this payment method (WooCommerce current: <strong>%s</strong>)', 'woocommerce-gateway-ecommerceconnect'),
                    get_woocommerce_currency()
                ),
                'default' => 'UAH',
                'options' => $currency_options,
            ),
            'enable_alt_currency' => array(
                'title' => __('Enable alternative display currency', 'woocommerce-gateway-ecommerceconnect'),
                'label' => __('Show alternative currency selector', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'checkbox',
                'description' => __('Enable to display and use the alternative display currency selector (USD/EUR).', 'woocommerce-gateway-ecommerceconnect'),
                'default' => 'no',
            ),
            'alt_currency' => [
                'title' => __('Alternative display currency (AltCurrency)', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'select',
                'description' => __('Used for displaying price in another currency (must be USD or EUR). Used only for visual purposes.', 'woocommerce-gateway-ecommerceconnect'),
                'default' => 'USD',
                'options' => array_intersect_key($currency_options, array_flip(['840', '978'])),  // USD, EUR
            ],
            'eur_conversion' => array(
                'title' => __('EUR Conversion Rate', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'number',
                'description' => __('Specify the conversion rate for EUR. Use dot (.) as decimal separator.', 'woocommerce-gateway-ecommerceconnect'),
                'desc_tip' => false,
                'custom_attributes' => array(
                    'step' => '0.0001',
                    'min' => '0',
                ),
            ),
            'usd_conversion' => array(
                'title' => __('USD Conversion Rate', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'number',
                'description' => __('Specify the conversion rate for USD. Use dot (.) as decimal separator.', 'woocommerce-gateway-ecommerceconnect'),
                'desc_tip' => false,
                'custom_attributes' => array(
                    'step' => '0.0001',
                    'min' => '0',
                ),
            ),
            'test_crt' => array(
                'title' => __('Test certificate', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'textarea',
                'default' => $this->get_masked_secret_preview($this->id . '_test_crt'),
                'description' => $this->get_private_key_status_description(
                    $this->id . '_test_crt',
                    __('This is the Test certificate for signature verification from UPC e-Commerce Connect payment gateway', 'woocommerce-gateway-ecommerceconnect'),
                    __('Test certificate', 'woocommerce-gateway-ecommerceconnect')
                ),
            ),
            'private_key_test' => array(
                'title' => __('Private key for test mode', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'textarea',
                'default' => $this->get_masked_secret_preview($this->id . '_private_key_test'),
                'description' => $this->get_private_key_status_description(
                    $this->id . '_private_key_test',
                    __('This is the Private key for test mode, received from eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'),
                    __('Private key for test mode', 'woocommerce-gateway-ecommerceconnect')
                ),
            ),
            'work_crt' => array(
                'title' => __('Work certificate', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'textarea',
                'default' => $this->get_masked_secret_preview($this->id . '_work_crt'),
                'description' => $this->get_private_key_status_description(
                    $this->id . '_work_crt',
                    __('This is the Work certificate for signature verification from UPC e-Commerce Connect payment gateway', 'woocommerce-gateway-ecommerceconnect'),
                    __('Work certificate', 'woocommerce-gateway-ecommerceconnect')
                ),
            ),
            'private_key' => array(
                'title' => __('Private key', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'textarea',
                'default' => $this->get_masked_secret_preview($this->id . '_private_key'),
                'description' => $this->get_private_key_status_description(
                    $this->id . '_private_key',
                    __('This is the Private key, received from eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'),
                    __('Private key', 'woocommerce-gateway-ecommerceconnect')
                ),
            ),
            'custom_success_status' => [
                'title' => __('Status for Successful Withdrawal', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'select',
                'description' => __('Set the current status of your WooCommerce order for the corresponding transaction', 'woocommerce-gateway-ecommerceconnect'),
                'options' => wc_get_order_statuses(),
                'default' => 'wc-processing',
                'desc_tip' => false,
            ],
            'lang' => array(
                'title' => __('eCommerceConnect interface language', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'select',
                'label' => __('Set the payment gateway language', 'woocommerce-gateway-ecommerceconnect'),
                'default' => 'en',
                'options' => [
                    'en' => __('English (default)', 'woocommerce-gateway-ecommerceconnect'),  // fallback
                    'uk' => __('Ukrainian (UAH)', 'woocommerce-gateway-ecommerceconnect'),
                    'sr' => __('Serbian (RSD)', 'woocommerce-gateway-ecommerceconnect'),
                    'hu' => __('Hungarian (HUF)', 'woocommerce-gateway-ecommerceconnect'),
                    'bg' => __('Bulgarian (BGN)', 'woocommerce-gateway-ecommerceconnect'),
                    'sq' => __('Albanian (ALL)', 'woocommerce-gateway-ecommerceconnect'),
                    'bs' => __('Bosnian (BAM)', 'woocommerce-gateway-ecommerceconnect'),
                    'de' => __('German (EUR)', 'woocommerce-gateway-ecommerceconnect'),
                ],
            ),
            'skip_form' => array(
                'title' => __('Skip receipt page', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'checkbox',
                'label' => __('Skip receipt page', 'woocommerce-gateway-ecommerceconnect'),
                'default' => 'no',
            ),
            'is_pre_autorization' => array(
                'title' => __('Pre-authorization', 'woocommerce-gateway-ecommerceconnect'),
                'type' => 'checkbox',
                'label' => __('Pre-authorization', 'woocommerce-gateway-ecommerceconnect'),
                'description' => __('For pre-authorization, check the checkbox', 'woocommerce-gateway-ecommerceconnect'),
                'default' => 'no',
            ),
        );
    }

    public function process_admin_options()
    {
        $hidenFieldKeyValues = array(
            'woocommerce_' . $this->id . '_private_key' => $this->id . '_private_key',
            'woocommerce_' . $this->id . '_private_key_test' => $this->id . '_private_key_test',
            'woocommerce_' . $this->id . '_work_crt' => $this->id . '_work_crt',
            'woocommerce_' . $this->id . '_test_crt' => $this->id . '_test_crt',
        );
        foreach ($hidenFieldKeyValues as $key => $value) {
            if (isset($_POST[$key])) {
                $posted_key = isset($_POST[$key]) ? trim(wp_unslash($_POST[$key])) : '';
                $current_saved_key = get_option($value, '');

                if ($this->is_masked_secret_preview($value, $posted_key)) {
                    unset($_POST[$key]);

                    continue;
                }

                if (!empty($posted_key)) {
                    update_option($value, $posted_key);

                    WC_Admin_Settings::add_message(
                        // translators: %s is the setting name.
                        sprintf(__('%s updated successfully.', 'woocommerce-gateway-ecommerceconnect'), $value)
                    );
                } else {
                    if (empty($current_saved_key)) {
                        WC_Admin_Settings::add_error(
                            sprintf(
                                // translators: 1: Field name, 2: Field name again.
                                __('%1$s cannot be empty. Please enter a %2$s.', 'woocommerce-gateway-ecommerceconnect'),
                                $value,
                                $value
                            )
                        );
                    }
                }

                unset($_POST[$key]);
            }
        }

        return parent::process_admin_options();
    }

    protected function get_masked_secret_preview($field_key)
    {
        $current_value = (string) get_option($field_key, '');

        if ('' === trim($current_value)) {
            return '';
        }

        $fingerprint = strtoupper(substr(hash('sha256', $current_value), 0, 12));

        return "[SAVED SECRET]\nFingerprint: {$fingerprint}\nPaste a new value to replace and save changes.";
    }

    protected function is_masked_secret_preview($field_key, $value)
    {
        $preview = $this->get_masked_secret_preview($field_key);

        return '' !== $preview && $preview === $value;
    }

    protected function sync_masked_secret_previews_to_settings()
    {
        $secret_fields = array(
            'private_key' => $this->id . '_private_key',
            'private_key_test' => $this->id . '_private_key_test',
            'work_crt' => $this->id . '_work_crt',
            'test_crt' => $this->id . '_test_crt',
        );

        foreach ($secret_fields as $setting_key => $secret_option_key) {
            $this->settings[$setting_key] = $this->get_masked_secret_preview($secret_option_key);
        }
    }

    public function get_private_key_status_description($field_key, $description, $title)
    {
        $current_key_exists = !empty(get_option($field_key, ''));

        $message = $description;

        if ($current_key_exists) {
            $message .= '<br><strong style="color:#00a32a">';

            $message .= sprintf(
                // translators: %s is the field name.
                __('A %s is currently saved.', 'woocommerce-gateway-ecommerceconnect'),
                $title
            );
            $message .= '</strong>';
        } else {
            $message .= '<br><span style="color: #d63638">';
            $message .= sprintf(
                // translators: 1: Field label, 2: Field label again.
                __('No %1$s is currently saved. Please enter a %2$s.', 'woocommerce-gateway-ecommerceconnect'),
                $title,
                $title
            );
            $message .= '</span>';
        }

        return $message;
    }

    public function get_required_settings_keys()
    {
        return array(
            'merchant_id',
            'terminal_id',
            'url',
        );
    }

    public function needs_setup()
    {
        return !$this->get_option('merchant_id') || !$this->get_option('terminal_id');
    }

    public function add_testmode_admin_settings_notice()
    {
        $this->form_fields['merchant_id']['description'] .= ' <strong>' . esc_html__('Sandbox Merchant ID currently in use', 'woocommerce-gateway-ecommerceconnect') . ' ( ' . esc_html($this->merchant_id) . ' )</strong>';
        $this->form_fields['terminal_id']['description'] .= ' <strong>' . esc_html__('Sandbox Terminal ID currently in use', 'woocommerce-gateway-ecommerceconnect') . ' ( ' . esc_html($this->terminal_id) . ' )</strong>';
    }

    public function check_requirements()
    {
        $errors = array(
            !key_exists(get_woocommerce_currency(), $this->available_currencies) ? 'wc-gateway-ecommerceconnect-error-invalid-currency' : null,
            empty($this->get_option('merchant_id')) ? 'wc-gateway-ecommerceconnect-error-missing-merchant-id' : null,
            empty($this->get_option('terminal_id')) ? 'wc-gateway-ecommerceconnect-error-missing-terminal-id' : null,
            empty($this->get_option('url')) ? 'wc-gateway-ecommerceconnect-error-missing-url' : null,
        );

        return array_filter($errors);
    }

    public function is_available()
    {
        if ('yes' === $this->enabled) {
            $errors = $this->check_requirements();

            return 0 === count($errors);
        }

        return parent::is_available();
    }

    public function validate_fields()
    {
        if (!key_exists(get_woocommerce_currency(), $this->available_currencies)) {
            wc_add_notice(__('This currency is not supported by eCommerceConnect gateway.', 'woocommerce-gateway-ecommerceconnect'), 'error');

            return false;
        }

        return true;
    }

    public function get_error_message($key)
    {
        switch ($key) {
            case 'wc-gateway-ecommerceconnect-error-invalid-currency':
                return esc_html__("Your store uses a currency that ecommerceconnect doesn't support yet.", 'woocommerce-gateway-ecommerceconnect');
            case 'wc-gateway-ecommerceconnect-error-missing-merchant-id':
                return esc_html__('You forgot to fill your merchant ID.', 'woocommerce-gateway-ecommerceconnect');
            case 'wc-gateway-ecommerceconnect-error-missing-terminal-id':
                return esc_html__('You forgot to fill your terminal ID.', 'woocommerce-gateway-ecommerceconnect');
            case 'wc-gateway-ecommerceconnect-error-missing-url':
                return esc_html__('eCommerceConnect requires Payment Gateway URL to work', 'woocommerce-gateway-ecommerceconnect');
            default:
                return '';
        }
    }

    public function admin_notices()
    {
        $errors_to_show = $this->check_requirements();

        if (!count($errors_to_show)) {
            return;
        }

        if ('no' === $this->enabled) {
            return;
        }

        if (!get_transient('wc-gateway-ecommerceconnect-admin-notice-transient')) {
            set_transient('wc-gateway-ecommerceconnect-admin-notice-transient', 1, 1);

            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__('To use eCommerceConnect as a payment provider, you need to fix the problems below:', 'woocommerce-gateway-ecommerceconnect') . '</p>'
                . '<ul style="list-style-type: disc; list-style-position: inside; padding-left: 2em;">'
                . wp_kses_post(
                    array_reduce(
                        $errors_to_show,
                        function ($errors_list, $error_item) {
                            $errors_list = $errors_list . PHP_EOL . ('<li>' . $this->get_error_message($error_item) . '</li>');

                            return $errors_list;
                        },
                        ''
                    )
                )
                . '</ul></p></div>';
        }
    }

    public function callbackAction()
    {
        if (!$_POST) {
            return;
        }

        $order = wc_get_order($_POST['OrderID']);

        if (!$order) {
            echo "Response.action=reverse\n";
            echo "Response.reason=Order not found\n";
            die();
        }

        if (!empty($_POST['SD'])) {
            $order->update_meta_data('_upc_session_token', $_POST['SD']);
        }

        if ($_POST['TranCode'] === '000') {
            try {
                $upcToken = $_POST['UPCToken'] ?? null;
                $upcTokenExp = $_POST['UPCTokenExp'] ?? null;

                $data = sprintf(
                    '%s;%s;%s;%s,%s;%s;%s,%s;%s,%s;%s;%s;%s;%s%s',
                    $_POST['MerchantID'] ?? '',
                    $_POST['TerminalID'] ?? '',
                    $_POST['PurchaseTime'] ?? '',
                    $order->get_id(),
                    $_POST['Delay'],
                    $_POST['XID'] ?? '',
                    $_POST['Currency'] ?? '',
                    $_POST['AltCurrency'] ?? '',
                    $_POST['TotalAmount'] ?? '',
                    $_POST['AltTotalAmount'] ?? '',
                    $_POST['SD'] ?? '',
                    $_POST['TranCode'],
                    $_POST['ApprovalCode'] ?? '',
                    $upcToken ?? '',
                    $upcTokenExp ? ',' . $upcTokenExp . ';' : ''
                );

                $verified = 'yes' === $this->get_option('testmode') || $this->signatureVerify($data, $_POST['Signature']);

                if (!$verified) {
                    echo "Response.action=reverse\n";
                    echo "Response.reason=Signature verification failed. Provided signature: {$_POST['Signature']}\n";
                    echo "Response.forwardUrl={$order->get_checkout_order_received_url()}\n";
                    die();
                }

                if ($upcToken && $upcTokenExp) {
                    $this->setUPCToken($order, $upcToken, $upcTokenExp);
                }

                if ('yes' === $this->is_pre_autorization && ((bool) $_POST['Delay'])) {
                    $order->update_status('wc-on-hold', __('Pre-authorization', 'woocommerce-gateway-ecommerceconnect'));

                    $order->update_meta_data('upc_purchase_time', $_POST['PurchaseTime']);
                    $order->update_meta_data('upc_approval_code', $_POST['ApprovalCode']);
                    $order->update_meta_data('upc_rrn', $_POST['Rrn']);
                    $order->update_meta_data('upc_signature', $_POST['Signature']);
                } else {
                    $order->set_status($this->custom_success_status);
                    $order->payment_complete();
                }

                $order->save();
            } catch (Exception $e) {
                echo 'Message: ' . $e->getMessage();
            }
        } else {
            try {
                $order->set_status('wc-failed');
                $order->save();
            } catch (Exception $e) {
                echo 'Message: ' . $e->getMessage();
            }
        }

        $secret = $this->private_key;
        $signature = hash_hmac('sha256', $order->get_id() . '|' . $_POST['SD'], $secret);

        $frwrdUrl = add_query_arg(array(
            'oid' => $order->get_id(),
            'sig' => $signature,
        ), $order->get_checkout_order_received_url());

        echo 'MerchantID = ' . $_POST['MerchantID'] . "\n";
        echo 'TerminalID = ' . $_POST['TerminalID'] . "\n";
        echo 'OrderID = ' . $_POST['OrderID'] . "\n";
        echo 'Currency = ' . $_POST['Currency'] . "\n";
        echo 'TotalAmount = ' . $_POST['TotalAmount'] . "\n";
        echo 'XID = ' . $_POST['XID'] . "\n";
        echo 'PurchaseTime = ' . $_POST['PurchaseTime'] . "\n";
        echo 'ApprovalCode= ' . $_POST['ApprovalCode'] . "\n";
        echo 'SD= ' . $_POST['SD'] . " \n";
        echo 'TranCode= ' . $_POST['TranCode'] . " \n";
        echo "Response.action= approve \n";
        echo "Response.reason= ok \n";
        echo 'Response.forwardUrl= ' . $frwrdUrl . " \n";
        die();
    }

    public function admin_options()
    {
        if (key_exists(get_woocommerce_currency(), $this->available_currencies)) {
            ?>

                    <h2 class="ecommconnect-settings-title">
                        <span class="ecommconnect-settings-title__brand">
                            <span class="ecommconnect-settings-title__icon" aria-hidden="true"></span>
                            <span class="ecommconnect-settings-title__text"><?php esc_html_e('eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'); ?></span>
                            <span class="ecommconnect-settings-title__badge"><?php echo esc_html('v' . WC_GATEWAY_ECOMMERCECONNECT_VERSION); ?></span>
                        </span>
                            <?php
                        if (function_exists('wc_back_link')) {
                                wc_back_link(__('Return to payments', 'woocommerce-gateway-ecommerceconnect'), admin_url('admin.php?page=wc-settings&tab=checkout'));
                        }
                        ?>
                    </h2>

                    <div class="ecommconnect-settings-info" role="status" aria-live="polite">
                            <?php
                                $notify_url = WC()->api_request_url(strtolower(get_class($this)));
                                ?>
                            <p><?php esc_html_e('Please, enter this link into the notify url field in the merchant portal for return customer back to your store after payment', 'woocommerce-gateway-ecommerceconnect'); ?></p>
                            <div class="ecommconnect-settings-info__copy-row">
                                <pre><code id="ecommconnect-notify-url"><?php echo esc_html($notify_url); ?></code></pre>
                                <button
                                    type="button"
                                    class="button ecommconnect-copy-notify-url"
                                    data-copy-target="ecommconnect-notify-url"
                                    data-copy-text="<?php echo esc_attr__('Copy link', 'woocommerce-gateway-ecommerceconnect'); ?>"
                                    data-copied-text="<?php echo esc_attr__('Copied', 'woocommerce-gateway-ecommerceconnect'); ?>"
                                    data-copy-error-text="<?php echo esc_attr__('Unable to copy. Please copy manually.', 'woocommerce-gateway-ecommerceconnect'); ?>"
                                >
                                        <?php esc_html_e('Copy link', 'woocommerce-gateway-ecommerceconnect'); ?>
                                </button>
                            </div>
                            <p class="description ecommconnect-copy-feedback" role="status" aria-live="polite"></p>
                    </div>
            <?php
            echo '<div class="ecommconnect-settings-page">';
            echo '<div class="ecommconnect-settings-card">';
            echo '<table class="form-table ecommconnect-settings-table">';
            $this->generate_settings_html();
            echo '</table>';
            echo '</div>';
            echo '</div>';
        } else {
            ?>
          <h3><?php esc_html_e('eCommerceConnect', 'woocommerce-gateway-ecommerceconnect'); ?></h3>
          <div class="inline error">
            <p>
              <strong><?php esc_html_e('Gateway Disabled', 'woocommerce-gateway-ecommerceconnect'); ?></strong>
                <?php
                echo wp_kses_post(
                    sprintf(
                        // translators: %1$s and %2$s are opening and closing anchor or <strong> tags.
                        __('Choose UAH as your store currency in %1$sGeneral Settings%2$s to enable the eCommerceConnect Gateway.', 'woocommerce-gateway-ecommerceconnect'),
                        '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=general')) . '">',
                        '</a>'
                    )
                );
                ?>
            </p>
          </div>
            <?php
        }
    }

    public function generate_ecommerceconnect_form($order_id)
    {
        $order = wc_get_order($order_id);

        $order_currency = get_woocommerce_currency();
        $contract_currency = $this->currency;
        $available = $this->available_currencies;

        $alt_currency = (int) $this->get_option('alt_currency', 840);
        $is_alt_currency_enabled = 'yes' === $this->enable_alt_currency;

        $order_total = $order->get_total();
        $total_amount = round($order_total * 100);
        $alt_total = $total_amount;

        $rate = 1.0;

        if (!$is_alt_currency_enabled) {
            $alt_currency = (int) $contract_currency;
            $alt_total = $total_amount;
        } else if ($available[$order_currency] === $contract_currency) {
            $alt_currency = $contract_currency;
        } else if ($alt_currency === 978) {
            $rate = (float) $this->eurRate;
            $alt_total = round(($order_total / $rate) * 100);
        } else if ($alt_currency === 840) {
            $rate = (float) $this->usdRare;
            $alt_total = round(($order_total / $rate) * 100);
        } else {
            $alt_total = $total_amount;
        }

        $total_amount = round($order->get_total() * 100);

        $orderID = ltrim($order->get_order_number());
        $merchantID = $this->merchant_id;
        $terminalID = $this->terminal_id;
        $purchaseTime = date('ymdHis');
        $locale = $this->lang;
        $delay = 'yes' === $this->is_pre_autorization ? '1' : '0';
        $sd = wp_get_session_token();
        $upc_token = '';

        $customerId = $order->get_user_id();

        if ($customerId > 0) {
            $tokenExp = get_user_meta($customerId, 'upc_token_exp', true);
            $token = get_user_meta($customerId, 'upc_token', true);

            $expMonth = (int) substr($tokenExp, 0, 2);
            $expYear = (int) substr($tokenExp, 2, 4);

            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');

            $isValid = ($expYear > $currentYear) || ($expYear === $currentYear && $expMonth >= $currentMonth);

            if ($isValid) {
                $upc_token = $token;
            }
        }

        // $data = "$merchantID;$terminalID;$purchaseTime;$orderID,$delay;$currency_numeric;$totalAmount;$sd;";
        $data = "$merchantID;$terminalID;$purchaseTime;$orderID,$delay;$contract_currency,$alt_currency;$total_amount,$alt_total;$sd;";

        $priv_key_raw = ('yes' === $this->get_option('testmode'))
            ? $this->private_key_test
            : $this->private_key;

        if (empty($priv_key_raw)) {
            echo 'Private key is empty';
            $b64sign = '';
        }

        $priv_key = str_replace(['\r\n', '\n', '\r'], PHP_EOL, trim($priv_key_raw));

        if (
            stripos($priv_key, '-----BEGIN') === false ||
            stripos($priv_key, 'PRIVATE KEY-----') === false
        ) {
            echo 'Private key appears to be incorrectly formatted';
            $b64sign = '';
        }

        $pkeyid = openssl_get_privatekey($priv_key);
        if (!$pkeyid) {
            echo 'Failed to parse private key';
            $b64sign = '';
        }

        if (openssl_sign($data, $signature, $pkeyid, OPENSSL_ALGO_SHA512)) {
            $b64sign = base64_encode($signature);
        } else {
            echo 'Failed to sign data';
            $b64sign = '';
        }

        if (PHP_VERSION_ID < 80000) {
            openssl_free_key($pkeyid);
        }

        $data_to_send = array(
            'Version' => '1',
            'MerchantID' => $merchantID,
            'TerminalID' => $terminalID,
            'OrderID' => $orderID,
            'Delay' => $delay,
            'TotalAmount' => $total_amount,
            'AltTotalAmount' => $alt_total,
            'Currency' => $contract_currency,
            'AltCurrency' => $alt_currency,
            'PurchaseTime' => $purchaseTime,
            'PurchaseDesc' => get_bloginfo('name') . ' - ' . $order->get_order_number(),
            'SD' => $sd,
            'locale' => $locale,
            'Signature' => $b64sign,
        );

        if ($upc_token !== '') {
            $data_to_send['UPCToken'] = $upc_token;
        }

        $this->data_to_send = $data_to_send;

        $this->data_to_send = apply_filters('woocommerce_gateway_ecommerceconnect_payment_data_to_send', $this->data_to_send, $order_id);

        $ecommerceconnect_args_array = array();

        foreach ($this->data_to_send as $key => $value) {
            $ecommerceconnect_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
        }

        echo '<form action="' . esc_url($this->url . '/go/pay') . '" method="post" id="ecommerceconnect_payment_form">';
        echo implode('', $ecommerceconnect_args_array);
        echo '<input type="submit" class="button-alt" id="submit_ecommerceconnect_payment_form" value="' . esc_attr__('Purchase', 'woocommerce-gateway-ecommerceconnect') . "\"/>
\t\t\t\t<a class=\"button btn alt btn-black\"\thref=\"" . esc_url($order->get_cancel_order_url()) . '">'
            . esc_html__('Back', 'woocommerce-gateway-ecommerceconnect') . '</a>';
        if ('yes' === $this->get_option('skip_form')) {
            echo '<script>
                  jQuery(function(){
                    jQuery("#submit_ecommerceconnect_payment_form" ).click();
                  });
                </script>';
        }
        echo '</form>';
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        );
    }

    public function receipt_page($order)
    {
        echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with eCommerceConnect.', 'woocommerce-gateway-ecommerceconnect') . '</p>';
        $this->generate_ecommerceconnect_form($order);
    }

    public function render_admin_capture_form($order)
    {
        if (
            $order->get_status() !== 'on-hold' ||
            $order->get_payment_method() !== $this->id
        ) {
            return;
        }

        $order_id = $order->get_id();
        $max_amount = $order->get_total();

        echo '<div class="ecommconnect-capture-box" style="margin-top:20px;display: inline-block;width: 100%;">';
        echo '<h3>' . esc_html__('Capture via UPC', 'woocommerce-gateway-ecommerceconnect') . '</h3>';
        echo '<form id="ecommconnect-capture-form">';
        echo wp_nonce_field('ecommconnect_capture_action', 'ecommconnect_nonce', true, false);
        echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';
        echo '<input type="number" name="amount" style="width:49%" min="1" max="' . esc_attr($max_amount) . '" step="0.01" required value="' . esc_attr($max_amount) . '">';
        echo '<button id="ecommconnect-capture-form" type="submit" style="width:50%" class="button button-primary">' . esc_html__('Оплатити', 'woocommerce-gateway-ecommerceconnect') . '</button>';
        echo '<div class="ecommconnect-message" style="margin-top:10px;color:red;"></div>';
        echo '</form>';
        echo '</div>';
    }

    public function ajax_capture_order($order, $amount)
    {
        check_ajax_referer('ecommconnect_capture_action', 'security');

        $amount = (int) (($amount ?? 0) * 100);

        if ($amount < 1) {
            wp_send_json_success([
                'error' => true,
                'message' => __('Amount must be at least 1.', 'woocommerce-gateway-ecommerceconnect'),
            ]);
        }

        if (!$order || $order->get_payment_method() !== $this->id) {
            wp_send_json_success([
                'error' => true,
                'message' => __('Order ID is missing', 'woocommerce-gateway-ecommerceconnect'),
            ]);
        }

        $orderTotal = ((int) $order->get_total() * 100);
        if ($amount > $orderTotal) {
            wp_send_json_success([
                'error' => true,
                'message' => __('Amount cannot exceed order total: %1', $order->get_total()),
            ]);
        }

        $postData = $this->getCaptureFormData($order, $amount);

        $response = wp_remote_post($this->url . '/go/capture', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($postData),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_success([
                'error' => true,
                'message' => __('Запит не відбувся: ', 'woocommerce-gateway-ecommerceconnect') . $response->get_error_message(),
            ]);
        }

        $body = wp_remote_retrieve_body($response);

        if (preg_match('/<p>(.*?)<\/p>/s', $body, $matches)) {
            $lines = explode("\n", trim($matches[1]));
            $parsed = [];

            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    [$key, $value] = array_pad(explode('=', trim($line), 2), 2, '');
                    $parsed[trim($key)] = trim($value);
                }
            }

            if (isset($parsed['TranCode']) && $parsed['TranCode'] === '000') {
                $order->update_status($this->custom_success_status, __('Captured via UPC Gateway', 'woocommerce-gateway-ecommerceconnect'));
                wp_send_json_success([
                    'success' => true,
                    'message' => __('Capture success. Order moved to Processing', 'woocommerce-gateway-ecommerceconnect'),
                ]);
                $order->payment_complete();
            } else {
                wp_send_json_success([
                    'error' => true,
                    'message' => 'Capture failed: ' . json_encode($parsed),
                    'parsed' => $parsed,
                    'post_data' => $postData,
                ]);
            }
        }

        wp_send_json_success([
            'error' => true,
            'message' => __('Invalid response from UPC', 'woocommerce-gateway-ecommerceconnect'),
        ]);
    }

    private function getCaptureFormData($order, $totalAmount)
    {
        return [
            'MerchantID' => $this->merchant_id,
            'TerminalID' => $this->terminal_id,
            'OrderID' => $order->get_id(),
            'Currency' => isset($this->available_currencies[$order->get_currency()]) ? $this->available_currencies[$order->get_currency()] : null,
            'TotalAmount' => ((int) $order->get_total() * 100),
            'PurchaseTime' => $order->get_meta('upc_purchase_time'),
            'ApprovalCode' => $order->get_meta('upc_approval_code'),
            'RRN' => $order->get_meta('upc_rrn'),
            'PostauthorizationAmount' => $totalAmount,
            'Signature' => $order->get_meta('upc_signature'),
        ];
    }

    private function setUPCToken($order, string $token, string $tokenExp): void
    {
        $customerId = $order->get_user_id();
        if ($customerId < 1) {
            return;
        }

        if ($customerId && $token && $tokenExp) {
            $expMonth = (int) substr($tokenExp, 0, 2);
            $expYear = (int) substr($tokenExp, 2, 4);

            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');

            $isValid = ($expYear > $currentYear) || ($expYear === $currentYear && $expMonth >= $currentMonth);

            if ($isValid) {
                $user = get_userdata($customerId);

                if ($user) {
                    update_user_meta($customerId, 'upc_token', $token);
                    update_user_meta($customerId, 'upc_token_exp', $tokenExp);
                }
            }
        }
    }

    private function signatureVerify($data, $signature)
    {
        $crt = 'yes' === $this->get_option('testmode') ? $this->test_crt : $this->work_crt;
        $publicKey = openssl_pkey_get_public($crt);

        if ($publicKey === false) {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return false;
        }

        return openssl_verify($data, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA512) === 1;
    }

    public function restore_session_from_sig($order, $sd, $provided_sig)
    {
        $secret = $this->private_key;
        $expected_sig = hash_hmac('sha256', $order->get_id() . '|' . $sd, $secret);

        if (!hash_equals($expected_sig, $provided_sig)) {
            return;
        }

        if (function_exists('wc_setcookie')) {
            wc_setcookie('wp_woocommerce_session_' . COOKIEHASH, $sd);
        }

        if (class_exists('WC_Session_Handler')) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        $clean_url = remove_query_arg(array('oid', 'sig'));
        wp_safe_redirect($clean_url);
        exit;
    }
}
