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

class ProductStorageModule extends AdminControlPanelModule{

	public function getRequiredPermissions(){
		return array('product');
	}

	public function editAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$input = json_decode(file_get_contents('php://input'), true);

		if(empty($input['content']) || !is_array($input['content'])){
			showmsg('illegal_operation');
		}
		$input = $input['content'];

		$totalcosts = 0;
		$logs = array();
		foreach($input as $row) {
			$productid = intval($row['productid']);
			$warehouseid = intval($row['warehouseid']);

			$storageid = $db->result_first("SELECT id FROM {$tpre}productstorage WHERE productid=$productid AND warehouseid=$warehouseid");
			if($storageid <= 0)
				continue;

			$delta = intval($row['delta']);
			$db->query("UPDATE {$tpre}productstorage SET num=num+$delta WHERE id=$storageid");
			if($db->affected_rows > 0){
				$s = $db->fetch_first("SELECT p.name AS productname, s.remark
					FROM {$tpre}productstorage s
						LEFT JOIN {$tpre}product p ON p.id=s.productid
					WHERE s.id=$storageid");

				$subtotal = isset($row['subtotal']) ? floatval($row['subtotal']) : 0.0;
				$logs[] = array(
					'storageid' => $storageid,
					'dateline' => TIMESTAMP,
					'amount' => $delta,
					'totalcosts' => $subtotal,
					'adminid' => $_G['admin']->id,
					'productname' => $s['productname'],
					'storageremark' => $s['remark'],
					'importamount' => $delta,
				);
				$totalcosts += $subtotal;
			}
		}

		if($logs){
			$table = $db->select_table('productstoragelog');
			$table->multi_insert($logs);

			showmsg('storage_is_updated', 'refresh');
		}

		showmsg('no_storage_need_updating');
	}

	public function logAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$condition = array();

		if(!empty($_REQUEST['time_start'])){
			$time_start = rstrtotime($_REQUEST['time_start']);
			$condition[] = "l.dateline>=$time_start";
		}else{
			$time_start = '';
		}

		if(!empty($_REQUEST['time_end'])){
			$time_end = rstrtotime($_REQUEST['time_end']);
			$condition[] = "l.dateline<=$time_end";
		}else{
			$time_end = '';
		}

		$condition = $condition ? implode(' AND ', $condition) : '1';

		$limit = 20;
		$offset = ($page - 1) * $limit;
		$logs = $db->fetch_all("SELECT l.*,b.remark AS bankaccountremark,a.realname
			FROM {$tpre}productstoragelog l
				LEFT JOIN {$tpre}bankaccount b ON b.id=l.bankaccountid
				LEFT JOIN {$tpre}administrator a ON a.id=l.adminid
			wHERE $condition
			ORDER BY l.dateline DESC
			LIMIT $offset,$limit");

		$total = $db->result_first("SELECT COUNT(*) FROM {$tpre}productstoragelog l WHERE $condition");

		$time_start && $time_start = rdate($time_start);
		$time_end && $time_end = rdate($time_end);
		include view('storage_log');
	}

	public function configAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		if($_POST){
			$config = array();
			foreach(array('bookingtime_start', 'bookingtime_end') as $var){
				$config[$var] = 0;
				if(isset($_POST[$var])){
					$time = explode(':', $_POST[$var]);
					$config[$var] = $time[0] * 3600;
					if(isset($time[1])){
						$config[$var] += $time[1] * 60;
						if(isset($time[2])){
							$config[$var] += $time[2];
						}
					}
				}
			}

			ProductStorage::WriteConfig($config);
			showmsg('edit_succeed', 'back');
		}

		$storageconfig = ProductStorage::ReadConfig();

		foreach(array('bookingtime_start', 'bookingtime_end') as $var){
			$s = $storageconfig[$var];
			$i = $s / 60;
			$H = $i / 60;
			$i %= 60;
			$s %= 60;
			$storageconfig[$var] = sprintf('%02d', $H).':'.sprintf('%02d', $i).':'.sprintf('%02d', $s);
		}

		include view('storage_config');
	}

	public function defaultAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

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
		if($productids){
			$product_storages = $table->fetch_all('*', 'productid IN ('.implode(',', $productids).')');
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

		$table = $db->select_table('bankaccount');
		$bankaccounts = $table->fetch_all('*');

		include view('storage');
	}

}

?>
