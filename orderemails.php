<?php

if ( !defined( '_PS_VERSION_' ) )
  exit;

class orderemails extends Module {

	private $country_support;
	private $PS_VERSION;

	public function __construct() {
		$this->name = 'orderemails';
		$this->tab = 'shipping_logistics';
		$this->version = '1.4';
		$this->author = 'SN Solutions';
		$this->need_instance = 0;
		$this->module_key = '99da336dc05649250fd95d97cb370cd3';
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall this module and delete its settings?');

		parent::__construct();

		$this->displayName = $this->l('Order emails');
		$this->description = $this->l('Send order emails to suppliers (dropshipping)');

		$this->country_support = ($this->getConfig('PS_ORDEREMAILS_COUNTRY_SUPPORT') == 1 ? true : false);
		$this->PS_VERSION = substr(_PS_VERSION_, 0, 3);
    }

	public function install() {
		if ($this->PS_VERSION == "1.5") {
			$shops = Shop::getShops();
			foreach ($shops as $shop) {
				Configuration::updateValue('PS_ORDEREMAILS_FROM', Configuration::get('PS_SHOP_EMAIL', null, null, $shop['id_shop']), false, null, $shop['id_shop']);
				Configuration::updateValue('PS_ORDEREMAILS_FROMNAME', Configuration::get('PS_SHOP_NAME', null, null, $shop['id_shop']), false, null, $shop['id_shop']);
				Configuration::updateValue('PS_ORDEREMAILS_COUNTRY_SUPPORT', 0, false, null, $shop['id_shop']);
			}
		} else {
			Configuration::updateValue('PS_ORDEREMAILS_FROM', Configuration::get('PS_SHOP_EMAIL'));
			Configuration::updateValue('PS_ORDEREMAILS_FROMNAME', Configuration::get('PS_SHOP_NAME'));
			Configuration::updateValue('PS_ORDEREMAILS_COUNTRY_SUPPORT', 0);
		}
		return (
			parent::install() &&
			$this->registerHook( ($this->PS_VERSION == '1.5' ? 'actionValidateOrder' : 'newOrder') ) &&
			$this->registerHook( ($this->PS_VERSION == '1.5' ? 'actionOrderStatusUpdate' : 'postUpdateOrderStatus') ) &&
			$this->installSQL() &&
			$this->insertData()
		);
	}

	private function installSQL() {
		$sql = file_get_contents(dirname(__FILE__) . '/install.sql');
		$sql = str_replace('_DB_PREFIX_', _DB_PREFIX_, $sql);
		$sql = str_replace('_MYSQL_ENGINE_', _MYSQL_ENGINE_, $sql);
		$sql = explode("#[NEW_QUERY]#", $sql);
		foreach ($sql as $query) {
			if (!Db::getInstance()->Execute($query)) {
				print Db::getInstance()->getMsgError();
				return false;
			}
		}
		$this->updateDatabase();
		return true;
	}

