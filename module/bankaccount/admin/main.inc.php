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

class BankAccountMainModule extends AdminControlPanelModule{

	public function editAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

		if($_POST){
			$bankaccount = new BankAccount($id);

			if(isset($_POST['remark'])){
				$bankaccount->remark = trim($_POST['remark']);
			}

			if(isset($_POST['handleorder'])){
				$bankaccount->handleorder = !empty($_POST['handleorder']);
			}

			if(isset($_POST['orderpaymentmethod'])){
				$paymentconfig = readdata('payment');
				$orderpaymentmethod = intval($_POST['orderpaymentmethod']);
				if(!empty($paymentconfig['enabled_method'][$orderpaymentmethod])){
					$bankaccount->orderpaymentmethod = $orderpaymentmethod;
				}
			}

			if(isset($_POST['addressrange'])){
				$components = explode(',', $_POST['addressrange']);
				do{
					$componentid = array_pop($components);
					$componentid = intval($componentid);
				}while($components && empty($componentid));

				if($componentid){
					$bankaccount->addressrange = $componentid;
				}
			}

			if($id <= 0){
				$bankaccount->insert();
			}

			showmsg('edit_succeed', $mod_url);
		}


		$a = new BankAccount($id);
		$a = $a->toReadable();

		$paymentconfig = readdata('payment');
		include view('edit');
	}

	public function deleteAction(){
		$id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

		if($id <= 0){
			showmsg('illegal_operation');
		}

		if(!empty($_GET['confirm'])){
			BankAccount::Delete($id);
			redirect($mod_url);
		}else{
			showmsg('confirm_to_delete_bank_account', 'confirm');
		}
	}

	public function transferAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$sourceaccount = isset($_REQUEST['sourceaccount']) ? intval($_REQUEST['sourceaccount']) : 0;
		$targetaccount = isset($_REQUEST['targetaccount']) ? intval($_REQUEST['targetaccount']) : 0;

		if($_POST){
			$delta = floatval($_POST['delta']);
			if($delta <= 0){
				showmsg('the_number_you_must_be_kidding_me', 'back');
			}

			$bankaccount = new BankAccount($sourceaccount);
			$result = $bankaccount->transferTo($targetaccount, $delta, isset($_POST['reason']) ? $_POST['reason'] : '');

			if($result === true){
				showmsg('successfully_transfered', 'refresh');
			}else{
				switch($result){
				case BankAccount::ERROR_INVALID_INSUFFICIENT_AMOUNT:
					showmsg('source_account_is_insufficient', 'back');
				case BankAccount::ERROR_TARGET_NOT_EXIST:
					showmsg('target_does_not_exist', 'back');
				case BankAccount::ERROR_INVALID_ARGUMENT:
					showmsg('invalid_argument_received', 'back');
				default:
					showmsg('unknown_error', 'back');
				}
			}
		}

		$accounts = array();
		$account_amount = array();
		$table = $db->select_table('bankaccount');
		foreach($table->fetch_all('id,remark,amount') as $a){
			$accounts[$a['id']] = $a['remark'];
			$account_amount[$a['id']] = $a['amount'];
		}
		unset($a);

		include view('transfer');
	}

	public function withdrawAction(){
		$this->_changeAccount('withdraw');
	}

	public function depositAction(){
		$this->_changeAccount('deposit');
	}

	public function _changeAccount($action){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
		if($_POST){
			$delta = isset($_POST['delta']) ? abs($_POST['delta']) : 0;
			if ($delta <= 0){
				showmsg('the_number_you_must_be_kidding_me');
			}

			if($action == 'withdraw'){
				$operation = BankAccount::OPERATION_WITHDRAW;
				$delta = -$delta;
			}else{
				$operation = BankAccount::OPERATION_DEPOSIT;
			}
			$a = new BankAccount;
			$a->id = $id;
			if($a->updateAmount($delta)){
				$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
				$a->addLog($operation, $delta, $reason, $_G['admin']->id);
				showmsg('successfully_'.$action.'_bankaccount', 'refresh');
			}else{
				showmsg('source_account_is_insufficient', 'back');
			}
		}

		$account = new BankAccount($id);
		if ($account->exists()) {
			$account = $account->toReadable();
			include view('withdraw');
		}
	}

	public function logAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		if ($id <= 0)
			exit('access denied');

		$condition = array("l.accountid=$id");

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

		$condition = implode(' AND ', $condition);

		$limit = 20;
		$offset = ($page - 1) * $limit;
		$logs = $db->fetch_all("SELECT l.*, a.realname, a.account
			FROM {$tpre}bankaccountlog l
				LEFT JOIN {$tpre}administrator a ON a.id=l.operatorid
			WHERE $condition
			ORDER BY l.id DESC
			LIMIT $offset, $limit");

		foreach($logs as &$l){
			$l['operator'] = empty($l['realname']) ? $l['account'] : $l['realname'];
		}
		unset($l);

		$time_start && $time_start = rdate($time_start);
		$time_end && $time_end = rdate($time_end);

		$pagenum = $db->result_first("SELECT COUNT(*)
			FROM {$tpre}bankaccountlog l
			WHERE $condition");

		include view('log');
	}

	public function listAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$limit = 20;
		$offset = ($page - 1) * $limit;
		$table = $db->select_table('bankaccount');
		$accounts = $table->fetch_all('*', "1 LIMIT $offset,$limit");
		$pagenum = $table->result_first('COUNT(*)');
		include view('list');
	}

	public function defaultAction(){
		$this->listAction();
	}
}

?>
