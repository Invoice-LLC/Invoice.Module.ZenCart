<?php

require('includes/application_top.php');

class callback {

    public $tranId, $id, $amount, $status, $signature, $notification;

    public function __construct($data)
    {
        $this->notification = json_decode($data);
        $this->tranId = $this->notification['id'];
        $this->id = $this->notification["order"]["id"];
        $this->amount = $this->notification["order"]["amount"];
        $this->status = $this->notification["status"];
        $this->signature = $this->notification["signature"];

        switch ($this->notification["notification_type"]) {
            case "pay" :
                switch ($this->status) {
                    case "successful":
                        $this->pay();
                        break;
                    case "error":
                        $this->error();
                        break;
                }
                break;
        }
    }

    public function pay() {
        global $db;

        if(!$this->check()) {
            echo "Error";
            return;
        }

        $order_status_id = MODULE_PAYMENT_INVOICE_PAID_STATUS_ID;
        $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . $order_status_id . "', last_modified = now() where orders_id = '" . $this->id . "'");
        $sql_data_array = array('orders_id' => $this->id,
            'orders_status_id' => $order_status_id,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Paid(Invoice)');
        zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        echo "OK";
    }

    public function error() {
        global $db;

        if(!$this->check()) {
            echo "Error";
            return;
        }

        $order_status_id = MODULE_PAYMENT_INVOICE_FAILED_STATUS_ID;
        $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . $order_status_id . "', last_modified = now() where orders_id = '" . $this->id . "'");
        $sql_data_array = array('orders_id' => $this->id,
            'orders_status_id' => $order_status_id,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Paid(Invoice)');
        zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        echo "OK";
    }

    private function check() {
        global $db;

        $order_query = $db->Execute("select * from " . TABLE_ORDERS . " where orders_id = '" . $this->id . "'");

        if (!$order_query->RecordCount()) {
            return false;
        }

        if($this->signature != $this->getSignature($this->tranId, $this->status,  MODULE_PAYMENT_INVOICE_API_KEY)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $id - Payment ID
     * @param string $status - Payment status
     * @param string $key - API Key
     * @return string Payment signature
     */
    public function getSignature($id, $status, $key) {
        return md5($id.$status.$key);
    }
}

$postData = file_get_contents('php://input');
new callback($postData);