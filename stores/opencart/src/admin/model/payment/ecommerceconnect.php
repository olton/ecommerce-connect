<?php
namespace Opencart\Admin\Model\Extension\Ecommerceconnect\Payment;

class Ecommerceconnect extends \Opencart\System\Engine\Model {

	public function install() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ecommerceconnect_session`;");
		$this->createMissingTables();
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ecommerceconnect_session`;");
	}

	public function createMissingTables(){

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ecommerceconnect_session` (
			`ecommerceconnect_session_id` INT(11) NOT NULL AUTO_INCREMENT,
			`session_id` VARCHAR(255) NOT NULL,
			`session_data` TEXT,
			PRIMARY KEY (`ecommerceconnect_session_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
		");		
	}
}
