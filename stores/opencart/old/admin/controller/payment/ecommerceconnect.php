<?php
namespace Opencart\Admin\Controller\Extension\Ecommerceconnect\Payment;

class Ecommerceconnect extends \Opencart\System\Engine\Controller {

	public function index(): void {

		$this->load->language('extension/ecommerceconnect/payment/ecommerceconnect');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/ecommerceconnect/payment/ecommerceconnect', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/ecommerceconnect/payment/ecommerceconnect|save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');


		//restore form with previous data
		$data['payment_ecommerceconnect_approved_status_id'] = $this->config->get('payment_ecommerceconnect_approved_status_id');
		$data['payment_ecommerceconnect_failed_status_id'] = $this->config->get('payment_ecommerceconnect_failed_status_id');
		$data['payment_ecommerceconnect_geo_zone_id'] = $this->config->get('payment_ecommerceconnect_geo_zone_id');
		$data['payment_ecommerceconnect_status'] = $this->config->get('payment_ecommerceconnect_status');
		$data['payment_ecommerceconnect_sort_order'] = $this->config->get('payment_ecommerceconnect_sort_order');

		$data['payment_ecommerceconnect_pem_0'] = $this->config->get('payment_ecommerceconnect_pem_0');
		$data['payment_ecommerceconnect_cert_0'] = $this->config->get('payment_ecommerceconnect_cert_0');

		$data['payment_ecommerceconnect_url_0'] = $this->config->get('payment_ecommerceconnect_url_0');
		$data['payment_ecommerceconnect_merchant_id_0'] = $this->config->get('payment_ecommerceconnect_merchant_id_0');
		$data['payment_ecommerceconnect_terminal_id_0'] = $this->config->get('payment_ecommerceconnect_terminal_id_0');
	

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
				

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
	
		$this->response->setOutput($this->load->view('extension/ecommerceconnect/payment/ecommerceconnect', $data));
	}

	public function save(): void {
		
		$this->load->language('extension/ecommerceconnect/payment/ecommerceconnect');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/ecommerceconnect/payment/ecommerceconnect')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_ecommerceconnect', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/payment')) {
			$this->load->model('extension/ecommerceconnect/payment/ecommerceconnect');

			$this->model_extension_ecommerceconnect_payment_ecommerceconnect->install();
		}
	}

	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/payment')) {
			$this->load->model('extension/ecommerceconnect/payment/ecommerceconnect');

			$this->model_extension_ecommerceconnect_payment_ecommerceconnect->uninstall();
		}
	}
}
