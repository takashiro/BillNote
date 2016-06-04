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

class Order extends DBObject{
	const TABLE_NAME = 'order';

	//States
	public static $Status;
	const Unsorted = 0;
	const Canceled = 1;
	const Sorted = 2;

	private $detail = array();

	public function __construct($id = 0){
		parent::__construct();

		$id = intval($id);
		if($id > 0){
			$this->fetch('*', 'id='.$id);

			global $db, $tpre;
			$this->detail = $db->fetch_all("SELECT * FROM {$tpre}orderdetail d WHERE orderid=$id");
		}
	}

	public function toReadable(){
		$attr = parent::toReadable();

		$attr['dateline'] = rdate($attr['dateline']);
		$attr['detail'] = $this->detail;

		return $attr;
	}

	public function getUserOrderNum(){
		global $db, $tpre;
		$userid = $this->userid;
		$dateline = $this->dateline;
		return $db->result_first("SELECT COUNT(*) FROM {$tpre}order WHERE userid=$userid AND dateline<$dateline");
	}

	public function getDetails(){
		return $this->detail;
	}

	//warning: you must call insert() after all the details has been added
	public function addDetail($d){
		global $db, $tpre;

		if($d['storageid']){
			$number = $d['amount'] * $d['number'];
			$storage = new ProductStorage;
			$storage->id = $d['storageid'];
			if(!$storage->updateNum(-$number)){
				return false;
			}
		}

		$d['subtotal'] = $d['number'] * $d['price'];
		$this->totalprice += $d['subtotal'];

		$this->detail[] = array(
			'productid' => $d['productid'],
			'storageid' => $d['storageid'],
			'productname' => $d['name'],
			'subtype' => $d['subtype'],
			'amount' => $d['amount'],
			'amountunit' => $d['amountunit'],
			'number' => $d['number'],
			'subtotal' => $d['subtotal'],
		);

		return true;
	}

	public function clearDetail(){
		$this->detail = array();
		$this->totalprice = 0;
	}

	public function insert($extra = ''){
		if(empty($this->detail)){
			return false;
		}

		global $tpre;
		$this->dateline = TIMESTAMP;

		parent::insert($extra);

		global $db;
		foreach($this->detail as &$d){
			$d['orderid'] = $this->id;
			if(is_numeric($d['amountunit'])){
				$d['amountunit'] = Product::AmountUnits($d['amountunit']);
			}
		}
		unset($d);

		$table = $db->select_table('orderdetail');
		$table->multi_insert($this->detail);

		return true;
	}

	public function cancel(){
		global $db, $tpre;

		$table = $db->select_table('orderdetail');
		$details = $table->fetch_all('storageid,amount,number', 'orderid='.$this->id.' AND storageid IS NOT NULL');
		foreach($details as $d){
			$num = $d['amount'] * $d['number'];
			$db->query("UPDATE {$tpre}productstorage SET num=num+$num WHERE id={$d['storageid']}");
		}

		runhooks('order_canceled', array($this));
	}

	//Operator Group
	const SystemOperated = 0;
	const AdministratorOperated = 1;
	const UserOperated = 2;

	//Operations
	const StatusChanged = 1;
	const DetailOutOfStock = 2;
	const PriceChanged = 3;
	const DetailInStock = 4;

	public function addLog($operator, $operation, $extra = NULL){
		$log = array(
			'orderid' => $this->id,
			'operator' => $operator->id,
			'operatorgroup' => self::SystemOperated,
			'operation' => $operation,
			'extra' => $extra,
			'dateline' => TIMESTAMP,
		);

		if($log['extra'] !== NULL){
			$log['extra'] = (string) $log['extra'];
		}

		if($operator instanceof Administrator){
			$log['operatorgroup'] = self::AdministratorOperated;
		}elseif($operator instanceof User){
			$log['operatorgroup'] = self::UserOperated;
		}

		runhooks('order_log_added', array($this, $log));

		global $db;
		$table = $db->select_table('orderlog');
		$table->insert($log, false, 'DELAYED');
		return $table->insert_id();
	}

	public function getLogs(){
		global $db, $tpre;
		$detail_in_stock = self::DetailInStock;
		$detail_out_of_stock = self::DetailOutOfStock;
		$detailop = $detail_in_stock.','.$detail_out_of_stock;

		$operatorgroup = self::AdministratorOperated;
		return $db->fetch_all("SELECT l.*,a.realname,a.mobile,d.productname,d.subtype,d.amount,d.number,d.amountunit
			FROM {$tpre}orderlog l
				LEFT JOIN {$tpre}administrator a ON l.operatorgroup=$operatorgroup AND a.id=l.operator
				LEFT JOIN {$tpre}orderdetail d ON l.operation IN ($detailop) AND d.id=l.extra
			WHERE l.orderid={$this->id}");
	}

	public function makeComment($comment){
		global $db;
		$table = $db->select_table('ordercomment');
		$comment['orderid'] = $this->id;
		isset($comment['dateline']) || $comment['dateline'] = TIMESTAMP;
		$comment['content'] = htmlspecialchars($comment['content']);
		$table->insert($comment, false, 'IGNORE');
	}

	public function getComment(){
		global $db;
		$table = $db->select_table('ordercomment');
		return $table->fetch_first('*', 'orderid='.$this->id);
	}
}

Order::$Status = array(
	Order::Unsorted => lang('common', 'order_unsorted'),
	Order::Sorted => lang('common', 'order_sorted'),
	Order::Canceled => lang('common', 'order_canceled'),
);
