<?php

include(dirname(__FILE__).'/../../config/config.inc.php');

if (!Employee::checkPassword((int)Tools::getValue('id_employee'), Tools::getValue('passwd'))) {
	die(json_encode(array('error' => true)));
}

$id_lang = (int)Tools::getValue('id_lang');
$id_supplier = (int)Tools::getValue('id_supplier');
$n = (int)Tools::getValue('n');
$p = (int)Tools::getValue('p');

$PS_VERSION = substr(_PS_VERSION_, 0, 3);

if ($id_supplier != 0 && $id_lang != 0) {
	if ($PS_VERSION == "1.5") {
		$context = Context::getContext();
		$sql = "SELECT p.`id_product`, pl.`name` from `"._DB_PREFIX_."product` p
				INNER JOIN `"._DB_PREFIX_."product_shop` ps
					ON ps.`id_product` = p.`id_product`
				LEFT JOIN `"._DB_PREFIX_."product_lang` pl
					ON pl.`id_product` = p.`id_product`
				WHERE p.`id_supplier` = {$id_supplier} AND ps.`id_shop` = " . (int)$context->shop->id . " AND pl.`id_lang` = {$id_lang}
				GROUP by ps.`id_product`
				ORDER by pl.`name` ASC
				LIMIT " . ($p*$n) . ", {$n}";
	} else {
		$sql = "SELECT p.`id_product`, pl.`name` from `"._DB_PREFIX_."product` p
				LEFT JOIN `"._DB_PREFIX_."product_lang` pl
					ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = {$id_lang}
				WHERE p.`id_supplier` = {$id_supplier}
				ORDER by pl.`name` ASC
				LIMIT " . ($p*$n) . ", {$n}";
	}
	$result = Db::getInstance()->ExecuteS($sql);
	die(json_encode($result));
} else {
	die(json_encode(array()));
}