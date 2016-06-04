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

class BankAccount extends DBObject{
	const TABLE_NAME = 'bankaccount';

	const ERROR_INVALID_ARGUMENT = -1;
	const ERROR_INVALID_INSUFFICIENT_AMOUNT = -2;
	const ERROR_TARGET_NOT_EXIST = -3;

	const OPERATION_TRANSFER = 0;
	const OPERATION_ORDER_INCOME = 1;
	const OPERATION_PRODUCT_IMPORT = 2;
	const OPERATION_WITHDRAW = 3;
	const OPERATION_DEPOSIT = 4;
	const OPERATION_ORDER_OUTCOME = 5;

	const OPERATOR_SYSTEM = 0;

	public function __construct($id = 0){
		parent::__construct();
		if($id = intval($id)){
			$this->fetch('*', 'id='.$id);
		}
	}

	public function toArray(){
		if($this->id > 0){
			return parent::toArray();
		}else{
			return array(
				'id' => 0,
				'remark' => '',
				'amount' => 0,
				'addressrange' => 0,
			);
		}
	}

	public function toReadable(){
		return $this->toArray();
	}

	public function updateAmount($delta){
		global $db, $tpre;
		$extrasql = $delta <= 0 ? ' AND amount>='.(-$delta) : '';
		$db->query("UPDATE {$tpre}bankaccount SET amount=amount+{$delta} WHERE id={$this->id}".$extrasql);
		return $db->affected_rows > 0;
	}

	public function addLog($operation, $delta, $reason, $operatorid, $targetid = 0){
		if(!$this->id || $this->id <= 0)
			return 0;

		global $db;
		$table = $db->select_table('bankaccountlog');
		$log = array(
			'accountid' => $this->id,
			'dateline' => TIMESTAMP,
			'delta' => $delta,
			'reason' => $reason,
			'operation' => $operation,
			'operatorid' => $operatorid,
			'targetid' => $targetid,
		);
		$table->insert($log);
		return $table->insert_id();
	}

	public function transferTo($target, $delta, $reason = ''){
		$delta = floatval($delta);

		if($target instanceof BankAccount)
			$target = $target->id;
		else
			$target = intval($target);

		$error = self::ERROR_INVALID_ARGUMENT;
		if($delta > 0 && $target > 0){
			global $_G, $db, $tpre;
			$db->query('START TRANSACTION');
			if($this->updateAmount(-$delta)){
				$db->query("UPDATE {$tpre}bankaccount SET amount=amount+$delta WHERE id=$target");
				if($db->affected_rows > 0){
					$this->addLog(self::OPERATION_TRANSFER, -$delta, $reason, $_G['admin']->id, $target);
					$db->query('COMMIT');
					return true;
				}else{
					$error = ERROR_TARGET_NOT_EXIST;
				}
			}else{
				$error = ERROR_INVALID_INSUFFICIENT_AMOUNT;
			}
			$db->query('ROLLBACK');
		}

		return $error;
	}

	static public function __on_order_log_add($order, $log){
		if($log['operation'] != Order::StatusChanged || $log['extra'] != Order::Sorted){
			return;
		}

		if($order->bankaccountid > 0){
			$bankaccount = new BankAccount;
			$bankaccount->id = $order->bankaccountid;
			$db->query('START TRANSACTION');
			if($bankaccount->updateAmount($order->totalprice)){
				$bankaccount->addLog(
					BankAccount::OPERATION_ORDER_INCOME,
					$order->totalprice,
					lang('common', 'order').lang('common', 'order_sorted'),
					BankAccount::OPERATOR_SYSTEM,
					$order->id
				);
				$db->query('COMMIT');
			}
		}
	}
}

?>
