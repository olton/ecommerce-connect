<?php
namespace Opencart\Catalog\Model\Extension\Ecommerceconnect\Payment;

class Ecommerceconnect extends \Opencart\System\Engine\Model {


	public function getMethods(array $address): array {
		$this->load->language('extension/ecommerceconnect/payment/ecommerceconnect');
		
		
		if (!$this->config->get('payment_ecommerceconnect_geo_zone_id')) {
			$status = true;
		} elseif(isset($address['country_id']) && isset($address['zone_id'])) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_ecommerceconnect_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");
			if ($query->num_rows) {
				$status = true;
			} else {
				$status = false;
			}
		}else{
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$option_data['ecommerceconnect'] = [
				'code' => 'ecommerceconnect.ecommerceconnect',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'ecommerceconnect',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_ecommerceconnect_sort_order')
			];
		}

		return $method_data;
	}

	public function addOrder($data) {

		$orderByThisId= $this->getOrder($data['order_id']);
		if ($orderByThisId && $orderByThisId['payment_status'] != 'failed_3dsv1') {

		}else{
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ecommerceconnect_order` SET `order_id` = '" . (int)$data['order_id'] . "', `payment_id` = '" . $this->db->escape($data['payment_id']) . "'");
		}
		
	}

	public function getOrder($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ecommerceconnect_order` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY ecommerceconnect_order_id DESC LIMIT 1");

		return $query->row;
	}


	public function updateOrder($data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "ecommerceconnect_order` SET `payment_status` = '" . $this->db->escape($data['payment_status']) . "' WHERE `payment_id` = '" . $this->db->escape($data['payment_id']) . "'");
	}


	public function storeSession($data) {

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ecommerceconnect_session` WHERE `session_id` = '" . $data['session_id'] . "' ORDER BY `ecommerceconnect_session_id` ASC  LIMIT 1");

		if($query->num_rows){
			$this->db->query("UPDATE  `" . DB_PREFIX . "ecommerceconnect_session` SET `session_data` = '" .  $this->db->escape($data['session_data'])  . "' WHERE `session_id` = '" . $data['session_id'] . "'");
		}else{
			$this->db->query("INSERT INTO `" . DB_PREFIX . "ecommerceconnect_session` SET `session_id` = '" . $data['session_id'] . "', `session_data` = '" . $this->db->escape($data['session_data']) . "'");
		}

	}

	public function fetchSession($sessionId) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ecommerceconnect_session` WHERE `session_id` = '" . $sessionId . "' ORDER BY `ecommerceconnect_session_id` ASC  LIMIT 1");

		return $query->row;
	}

	
	public function log($data, $class_step = 6, $function_step = 6) {
		if ($this->config->get('payment_ecommerceconnect_debug')) {
			$backtrace = debug_backtrace();
			$log = new Log('ecommerceconnect.log');
			$log->write('(' . $backtrace[$class_step]['class'] . '::' . $backtrace[$function_step]['function'] . ') - ' . print_r($data, true));
		}
	}
}
