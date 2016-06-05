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

function parse_time_range($var){
	$start = !empty($_REQUEST[$var.'_start']) ? rstrtotime($_REQUEST[$var.'_start']) : null;
	$end = !empty($_REQUEST[$var.'_end'])  ? rstrtotime($_REQUEST[$var.'_end']) : null;
	$end && $start && $end < $start && $end = $start;
	return array($start, $end);
}

class UserMainModule extends AdminControlPanelModule{

	public function getPermissions(){
		return array(
			'user_update_wallet',
		);
	}

	public function defaultAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$condition = array('deleted=0');		//SQL
		$query_string = array();	//分页

		//注册时间范围
		list($regtime_start, $regtime_end) = parse_time_range('regtime');
		if($regtime_start !== null){
			$condition[] = 'u.regtime>='.$regtime_start;
			$regtime_start = rdate($regtime_start);
			$query_string[] = 'regtime_start='.$regtime_start;
		}
		if($regtime_end !== null){
			$condition[] = 'u.regtime<='.$regtime_end;
			$regtime_end = rdate($regtime_end);
			$query_string[] = 'regtime_end='.$regtime_end;
		}

		//下单时间范围
		$order_condition = array();
		list($ordertime_start, $ordertime_end) = parse_time_range('ordertime');
		if($ordertime_start !== null){
			$order_condition[] = 'o.dateline>='.$ordertime_start;
			$ordertime_start = rdate($ordertime_start);
			$query_string[] = 'ordertime_start='.$ordertime_start;
		}
		if($ordertime_end !== null){
			$order_condition[] = 'o.dateline<='.$ordertime_end;
			$ordertime_end = rdate($ordertime_end);
			$query_string[] = 'ordertime_end='.$ordertime_end;
		}

		if($order_condition){
			$order_condition = implode(' AND ', $order_condition);
			$condition[] = "EXISTS (SELECT * FROM {$tpre}order o WHERE $order_condition)";
			$order_condition = 'AND '.$order_condition;
		}else{
			$order_condition = '';
		}

		//下单数量范围
		$subquery_ordernum = "(SELECT COUNT(*) FROM {$tpre}order o WHERE o.userid=u.id $order_condition)";
		$ordernum_min = '';
		if(isset($_REQUEST['ordernum_min']) && $_REQUEST['ordernum_min'] != ''){
			$ordernum_min = max(0, intval($_REQUEST['ordernum_min']));
			$condition[] = $subquery_ordernum.'>='.$ordernum_min;
			$query_string[] = 'ordernum_min='.$ordernum_min;
		}
		$ordernum_max = '';
		if(isset($_REQUEST['ordernum_max']) && $_REQUEST['ordernum_max'] != ''){
			$ordernum_max = max(0, intval($_REQUEST['ordernum_max']));
			$condition[] = $subquery_ordernum.'<='.$ordernum_max;
			$query_string[] = 'ordernum_max='.$ordernum_max;
		}

		//生成条件子句
		if($condition){
			$condition = implode(' AND ', $condition);
		}else{
			$condition = '1';
		}

		$output_formats = array('csv', 'html');
		$output_format = isset($_REQUEST['format']) && in_array($_REQUEST['format'], $output_formats) ? $_REQUEST['format'] : 'html';

		$limit_subsql = '';
		if($output_format == 'html'){
			$total_user_num = $db->result_first("SELECT COUNT(*) FROM {$tpre}user WHERE 1");

			$user_num = $db->result_first("SELECT COUNT(*)
				FROM {$tpre}user u
				WHERE $condition");

			$limit = 20;
			$offset = ($page - 1) * $limit;
			$limit_subsql = "LIMIT $offset, $limit";
		}

		$user_list = $db->fetch_all("SELECT u.*
			FROM {$tpre}user u
			WHERE $condition $limit_subsql");
		foreach($user_list as &$u){
			$u['ordernum'] = 0;
		}
		unset($u);

		$userids = array();
		$user_map = array();
		foreach($user_list as &$u){
			$userids[] = $u['id'];
			$user_map[$u['id']] = &$u;
		}
		unset($u);

		if($userids){
			$userids = implode(',', $userids);
			$user_ordernum = $db->fetch_all("SELECT userid,COUNT(*) AS ordernum FROM {$tpre}order WHERE userid IN ($userids) GROUP BY userid");
			foreach($user_ordernum as $u){
				$user_map[$u['userid']]['ordernum'] = $u['ordernum'];
			}
			unset($u, $user_map);
		}

		if($output_format == 'html'){
			if($query_string){
				$query_string = '&'.implode('&', $query_string);
			}else{
				$query_string = '';
			}

			include view('list');
		}else{
			include view('list_csv');
		}
	}

	public function profileAction(){
		if(empty($_GET['id']))
			exit('parameter id is missing.');

		$id = intval($_GET['id']);
		global $db;
		$table = $db->select_table('user');
		$user = $table->fetch_first('*', 'id='.$id);
		if(!$user)
			showmsg('user_does_not_exist');

		extract($GLOBALS, EXTR_REFS | EXTR_SKIP);
		include view('profile');
	}

	public function updateWalletAction(){
		global $_G;
		if(empty($_G['admin']) || !$_G['admin']->hasPermission('user_update_wallet'))
			exit('permission denied');

		if(empty($_GET['id']))
			exit('parameter id is missing.');

		if(empty($_POST['wallet_delta']))
			exit('parameter wallet_delta is missing.');

		$uid = intval($_GET['id']);
		if($uid <= 0)
			showmsg('invalid uid', 'back');

		$delta = floatval($_POST['wallet_delta']);
		if($delta == 0)
			showmsg('the_number_you_must_be_kidding_me', 'back');

		global $db, $tpre;
		$db->query("UPDATE {$tpre}user SET wallet=wallet+$delta WHERE id=$uid");

		if($db->affected_rows > 0){
			$log = array(
				'uid' => $uid,
				'type' => Wallet::AdminModLog,
				'dateline' => TIMESTAMP,
				'delta' => $delta,
			);
			$table = $db->select_table('userwalletlog');
			$table->insert($log);
			showmsg('update_wallet_successfully', 'refresh');
		}

		showmsg('failed_to_modify_wallet', 'back');
	}

	public function editAction(){
		$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

		if($_POST){
			if(empty($_POST['nickname']))
				exit('invalid nickname');

			$user = array(
				'nickname' => trim($_POST['nickname']),
			);

			global $db;
			$table = $db->select_table('user');

			if($id <= 0){
				$user['regtime'] = TIMESTAMP;
				$table->insert($user);
				$user['id'] = $table->insert_id();
			}else{
				$table->update($user, 'id='.$id);
				$user['id'] = $id;
			}

			global $mod_url;
			showmsg('edit_succeed', $mod_url.'&action=profile&id='.$user['id']);
		}

		if($id > 0){
			global $db;
			$table = $db->select_table('user');
			$u = $table->fetch_first('*', 'id='.$id);
		}else{
			$u = array(
				'nickname' => '',
			);
		}

		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);
		include view('edit');
	}

	public function deleteAction(){
		$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
		if($id > 0){
			global $db;
			$table = $db->select_table('user');
			$table->update(array('deleted' => 1),'id='.$id);
			showmsg('successfully_deleted_a_user', 'refresh');
		}
	}

}