	private function insertData() {
		$db = Db::getInstance();
		if ($this->PS_VERSION == "1.5") {
			$shops = Shop::getShops();
			foreach ($shops as $shop) {
				$db->Execute("INSERT INTO `"._DB_PREFIX_."orderemails_addresses` (`id_shop`, `title`, `address`) VALUES (".(int)$shop['id_shop'].", '', '')");
				$db->Execute("INSERT INTO `"._DB_PREFIX_."orderemails_email_templates` (`id_shop`, `content_type`, `title`, `subject`, `content`)
						 	  VALUES (".(int)$shop['id_shop'].", 'text/plain', 'Default template', 'New order from ".pSQL($shop['name'])."', '------------------------
To: [supplier_name]
OrderID: [id_order]
------------------------
[products]
Picture: [image_link]
Product: [product_name]
Reference: [supplier_reference]
Supplier: [supplier_name]
Wholesale price: [wholesale_price]
Price: [price]
Final price: [final_price]
Quantity: [product_quantity]
[/products]
------------------------
Delivery address:
[delivery_address]
------------------------
Best regards
".pSQL($shop['name'])."');");
			}
		} else {
			$db->Execute("INSERT INTO `"._DB_PREFIX_."orderemails_addresses` (`title`, `address`) VALUES ('', '')");
			$db->Execute("INSERT INTO `"._DB_PREFIX_."orderemails_email_templates` (`content_type`, `title`, `subject`, `content`)
						  VALUES ('text/plain', 'Default template', 'New order from ".pSQL(Configuration::get('PS_SHOP_NAME'))."', '------------------------
To: [supplier_name]
OrderID: [id_order]
------------------------
[products]
Picture: [image_link]
Product: [product_name]
Reference: [supplier_reference]
Supplier: [supplier_name]
Wholesale price: [wholesale_price]
Price: [price]
Final price: [final_price]
Quantity: [product_quantity]
[/products]
------------------------
Delivery address:
[delivery_address]
------------------------
Best regards
".pSQL(Configuration::get('PS_SHOP_NAME'))."');");
		}
		return true;
	}

	private function updateDatabase() {
		if (!Configuration::get('PS_ORDEREMAILS_UPDATED_TO_1_3')) {
			$this->update_1_3();
		}
		if (!Configuration::get('PS_ORDEREMAILS_UPDATED_TO_1_4')) {
			$this->update_1_4();
		}
	}

	private function update_1_3() {
		$sql = "SHOW COLUMNS FROM `"._DB_PREFIX_."orderemails` LIKE 'id_country'";
		$column = Db::getInstance()->Execute($sql);
		if (Db::getInstance()->numRows() == 0) {
			$sql = "ALTER TABLE `"._DB_PREFIX_."orderemails` ADD `id_country` INT NOT NULL DEFAULT '0' AFTER `id_supplier` , ADD INDEX ( `id_country` )";
			if (Db::getInstance()->Execute($sql)) {
				Db::getInstance()->Execute("UPDATE `"._DB_PREFIX_."orderemails` SET `id_country` = " . (int)Configuration::get('PS_COUNTRY_DEFAULT'));
			}
		}
		Configuration::updateValue('PS_ORDEREMAILS_UPDATED_TO_1_3', 1);
	}

	private function update_1_4() {
		$sql = "SHOW COLUMNS FROM `"._DB_PREFIX_."orderemails` LIKE 'id_shop'";
		$column = Db::getInstance()->Execute($sql);
		if (Db::getInstance()->numRows() == 0) {
			$sql = "ALTER TABLE `"._DB_PREFIX_."orderemails` ADD `id_shop` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id_supplier` , ADD INDEX ( `id_shop` )";
			Db::getInstance()->Execute($sql);

			$sql = "ALTER TABLE `"._DB_PREFIX_."orderemails_addresses` ADD `id_shop` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id_address` , ADD INDEX ( `id_shop` )";
			Db::getInstance()->Execute($sql);

			$sql = "ALTER TABLE `"._DB_PREFIX_."orderemails_templates` ADD `id_shop` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id_email_template` , ADD INDEX ( `id_shop` )";
			Db::getInstance()->Execute($sql);

			$sql = "ALTER TABLE `"._DB_PREFIX_."orderemails_sent_emails` ADD `id_shop` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `id_supplier` , ADD INDEX ( `id_shop` )";
			Db::getInstance()->Execute($sql);
		}
		Configuration::updateValue('PS_ORDEREMAILS_UPDATED_TO_1_4', 1);
	}

	public function uninstall()
	{
		Configuration::deleteByName('PS_ORDEREMAILS_FROM');
		Configuration::deleteByName('PS_ORDEREMAILS_FROMNAME');
		Configuration::deleteByName('PS_ORDEREMAILS_COUNTRY_SUPPORT');
		Configuration::deleteByName('PS_ORDEREMAILS_UPDATED_TO_1_3');
		Configuration::deleteByName('PS_ORDEREMAILS_UPDATED_TO_1_4');
		$sql = "DROP TABLE IF EXISTS
					`"._DB_PREFIX_."orderemails`,
					`"._DB_PREFIX_."orderemails_addresses`,
					`"._DB_PREFIX_."orderemails_email_templates`,
					`"._DB_PREFIX_."orderemails_sent_emails`";
		return Db::getInstance()->Execute($sql) && parent::uninstall();
	}

	public function hookpostUpdateOrderStatus( $params ) {
		return $this->sendOrderEmails(new Order($params['id_order']), $params['newOrderStatus']);
	}

	public function hooknewOrder( $params ) {
		return $this->sendOrderEmails($params['order'], $params['orderStatus']);
	}

	// PrestaShop 1.5
	public function hookactionValidateOrder($params) {
		return $this->sendOrderEmails($params['order'], $params['orderStatus']);
	}

	// PrestaShop 1.5
	public function hookactionOrderStatusUpdate($params) {
		return $this->sendOrderEmails(new Order($params['id_order']), $params['newOrderStatus']);
	}

	private function getConfig($name) {
		if ($this->PS_VERSION == "1.5") {
			return Configuration::get($name, null, null, $this->context->shop->id);
		} else {
			return Configuration::get($name);
		}
	}

	public function sendOrderEmails($order, $orderState, $forceSend = false) {
		if (Validate::isLoadedObject($order) && Validate::isLoadedObject($orderState)) {

			// Fetch supplier and all info needed for every supplier
			if ($this->PS_VERSION == "1.5") {
				$sql = "SELECT
							o.id_order, a.firstname as customer_firstname, a.lastname as customer_lastname, a.address1 as customer_address1,
							a.address2 as customer_address2, a.phone as customer_phone, a.phone_mobile as customer_phone_mobile, a.postcode as customer_postcode,
							a.city as customer_city, a.company as customer_company, st.name as customer_state, st.iso_code as customer_state_code,
							co.iso_code as customer_country_code, cl.name as customer_country, oe.emails as supplier_email, oe.order_states,
							s.name as supplier_name, c.email as customer_email, oea.id_address, oea.address as delivery_address, oe.id_email_template,
							oe.emails as send_to, oe.exceptions, oe.id_lang, oe.id_supplier, oet.content_type, oet.subject as email_subject,
							oet.content as email_content, ose.id_supplier as is_sent, o.total_discounts, o.total_paid, o.total_paid_real,
							o.total_products, o.total_products_wt, o.total_shipping, o.carrier_tax_rate, o.total_wrapping, o.invoice_number, o.delivery_number,
							o.conversion_rate
						FROM `"._DB_PREFIX_."orderemails` oe
						LEFT JOIN `"._DB_PREFIX_."orders` o
							ON o.`id_shop` = oe.`id_shop`
						LEFT JOIN `"._DB_PREFIX_."address` a
							ON a.`id_address` = o.`id_address_delivery`
						LEFT JOIN `"._DB_PREFIX_."state` st
							ON st.`id_state` = a.`id_state`
						LEFT JOIN `"._DB_PREFIX_."country` co
							ON co.`id_country` = a.`id_country`
						INNER JOIN `"._DB_PREFIX_."country_shop` cs
							ON cs.`id_country` = co.`id_country` AND cs.`id_shop` = oe.`id_shop`
						LEFT JOIN `"._DB_PREFIX_."country_lang` cl
							ON cl.`id_country` = a.`id_country` AND cl.`id_lang` = oe.`id_lang`
						LEFT JOIN `"._DB_PREFIX_."customer` c
							ON c.`id_customer` = a.`id_customer` AND c.`id_shop` = oe.`id_shop`
						LEFT JOIN `"._DB_PREFIX_."supplier` s
							ON s.`id_supplier` = oe.`id_supplier`
						INNER JOIN `"._DB_PREFIX_."supplier_shop` ss
							ON ss.`id_supplier` = s.`id_supplier` AND ss.`id_shop` = oe.`id_shop`
						LEFT JOIN `"._DB_PREFIX_."orderemails_addresses` oea
							ON oea.`id_address` = oe.`id_address` AND oea.`id_shop` = oe.`id_shop`
						LEFT JOIN `"._DB_PREFIX_."orderemails_email_templates` oet
							ON oet.`id_email_template` = oe.`id_email_template` AND oet.`id_shop` = oe.`id_shop`
						LEFT JOIN `"._DB_PREFIX_."orderemails_sent_emails` ose
							ON ose.`id_order` = o.`id_order` AND ose.`id_supplier` = oe.`id_supplier` AND ose.`id_shop` = oe.`id_shop`
						WHERE
							o.`id_order` = {$order->id} AND
							oe.`id_supplier` IS NOT NULL
							" . ($this->country_support === true ? "AND oe.`id_country` = a.`id_country`" : '') . " AND
							oe.`active` = 1 AND
							oe.`id_shop` = " . (int)$this->context->shop->id . "
						GROUP by oe.`id_supplier`";
			} else {
				$sql = "SELECT
							o.id_order, a.firstname as customer_firstname, a.lastname as customer_lastname, a.address1 as customer_address1,
							a.address2 as customer_address2, a.phone as customer_phone, a.phone_mobile as customer_phone_mobile, a.postcode as customer_postcode,
							a.city as customer_city, a.company as customer_company, st.name as customer_state, st.iso_code as customer_state_code,
							co.iso_code as customer_country_code, cl.name as customer_country, oe.emails as supplier_email, oe.order_states,
							s.name as supplier_name, c.email as customer_email, oea.id_address, oea.address as delivery_address, oe.id_email_template,
							oe.emails as send_to, oe.exceptions, oe.id_lang, oe.id_supplier, oet.content_type, oet.subject as email_subject,
							oet.content as email_content, ose.id_supplier as is_sent, o.total_discounts, o.total_paid, o.total_paid_real,
							o.total_products, o.total_products_wt, o.total_shipping, o.carrier_tax_rate, o.total_wrapping, o.invoice_number, o.delivery_number,
							o.conversion_rate
						FROM `"._DB_PREFIX_."orderemails` oe
						LEFT JOIN `"._DB_PREFIX_."orders` o
							ON o.`id_order` = {$order->id}
						LEFT JOIN `"._DB_PREFIX_."address` a
							ON a.`id_address` = o.`id_address_delivery`
						LEFT JOIN `"._DB_PREFIX_."state` st
							ON st.`id_state` = a.`id_state`
						LEFT JOIN `"._DB_PREFIX_."country` co
							ON co.`id_country` = a.`id_country`
						LEFT JOIN `"._DB_PREFIX_."country_lang` cl
							ON cl.`id_country` = a.`id_country` AND cl.`id_lang` = oe.`id_lang`
						LEFT JOIN `"._DB_PREFIX_."customer` c
							ON c.`id_customer` = a.`id_customer`
						LEFT JOIN `"._DB_PREFIX_."supplier` s
							ON s.`id_supplier` = oe.`id_supplier`
						LEFT JOIN `"._DB_PREFIX_."orderemails_addresses` oea
							ON oea.`id_address` = oe.`id_address`
						LEFT JOIN `"._DB_PREFIX_."orderemails_email_templates` oet
							ON oet.`id_email_template` = oe.`id_email_template`
						LEFT JOIN `"._DB_PREFIX_."orderemails_sent_emails` ose
							ON ose.`id_order` = o.`id_order` AND ose.`id_supplier` = oe.`id_supplier`
						WHERE oe.`id_supplier` IS NOT NULL " . ($this->country_support === true ? "AND oe.`id_country` = a.`id_country`" : '') . " AND oe.`active` = 1
						GROUP by oe.`id_supplier`";
			}
			$suppliers = Db::getInstance()->ExecuteS($sql);

			if (!empty($suppliers)) {
				$link = new Link();
				foreach ($suppliers as $supplier) {
					// Check if email is supposed to be sent to this supplier for the current order state, if not skip this supplier
					$order_states = unserialize($supplier['order_states']);
					if (!in_array($orderState->id, $order_states)) continue;

					// Send emails only if it haven't been sent before or if $forceSend == true
					if ($supplier['is_sent'] == NULL || $forceSend) {
						// Fetch products for this supplier in this order
						if ($this->PS_VERSION == "1.5") {
							$sql = "SELECT ps.id_product, p.id_supplier, ps.wholesale_price, od.product_price as final_price,
									   od.tax_name, od.tax_rate, od.product_reference, od.product_supplier_reference as supplier_reference,
									   ps.price, od.product_name as product_name, pl.description as product_description, pl.description_short as product_description_short,
									   pl.description as product_description_stripped, pl.description_short as product_description_short_stripped,
									   pl.link_rewrite as product_link_rewrite, pl.meta_description as product_meta_description,
									   pl.meta_keywords as product_meta_keywords, pl.meta_title as product_meta_title,
									   pl.available_now as product_available_now, pl.available_later as product_available_later,
									   od.product_quantity, od.product_quantity_discount, od.product_ean13, od.product_upc, od.reduction_percent,
									   od.reduction_amount, od.group_reduction, od.ecotax, od.ecotax_tax_rate, od.discount_quantity_applied,
									   od.product_quantity_in_stock, od.product_quantity_refunded, od.product_quantity_return,
									   od.product_quantity_reinjected
								FROM `"._DB_PREFIX_."order_detail` od
								LEFT JOIN `"._DB_PREFIX_."product` p
									ON p.id_product = od.product_id
								INNER JOIN `"._DB_PREFIX_."product_shop` ps
									ON ps.`id_product` = od.`product_id` AND ps.`id_shop` = od.`id_shop`
								LEFT JOIN `"._DB_PREFIX_."product_lang` pl
									ON pl.id_product = od.product_id
								WHERE od.`id_order` = {$order->id} AND
									  p.`id_supplier` = {$supplier['id_supplier']} AND
									  pl.id_lang = {$supplier['id_lang']} AND
									  od.`id_shop` = " . (int)$this->context->shop->id;
						} else {
							$sql = "SELECT p.id_product, p.id_supplier, p.wholesale_price, od.product_price as final_price,
									   od.tax_name, od.tax_rate, od.product_reference, od.product_supplier_reference as supplier_reference,
									   p.price, od.product_name as product_name, pl.description as product_description, pl.description_short as product_description_short,
									   pl.description as product_description_stripped, pl.description_short as product_description_short_stripped,
									   pl.link_rewrite as product_link_rewrite, pl.meta_description as product_meta_description,
									   pl.meta_keywords as product_meta_keywords, pl.meta_title as product_meta_title,
									   pl.available_now as product_available_now, pl.available_later as product_available_later,
									   od.product_quantity, od.product_quantity_discount, od.product_ean13, od.product_upc, od.reduction_percent,
									   od.reduction_amount, od.group_reduction, od.ecotax, od.ecotax_tax_rate, od.discount_quantity_applied,
									   od.product_quantity_in_stock, od.product_quantity_refunded, od.product_quantity_return,
									   od.product_quantity_reinjected
								FROM `"._DB_PREFIX_."order_detail` od
								LEFT JOIN `"._DB_PREFIX_."product` p
									ON p.id_product = od.product_id
								LEFT JOIN `"._DB_PREFIX_."product_lang` pl
									ON pl.id_product = od.product_id AND pl.id_lang = {$supplier['id_lang']}
								WHERE od.`id_order` = {$order->id} AND
									  p.`id_supplier` = {$supplier['id_supplier']}";
						}
						$products = Db::getInstance()->ExecuteS($sql);

						// If no products, there is no need to send an order email
						if (!empty($products)) {
							$product_exceptions = array();
							foreach (unserialize($supplier['exceptions']) as $exception) {
								$product_exceptions[] = $exception['id_product'];
							}

							$email_template = htmlspecialchars_decode($supplier['email_content']);

							$plain_text = ($supplier['content_type'] == 'text/plain' ? true : false);
							if ($this->PS_VERSION == "1.5") {
								$addressCondition = ($supplier['id_address'] != $this->context->shop->id ? true : false);
							} else {
								$addressCondition = ($supplier['id_address'] != 1 ? true : false);
							}
							if ($addressCondition) {
								// Supplier specific address
								$email_template = str_replace('[delivery_address]', ($plain_text ? $supplier['delivery_address'] : nl2br($supplier['delivery_address'])), $email_template);
							} else {
								// Customer's address
								$nl = ($plain_text ? "\n" : "<br />");
								$cAddress = $supplier['customer_firstname'] . ' ' . $supplier['customer_lastname'] . $nl;
								$cAddress .= $supplier['customer_address1'] . $nl;
								$cAddress .= ($supplier['customer_address2'] == '' ? $supplier['customer_address2'] . $nl : '');
								$cAddress .= $supplier['customer_postcode'] . " " . $supplier['customer_city'];
								$email_template = str_replace('[delivery_address]', $cAddress, $email_template);
							}

							// We must do these separately in case the order was payed in a different currency than the default
							$currency_dependent = array('total_shipping', 'total_paid', 'total_paid_real', 'total_products', 'total_products_wt', 'total_wrapping');
							foreach ($currency_dependent as $tag) {
								$email_template = str_replace("[$tag]", round($supplier[$tag]/$supplier['conversion_rate'], 6), $email_template);
							}

							// Replace the non-product specific tags
							foreach ($supplier as $tag => $value) {
								$email_template = str_replace("[$tag]", $value, $email_template);
							}

							preg_match('#\[products\](.*?)\[\/products\]#si', $email_template, $matches);
							$product_template = $matches[1];

							$product_content = '';
							$total_products = 0;
							$total_products_wholesale_price = 0;
							foreach ($products as $product) {
								// Skip this product if it's been added to the product exceptions
								if (in_array($product['id_product'], $product_exceptions)) continue;

								$id_image = Product::getCover($product['id_product']);
								$imageLink = $link->getImageLink($product['id_product'] . '-' . $id_image['id_image'] . '-thickbox', $product['id_product'] . '-' . $id_image['id_image']);
								$temp = $product_template;
								$temp = str_replace('[image_link]', $imageLink, $temp);
								$temp = str_replace('[product_line_price]', round($product['wholesale_price']*$product['product_quantity'], 6), $temp);
								$temp = str_replace('[product_line_price_wt]', round(($product['final_price']/$supplier['conversion_rate'])*$product['product_quantity'], 6), $temp);

								// We must do these separately in case the order was payed in a different currency than the default
								$currency_dependent = array('final_price');
								foreach ($currency_dependent as $tag) {
									$temp = str_replace("[$tag]", round($product[$tag]/$supplier['conversion_rate'], 6), $temp);
								}

								foreach ($product as $placeholder => $value) {
									// Strip HTML tags if the placeholder ends with '_stripped'
									if (mb_substr($placeholder, -9, 9, 'utf-8') == '_stripped') {
										$value = strip_tags($value);
									}
									$temp = str_replace("[$placeholder]", $value, $temp);
								}
								$product_content .= $temp;
								$total_products++;
								$total_products_wholesale_price += $product['wholesale_price']*$product['product_quantity'];
							}

							$total_products_wholesale_price = round($total_products_wholesale_price, 6);

							// Replace the [total_products_wholesale_price] tag
							$email_template = str_replace('[total_products_wholesale_price]', $total_products_wholesale_price, $email_template);

							$email_content = preg_replace('#\[products\](.*?)\[\/products\]#si', $product_content, $email_template);

							// No need to send if there are no products for this supplier
							if ($total_products > 0) {
								if ($this->send($supplier['send_to'], $supplier['email_subject'], $email_content, $supplier['content_type'])) {
									if ($this->PS_VERSION == "1.5") {
										$sql = "INSERT into `"._DB_PREFIX_."orderemails_sent_emails`(`id_order`, `id_order_state`, `id_supplier`, `id_shop`, `sent_to`)
												VALUES({$order->id}, {$orderState->id}, {$supplier['id_supplier']}, ".(int)$this->context->shop->id.", '".pSQL($supplier['send_to'])."')";
									} else {
										$sql = "INSERT into `"._DB_PREFIX_."orderemails_sent_emails`(`id_order`, `id_order_state`, `id_supplier`, `sent_to`)
												VALUES({$order->id}, {$orderState->id}, {$supplier['id_supplier']}, '".pSQL($supplier['send_to'])."')";
									}
									Db::getInstance()->Execute($sql);
								}
							}
						}
					}
				}
			}
		}
	}

	public function send($to, $subject, $content, $content_type) {
		include_once(_PS_SWIFT_DIR_.'Swift.php');
		include_once(_PS_SWIFT_DIR_.'Swift/Connection/SMTP.php');
		include_once(_PS_SWIFT_DIR_.'Swift/Connection/NativeMail.php');

		$to = explode(',', $to);
		$die = false;
		if (is_array($to)) {
			$to_list = new Swift_RecipientList();
			foreach ($to AS $key => $addr)
			{
				$addr = trim($addr);
				if (!Validate::isEmail($addr))
				{
					Tools::dieOrLog(Tools::displayError('Error: invalid email address'), $die);
					return false;
				}
				$to_list->addTo($addr, null);
			}
			$to_plugin = $to[0];
			$to = $to_list;
		} else {
			/* Simple recipient, one address */
			$to_plugin = $to;
			$to = new Swift_Address($to, $toName);
		}
		$configuration = Configuration::getMultiple(array('PS_MAIL_METHOD', 'PS_MAIL_SERVER', 'PS_MAIL_USER', 'PS_MAIL_PASSWD', 'PS_MAIL_SMTP_ENCRYPTION', 'PS_MAIL_SMTP_PORT', 'PS_MAIL_METHOD', 'PS_ORDEREMAILS_FROM', 'PS_ORDEREMAILS_FROMNAME'));
		try {
			/* Connect with the appropriate configuration */
			if ($configuration['PS_MAIL_METHOD'] == 2) {
				if (empty($configuration['PS_MAIL_SERVER']) OR empty($configuration['PS_MAIL_SMTP_PORT'])) {
					Tools::dieOrLog(Tools::displayError('Error: invalid SMTP server or SMTP port'), $die);
					return false;
				}
				$connection = new Swift_Connection_SMTP($configuration['PS_MAIL_SERVER'], $configuration['PS_MAIL_SMTP_PORT'], ($configuration['PS_MAIL_SMTP_ENCRYPTION'] == "ssl") ? Swift_Connection_SMTP::ENC_SSL : (($configuration['PS_MAIL_SMTP_ENCRYPTION'] == "tls") ? Swift_Connection_SMTP::ENC_TLS : Swift_Connection_SMTP::ENC_OFF));
				$connection->setTimeout(4);
				if (!$connection) {
					return false;
				}
				if (!empty($configuration['PS_MAIL_USER'])) {
					$connection->setUsername($configuration['PS_MAIL_USER']);
				}
				if (!empty($configuration['PS_MAIL_PASSWD'])) {
					$connection->setPassword($configuration['PS_MAIL_PASSWD']);
				}
			}
			else {
				$connection = new Swift_Connection_NativeMail();
			}
			if (!$connection) {
				return false;
			}
			$swift = new Swift($connection, Configuration::get('PS_MAIL_DOMAIN'));

			$message = new Swift_Message($subject);
			$message->attach(new Swift_Message_Part($content, $content_type, '8bit', 'utf-8'));

			/* Send mail */
			$send = $swift->send($message, $to, new Swift_Address($configuration['PS_ORDEREMAILS_FROM'], $configuration['PS_ORDEREMAILS_FROMNAME']));
			$swift->disconnect();

			return $send;
		} catch (Swift_ConnectionException $e) { return false; }
	}

	public function getSuppliers($id_country) {
		global $cookie;

		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT s.*, sl.*, oe.emails, oe.active, oea.id_address, oea.title as address_title
					FROM `"._DB_PREFIX_."supplier` s
					INNER JOIN `"._DB_PREFIX_."supplier_shop` ss
						ON ss.`id_supplier` = s.`id_supplier`
					LEFT JOIN `"._DB_PREFIX_."orderemails` oe
						ON oe.id_supplier = s.id_supplier " . ($this->country_support === true ? "AND oe.id_country = " . (int)$id_country : "") . " AND oe.`id_shop` = ss.`id_shop`
					LEFT JOIN `"._DB_PREFIX_."supplier_lang` sl
						ON sl.id_supplier = s.id_supplier
					LEFT JOIN `"._DB_PREFIX_."orderemails_addresses` oea
						ON oea.id_address = oe.id_address AND oea.`id_shop` = ss.`id_shop`
					WHERE ss.`id_shop` = " . (int)$this->context->shop->id . " AND sl.id_lang = '{$cookie->id_lang}'
					ORDER by s.`name` asc";
		} else {
			$sql = "SELECT s.*, sl.*, oe.emails, oe.active, oea.id_address, oea.title as address_title FROM `"._DB_PREFIX_."supplier` s
				LEFT JOIN `"._DB_PREFIX_."supplier_lang` sl
					ON sl.id_supplier = s.id_supplier AND sl.id_lang = '{$cookie->id_lang}'
				LEFT JOIN `"._DB_PREFIX_."orderemails` oe
					ON oe.id_supplier = s.id_supplier " . ($this->country_support === true ? "AND oe.id_country = " . (int)$id_country : "") . "
				LEFT JOIN `"._DB_PREFIX_."orderemails_addresses` oea
					ON oea.id_address = oe.id_address
				ORDER by s.`name` asc";
		}
		return Db::getInstance()->ExecuteS($sql);
	}

	public function getSupplier($id_supplier, $id_country = NULL) {
		global $cookie;
		if ($id_country === NULL) {
			$id_country = $this->getConfig('PS_COUNTRY_DEFAULT');
		}
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT s.*, sl.*, oe.emails, oe.id_address, oe.id_lang as email_lang, oe.order_states, oe.id_email_template, oea.title as address_title, oe.active, oe.`exceptions`
				FROM `"._DB_PREFIX_."supplier` s
				INNER JOIN `"._DB_PREFIX_."supplier_shop` ss
					ON ss.`id_supplier` = s.`id_supplier`
				LEFT JOIN `"._DB_PREFIX_."supplier_lang` sl
					ON sl.id_supplier = s.id_supplier
				LEFT JOIN `"._DB_PREFIX_."orderemails` oe
					ON oe.id_supplier = s.id_supplier " . ($this->country_support === true ? "AND oe.id_country = " . (int)$id_country : "") . " AND oe.`id_shop` = ss.`id_shop`
				LEFT JOIN `"._DB_PREFIX_."orderemails_addresses` oea
					ON oea.id_address = oe.id_address AND oea.`id_shop` = ss.`id_shop`
				WHERE s.id_supplier = ".(int)$id_supplier." AND ss.`id_shop` = " . (int)$this->context->shop->id . " AND sl.id_lang = '{$cookie->id_lang}'
				ORDER by s.`name` asc";
		} else {
			$sql = "SELECT s.*, sl.*, oe.emails, oe.id_address, oe.id_lang as email_lang, oe.order_states, oe.id_email_template, oea.title as address_title, oe.active, oe.`exceptions`
				from `"._DB_PREFIX_."supplier` s
				LEFT JOIN `"._DB_PREFIX_."supplier_lang` sl
					ON sl.id_supplier = s.id_supplier AND sl.id_lang = '{$cookie->id_lang}'
				LEFT JOIN `"._DB_PREFIX_."orderemails` oe
					ON oe.id_supplier = s.id_supplier " . ($this->country_support === true ? "AND oe.id_country = " . (int)$id_country : "") . "
				LEFT JOIN `"._DB_PREFIX_."orderemails_addresses` oea
					ON oea.id_address = oe.id_address
				WHERE s.id_supplier = ".(int)$id_supplier."
				ORDER by s.`name` asc";
		}
		$supplier = Db::getInstance()->ExecuteS($sql);
		return $supplier[0];
	}

	public function getAddresses() {
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_addresses`
					WHERE `id_shop` = " . (int)$this->context->shop->id . "
					ORDER by `title` ASC";
		} else {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_addresses`
					ORDER by `title` ASC";
		}
		return Db::getInstance()->ExecuteS($sql);
	}

	public function getAddress($id_address) {
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_addresses`
					WHERE `id_address` = " . (int)$id_address . " AND `id_shop` = " . (int)$this->context->shop->id . "
					ORDER by `title` ASC";
		} else {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_addresses`
					WHERE `id_address` = " . (int)$id_address . "
					ORDER by `title` ASC";
		}
		$address = Db::getInstance()->ExecuteS($sql);
		return (count($address) > 0 ? $address[0] : array('title' => '', 'address' => ''));
	}

