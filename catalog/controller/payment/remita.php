<?php

/**
 * Plugin Name: Remita OpenCart Payment Gateway
 * Plugin URI:  https://www.remita.net
 * Description: Remita OpenCart Payment gateway allows you to accept payment on your OpenCart store via Visa Cards, Mastercards, Verve Cards, eTranzact, PocketMoni, Paga, Internet Banking, Bank Branch and Remita Account Transfer.
 * Author:      SystemSpecs Limited
 * Version:     1.0
 */
class ControllerPaymentRemita extends Controller {

    public function index() {
        $this->language->load('payment/remita');

        $data['text_testmode'] = $this->language->get('text_testmode');
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $order_id = $this->session->data['order_id'];
        if ($order_info) {
            $data['remita_publickey'] = trim($this->config->get('remita_publickey'));
            $data['remita_secretkey'] = trim($this->config->get('remita_secretkey'));
            $mode = trim($this->config->get('remita_mode'));
            $data['remita_mode'] = trim($this->config->get('remita_mode'));
            $data['storeorderid'] = $this->session->data['order_id'];
            $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
            $data['totalAmount'] = html_entity_decode($data['total']);
            $data['payment_firstname'] = $order_info['payment_firstname'];
            $data['payment_lastname'] = $order_info['payment_lastname'];
            $data['payerName'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
            $data['payerEmail'] = $order_info['email'];
            $data['payerPhone'] = html_entity_decode($order_info['telephone'], ENT_QUOTES, 'UTF-8');
            $data['button_confirm'] = $this->language->get('button_confirm');
            $uniqueRef = uniqid();
            $data['transactionId'] = $uniqueRef . '_' . $data['storeorderid'];
            $data['returnurl'] = $this->url->link('payment/remita/callback', 'trxref='. rawurlencode($data['transactionId']), 'SSL');

            if ($mode == 0) {
                $data['gateway_url'] = 'https://remitademo.net/payment/v1/remita-pay-inline.bundle.js';
            } else if ($mode == 1) {
                $data['gateway_url'] = 'https://login.remita.net/payment/v1/remita-pay-inline.bundle.js';
            }

        }


        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/remita.tpl')) {
            return $this->load->view($this->config->get('config_template') . '/template/payment/remita.tpl', $data);
        } else {
            return $this->load->view('default/template/payment/remita.tpl', $data);
        }
    }

    function remita_transaction_details() {
        // Callback remita to get real remita transaction status
        if (trim($this->config->get('remita_mode')) == 0) {
            $query_url = 'https://remitademo.net/payment/v1/payment/query/';
        } else if (trim($this->config->get('remita_mode')) == 1) {
            $query_url = 'https://login.remita.net/payment/v1/payment/query/';
        }
        $trxref = $this->request->get['trxref'];
        $url = $query_url . $trxref ;
        $hash_string = $trxref . trim($this->config->get('remita_secretkey'));
        $txnHash = hash('sha512', $hash_string);

        $header = array(
            'Content-Type: application/json',
            'publicKey:' . trim($this->config->get('remita_publickey')),
            'TXN_HASH:' . $txnHash
        );


        //  Initiate curl
        $ch = curl_init();

        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        // Set the url
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set the header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);


        // Execute
        $result = curl_exec($ch);

        // Closing
        curl_close($ch);

        // decode json
        $response = json_decode($result, true);

        return $response;
    }

    private function redir_and_die($url, $onlymeta = false)
    {
        if (!headers_sent() && !$onlymeta) {
            header('Location: ' . $url);
        }
        echo "<meta http-equiv=\"refresh\" content=\"0;url=" . addslashes($url) . "\" />";
        die();
    }

    public function callback() {

        //echo "Return URL";

        if (isset($this->request->get['trxref'])) {
            $trxref = $this->request->get['trxref'];

            $order_id = substr($trxref, 0, strpos($trxref, '_'));

            $response = $this->remita_transaction_details();
            $data['response_code'] = $response['responseCode'];
            $data['response_msg'] = $response['responseMsg'];
            //$paymentState = $response['responseData']['0']['paymentState'];
            //$amount = $response['responseData']['0']['amount'];

            $order_details = explode('_', $trxref);
            //$remitaorderid = $order_details[0];
            $storeorder_id = $order_details[1];
            $data['order_id'] = $storeorder_id;
            $this->load->model('checkout/order');



            if($response['responseCode'] == "00" && $response['responseData']['0']['paymentState']='SUCCESSFUL'){




                $order_status_id = $this->config->get('remita_processed_status_id');
                $redir_url = $this->url->link('checkout/success');


            } elseif ($data['response_code'] == "34"){

                $order_status_id = $this->model_checkout_order->addOrderHistory($order_id, 1, $data['response_msg']);
                $redir_url = $this->url->link('checkout/failure', 'responseMessage='.$response['responseMsg'], 'SSL');

            }else{
                $order_status_id = $this->model_checkout_order->addOrderHistory($order_id, 1, $data['response_msg']);
                $redir_url = $this->url->link('checkout/checkout', '', 'SSL');
            }

            $this->model_checkout_order->addOrderHistory($storeorder_id, $order_status_id);
            $this->redir_and_die($redir_url);

        }
    }

}

?>