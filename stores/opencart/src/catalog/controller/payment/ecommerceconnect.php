<?php
namespace Opencart\Catalog\Controller\Extension\Ecommerceconnect\Payment;

class Ecommerceconnect extends \Opencart\System\Engine\Controller
{
    private function setSession()
	{
        $this->load->model('extension/ecommerceconnect/payment/ecommerceconnect');
        
		$rawSessionData = $this->session->data; 

		$serializedSession = json_encode($rawSessionData);

        $this->log->write("Session Write::".$serializedSession);

		try {
			$this->model_extension_ecommerceconnect_payment_ecommerceconnect->storeSession(array(
				'session_id' => $this->session->getId(),
				'session_data' => $serializedSession,
			));
		} catch (Exception $e) {
			$this->log->write("db error" . $e->getMessage());
		}

	}

	private function getSession($sessionId): string
	{

		$this->load->model('extension/ecommerceconnect/payment/ecommerceconnect');

        if($this->session->getId() != $sessionId){
            $sessionDataOnDB = $this->model_extension_ecommerceconnect_payment_ecommerceconnect->fetchSession($sessionId);
            $this->session->data = json_decode(($sessionDataOnDB['session_data']), true);
        }
		
		return $this->session->data['order_id'];
	}

    private function buildPaymentForm()
    {

 		$totals = [];
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$this->load->model('checkout/cart');

		if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
			($this->model_checkout_cart->getTotals)($totals, $taxes, $total);
		}
        
        $amount = number_format($total, 2, '.', '')*100;
        $language = strtoupper(substr($this->config->get('config_language'), 0, 2));

        if ($language == 'EN') {
            $locale = 'en';
        } else {
            $locale = 'ua';
        }

        $currency = $this->session->data['currency'];
        $currency_code = '980';

        $purchaseTime = date("ymdHis");

        $formattedOrderId = str_pad($this->session->data['order_id'], 3, '0', STR_PAD_LEFT);

        $session = $this->session->getId();
        $order_id = $formattedOrderId;
        
        $merchant_id = $this->config->get('payment_ecommerceconnect_merchant_id_0');
        $terminal_id = $this->config->get('payment_ecommerceconnect_terminal_id_0');
       
        $pem = $this->config->get('payment_ecommerceconnect_pem_0');
        
        $data_str = "$merchant_id;$terminal_id;$purchaseTime;$order_id;$currency_code;$amount;$session;";

        $pkeyid = openssl_get_privatekey($pem); 
		openssl_sign($data_str, $signature, $pkeyid); 
		unset($pkeyid); 
		$b64sign = base64_encode($signature);

        $return_url = $this->url->link('extension/ecommerceconnect/payment/ecommerceconnect.callback', '', true);
        $cancel_url = $this->url->link('extension/ecommerceconnect/payment/ecommerceconnect.cancel&session='.$this->session->getId(), '', true);

        $attributes = [
            "MerchantID" => $merchant_id,
            "TerminalID" => $terminal_id,
            "TotalAmount" => $amount,
            "Currency" => $currency_code,
            "Locale" => $locale,
            "PurchaseTime" => $purchaseTime,
            "OrderID" => $order_id,
            "Session" => $session,
            "Signature" => $b64sign
        ];

        ksort($attributes);
        $message = '';
        foreach ($attributes as $key => $value) {
            $message .= $key . $value;
        }

        $data = $attributes;
        $data['logged'] = $this->customer->isLogged();
        $data['url'] = $this->config->get('payment_ecommerceconnect_url_0');
        $this->setSession();
        return $this->load->view('extension/ecommerceconnect/payment/ecommerceconnect', $data);
    }

    /**
     * Callback
     *
     * @return view
     */
    private function handlePaymentResponse()
    {
        if (!isset($_POST) || empty($_POST)) {
            exit;
        }

        $this->load->language('extension/ecommerceconnect/payment/ecommerceconnect');
        $this->load->model('extension/ecommerceconnect/payment/ecommerceconnect');
        $this->load->model('checkout/order');

        $this->getSession($this->request->post['SD']);

        $json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

        
        if (!isset($this->session->data['order_id'])) {

            $this->log->write("Order was not found in session");
            $json['error']['warning'] = $this->language->get('error_order');

        } else {


            $cert = $this->config->get('payment_ecommerceconnect_cert_0');

            $MerchantID = $this->request->post['MerchantID'];
            $TerminalID = $this->request->post['TerminalID'];
            $OrderID = $this->request->post['OrderID'];
            $PurchaseTime = $this->request->post['PurchaseTime'];
            $TotalAmount = $this->request->post['TotalAmount'];
            $CurrencyID = $this->request->post['Currency'];
            $XID = $this->request->post['XID'];
            $TranCode = $this->request->post['TranCode'];
            $ApprovalCode = $this->request->post['ApprovalCode'];
            $SD = $this->request->post['SD'];

            $data = "$MerchantID;$TerminalID;$PurchaseTime;$OrderID;$XID;$CurrencyID;$TotalAmount;$SD;$TranCode;$ApprovalCode;";

            $signature_response = base64_decode($this->request->post["Signature"]);

            $pubkeyid = openssl_get_publickey($cert);
            $ok = openssl_verify($data, $signature_response, $pubkeyid);

            if ($ok == 1){
                //payment accepted
                $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_ecommerceconnect_approved_status_id'), '', true);
            } 
            else if ($ok == 0){
                //payment failed
                $this->log->write("payment fail other reason");
                $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_ecommerceconnect_failed_status_id'), '', true);
            }else{
                //bad signature
                $this->log->write("signaure mismatch");
                $json['error']['warning'] = $this->language->get('error_signature');
                $this->model->model_extension_ecommerceconnect_payment_ecommerceconnect->log("Error signature did not match");
            }

            unset($pubkeyid);

            print
	        "MerchantID=".$MerchantID."\n".
	        "TerminalID=".$TerminalID."\n".
	        "OrderID=".$OrderID."\n".
	        "Currency=".$CurrencyID."\n".
	        "TotalAmount=".$TotalAmount."\n".
	        "XID=".$XID."\n".
            "TranCode = " . $TranCode . "\n".
	        "PurchaseTime=".$PurchaseTime."\n\n".
	        "Response.action=approve\n".
	        "Response.reason=OK\n".
	        "Response.forwardUrl=".$this->url->link('checkout/success')."\n";
        }

        return $this->response->redirect($json['redirect']);
    }

    public function index(): string
    {
        return $this->buildPaymentForm();
    }

    public function callback(): string 
    {
        return $this->handlePaymentResponse();
    }

    public function cancel(): string 
    {
        $this->getSession($_GET['session']);
        $this->log->write("Cancel returned, session restored to ".$_GET['session']);
        $redirect = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
        return $this->response->redirect($redirect);
    }

    public function confirm(): void
    {
        $this->load->language('extension/ecommerceconnect/payment/ecommerceconnect');

        $json = [];

       if (!isset($this->session->data['order_id'])) {
            $json['error']['warning'] = $this->language->get('error_order');
        }

        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method'] != 'ecommerceconnect') {
            $json['error']['warning'] = $this->language->get('error_payment_method');
        }

        if (!$json) {

            if ($this->config->get('payment_ecommerceconnect_response')) {

               $this->load->model('checkout/order');

                $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_ecommerceconnect_approved_status_id'), '', true);

                $json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
            } else {
                $this->load->model('checkout/order');

                $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_ecommerceconnect_failed_status_id'), '', true);

                $json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}