	public function getEmailTemplates() {
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_email_templates`
					WHERE `id_shop` = " . (int)$this->context->shop->id . "
					ORDER by `title` asc";
		} else {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_email_templates`
					ORDER by `title` asc";
		}
		return Db::getInstance()->ExecuteS($sql);
	}

	public function getEmailTemplate($id_email) {
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_email_templates`
					WHERE `id_email_template` = " . (int)$id_email . " AND `id_shop` = " . (int)$this->context->shop->id;
		} else {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails_email_templates`
					WHERE `id_email_template` = " . (int)$id_email;
		}
		$email = Db::getInstance()->ExecuteS($sql);
		return (count($email) > 0 ? $email[0] : array('title' => '', 'subject' => '', 'content' => '', 'content_type' => ''));
	}

	public function getSentEmails($p = 0, $n = 20) {
		global $cookie;
		if ($this->PS_VERSION == 1.5) {
			$sql = "SELECT ose.*, osl.name as order_state
					FROM `"._DB_PREFIX_."orderemails_sent_emails` ose
					LEFT JOIN `"._DB_PREFIX_."order_state_lang` osl
						ON osl.id_order_state = ose.id_order_state AND osl.id_lang = ".(int)$cookie->id_lang."
					WHERE ose.`id_shop` = " . (int)$this->context->shop->id . "
					ORDER by ose.`id_sent_email` DESC
					LIMIT " . ($p*$n) . ", $n";
		} else {
			$sql = "SELECT ose.*, osl.name as order_state from `"._DB_PREFIX_."orderemails_sent_emails` ose
					LEFT JOIN `"._DB_PREFIX_."order_state_lang` osl
						ON osl.id_order_state = ose.id_order_state AND osl.id_lang = ".(int)$cookie->id_lang."
					ORDER by ose.`id_sent_email` DESC
					LIMIT " . ($p*$n) . ", $n";
		}
		return Db::getInstance()->ExecuteS($sql);
	}

	public function getTotalSentEmails() {
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT COUNT(*) as total from `"._DB_PREFIX_."orderemails_sent_emails`
					WHERE `id_shop` = " . (int)$this->context->shop->id;
		} else {
			$sql = "SELECT COUNT(*) as total from `"._DB_PREFIX_."orderemails_sent_emails`";
		}
		$result = Db::getInstance()->ExecuteS($sql);
		return $result[0]['total'];
	}

	public function getProducts($id_supplier) {
		global $cookie;
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT p.`id_product`, pl.`name` from `"._DB_PREFIX_."product` p
					INNER JOIN `"._DB_PREFIX_."product_shop` ps
						ON ps.`id_product` = p.`id_product`
					LEFT JOIN `"._DB_PREFIX_."product_lang` pl
						ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = {$cookie->id_lang}
					WHERE p.`id_supplier` = " . (int)$id_supplier . " AND ps.`id_shop` = " . (int)$this->context->shop->id . "
					ORDER by pl.`name` ASC";
		} else {
			$sql = "SELECT p.`id_product`, pl.`name` from `"._DB_PREFIX_."product` p
					LEFT JOIN `"._DB_PREFIX_."product_lang` pl
						ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = {$cookie->id_lang}
					WHERE p.`id_supplier` = " . (int)$id_supplier . "
					ORDER by pl.`name` ASC";
		}
		return Db::getInstance()->ExecuteS($sql);
	}

	public function getTotalProductsOfSupplier($id_supplier) {
		if ($this->PS_VERSION == "1.5") {
			$sql = "SELECT COUNT(*) as total
					FROM `"._DB_PREFIX_."product` p
					INNER JOIN `"._DB_PREFIX_."product_shop` ps
						ON ps.`id_product` = p.`id_product`
					WHERE p.`id_supplier` = " . (int)$id_supplier . " AND ps.`id_shop` = " . (int)$this->context->shop->id;
		} else {
			$sql = "SELECT COUNT(*) as total FROM `"._DB_PREFIX_."product` p
					WHERE p.`id_supplier` = " . (int)$id_supplier;
		}
		$result = Db::getInstance()->ExecuteS($sql);
		return $result[0]['total'];
	}

	public function saveGeneralSettings() {
		Configuration::updateValue('PS_ORDEREMAILS_FROM', Tools::getValue('from_email'));
		Configuration::updateValue('PS_ORDEREMAILS_FROMNAME', Tools::getValue('from_name'));
		Configuration::updateValue('PS_ORDEREMAILS_COUNTRY_SUPPORT', Tools::getValue('country_support'));
		$this->returnToMainView('success', 'General settings have been saved');
	}

	public function saveSupplier($id_supplier, $countries = false) {
		global $cookie;
		if ($this->country_support) {
			if ($countries === false) {
				$countries = Country::getCountries($cookie->id_lang, true);
			} else {
				$countries = array(0 => array('id_country' => $countries));
			}
		} else {
			//dummy array
			$countries = array(array());
		}

		foreach ($countries as $country) {
			$sql = "SELECT * from `"._DB_PREFIX_."orderemails` WHERE `id_supplier` = " . (int)$id_supplier . " AND `id_country` = " . ($this->country_support === true ? (int)$country['id_country'] : $this->getConfig('PS_COUNTRY_DEFAULT'));
			if ($this->PS_VERSION == "1.5") {
				$sql .= " AND `id_shop` = " . (int)$this->context->shop->id;
			}
			$exists = Db::getInstance()->ExecuteS($sql);
			$exceptions = Tools::getValue('products_exceptions');
			$order_states = Tools::getValue('order_states');
			if (!$exceptions || !is_array($exceptions)) {
				$exceptions = array();
			} else {
				if (!empty($exceptions)) {
					foreach ($exceptions as $key => $exception) {
						$data = explode('##', $exception);
						$exceptions[$key] = array('id_product' => $data[0], 'name' => $data[1]);
					}
				}
			}
			if (!is_array($order_states)) {
				$order_states = array();
			}
			if (count($exists) > 0) {
				$sql = "UPDATE `"._DB_PREFIX_."orderemails`
						SET `emails` = '".pSQL(Tools::getValue('emails'))."',
							`id_address` = ".(int)Tools::getValue('id_address').",
							`id_email_template` = ".(int)Tools::getValue('id_email_template').",
							`id_lang` = ".(int)Tools::getValue('id_lang') . ",
							`order_states` = '".serialize($order_states)."',
							`active` = " . (int)Tools::getValue('supplier_active') . ",
							`exceptions` = '".serialize($exceptions)."'
						WHERE id_supplier = " . (int)$id_supplier . " AND `id_country` = " . ($this->country_support === true ? (int)$country['id_country'] : $this->getConfig('PS_COUNTRY_DEFAULT')) . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
				Db::getInstance()->Execute($sql);
			} else {
				$sql = "INSERT into `"._DB_PREFIX_."orderemails`(id_supplier, id_country," . ($this->PS_VERSION == "1.5" ? ' id_shop,' : '') . " id_address, id_email_template, id_lang, emails, order_states, active, `exceptions`)
						VALUES(
							".(int)$id_supplier.",
							".($this->country_support === true ? (int)$country['id_country'] : $this->getConfig('PS_COUNTRY_DEFAULT')).",
							".($this->PS_VERSION == "1.5" ? (int)$this->context->shop->id . ", " : '')."
							".(int)Tools::getValue('id_address').",
							".(int)Tools::getValue('id_email_template').",
							".(int)Tools::getValue('id_lang').",
							'".pSQL(Tools::getValue('emails'))."',
							'".serialize($order_states)."',
							".(int)Tools::getValue('supplier_active').",
							'".serialize($exceptions)."'
						)";
				Db::getInstance()->Execute($sql);
			}
		}
		$this->returnToMainView('success', 'Supplier has been saved');
	}

	public function saveAddress($id_address) {
		$sql = "SELECT * from `"._DB_PREFIX_."orderemails_addresses` WHERE id_address = " . (int)$id_address . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
		$exists = Db::getInstance()->ExecuteS($sql);
		if (count($exists) > 0) {
			$sql = "UPDATE `"._DB_PREFIX_."orderemails_addresses`
					SET `title` = '".pSQL(Tools::getValue('title'))."',
						`address` = '".pSQL(Tools::getValue('address'))."'
					WHERE id_address = " . (int)$id_address . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
			Db::getInstance()->Execute($sql);
			$this->returnToMainView('success', 'The address has been saved');
		} else {
			if (Tools::getValue('title') != '' && Tools::getValue('address') != '') {
				$sql = "INSERT into `"._DB_PREFIX_."orderemails_addresses`(".($this->PS_VERSION == "1.5" ? 'id_shop, ' : '')."title, address)
						VALUES(
							".($this->PS_VERSION == "1.5" ? (int)$this->context->shop->id . ", " : '')."
							'".pSQL(Tools::getValue('title'))."',
							'".pSQL(Tools::getValue('address'))."'
						)";
				Db::getInstance()->Execute($sql);
				$this->returnToMainView('success', 'Address has been added');
			} else {
				return $this->displayError($this->l('Please fill in all fields'));
			}
		}
	}

	public function saveEmailTemplate($id_email) {
		if ( ($this->PS_VERSION != "1.5" && (int)$id_email == 1) || ($this->PS_VERSION == "1.5" && (int)$id_email == $this->context->shop->id) ) {
			return $this->displayError($this->l('You may not edit the default template, copy & paste to a new template to make changes to it.'));
		}
		$sql = "SELECT * from `"._DB_PREFIX_."orderemails_email_templates` WHERE `id_email_template` = " . (int)$id_email . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
		$exists = Db::getInstance()->ExecuteS($sql);
		if (count($exists) > 0) {
			$sql = "UPDATE `"._DB_PREFIX_."orderemails_email_templates`
					SET	`content_type` = '" . pSQL(Tools::getValue('content_type')) . "',
						`title` = '". pSQL(Tools::getValue('title')) . "',
						`subject` = '" . pSQL(Tools::getValue('subject')) . "',
						`content` = '" . pSQL(htmlspecialchars(Tools::getValue('email_content'))) . "'
					WHERE `id_email_template` = " . (int)$id_email . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
			Db::getInstance()->Execute($sql);
			$this->returnToMainView('success', 'Email template has been updated');
		} else {
			if (Tools::getValue('title') != '' && Tools::getValue('email_content') != '') {
				$sql = "INSERT into `"._DB_PREFIX_."orderemails_email_templates`(".($this->PS_VERSION == "1.5" ? '`id_shop`, ' : '')."`content_type`, `title`, `subject`, `content`)
						VALUES(
							".($this->PS_VERSION == "1.5" ? (int)$this->context->shop->id . ", " : '')."
							'".pSQL(Tools::getValue('content_type'))."',
							'".pSQL(Tools::getValue('title'))."',
							'".pSQL(Tools::getValue('subject'))."',
							'".pSQL(htmlspecialchars(Tools::getValue('email_content')))."'
						)";
				Db::getInstance()->Execute($sql);
				$this->returnToMainView('success', 'Email template has been created');
			} else {
				return $this->displayError($this->l('Please fill in all fields'));
			}
		}
	}

	public function clearSupplierAllCountries($id_supplier) {
		$sql = "DELETE from `"._DB_PREFIX_."orderemails`
				WHERE `id_supplier` = " . (int)$id_supplier . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
		if (Db::getInstance()->Execute($sql)) {
			$this->returnToMainView('success', 'All settings for this supplier have been cleared');
		} else {
			$this->displayError($this->l('Could not clear all settings for this supplier'));
		}
	}

	public function deleteAddress($id_address) {
		//Customer's address is not to be removed
		if ( ($this->PS_VERSION != "1.5" && (int)$id_address == 1) || ($this->PS_VERSION == "1.5" && (int)$id_address == $this->context->shop->id) ) {
			return;
		}
		$sql = "SELECT * from `"._DB_PREFIX_."orderemails` WHERE `id_address` = " . (int)$id_address . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
		$result = Db::getInstance()->ExecuteS($sql);
		if (count($result) == 0) {
			$sql = "DELETE from `"._DB_PREFIX_."orderemails_addresses`
					WHERE `id_address` = " . (int)$id_address . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
			Db::getInstance()->Execute($sql);
			$this->returnToMainView('success', 'Address has been deleted');
		} else {
			$this->returnToMainView('error', 'This address is in use and can therefore not be deleted');
		}
	}

	public function deleteEmailTemplate($id_email) {
		//Default template is not to be removed
		if (($this->PS_VERSION != "1.5" && (int)$id_email == 1) || ($this->PS_VERSION == "1.5" && (int)$id_email == $this->context->shop->id)) {
			return;
		}
		$sql = "SELECT * from `"._DB_PREFIX_."orderemails` WHERE `id_email_template` = " . (int)$id_email . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
		$result = Db::getInstance()->ExecuteS($sql);
		if (count($result) == 0) {
			$sql = "DELETE from `"._DB_PREFIX_."orderemails_email_templates`
					WHERE `id_email_template` = " . (int)$id_email . ($this->PS_VERSION == "1.5" ? ' AND `id_shop` = ' . (int)$this->context->shop->id : '');
			Db::getInstance()->Execute($sql);
			$this->returnToMainView('success', 'Email template has been deleted');
		} else {
			$this->returnToMainView('error', 'This email template is in use and can therefore not be deleted');
		}
	}

	public function editSupplierView($id_supplier) {
		global $currentIndex, $cookie;

		$id_country = (Tools::getValue('id_country') ? Tools::getValue('id_country') : $this->getConfig('PS_COUNTRY_DEFAULT'));

		$supplier = $this->getSupplier($id_supplier, $id_country);
		$addresses = $this->getAddresses();
		$email_templates = $this->getEmailTemplates();
		$countries = Country::getCountries($cookie->id_lang, true);

		$output = "";

		if ($this->country_support) {
			$output .= '<form action="' . $currentIndex . '" method="get" id="country_form">';
			$output .= '<input type="hidden" name="tab" value="AdminModules" />';
			$output .= '<input type="hidden" name="configure" value="' . $this->name . '" />';
			$output .= '<input type="hidden" name="token" value="' . Tools::getAdminTokenLite('AdminModules') . '" />';
			$output .= '<input type="hidden" name="edit" value="supplier" />';
			$output .= '<input type="hidden" name="id_supplier" value="' . $id_supplier . '" />';
			$output .= '<fieldset>';
			$output .= '<legend>' . $this->l('Country to apply settings for') . '</legend>';
			$output .= '<label>' . $this->l('Country') . '</label>';
			$output .= '<div class="margin-form">';
			$output .= '	<select name="id_country" id="id_country">';
			foreach ($countries as $country) {
				$output .= '	<option value="' . $country['id_country'] . '"';
				if ($id_country == $country['id_country']) {
					$output .= ' selected="selected"';
				}
				$output .= '>' . $country['name'] . ($country['id_country'] == $this->getConfig('PS_COUNTRY_DEFAULT') ? ' (' . $this->l('shop\'s default country') . ')' : '') . '</option>';
			}
			$output .= '	</select>';
			$output .= '	<script type="text/javascript">';
			$output .= '		$("#id_country").change(function() {
									$("#country_form").submit();
								});';
			$output .= '	</script>';
			$output .= '	<p>' . $this->l('Choose which country to apply the settings for. Apply different settings for customer\'s in different countries.') . '</p>';
			$output .= '</div>';
			$output .= '</fieldset>';
			$output .= '</form>';

			$output .= '<br />';
		} // End country support

		$output .= '<form action="'.$_SERVER['REQUEST_URI'].'" method="post" onsubmit="$(\'#products_exceptions option\').each(function(){$(this).attr(\'selected\', \'selected\');});">';
		$output .= '<input type="hidden" name="id_supplier" value="' . $id_supplier . '" />';
		$output .= '<input type="hidden" name="id_country" value="' . $id_country . '" />';
		$output .= '<fieldset>';

		$output .= '<legend>' . $supplier['name'] . '</legend>';
		$output .= '<label>' . $this->l('Active?') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '	<input id="display_on" type="radio" value="1" '.($supplier['active'] == "1" ? "checked='checked'" : '').' name="supplier_active" />';
		$output .= '	<label class="t" for="display_on">';
		$output .= '		<img title="' . $this->l('Enabled') . '" alt="' . $this->l('Enabled') .'" src="../img/admin/enabled.gif">';
		$output .= '	</label>';
		$output .= '	<input id="display_off" type="radio" '.($supplier['active'] == "0" ? "checked='checked'" : '').' value="0" name="supplier_active">';
		$output .= '	<label class="t" for="display_off">';
		$output .= '		<img title="' . $this->l('Disabled') . '" alt="' . $this->l('Disabled') . '" src="../img/admin/disabled.gif">';
		$output .= '	</label>';
		$output .= '	<p>' . $this->l('Enable or disable order emails for this supplier') . '</p>';
		$output .= '</div>';

		$defaultLanguage = intval($this->getConfig('PS_LANG_DEFAULT'));
        $languages = Language::getLanguages();

		$output .= '<label>' . $this->l('Product language in email') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '	<select name="id_lang">';
		foreach ($languages as $language) {
			$output .= '<option value="' . $language['id_lang'] . '"';
			if ($supplier['email_lang'] == $language['id_lang'] || ($supplier['email_lang'] == NULL && $language['id_lang'] == $defaultLanguage)) {
				$output .= ' selected="selected"';
			}
			$output .= '>' . $language['name'] . '</option>';
		}
		$output .= '	</select>';
		$output .= '</div>';

		$output .= '<label>' . $this->l('Delivery address') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<select name="id_address">';
		foreach ($addresses as $address) {
			$output .= '<option value="'.$address['id_address'].'"';
			if ($supplier['id_address'] == $address['id_address']) {
				$output .= ' selected="selected"';
			}
			$output .= '>';
			$output .= ( ($this->PS_VERSION != "1.5" && $address['id_address'] == 1) || ($this->PS_VERSION == "1.5" && $address['id_address'] == $this->context->shop->id) ? $this->l("Customer's adress") : $address['title']);
			$output .= '</option>';
		}
		$output .= '</select>';
		$output .= '</div>';

		$output .= '<label>' . $this->l('Send to emails') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<input type="text" name="emails" size="50" value="' . $supplier['emails'] . '" />';
		$output .= '<p class="clear">' . $this->l('Multiple emails are to be separated with a <b>comma</b>.') . '</p>';
		$output .= '</div>';

		$output .= '<label>' . $this->l('Email template') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<select name="id_email_template">';
		foreach ($email_templates as $template) {
			$output .= '<option value="'.$template['id_email_template'].'"';
			if ($template['id_email_template'] == $supplier['id_email_template']) {
				$output .= ' selected="selected"';
			}
			$output .= '>';
			$output .= $template['title'];
			$output .= '</option>';
		}
		$output .= '</select>';
		$output .= '</div>';

		$output .= '<label>' . $this->l('Send for these order states') . '</label>';
		$output .= '<table class="table" cellspacing="0" cellpadding="0" style="margin-left:210px;">';
		$output .= '<thead>';
		$output .= '	<th style="width:18px;"></th>';
		$output .= '	<th class="center" width="5%">ID</th>';
		$output .= '	<th>' . $this->l('Name') . '</th>';
		$output .= '	<th class="center">' . $this->l('Icon') . '</th>';
		$output .= '</thead>';

		$order_states = ($supplier['order_states'] == NULL ? array() : unserialize($supplier['order_states']));
		$output .= '<tbody>';
		if ($this->PS_VERSION == "1.5") {
			foreach (OrderState::getOrderStates($cookie->id_lang) as $state) {
				$output .= '	<tr>';
				$output .= '		<td class="center"><input type="checkbox" name="order_states[]" value="' . $state['id_order_state'] . '"';
				if (in_array($state['id_order_state'], $order_states)) {
					$output .= ' checked="checked"';
				}
				$output .= ' /></td>';
				$output .= '		<td class="center">' . $state['id_order_state'] . '</td>';
				$output .= '		<td style="height:25px;"><span style="text-shadow:none;background-color:'.$state['color'].';color:white" class="color_field">' . $state['name'] . '</span></td>';
				$output .= '		<td class="center">';
				if (file_exists(_PS_TMP_IMG_DIR_ . 'order_state_mini_' . $state['id_order_state'] . '.gif')) {
					$output .= '<img src="'._PS_TMP_IMG_.'order_state_mini_' . $state['id_order_state'] . '.gif" alt="" />';
				}
				$output .= '</td>';
				$output .= '	</tr>';
			}
		} else {

foreach (OrderState::getOrderStates($cookie->id_lang) as $state) {
				$output .= '	<tr>';
				$output .= '		<td class="center" style="background-color:' . $state['color'] . ';"><input type="checkbox" name="order_states[]" value="' . $state['id_order_state'] . '"';
				if (in_array($state['id_order_state'], $order_states)) {
					$output .= ' checked="checked"';
				}
				$output .= ' /></td>';
				$output .= '		<td class="center" style="background-color:' . $state['color'] . ';">' . $state['id_order_state'] . '</td>';
				$output .= '		<td style="background-color:' . $state['color'] . ';">' . $state['name'] . '</td>';
				$output .= '		<td class="center" style="background-color:' . $state['color'] . ';">';
				if (file_exists(_PS_TMP_IMG_DIR_ . 'order_state_mini_' . $state['id_order_state'] . '.gif')) {
					$output .= '<img src="'._PS_TMP_IMG_.'order_state_mini_' . $state['id_order_state'] . '.gif" alt="" />';
				}
				$output .= '</td>';
				$output .= '	</tr>';
			}
		}
		$output .= '</tbody>';

		$output .= '</table>';
		$output .= '<p style="margin-left:210px;color:#7F7F7F;font-size:0.85em;margin-bottom:10px;">';
		$output .= $this->l('Note: Even though you select multiple order states, emails will only be sent <b>once</b> per supplier.');
		$output .= '</p>';

		//$products = $this->getProducts($id_supplier);
		$products_exceptions = ($supplier['exceptions'] == NULL ? array() : unserialize($supplier['exceptions']));

		$output .= '<label>' . $this->l('Except these products') . '</label>';
		$output .= '<div class="margin-form" style="position:relative;">';
		$output .= '<select id="products_exceptions" name="products_exceptions[]" multiple="multiple" size="10" style="padding:5px;width:250px;height:150px;">';
		$exceptions = array();
		foreach ($products_exceptions as $product) {
			$exceptions[] = $product['id_product'];
			$output .= '<option value="'.$product['id_product'].'##'.$product['name'].'">';
			$output .= $product['name'] . " [id:" . $product['id_product'] . "]";
			$output .= '</option>';
		}
		$output .= '</select>';
		$output .= '&nbsp;';
		$output .= '<select id="supplier_products" name="supplier_products" multiple="multiple" size="10" style="padding:5px;width:250px;height:150px;">';
		/*foreach ($products as $product) {
			if (!in_array($product['id_product'], $exceptions)) {
				$output .= '<option value="'.$product['id_product'].'##' . $product['name'] . '">';
				$output .= $product['name'] . " [id:" . $product['id_product'] . "]";
				$output .= '</option>';
			}
		}*/
		$output .= '</select>';
		$output .= '<div id="loading-products" style="display:none;position:absolute;left:625px;top:65px;"><img src="' . _PS_IMG_ . 'loader.gif" alt="" /></div>';
		$output .= '<script type="text/javascript">
						var totalProducts = '.(int)$this->getTotalProductsOfSupplier($id_supplier).';
						var productsPerLoad = 70;
						var loadedProducts = 0;
						$(document).ready(function() {
							loadSupplierProducts();
						});
						function loadSupplierProducts() {
							$.ajax({
								url: "' . $this->_path . 'get-products.php",
								type: "GET",
								dataType: "json",
								data: {
									n:					productsPerLoad,
									p:					(loadedProducts/productsPerLoad),
									id_lang: 			"' . $cookie->id_lang . '",
									id_supplier: 		'.$id_supplier.',
									id_employee:		' . $cookie->id_employee . ',
									passwd:				"' . $cookie->passwd . '"
								},
								beforeSend: function() {
									$("#loading-products").show();
									$("#supplier_products").attr("disabled","disabled");
								},
								success: function(json) {
									if (json != null) {
										$.each(json, function(key, product) {
											if ($("#products_exceptions").find(\'option[value="\' + product.id_product + \'##\' + product.name + \'"]\').size() == 0) {
												$("#supplier_products").append(\'<option value="\' + product.id_product + \'##\' + product.name + \'">\' + product.name + \' [id:\' + product.id_product + \']</option>\');
											}
										});
									} else {
										alert("' . $this->l('AJAX Error: Please refresh the page. Your cookie has expired.') . '");
									}
								},
								complete: function() {
									$("#loading-products").hide();
									$("#supplier_products").removeAttr("disabled");
									loadedProducts += productsPerLoad;
									if (loadedProducts < totalProducts) {
										loadSupplierProducts();
									}
								},
								error: function(msg) {
									alert("' . $this->l('AJAX Error: Could not load supplier products') . '");
								}
							});
						}
					</script>';
		$output .= '<br />';
		$output .= '<a id="removeException" style="float:left;width:244px;text-align:center;display:inline;border:1px solid #E0D0B1;text-decoration:none;background-color:#fafafa;color:#123456;margin:4px 3px 5px 0;padding:2px;cursor:pointer;">' . $this->l('Remove') . ' &raquo;</a>';
		$output .= '&nbsp;<a id="addException" style="float:left;width:244px;text-align:center;display:inline;border:1px solid #E0D0B1;text-decoration:none;background-color:#fafafa;color:#123456;margin:4px 0 5px 0;padding:2px;cursor:pointer;">&laquo; ' . $this->l('Add') . '</a>';
		$output .= '<p class="clear">' . $this->l('Select products that should') . ' <b>' . $this->l('not') . '</b> ' . $this->l('be included in order emails to this supplier') . '</p>';
		$output .= '</div>';

		$output .= '<script type="text/javascript">';
		$output .= "$('#addException').click(function() {
						return !$('#supplier_products option:selected').remove().appendTo('#products_exceptions').removeAttr('selected');
					});
					$('#removeException').click(function() {
						return !$('#products_exceptions option:selected').remove().appendTo('#supplier_products').removeAttr('selected');
					});";
		$output .= '</script>';
		$output .= '<p style="margin-left:210px;">';
		$output .= '<input class="button" type="submit" name="saveSupplier" value="' . ($this->country_support === true ? $this->l('Save for country') . " " . Country::getNameById($cookie->id_lang, $id_country) : $this->l('Save')) . '" />';
		if ($this->country_support) {
			$output .= '<input class="button" type="submit" name="saveSupplierAllCountries" value="' . $this->l('Save settings for all countries') . '" style="margin-left:10px;" onclick="return confirm(\'' . $this->l('Are you sure you want to set these values for ALL countries?') . '\');" />';
			$output .= '<input class="button" type="submit" name="clearSupplierAllCountries" value="' . $this->l('Clear settings for all countries') . '" style="margin-left:10px;" onclick="return confirm(\'' . $this->l('Are you sure you want to clear the settings for ALL countries?') . '\');" />';
		}
		$output .= '<a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . ($this->country_support === true ? '&id_country=' . $id_country : '') . '" class="button" style="position:relative; padding:3px 3px 4px 3px; left:10px;font-size:12px;" title="' . $this->l('Cancel') . '">' . $this->l('Cancel') . '</a>';
		$output .= '</p>';
		$output .= '</fieldset>';
		$output .= '</form>';

		return $output;
	}

	public function editAddressView($id_address) {
		global $currentIndex;

		$address = $this->getAddress($id_address);

		$output = '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';
		$output .= '<input type="hidden" name="id_address" value="' . $id_address . '" />';
		$output .= '<fieldset>';
		$output .= '<legend>' . ($id_address != 0 ? $address['title'] : $this->l('Create new address')) . '</legend>';
		$output .= '<label>' . $this->l('Title') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<input type="text" name="title" size="50" value="' . $address['title'] . '" />';
		$output .= '</div>';
		$output .= '<label>' . $this->l('Address') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<textarea name="address" cols="30" rows="5">' . $address['address'] . '</textarea>';
		$output .= '</div>';
		$output .= '<p style="margin-left:210px;">';
		$output .= '<input class="button" type="submit" name="saveAddress" value="' . $this->l('Save') . '" />';
		$output .= '<a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '" class="button" style="position:relative; padding:3px 3px 4px 3px; left:10px;font-size:12px;" title="' . $this->l('Cancel') . '">' . $this->l('Cancel') . '</a>';
		$output .= '</p>';
		$output .= '</fieldset>';
		$output .= '</form>';

		return $output;
	}

	public function editEmailTemplateView($id_email_template) {
		global $currentIndex;
		$output = "";

		if (($this->PS_VERSION != "1.5" && (int)$id_email_template == 1) || ($this->PS_VERSION == "1.5" && (int)$id_email_template == $this->context->shop->id)) {
			$warning = '<div class="warn">';
			$warning .= '<p>';
			$warning .= '<span style="float: left">';
			$warning .= '<img src="../img/admin/warn2.png">';
			$warning .= $this->l('You may not edit the default template, copy & paste to a new template to make changes to it.');
			$warning .= '</span>';
			$warning .= '<br class="clear" />';
			$warning .= '</p>';
			$warning .= '</div>';
			$output .= $warning;
		}

		$email_template = $this->getEmailTemplate($id_email_template);

		$output .= '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
		$output .= '<input type="hidden" name="id_email_template" value="'.$id_email_template.'" />';
		$output .= '<fieldset>';
		$output .= '<legend>' . $this->l('Email template') . '</legend>';

		$output .= '<label>' . $this->l('Template name') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<input type="text" name="title" value="' . $email_template['title'] . '" size="80" maxlength="200" />';
		$output .= '</div>';

		$output .= '<label>' . $this->l('Email subject') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<input type="text" name="subject" value="' . $email_template['subject'] . '" size="80" maxlength="200" />';
		$output .= '</div>';

		$output .= '<label>' . $this->l('Email content type') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<select name="content_type">';
		$output .= '	<option value="text/plain"' . ($email_template['content_type'] == 'text/plain' ? ' selected="selected"' : '') . '>text/plain</option>';
		$output .= '	<option value="text/html"' . ($email_template['content_type'] == 'text/html' ? ' selected="selected"' : '') . '>text/html</option>';
		$output .= '</select>';
		$output .= '</div>';

		$output .= '<label>' . $this->l('Email template') . '</label>';
		$output .= '<div class="margin-form">';
		$output .= '<textarea name="email_content" style="width:100%; height:500px;">' . $email_template['content'] . '</textarea>';
		$output .= '<p class="clear">';
		$output .= '[supplier_name] = ' . $this->l('Supplier name') . '<br />';
		$output .= '[id_supplier] = ' . $this->l('Supplier ID') . '<br /><br />';
		$output .= $this->l('The tags below must be placed within the products tag:') . ' [products] ' . $this->l('tags here') . ' [/products]<br />';
		$output .= '[products]<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[image_link] = ' . $this->l('URL to thickbox image of the product') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[id_product] = ' . $this->l('Product ID') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_name] = ' . $this->l('Name of the product') . '<br />';

		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_description] = ' . $this->l('The product\'s description') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_description_stripped] = ' . $this->l('The product\'s description without HTML tags') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_description_short] = ' . $this->l('The product\'s short description') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_description_short_stripped] = ' . $this->l('The product\'s short description without HTML tags') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_meta_keywords] = ' . $this->l('The product\'s meta keywords') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_meta_description] = ' . $this->l('The product\'s meta description') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_meta_title] = ' . $this->l('The product\'s meta title') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_link_rewrite] = ' . $this->l('The product\'s link rewrite') . '<br />';

		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_reference] = ' . $this->l('Product reference') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_ean13] = ' . $this->l('Product EAN13') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_upc] = ' . $this->l('Product UPC') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[supplier_reference] = ' . $this->l('Supplier reference for the product') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[supplier_name] = ' . $this->l('Supplier name') . '(' . $this->l('This can also be used outside the product loop') . ')' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[price] = ' . $this->l('Base price') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[wholesale_price] = ' . $this->l('Wholesale price') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[final_price] = ' . $this->l('Final price') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_line_price] = ' . $this->l('Total line price (based on the wholesale price)') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_line_price_wt] = ' . $this->l('Total line price (based on the final price)') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_quantity] = ' . $this->l('Wanted quantity of this product') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_quantity_in_stock]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_quantity_refunded]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_quantity_return]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_quantity_reinjected]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[tax_name] = ' . $this->l('Name of the tax applied for this product') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[tax_rate] = ' . $this->l('Tax rate applied for this product (in percent)') . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_quantity_discount]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[reduction_percent]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[reduction_amount]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[group_reduction]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[ecotax]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[ecotax_tax_rate]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[discount_quantity_applied]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_available_now]' . '<br />';
		$output .= '&nbsp;&nbsp;&nbsp;&nbsp;[product_available_later]' . '<br />';

		$output .= '[/products]<br /><br />';

		$output .= '[total_discounts]' . '<br />';
		$output .= '[total_paid] = ' . $this->l('Total amount for the customer to pay') . '<br />';
		$output .= '[total_paid_real] = ' . $this->l('Total amount payed by the customer') . '<br />';
		$output .= '[total_products] = ' . $this->l('Total order amount (tax excluded)') . '<br />';
		$output .= '[total_products_wt] = ' . $this->l('Total order amount (tax included)') . '<br />';
		$output .= '[total_products_wholesale_price] = ' . $this->l('Total order amount (based on the wholesale price)') . '<br />';
		$output .= '[total_shipping] = ' . $this->l('Total shipping cost') . '<br />';
		$output .= '[carrier_tax_rate] = ' . $this->l('Total wrapping cost') . '<br />';
		$output .= '[total_wrapping]' . '<br />';
		$output .= '[invoice_number]' . '<br />';
		$output .= '[delivery_number' . '<br />';

		$output .= '[delivery_address] = ' . $this->l('The delivery address for this supplier') . " (" . $this->l("if customer's address is chosen, this tag will consist of name, address, post code and city") . ")<br />";
		$output .= '<br />' . $this->l('Customer') . ':<br />';
		$output .= '[customer_company] = ' . $this->l("Customer's company") . '<br />';
		$output .= '[customer_firstname] = ' . $this->l('Firstname of the customer') . '<br />';
		$output .= '[customer_lastname] = ' . $this->l('Lastname of the customer') . '<br />';
		$output .= '[customer_address1] = ' . $this->l("First address row of the customer's address") . '<br />';
		$output .= '[customer_address2] = ' . $this->l("Second address row of the customer's address") . '<br />';
		$output .= '[customer_phone] = ' . $this->l("Phonenumber to the customer") . '<br />';
		$output .= '[customer_phone_mobile] = ' . $this->l("Phonenumber to the customer's mobile") . '<br />';
		$output .= '[customer_postcode] = ' . $this->l("The customer's post code") . '<br />';
		$output .= '[customer_city] = ' . $this->l("The customer's city") . '<br />';
		$output .= '[customer_email] = ' . $this->l("The customer's email") . '<br />';
		$output .= '[customer_state] = ' . $this->l("The customer's state") . '<br />';
		$output .= '[customer_state_code] = ' . $this->l("The customer's state code") . '<br />';
		$output .= '[customer_country] = ' . $this->l("The customer's country") . '<br />';
		$output .= '[customer_country_code] = ' . $this->l("The customer's country code");
		$output .= '</p>';
		$output .= '</div>';

		$output .= '<p style="margin-left: 210px;">';
		$output .= '<input class="button" type="submit" name="saveEmail" value="' . $this->l('Save') . '"' . (($this->PS_VERSION != "1.5" && (int)$id_email_template == 1) || ($this->PS_VERSION == "1.5" && (int)$id_email_template == $this->context->shop->id) ? ' disabled="disabled"' : '') . ' />';
		$output .= '<a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '" class="button" style="position:relative; padding:3px 3px 4px 3px; left:10px;font-size:12px;" title="' . $this->l('Cancel') . '">' . $this->l('Cancel') . '</a>';
		$output .= '</p>';
		$output .= '</fieldset>';
		$output .= '</form>';

		if (($this->PS_VERSION != "1.5" && (int)$id_email_template == 1) || ($this->PS_VERSION == "1.5" && (int)$id_email_template == $this->context->shop->id)) {
			$output .= '<br />'.  $warning;
		}

		return $output;
	}

	public function getContent()
	{
		global $currentIndex, $cookie;

		// Perform update if not done
		if (!Configuration::get('PS_ORDEREMAILS_UPDATED_TO_1_3') || !Configuration::get('PS_ORDEREMAILS_UPDATED_TO_1_4')) {
			$this->updateDatabase();
		}

		$msg = '';
		$output = '<h2>'.$this->displayName.'</h2>';

		// Take care of submitted data
		if (Tools::isSubmit('saveSupplier')) {
			$msg = $this->saveSupplier(Tools::getValue('id_supplier'), Tools::getValue('id_country'));
		} elseif (Tools::isSubmit('saveSupplierAllCountries')) {
			$msg = $this->saveSupplier(Tools::getValue('id_supplier'), false);
		} elseif (Tools::isSubmit('clearSupplierAllCountries')) {
			$msg = $this->clearSupplierAllCountries(Tools::getValue('id_supplier'));
		} elseif (Tools::isSubmit('saveAddress')) {
			$msg = $this->saveAddress(Tools::getValue('id_address'));
		} elseif (Tools::isSubmit('saveEmail')) {
			$msg = $this->saveEmailTemplate(Tools::getValue('id_email_template'));
		} elseif (Tools::isSubmit('saveGeneralSettings')) {
			$this->saveGeneralSettings();
		}

		// Display the correct view if not in the standard view
		if (Tools::getValue('edit')) {
			if (Tools::getValue('edit') == 'supplier' && Tools::getValue('id_supplier')) {
				return $msg . $this->editSupplierView(Tools::getValue('id_supplier'));
			} elseif (Tools::getValue('edit') == 'address' && Tools::getValue('id_address')) {
				return $msg . $this->editAddressView(Tools::getValue('id_address'));
			} elseif (Tools::getValue('edit') == 'email_template' && Tools::getValue('id_email_template')) {
				return $msg . $this->editEmailTemplateView(Tools::getValue('id_email_template'));
			} elseif (Tools::getValue('edit') == 'sent_email' && Tools::getValue('id_order') && Tools::getValue('id_order_state')) {
				$this->sendOrderEmails(new Order((int)Tools::getValue('id_order')), new OrderState((int)Tools::getValue('id_order_state')), true);
				$this->returnToMainView('success', 'The order emails for order #%d have been sent again', array(Tools::getValue('id_order')));
			}
		} elseif (Tools::getValue('add')) {
			if (Tools::getValue('add') == 'address') {
				return $msg . $this->editAddressView(0);
			} elseif (Tools::getValue('add') == 'email_template') {
				return $msg . $this->editEmailTemplateView(0);
			}
		} elseif (Tools::getValue('delete')) {
			if (Tools::getValue('delete') == 'address') {
				$this->deleteAddress(Tools::getValue('id_address'));
			} elseif (Tools::getValue('delete') == 'email_template') {
				$this->deleteEmailTemplate(Tools::getValue('id_email_template'));
			}
		}

		$countries = Country::getCountries($cookie->id_lang, true);
		$id_country = (Tools::getValue('id_country') ? Tools::getValue('id_country') : $this->getConfig('PS_COUNTRY_DEFAULT'));

		$suppliers = $this->getSuppliers($id_country);
		$addresses = $this->getAddresses();
		$email_templates = $this->getEmailTemplates();

		$output .= $msg;

		if (Tools::getValue('success')) {
			$output .= $this->displayConfirmation(urldecode(Tools::getValue('success')));
		} elseif (Tools::getValue('error')) {
			$output .= $this->displayError(urldecode(Tools::getValue('error')));
		}

		$output .= '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
		$output .= '<fieldset>';
		$output .= '<legend>' . $this->l('General settings') . '</legend>';

		$output .= '<label style="width:120px;">' . $this->l('From email') . '</label>';
		$output .= '<div class="margin-form" style="padding-left:130px;">';
		$output .= '<input type="text" name="from_email" value="'.$this->getConfig('PS_ORDEREMAILS_FROM').'" size="40" />';
		$output .= '</div>';

		$output .= '<label style="width:120px;">' . $this->l('From name') . '</label>';
		$output .= '<div class="margin-form" style="padding-left:130px;">';
		$output .= '<input type="text" name="from_name" value="'.$this->getConfig('PS_ORDEREMAILS_FROMNAME').'" size="40" />';
		$output .= '</div>';

		$output .= '<label style="width:120px;">' . $this->l('Country support') . '</label>';
		$output .= '<div class="margin-form" style="padding-left:130px;">';
		$output .= '	<input id="display_on" type="radio" value="1" '.($this->country_support === true ? "checked='checked'" : '').' name="country_support" />';
		$output .= '	<label class="t" for="display_on">';
		$output .= '		<img title="' . $this->l('Enabled') . '" alt="' . $this->l('Enabled') .'" src="../img/admin/enabled.gif">';
		$output .= '	</label>';
		$output .= '	<input id="display_off" type="radio" '.($this->country_support !== true ? "checked='checked'" : '').' value="0" name="country_support">';
		$output .= '	<label class="t" for="display_off">';
		$output .= '		<img title="' . $this->l('Disabled') . '" alt="' . $this->l('Disabled') . '" src="../img/admin/disabled.gif">';
		$output .= '	</label>';
		$output .= '	<p>' . $this->l('Enable or disable country support on a supplier basis') . '</p>';
		$output .= '</div>';

		$output .= '<p style="margin-left: 110px;">';
		$output .= '<input class="button" type="submit" name="saveGeneralSettings" value="' . $this->l('Save') . '" />';
		$output .= '</p>';

		$output .= '</fieldset>';
		$output .= '</form>';

		$output .= '<br />';

		$output .= '<fieldset>';
		$output .= '<legend>' . $this->l('Suppliers') . '</legend>';

		if ($this->country_support) {
			$output .= '<label style="width:160px;text-align:left;">' . $this->l('Show for country') . '</label>';
			$output .= '<div class="margin-form" style="padding-left:170px;">';
			$output .= '<form action="' . $currentIndex . '" method="get" id="country_form">';
			$output .= '<input type="hidden" name="tab" value="AdminModules" />';
			$output .= '<input type="hidden" name="configure" value="' . $this->name . '" />';
			$output .= '<input type="hidden" name="token" value="' . Tools::getAdminTokenLite('AdminModules') . '" />';
			$output .= '	<select name="id_country" id="id_country">';
			foreach ($countries as $country) {
				$output .= '	<option value="' . $country['id_country'] . '"';
				if ($id_country == $country['id_country']) {
					$output .= ' selected="selected"';
				}
				$output .= '>' . $country['name'] . ($country['id_country'] == $this->getConfig('PS_COUNTRY_DEFAULT') ? ' (' . $this->l('shop\'s default country') . ')' : '') . '</option>';
			}
			$output .= '	</select>';
			$output .= '	<script type="text/javascript">';
			$output .= '		$("#id_country").change(function() {
									$("#country_form").submit();
								});';
			$output .= '	</script>';
			$output .= '</form>';
			$output .= '</div>';
		}

		$output .= '<table class="table" cellspacing="0" cellpadding="0" style="width:100%;">';
		$output .= '<thead>';
		$output .= '	<th class="center" style="min-width:5%;">ID</th>';
		$output .= '	<th>' . $this->l('Supplier') . '</th>';
		$output .= '	<th>' . $this->l('Address') . '</th>';
		$output .= '	<th>' . $this->l('Mail to emails') . '</th>';
		$output .= '	<th class="center">' . $this->l('Status') . '</th>';
		$output .= '	<th class="center" width="10%">' . $this->l('Actions') . '</th>';
		$output .= '</thead>';

		$output .= '<tbody>';
		$i = 0;
		$sups = array();
		foreach ($suppliers as $supplier) {
			$sups[$supplier['id_supplier']] = $i;
			$output .= '	<tr>';
			$output .= '		<td class="center">' . $supplier['id_supplier'] . '</td>';
			$output .= '		<td>' . $supplier['name'] . '</td>';
			$output .= '		<td>' . ( ($this->PS_VERSION != "1.5" && $supplier['id_address'] == 1) || ($this->PS_VERSION == "1.5" && $supplier['id_address'] == $this->context->shop->id) ? $this->l("Customer's address") : ($supplier['address_title'] == '' ? '-' : $supplier['address_title'])) . '</td>';
			$output .= '		<td>' . (($supplier['emails'] == '') ? '-' : $supplier['emails']). '</td>';
			$output .= '		<td class="center"><img title="' . $this->l(($supplier['active'] == 1 ? 'Enabled' : 'Disabled')) .'" alt="' . $this->l(($supplier['active'] == 1 ? 'Enabled' : 'Disabled')) .'" src="../img/admin/' . ($supplier['active'] == 1 ? 'enabled' : 'disabled') . '.gif"></td>';
			$output .= '		<td class="center"><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&edit=supplier&id_supplier=' . (int)($supplier['id_supplier']) . ($this->country_support === true ? '&id_country=' . (int)$id_country : '') . '" title="' . $this->l('Edit') . '"><img src="' . _PS_ADMIN_IMG_ . 'edit.gif" alt="" /></a></td>';
			$output .= '	</tr>';
			$i++;
		}
		$output .= '</tbody>';

		$output .= '</table>';
		$output .= '</fieldset>';

		$output .= '<br />';

		$output .= '<fieldset>';
		$output .= '<legend>' . $this->l('Addresses') . '</legend>';
		$output .= '<p><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&add=address" title="' . $this->l('Add new address') . '"><img src="' . _PS_ADMIN_IMG_ . 'add.gif" alt="" />' . $this->l('Add new address') . '</a></p>';
		$output .= '<table class="table" cellspacing="0" cellpadding="0" style="width:100%;">';
		$output .= '<thead>';
		$output .= '	<th class="center" style="min-width:5%;">ID</th>';
		$output .= '	<th>' . $this->l('Title') . '</th>';
		$output .= '	<th>' . $this->l('Address') . '</th>';
		$output .= '	<th class="center" width="10%">' . $this->l('Actions') . '</th>';
		$output .= '</thead>';
		$output .= '<tbody>';
		foreach ($addresses as $address) {
			$output .= '	<tr>';
			$output .= '		<td class="center">' . $address['id_address'] . '</td>';
			$output .= '		<td>' . (($this->PS_VERSION != "1.5" && $address['id_address'] == 1) || ($this->PS_VERSION == "1.5" && $address['id_address'] == $this->context->shop->id) ? $this->l("Customer's address") : $address['title']) . '</td>';
			$output .= '		<td>' . ($address['address'] == '' ? '-' : strip_tags($address['address'])) . '</td>';
			//The customer's address is not to be changed since this is fetched automatically from the buying customer
			if ( ($this->PS_VERSION != "1.5" && $address['id_address'] != 1) || ($this->PS_VERSION == "1.5" && $address['id_address'] != $this->context->shop->id) ) {
				$output .= '		<td class="center"><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&edit=address&id_address=' . (int)($address['id_address']) . '" title="' . $this->l('Edit') . '"><img src="' . _PS_ADMIN_IMG_ . 'edit.gif" alt="" /></a><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&delete=address&id_address=' . (int)($address['id_address']) . '" title="' . $this->l('Delete') . '" onclick="return confirm(\''. $this->l('Are you sure you want to delete this address?') . '\');"><img src="' . _PS_ADMIN_IMG_ . 'delete.gif" alt="" /></a></td>';
			} else {
				$output .= '		<td class="center">-</td>';
			}

			$output .= '	</tr>';
		}
		$output .= '</tbody>';
		$output .= '</table>';
		$output .= '</fieldset>';

		$output .= '<br />';

		$output .= '<fieldset>';
		$output .= '<legend>' . $this->l('Email templates') . '</legend>';
		$output .= '<p><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&add=email_template" title="' . $this->l('Add new email template') . '"><img src="' . _PS_ADMIN_IMG_ . 'add.gif" alt="" />' . $this->l('Add new email template') . '</a></p>';
		$output .= '<table class="table" cellspacing="0" cellpadding="0" style="width:100%;">';
		$output .= '<thead>';
		$output .= '	<th class="center" style="min-width:5%;">ID</th>';
		$output .= '	<th>' . $this->l('Template name') . '</th>';
		$output .= '	<th>' . $this->l('Email subject') . '</th>';
		$output .= '	<th>' . $this->l('Content type') . '</th>';
		$output .= '	<th>' . $this->l('Created') . '</th>';
		$output .= '	<th class="center" width="10%">' . $this->l('Actions') . '</th>';
		$output .= '</thead>';

		$output .= '<tbody>';
		foreach ($email_templates as $template) {
			$output .= '	<tr>';
			$output .= '		<td class="center">' . $template['id_email_template'] . '</td>';
			$output .= '		<td>' . $template['title'] . '</td>';
			$output .= '		<td>' . $template['subject'] . '</td>';
			$output .= '		<td>' . $template['content_type'] . '</td>';
			$output .= '		<td>' . $template['timestamp'] . '</td>';
			$output .= '		<td class="center"><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&edit=email_template&id_email_template=' . (int)($template['id_email_template']) . '" title="' . $this->l('Edit') . '"><img src="' . _PS_ADMIN_IMG_ . 'edit.gif" alt="" /></a>';
			if ( ($this->PS_VERSION != "1.5" && $template['id_email_template'] != 1) || ($this->PS_VERSION == "1.5" && $template['id_email_template'] != $this->context->shop->id) ) {
				$output .= '<a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&delete=email_template&id_email_template=' . (int)($template['id_email_template']) . '" title="' . $this->l('Delete') . '" onclick="return confirm(\''. $this->l('Are you sure you want to delete this email template?') . '\');"><img src="' . _PS_ADMIN_IMG_ . 'delete.gif" alt="" /></a>';
			}
			$output .= '		</td>';
			$output .= '	</tr>';
		}
		$output .= '</tbody>';

		$output .= '</table>';
		$output .= '</fieldset>';

		$output .= '<br />';

		$n = 20;
		$sent_emails = $this->getSentEmails(Tools::getValue('sent_emails'), $n);
		$total_sent_emails = $this->getTotalSentEmails();
		$current_page = (Tools::getValue('sent_emails') ? (int)Tools::getValue('sent_emails') : 0);

		$output .= '<fieldset id="sent_emails">';
		$output .= '<legend>' . $this->l('Sent order emails') . '</legend>';
		$pagination = '<div class="pagination clear">';
		if ($current_page > 0) {
			$pagination .= '<p style="float:left;"><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&sent_emails='.($current_page-1).'#sent_emails"><img src="../img/admin/list-prev.gif" border="0" style="margin-bottom: 3px;" alt="' . $this->l('Previous page') . '" />' . $this->l('Previous page') . '</p>';
		}
		if ($current_page < ceil($total_sent_emails/$n)-1) {
			$pagination .= '<p style="float:right;"><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&sent_emails='.($current_page+1).'#sent_emails">' . $this->l('Next page') . '<img src="../img/admin/list-next.gif" border="0" style="margin: 2px 0 3px 5px;" alt="' . $this->l('Next page') . '" /></a></p>';
		}
		$pagination .= '</div>';

		$output .= $pagination;
		$output .= '<table class="table clear" cellspacing="0" cellpadding="0" style="width:100%;">';
		$output .= '<thead>';
		$output .= '	<th class="center" style="min-width:8%;">Order ID</th>';
		$output .= '	<th width="17%">' . $this->l('Sent for order state') . '</th>';
		$output .= '	<th width="13%">' . $this->l('To supplier') . '</th>';
		$output .= '	<th width="41%">' . $this->l('To emails') . '</th>';
		$output .= '	<th width="14%">' . $this->l('Time sent') . '</th>';
		$output .= '	<th class="center" width="7%">' . $this->l('Actions') . '</th>';
		$output .= '</thead>';

		$output .= '<tbody>';
		if (!empty($sent_emails)) {
			foreach ($sent_emails as $sent) {
				$output .= '	<tr>';
				$output .= '		<td class="center">' . $sent['id_order'] . '</td>';
				$output .= '		<td>' . $sent['order_state'] . '</td>';
				$output .= '		<td>' . $suppliers[$sups[$sent['id_supplier']]]['name'] . '</td>';
				$output .= '		<td>' . $sent['sent_to'] . '</td>';
				$output .= '		<td>' . $sent['timestamp'] . '</td>';
				$output .= '		<td class="center"><a href="' . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&edit=sent_email&id_order=' . (int)($sent['id_order']) . '&id_order_state=' . $sent['id_order_state'] . '" title="' . $this->l('Resend email') . '" onclick="return confirm(\''. $this->l('Are you sure you want to send this order email again?') . '\');"><img src="' . _PS_ADMIN_IMG_ . 'email.gif" alt="' . $this->l('Resend email') . '" /></a>';
				$output .= '		</td>';
				$output .= '	</tr>';
			}
		} else {
			$output .= '	<tr>';
			$output .= '		<td colspan="6">' . $this->l('No order emails have been sent yet') . '</td>';
			$output .= '	</tr>';
		}
		$output .= '</tbody>';

		$output .= '</table>';
		$output .= $pagination;
		$output .= '</fieldset>';

		return $output;
	}

	public function returnToMainView($status, $msg, $replacements = array()) {
		global $currentIndex;
		$msg = $this->l($msg);
		header("Location: " . $currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&'.$status.'=' . urlencode((!empty($replacements) ? vsprintf($msg, $replacements) : $msg)));
	}
}
