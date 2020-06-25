<?php
include "InvoiceSDK/RestClient.php";
include "InvoiceSDK/CREATE_PAYMENT.php";
include "InvoiceSDK/CREATE_TERMINAL.php";
include "InvoiceSDK/common/ORDER.php";
include "InvoiceSDK/common/SETTINGS.php";

class invoice
{
    //var $code, $title, $description, $enabled;
    // class constructor
    function invoice() {

        $this->code = 'invoice';
        $this->title = "Invoice";
        $this->description = "Invoice Payment Module";
        $this->sort_order = "Sort order";
        $this->enabled = true;
    }

    function update_status() {
        return false;
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
        return array('id' => $this->code, 'module' => $this->title);
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        return false;
    }

    function process_button() {
        return false;
    }

    function before_process() {
        return false;
    }

    function after_process() {
        global $insert_id, $cart, $order, $currencies;

        $currency_value = $order->info['currency_value'];
        $rate = (zen_not_null($currency_value)) ? $currency_value : 1;

        $sum = ($order->info['total'] * $rate);

        $payment = $this->createPayment($sum, $insert_id);

        $_SESSION['cart']->reset(true);

        unset($_SESSION['sendto']);
        unset($_SESSION['billto']);
        unset($_SESSION['shipping']);
        unset($_SESSION['payment']);
        unset($_SESSION['comments']);

        zen_redirect($payment->payment_url);

    }

    /**
     * @return TerminalInfo
     */
    function createTerminal() {
        $request = new CREATE_TERMINAL("ZenCartTerminal");
        $request->description = "";
        $request->type = "dynamical";

        return $this->getRestClient()->CreateTerminal($request);
    }

    private function setTerminal($value) {
        $fp = fopen('invoice_tid', 'w');
        fwrite($fp, $value);
        fclose($fp);
    }

    /**
     * @return string - Terminal ID
     */
    private function getTerminal() {
        $name = "invoice_tid";
        $fp = fopen($name, 'r');
        $value = fread($fp, filesize($name));
        fclose($fp);

        return $value;
    }

    function checkOrCreateTerminal() {
        $tid = $this->getTerminal();
        if($tid == null or empty($tid)) {
            $terminal = $this->createTerminal();
            if($terminal == null or isset($terminal->error)) {
                return "";
            }
            $tid = $terminal->id;
            $this->setTerminal($tid);
        }
        return $tid;
    }

    function createPayment($amount, $orderId) {
        $tid = $this->checkOrCreateTerminal();
        $order = new INVOICE_ORDER($amount);
        $order->id = $orderId;
        $settings = new SETTINGS($tid);
        $settings->success_url = $_SERVER['REQUEST_URI'];

        $request = new CREATE_PAYMENT($order, $settings, []);

        return $this->getRestClient()->CreatePayment($request);
    }

    function getRestClient() {
        $key = MODULE_PAYMENT_INVOICE_API_KEY;
        $login = MODULE_PAYMENT_INVOICE_LOGIN;

        return new RestClient($login, $key);
    }

    function output_error() {
        return false;
    }

    function check() {
        global $db;

        return true;
    }

    function install() {

        global $db;

        $pay_status_id = $this->createOrderStatus("Paid");
        $failed_status_id = $this->createOrderStatus("Failed");
        //config params

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            'Enabled', 
            'MODULE_PAYMENT_INVOICE_ENABLE', 
            '', 
            '', 
            '6', '0', now())"
        );

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            'API Key', 
            'MODULE_PAYMENT_INVOICE_API_KEY', 
            '', 
            '', 
            '6', '0', now())"
        );
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            'Login', 
            'MODULE_PAYMENT_INVOICE_LOGIN', 
            '', 
            '', 
            '6', '0', now())"
        );


        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values (
            'Paid', 
            'MODULE_PAYMENT_INVOICE_PAID_STATUS_ID', 
            '".$pay_status_id."',  
            '', 
            '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values (
            'Failed', 
            'MODULE_PAYMENT_INVOICE_FAILED_STATUS_ID', 
            '".$failed_status_id."',  
            '', 
            '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    }

    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return array(
            'MODULE_PAYMENT_INVOICE_API_KEY',
            'MODULE_PAYMENT_INVOICE_LOGIN',
            'MODULE_PAYMENT_INVOICE_PAID_STATUS_ID',
            'MODULE_PAYMENT_INVOICE_FAILED_STATUS_ID',
        );
    }

    function createOrderStatus( $title ){
        global $db;

        $q = $db->Execute("select orders_status_id from ".TABLE_ORDERS_STATUS." where orders_status_name = '".$title."' limit 1");
        if ($q->RecordCount() < 1) {
            $q = $db->Execute("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
            $row = $q->current();
            $status_id = $row['status_id'] + 1;
            $languages = zen_get_languages();

            foreach ($languages as $lang) {
                $db->Execute("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', " . "'" . $title . "')");
            }
        }else{
            $status = $q->current();
            $status_id = $status['orders_status_id'];
        }
        return $status_id;
    }
}