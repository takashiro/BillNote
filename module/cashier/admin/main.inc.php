<?php

/***********************************************************************
Orchard Hut Online Shop
Copyright (C) 2013-2015  Kazuichi Takashiro

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

takashiro@qq.com
************************************************************************/

if(!defined('IN_ADMINCP')) exit('access denied');

class CashierMainModule extends AdminControlPanelModule{

	public function getRequiredPermissions(){
		return array('order');
	}

	public function defaultAction(){
		extract($GLOBALS, EXTR_REFS);

		$table = $db->select_table('product');
		$productlist = $table->fetch_all('*', 'hide=0 ORDER BY displayorder');

		$products = array();
		$productids = array();
		$product = new Product;
		foreach($productlist as &$p){
			foreach($p as $attr => $value){
				$product->$attr = $value;
			}
			$p = $product->toArray();
			$p['introduction'] = str_replace(array("\r\n", "\n", "\r"), '<br />', $p['introduction']);
			$productids[] = $p['id'];
			$p['storages'] = array();
			$products[$p['id']] = $p;
		}
		unset($p);

		$table = $db->select_table('productstorage');
		$check_booking_mode = ProductStorage::IsBookingMode() ? ' OR mode='.ProductStorage::BookingMode : '';
		if($productids){
			$product_storages = $table->fetch_all('*', 'productid IN ('.implode(',', $productids).') AND (num>0 '.$check_booking_mode.')');
			foreach($product_storages as $storage){
				$products[$storage['productid']]['storages'][] = $storage;
			}
		}else{
			$product_storages = array();
		}


		foreach($products as $pid => $v){
			if(empty($v['storages'])){
				unset($products[$pid]);
			}
		}

		$config = readdata('cashier');

		$table = $db->select_table('user');
		$users = array();
		$userlist = $table->fetch_all('*', 'deleted=0');
		foreach($userlist as $user){
			$users[$user['id']] = $user['nickname'];
		}

		$table = $db->select_table('bankaccount');
		$bankaccounts = $table->fetch_all('*');

		include view('add');
	}

	public function orderAction(){
		global $_G, $db, $tpre;

		$timestamp = TIMESTAMP;
		$total_price = 0.00;

		//$cart is an array of items, with the key standing for its price id and the value for the number.
		//$priceids is array_keys($cart)
		$cart = array();
		$input = file_get_contents('php://input');
		if(!empty($input)){
			$input = json_decode($input, true);
			if(empty($input['bankaccountid'])){
				exit('invalid operation');
			}

			$shopping_cart = $input['content'];
			foreach($shopping_cart as $row){
				if(empty($row['productid']) || empty($row['number']) || empty($row['amountunit']) || empty($row['subtotal']) || empty($row['warehouseid']))
					continue;
				$row['productid'] = intval($row['productid']);
				$row['number'] = intval($row['number']);
				$row['amountunit'] = intval($row['amountunit']);
				$row['subtotal'] = floatval($row['subtotal']);
				$row['price'] = $row['subtotal'] / $row['number'];
				$row['warehouseid'] = intval($row['warehouseid']);
				$cart[] = $row;
			}
		}

		if($cart){//Now the shopping cart is not empty. Let's calculate as a cashier.
			$filtered_cart = array();
			foreach($cart as $row){
				$storage = $db->fetch_first("SELECT p.*,s.*
					FROM {$tpre}productstorage s
						LEFT JOIN {$tpre}product p ON p.id=s.productid
					WHERE s.warehouseid={$row['warehouseid']}
						AND p.id={$row['productid']}
						AND p.hide=0");
				if($storage && ($storage['num'] > 0 || ($storage['mode'] == ProductStorage::BookingMode && ProductStorage::IsBookingMode()))){
					$row = array_merge($row, $storage);
					$row['storageid'] = intval($storage['id']);
					$filtered_cart[] = $row;
				}else{
					showmsg(array('storage_is_insufficient', $storage['name']), 'back');
				}
			}

			$cart = $filtered_cart;
		}

		if(!$cart){
			showmsg('shopping_cart_empty', 'refresh');
		}

		$order = new Order;

		//补全默认信息
		foreach($cart as &$p){
			$p['amount'] = 1;
			$p['subtype'] = $p['remark'];
		}
		unset($p);

		//增加产品对应的销量，该销量仅供参考
		$db->query('START TRANSACTION');
		foreach($cart as &$p){
			if(!$order->addDetail($p)){
				$db->query('ROLLBACK');
				showmsg(array('storage_is_insufficient', $p['name'].'('.$p['subtype'].')'), 'back');
			}
		}
		unset($p);
		$db->query('COMMIT');

		//将订单插入到数据库中
		$order->tradestate = 3;
		$order->tradetime = TIMESTAMP;
		$order->paymentmethod = isset($input['paymentmethod']) ? intval($input['paymentmethod']) : Wallet::ViaCash;
		in_array($order->paymentmethod, array(Wallet::ViaCash, Wallet::ViaWallet)) || $order->paymentmethod = Wallet::ViaCash;
		$order->message = isset($input['message']) ? trim(htmlspecialchars($input['message'])) : '';
		$order->userid = isset($input['userid']) ? intval($input['userid']) : 0;
		$order->adminid = $_G['admin']->id;
		$order->bankaccountid = intval($input['bankaccountid']);
		$order->status = Order::Unsorted;
		$order_succeeded = $order->insert();

		if($order->paymentmethod == Wallet::ViaWallet){
			if($order->userid){
				$user = new User;
				$user->id = $order->userid;
				$wallet = new Wallet($user);
				$wallet->pay($order, true);
			}else{
				$order->paymentmethod = Wallet::ViaCash;
			}
		}

		//显示订单提交结果
		if($order_succeeded){
			global $mod_url;
			showmsg('successfully_submitted_order', $mod_url, $order->id);
		}else{
			showmsg('failed_to_submit_order', 'refresh');
		}
	}

}
