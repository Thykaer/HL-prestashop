<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

include(dirname(__FILE__) . '/csv.php');
include(dirname(__FILE__) . '/API/api.php');

class HeyLoyalty extends Module
{

    public function __construct()
    {
        $this->name = 'HeyLoyalty';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'HeyLoyalty';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('HeyLoyalty');
        $this->description = $this->l('HeyLoyalty');

        $this->api_key = Configuration::get('HeyLoyalty_api_key');
        $this->api_secret = Configuration::get('HeyLoyalty_api_secret');
        $this->list_id = Configuration::get('Heyloyalty_list_id');
        $this->only_subscribed = Configuration::get('HeyLoyalty_only_subscribed');

        $this->ExportedCustomersByID = array();
        $this->ExportedCustomersByEmail = array();

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install() ||
            !$this->registerHook('actionPaymentConfirmation') ||
            !$this->registerHook('actionObjectCustomerAddAfter')) {
            return false;
        }

        Configuration::updateValue('HeyLoyalty_only_subscribed', 1);
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
            !$this->unregisterHook('actionPaymentConfirmation') ||
            !$this->unregisterHook('actionObjectCustomerAddAfter')) {
            return false;
        }
        return true;
    }

    public function hookActionObjectCustomerAddAfter($params)
    {
        try {
            $customer = $params['object'];
            if ($customer->newsletter) {
                $customerData = $this->getCustomerData($customer);
                $orderData = $this->getOrderData($customer);
                $HLCustomer = $this->getHLCustomer($customerData, $orderData, $customer);
                $api = new HeyLoyaltyAPI($this->api_key, $this->api_secret);
                $response = $api->addMember($this->list_id, $HLCustomer);
            }
        } catch(Exception $e) {}
    }

    public function hookActionPaymentConfirmation($params)
    {
        try {
            $order = new Order($params['id_order']);
            $customer = $order->getCustomer();
            $api = new HeyLoyaltyAPI($this->api_key, $this->api_secret);
            $member = $api->getMemberByEmail($this->list_id, $customer->email);;
            if (isset($member['members'][0]['id'])) {
                $customerData = $this->getCustomerData($customer);
                $orderData = $this->getOrderData($customer);
                $HLCustomer = $this->getHLCustomer($customerData, $orderData, $customer);
                $api->updateMember($member['members'][0]['id'], $this->list_id, $HLCustomer);
            } elseif ($customer->newsletter) {
                $customerData = $this->getCustomerData($customer);
                $orderData = $this->getOrderData($customer);
                $HLCustomer = $this->getHLCustomer($customerData, $orderData, $customer);
                $api->addMember($this->list_id, $HLCustomer);
            }
        } catch(Exception $e) {}
    }

    protected function getHLCustomer($customerData, $orderData, $customer)
    {
        $member = [
            'firstname' => $customerData['firstname'],
            'lastname' => $customerData['lastname'],
            'email' => $customerData['email'],
            'mobile' => $customerData['phone'],
            'sex' => $customer->id_gender,
            'birthdate' => $customerData['birthday'],
            'address' => $customerData['address'],
            'postalcode' => $customerData['zip'],
            'city' => $customerData['city'],
            'order_count' => $orderData['total_orders'],
            'average_products_per_order' => round($orderData['average_quantity']),
            'total_products_ordered' => $orderData['total_products'],
            'spent_average' => round(Tools::convertPrice($orderData['average_spent'])),
            'spent_total' => round(Tools::convertPrice($orderData['total_spent'])),
            'spent_last_order' => round(Tools::convertPrice($orderData['spent_last_order'])),
            'discount' => $customerData['discount'],
            'customer_type' => $customerData['customerType'],
            'last_order_date' => $orderData['last_order'],
            'first_order_date' => $orderData['first_order']
        ];
        $member['mobile'] = str_replace('+', '00', $member['mobile']);
        $member['mobile'] = preg_replace("/[^a-zA-Z0-9]/", "", $member['mobile']);
        if ($member['sex'] == 0) {
            unset($member['sex']);
        }
        if (preg_match("/[^0-9]/", $member['postalcode'], $match)) {
            unset($member['postalcode']);
        }
        if (!preg_match("/[^ÅÆÉØåæéøa-zA-Z 0-9]/", $member['address'], $match)) {
            unset($member['address']);
        }
        foreach ($member as $key => $field) {
            if (is_null($field) || $field == '') {
                unset($member[$key]);
            }
        }
        return $member;
    }

    protected function convertToReadableDate($date)
    {
        $time = strtotime($date);
        if ($time <= 0) {
            return false;
        }
        return date('Y-m-d', $time);
    }

    protected function getOrdersFromMultipleCustomers($customers)
    {
        $orderData = [
            'first_order' => null,
            'last_order' => null,
            'total_products' => 0,
            'total_spent' => 0,
            'total_orders' => 0,
            'average_spent' => 0,
            'average_quantity' => 0,
            'dicount_codes' => '',
            'spent_last_order' => 0
        ];
        foreach ($customers as $customer) {
            $orders = Order::getCustomerOrders($customer['id_customer']);
            $orderCount = count($orders);
            if ($orderCount == 0) {
                continue;
            }
            foreach ($orders as $order) {
                $time = strtotime($date);
                $quantity = (int) $order['nb_products'];
                $order = new Order($order['id_order']);
                $cart_rules = $order->getCartRules();
                if (!empty($cart_rules)) {
                    foreach ($cart_rules as $i => $cart_rule) {
                        $cart_rule = new CartRule($cart_rule['id_cart_rule']);
                        if ($orderData['discount_codes'] != '') {
                            $orderData['discount_codes'] .= ', ';
                        }
                        $orderData['discount_codes'] .= $cart_rule->code;
                    }
                }
                $orderData['total_products'] += $quantity;
                $orderData['total_spent'] += (float) $order->total_paid_tax_incl;
            }
            $firstOrder = end($orders)['date_add'];
            if (!is_null($orderData['first_order'])) {
                if (strtotime($orderData['first_order']) > strtotime($firstOrder)) {
                    $orderData['first_order'] = $firstOrder;
                }
            } else {
                $orderData['first_order']  = $firstOrder;
            }
            $lastOrder = $orders[0];
            if (!is_null($orderData['last_order'])) {
                if (strtotime($orderData['last_order']) < strtotime($lastOrder['date_add'])) {
                    $orderData['last_order'] = $lastOrder;
                    $orderData['spent_laste_order'] = $lastOrder->total_paid_tax_incl;
                }
            } else {
                $orderData['last_order']  = $lastOrder;
                $orderData['spent_laste_order'] = $lastOrder->total_paid_tax_incl;
            }
            $orderData['total_orders'] += count($orders);
        }
        $orderData['first_order'] = $this->convertToReadableDate($orderData['first_order']);
        $orderData['last_order'] = $this->convertToReadableDate($orderData['last_order']);
        if ($orderData['total_orders'] != 0) {
            $orderData['spent_avarage'] = $orderData['total_spent'] / $orderData['total_orders'];
            $orderData['average_quantity'] = $orderData['total_products'] / $orderData['total_orders'];
        }
        return $orderData;
    }

    protected function getCustomersByEmail($email)
    {
        return Db::getInstance()->ExecuteS('
                    SELECT `id_customer`, `email`, `firstname`, `lastname`
                    FROM `'._DB_PREFIX_.'customer`
                    WHERE `active` = 1
                    AND `email` = \''.pSQL($email).'\'');
    }

    protected function getOrderData($customer)
    {
        $orders = Order::getCustomerOrders($customer->id);
        $customers = $this->getCustomersByEmail($customer->email);
        if (count($customers) > 1) {
            return $this->getOrdersFromMultipleCustomers($customers);
        }
        $orderCount = count($orders);
        if ($orderCount == 0) {
            return [
                'first_order' => null,
                'last_order' => null,
                'total_products' => 0,
                'total_spent' => 0,
                'total_orders' => 0,
                'average_spent' => 0,
                'average_quantity' => 0,
                'dicount_codes' => '',
                'spent_last_order' => 0
            ];
        }
        $totalProducts = 0;
        $totalSpent = 0;
        $discountCodes = '';
        foreach ($orders as $order) {
            $quantity = (int) $order['nb_products'];
            $order = new Order($order['id_order']);
            $cart_rules = $order->getCartRules();
            if (!empty($cart_rules)) {
                foreach ($cart_rules as $i => $cart_rule) {
                    $cart_rule = new CartRule($cart_rule['id_cart_rule']);
                    if ($i > 0) {
                        $discountCodes .= ', ';
                    }
                    $discountCodes .= $cart_rule->code;
                }
            }
            $totalProducts += $quantity;
            $totalSpent += (float) $order->total_paid_tax_incl;
        }
        return [
            'first_order' => $this->convertToReadableDate(end($orders)['date_add']),
            'last_order' => $this->convertToReadableDate($orders[0]['date_add']),
            'total_products' => $totalProducts,
            'total_spent' => $totalSpent,
            'total_orders' => $orderCount,
            'average_spent' => $totalSpent / $orderCount,
            'average_quantity' => $totalProducts / $orderCount,
            'discount_codes' => $discountCodes,
            'spent_last_order' => $orders[0]['total_paid_tax_incl']
        ];
    }

    protected function getCustomerData($customer)
    {
        $addresses = $customer->getAddresses($this->context->language->id);
        $address = new Address($addresses[0]['id_address']);
        $formatted_address = $address->address1;
        $orderData = $this->getOrderData($customer);
        $customer_type = new Group($customer->id_default_group);
        $gender = new Gender($customer->id_gender);
        $newsletter_date_add = $this->convertToReadableDate($customer->newsletter_date_add);

        return [
            'phone' => (empty($address->phone_mobile)) ? $address->phone : $address->phone_mobile,
            'id' => $customer->id,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,
            'gender' => $gender->name[$this->context->language->id],
            'birthday' => $this->convertToReadableDate($customer->birthday),
            'address' => $formatted_address,
            'zip' => $address->postcode,
            'city' => $address->city,
            'country' => $address->country->iso_code,
            'discount' => ((int) $customer_type->reduction) . '%',
            'customerType' => $customer_type->name[$this->context->language->id],
            'shopId' => $this->context->shop->id,
            'newsletterPermission' => ($newsletter_date_add === false) ? 0 : 1,
            'newsletterSignup' => $newsletter_date_add
        ];
    }

    public function generateData($start, $stop, $return = false)
    {
        $excel_data = [];
        $emails = [];
        $customers = Customer::getCustomers();
        $AddressPatternRules = Tools::jsonDecode(Configuration::get('PS_INVCE_DELIVERY_ADDR_RULES'), true);
        $c = 1;
        foreach ($customers as $customer) {
            if ($customer['id_customer'] == 0 || $c < $start || $c > $stop) {
                // If this customer does not exist

                $c++;
                continue;
            }
            $customer = new Customer($customer['id_customer']);
            $customerData = $this->getCustomerData($customer);
            if (isset($emails[$customerData['email']])) {
                continue;
            }
            $orderData = $this->getOrderData($customer);

            $emails[$customerData['email']] = $customerData['email'];
            $customer_data = [
                $customerData['id'],
                $customerData['firstname'],
                $customerData['lastname'],
                $customerData['email'],
                $customerData['phone'],
                $customerData['gender'],
                $customerData['birthday'],
                $customerData['address'],
                $customerData['zip'],
                $customerData['city'],
                $customerData['country'],
                $orderData['first_order'],
                $orderData['total_orders'],
                $orderData['average_quantity'],
                $orderData['total_products'],
                Tools::convertPrice($orderData['average_spent']),
                Tools::convertPrice($orderData['total_spent']),
                Tools::convertPrice($orderData['spent_last_order']),
                $customerData['discount'],
                $orderData['discount_codes'],
                $customerData['customerType'],
                $customerData['shopId'],
                $customerData['newsletterPermission'],
                $customerData['newsletterSignup'],
                $orderData['last_order']
            ];

            if (Module::isInstalled('loyalty') && Module::isEnabled('loyalty')) {
                include_once(dirname(dirname(__FILE__)) . '/loyalty/LoyaltyStateModule.php');
                include_once(dirname(dirname(__FILE__)) . '/loyalty/LoyaltyModule.php');
                $details = LoyaltyModule::getAllByIdCustomer($customer->id, $this->context->language->id);
                $points = (int) LoyaltyModule::getPointsByCustomer($customer->id);
                $bonus_name = Configuration::get('PS_LOYALTY_VOUCHER_DETAILS', $this->context->language->id);
                $points_in_real_currency = LoyaltyModule::getVoucherValue($points, (int) Configuration::get('PS_CURRENCY_DEFAULT'));
                $exchange_rate = (($points_in_real_currency / $points) * 100) . '%';
                $customer_data[] = $bonus_name;
                $customer_data[] = $points;
                $customer_data[] = $points_in_real_currency;
                $customer_data[] = $exchange_rate;
            }

            $excel_data[] = $customer_data;
            $c++;
        }

        file_put_contents(dirname(__FILE__) . '/data/data-' . $start . '-' . $stop . '.json', json_encode($excel_data));
        if (!$return) {
            echo json_encode([
                    'start' => $start,
                    'stop' => $stop,
                    'limit' => count($customers)
            ]);
            return $excel_data;
        } else {
            return json_encode([
                    'start' => $start,
                    'stop' => $stop,
                    'limit' => count($customers)
            ]);
        }
    }

    public function getExcelData($id_list = '', $only_subscribed = false)
    {
        $api = new HeyLoyaltyAPI($this->api_key, $this->api_secret);
        $dir = dirname(__FILE__) . '/data';
        $files = scandir($dir);
        $data = [
            [
                'reference',
                'firstname',
                'lastname',
                'email',
                'mobile',
                'sex',
                'birthdate',
                'address',
                'postalcode',
                'city',
                'country',
                'first_order_date',
                'order_count',
                'average_products_per_order',
                'total_products_ordered',
                'spent_average',
                'spent_total',
                'spent_last_order',
                'discount_level',
                'discount_codes',
                'customertype',
                'store',
                'newsletter_yes_no',
                'newsletter_signup_date',
                'last_order_date',
                'bonus_name',
                'bonus_point',
                'bonus_amount',
                'bonus_rate'
            ]
        ];

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $append = json_decode(file_get_contents($dir . '/' . $file));
            $range = str_replace(array('data-', '.json'), '', $file);
            $range = explode('-', $range);
            $filtered = array();
            if (!empty($id_list) || $only_subscribed) {
                foreach ($append as $filtering) {
                    if ($only_subscribed) {
                        if ($filtering[27] !== 'No') {
                            $filtered[] = $filtering;
                        }
                    } else {
                        $filtered[] = $filtering;
                    }
                }
            }
            if (!empty($id_list) || $only_subscribed) {
                $data = array_merge($data, $filtered);
            } else {
                $data = array_merge($data, $append);
            }
        }
        if (isset($_REQUEST['debug'])) {
            print_r($this->ExportedCustomersByID);
            exit;
        }
        return $data;
    }

    protected function addSubHeaderStyling(&$activeSheet, $col, $row)
    {
        $cell = $col . $row;
        $activeSheet->getStyle($cell . ':' . $cell)->applyFromArray(
            array(
                'fill' => array(
                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'CCCCCC')
                ),
                'alignment' => array(
                    'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
                ),
                'font'  => array(
                    'bold'  => false,
                    'color' => array('rgb' => '000000'),
                    'size'  => 11
                )
            )
        );
        $activeSheet->getColumnDimension($col)->setAutoSize(true);
        $activeSheet->getRowDimension($row)->setRowHeight(18);
    }

    protected function makeExcel($excel_data)
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="HeyLoyalty-Export.xlsx"');
        header('Cache-Control: max-age=0');
        csvmakeExcel($excel_data);
        exit;
    }

    protected function makeCSV($excel_data)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=HeyLoyalty-Export.csv');
        $fp = fopen('php://output', 'w');
        fwrite($fp, chr(239) . chr(187) . chr(191));
        foreach ($excel_data as $line) {
            $value = $line;
            fputcsv($fp, $value, ";");
        }
        fclose($fp);
        exit;
    }

    public function getContent()
    {
        $output = '';
        $is_running = Configuration::get('HeyLoyalty_running_import');
        if ($is_running == $_SERVER['REMOTE_ADDR']) {
            Configuration::updateValue('HeyLoyalty_running_import', '0');
        }
        if (Tools::getValue('generateData')) {
            $start = Tools::getValue('start');
            $stop  = Tools::getValue('stop');
            $this->generateData($start, $stop);
            exit;
        }
        $only_subscribed = false;
        if ($_REQUEST['only_subscribed'] == 1) {
            $only_subscribed = true;
        }
        if (Tools::getValue('export'.$this->name.'CSV')) {
            $excel_data = $this->getExcelData('', $only_subscribed);
            $this->makeCSV($excel_data);
        } elseif (Tools::getValue('export'.$this->name.'Excel')) {
            $excel_data = $this->getExcelData('', $only_subscribed);
            $this->makeExcel($excel_data);
        } elseif (isset($_POST['submitHeyLoyaltyAuth'])) {
            $api_key = Tools::getValue('api_key');
            if (isset($_POST['api_key'])) {
                Configuration::updateValue('HeyLoyalty_api_key', $api_key);
            }
            $api_secret = Tools::getValue('api_secret');
            if (isset($_POST['api_secret'])) {
                Configuration::updateValue('HeyLoyalty_api_secret', $api_secret);
            }
            if (isset($_POST['changeList'])) {
                $listId = Tools::getValue('changeList');
                Configuration::updateValue('Heyloyalty_list_id', $listId);
            }
            if ($_POST['only_subscribed'] == 1) {
                Configuration::updateValue('HeyLoyalty_only_subscribed', $_POST['only_subscribed']);
            } else {
                Configuration::updateValue('HeyLoyalty_only_subscribed', 0);
            }
        }
        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        $this->api_key = Configuration::get('HeyLoyalty_api_key');
        $this->api_secret = Configuration::get('HeyLoyalty_api_secret');
        $this->only_subscribed = Configuration::get('HeyLoyalty_only_subscribed');
        $domain = $this->context->shop->domain;
        $physical_uri = $this->context->shop->physical_uri;
        $virtual_uri = $this->context->shop->virtual_uri;
        $url = $domain . $physical_uri . $virtual_uri;

        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        if (version_compare($this->ps_versions_compliancy['max'], '1.6', '<')) {
            $output .= '<link rel="stylesheet" href="//' . $url . 'modules/HeyLoyalty/css/backend.v15.css?timestamp=' . time() . '"/>';
        }

        $output .= '<form id="configuration_form" class="defaultForm form-horizontal HeyLoyalty" action="" method="post" enctype="multipart/form-data" novalidate="">';
            $output .= '<div class="panel">';
                $output .= '<div class="panel-heading">' . $this->l('3 Steps and your HeyLoyalty integration is up and running.') . '</div>';
                $output .= '<div class="form-wrapper clearfix">';
                    $output .= '<p> First insert your HeyLoyalty API key and API secret. (Press save when ready for next step)</p>';
                    $output .= '<div class="form-group">';
                        $output .= '<label class="control-label col-lg-3 required">';
                            $output .= $this->l('API Key');
                        $output .= '</label>';
                        $output .= '<div class="col-lg-3">';
                            $output .= '<input type="text" id="api_key" name="api_key" class="" value="' . $this->api_key . '" required="required">';
                        $output .= '</div>';
                    $output .= '</div>';
                    $output .= '<div class="form-group">';
                        $output .= '<label class="control-label col-lg-3 required">';
                            $output .= $this->l('API Secret');
                        $output .= '</label>';
                        $output .= '<div class="col-lg-3">';
                            $output .= '<input type="text" id="api_secret" name="api_secret" class="" value="' . $this->api_secret . '" required="required">';
                        $output .= '</div>';
                    $output .= '</div>';
            if (!empty($this->api_key) && !empty($this->api_secret)) {
                $api = new HeyLoyaltyAPI($this->api_key, $this->api_secret);
                $lists = $api->getLists();
                if (!empty($lists)) {
                    $output .= '<p> Next choose which list you want new members synced to: </p>';
                    $output .= '<div class="form-group">';
                        $output .= '<label class="control-label col-lg-3 required">';
                            $output .= 'List';
                        $output .= '</label>';
                        $output .= '<div class="col-lg-3">';
                            $output .= '<select style="display:inline-block;width: 200px;" name="changeList" id="changeList">';
                                $output .= '<option value="">Please select a list</option>';
                                foreach ($lists as $list) {
                                    $output .= '<option '. (($_COOKIE['changeList'] == $list['id']) ? 'selected="selected"' : '') . ' value="' . $list['id'] . '">' . $list['name'] . '</option>';
                                }
                            $output .= '</select> ';
                        $output .= '</div>';
                    $output .= '</div>';
                }
            }
                $output .= '<div class="form-group">';
                    $output .= '<button style="margin-right:10px;" type="submit" value="1" name="submitHeyLoyaltyAuth" class="btn btn-default pull-right">';
                       $output .= '<i class="process-icon-save"></i> ' . $this->l('Save');
                    $output .= '</button>';
                $output .= '</div>';
                $output .= '<div class="panel" id="fieldset_0">';
                    $output .= '<div class="clearfix">'; //panel-footer
                        $output .= '<progress style="display:none;width:100%;height:24px;margin-bottom:15px;" value="0" max="100" class="update-progressbar"></progress>';
                            $output .= '<div style="display:none;
                            background-color: rgba(0,0,0,0.1);
                            border: 1px solid;
                            padding: 10px 20px;
                            margin-bottom: 20px;
                            max-height:200px;
                            overflow-y:auto;" id="importProgress"></div>';
                                $output .= '<button type="submit" value="1" id="exportHeyLoyaltyCSV" name="exportHeyLoyaltyCSV" class="btn btn-default pull-right">';
                                    $output .= '<i class="process-icon-save"></i> ' . $this->l('Download CSV');
                                $output .= '</button>';
                                $output .= '<button style="margin-right:10px;" type="submit" value="1" id="exportHeyLoyaltyExcel" name="exportHeyLoyaltyExcel" class="btn btn-default pull-right">';
                                    $output .= '<i class="process-icon-save"></i> ' . $this->l('Download Excel');
                                $output .= '</button>';
                            $output .= '</div>';
                        $output .= '</div>';
                    $output .= '</div>';
                $output .= '</div>';
            $output .= '</form>';
            if (!empty($this->api_key) && !empty($this->api_secret)) {
                $langs = Language::getLanguages();
                $currencies = Currency::getCurrencies();
                $base = '//' . $this->context->shop->domain . __PS_BASE_URI__;
                foreach ($langs as $lang){
                    if ($lang['id_shop'] != $this->context->shop->id) {
                        continue;
                    }
                    foreach ($currencies as $cur) {
                        if ($cur['id_shop'] != $this->context->shop->id) {
                            continue;
                        }
                        $output .= '<li><strong>'.$this->l('Export in').' <span style="color:#268CCD">'.$lang['name'].'</span>, with prices in <span style="color:#268CCD">'.$cur['name'].'</span> : </strong><br />
                            <a href="'.$base.'modules/'.$this->name.'/feed/?lang='.$lang['iso_code'].'&amp;currency='.$cur['iso_code'].'" >http:'.$base.'modules/'.$this->name.'/feed/?lang='.$lang['iso_code'].'&amp;currency='.$cur['iso_code'].'</a></li>';
                    }
                }
            }

            $output .= '<script>
                if(typeof baseDir == \'undefined\'){
                    var baseDir = \'' . $physical_uri . $virtual_uri . '\';
    }
        </script>';
        $output .= '<script src="//' . $url . 'modules/HeyLoyalty/js/backend.js?timestamp=' . time() . '"></script>';
        return $output;
    }
}
