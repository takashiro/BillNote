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

class OrderMainModule extends AdminControlPanelModule{

	public function defaultAction(){
		$this->listAction();
	}

	public function listAction($action = 'list'){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$available_status = array(
			Order::Unsorted => true,
			Order::Sorted => true,
			Order::Canceled => false
		);

		//显示（或导出Excel表格）订单列表
		if($action == 'list'){
			//保存查询条件，数组中的每个元素用AND连接构成一个WHERE子句
			$condition = array();

			//$display_status数组的键名即订单的状态，$display_status[X]为true则显示状态为X的订单
			if(!empty($_POST['display_status']) && is_array($_POST['display_status'])){
				$display_status = $_POST['display_status'];
			}elseif(isset($_GET['display_status'])){
				$display_status = array();
				if(is_string($_GET['display_status'])){
					foreach(explode(',', $_GET['display_status']) as $status){
						$display_status[$status] = true;
					}
				}else{
					foreach($_GET['display_status'] as $status => $on){
						$display_status[$status] = true;
					}
				}
			}else{
				$display_status = array();
				foreach(Order::$Status as $statusid => $value){
					$display_status[$statusid] = true;
				}
				unset($display_status[Order::Canceled]);
			}

			//过滤掉无权限查看的订单
			foreach($display_status as $statusid => $value){
				if(!isset($available_status[$statusid])){
					unset($display_status[$statusid]);
				}else{
					$available_status[$statusid] = true;
				}
			}

			//按订单号查询
			if(!empty($_REQUEST['orderid'])){
				$condition[] = 'o.id='.intval($_REQUEST['orderid']);

				$time_start = '';
				$time_end = '';
				$available_status;
				foreach($available_status as &$checked){
					$checked = true;
				}
				unset($checked);
				$display_status = $available_status;

				$_REQUEST['tradestate'] = 0;
				$_REQUEST['time_start'] = $_REQUEST['time_end'] = '';
			}

			//根据支付方式过滤订单
			if(isset($_REQUEST['paymentmethod'])) {
				$paymentmethod = intval($_REQUEST['paymentmethod']);
				if($paymentmethod >= 0){
					$condition[] = 'o.paymentmethod='.$paymentmethod;
				}
			}

			//根据收款账户过滤订单
			if(isset($_REQUEST['bankaccountid'])){
				$bankaccountid = intval($_REQUEST['bankaccountid']);
				if($bankaccountid > 0){
					$condition[] = '.o.bankaccountid='.$bankaccountid;
				}
			}

			//根据付款状态查询订单
			if(!isset($_REQUEST['tradestate'])){
				$tradestate = Wallet::TradeSuccess;
				$condition[] = 'o.tradestate='.$tradestate;
			}else{
				$tradestate = intval($_REQUEST['tradestate']);

				//@todo: resolve the hack
				if($tradestate > 0){
					if($tradestate != 1){
						$condition[] = 'o.tradestate='.$tradestate;
					}else{
						$condition[] = 'o.tradestate IN (0,1)';
					}
				}
			}

			//下单起始时间
			if(isset($_REQUEST['time_start'])){
				$time_start = empty($_REQUEST['time_start']) ? '' : rstrtotime($_REQUEST['time_start']);
			}else{
				$time_start = rmktime(0, 0, 0, rdate(TIMESTAMP, 'm'), rdate(TIMESTAMP, 'd'), rdate(TIMESTAMP, 'Y'));
			}

			//下单截止时间
			if(isset($_REQUEST['time_end'])){
				$time_end = empty($_REQUEST['time_end']) ? '' : rstrtotime($_REQUEST['time_end']);
			}elseif(empty($time_end)){
				$time_end = $time_start + 1 * 24 * 3600;
			}

			if($time_start !== ''){
				$condition[] = 'o.dateline>='.$time_start;
				$time_start = rdate($time_start, 'Y-m-d H:i');
			}

			if($time_end !== ''){
				$condition[] = 'o.dateline<='.$time_end;
				$time_end = rdate($time_end, 'Y-m-d H:i');
			}

			$display_status = array_keys($display_status);
			if($display_status){
				$condition[] = 'o.status IN ('.implode(',', $display_status).')';
			}else{
				$condition[] = '0';
			}

			//根据用户ID查询订单
			if(!empty($_REQUEST['userid'])){
				$userid = intval($_REQUEST['userid']);
				$condition[] = 'o.userid='.$userid;
			}else{
				$userid = '';
			}

			//根据管理员ID查询订单
			if(!empty($_REQUEST['admin'])){
				$adminid = intval($_REQUEST['adminid']);
				$condition[] = 'o.adminid='.$adminid;
			}else{
				$adminid = '';
			}

			//根据收件人姓名查询订单
			if(!empty($_REQUEST['addressee'])){
				$addressee = trim($_REQUEST['addressee']);
				$condition[] = 'o.addressee LIKE \'%'.$addressee.'%\'';
			}else{
				$addressee = '';
			}

			//根据手机号查询订单
			if(!empty($_REQUEST['mobile'])){
				$mobile = trim($_REQUEST['mobile']);
				$condition[] = 'o.mobile=\''.$mobile.'\'';
			}else{
				$mobile = '';
			}

			//连接成WHERE子句
			$condition = implode(' AND ', $condition);

			//处理统计信息
			$stat = array(
				'statonly' => !empty($_REQUEST['stat']['statonly']),		//仅显示统计信息
				'totalprice' => !empty($_REQUEST['stat']['totalprice']),	//计算总价格
				'item' => !empty($_REQUEST['stat']['item']),				//根据商品分类统计
			);

			//判断显示格式，若为csv则导出Excel表格
			$template_formats = array('html', 'csv', 'ticket', 'barcode', 'json');
			$template_format = &$_REQUEST['format'];
			if(empty($template_format) || !in_array($template_format, $template_formats)){
				$template_format = $template_formats[0];
			}

			//从数据库中查询订单，实现分页
			$pagenum = $db->result_first("SELECT COUNT(*) FROM {$tpre}order o WHERE $condition");
			if(!$stat['statonly']){
				$limit_subsql = '';
				if($template_format == 'html' || $template_format == 'json'){
					$limit = 20;
					$offset = ($page - 1) * $limit;
					$limit_subsql = "LIMIT $offset,$limit";
				}

				$confirmed_status = Order::Sorted;
				$orders = $db->fetch_all("SELECT o.*,u.nickname,u.account, b.remark AS bankaccount,
						IF(a.realname!='',a.realname,a.account) AS adminname,
						(SELECT COUNT(*) FROM {$tpre}order WHERE userid=o.userid AND status=$confirmed_status) AS ordernum
					FROM {$tpre}order o
						LEFT JOIN {$tpre}user u ON u.id=o.userid
						LEFT JOIN {$tpre}administrator a ON a.id=o.adminid
						LEFT JOIN {$tpre}bankaccount b ON b.id=o.bankaccountid
					WHERE $condition
					ORDER BY o.status,o.tradetime
					$limit_subsql");
			}else{
				$orders = array();
			}

			//计算统计信息
			if($template_format == 'html'){
				$statdata = array();
				if($stat['totalprice']){
					$statdata['totalprice'] = $db->result_first("SELECT SUM(totalprice) FROM {$tpre}order o WHERE $condition");
				}else{
					$statdata['totalprice'] = 0.00;
				}

				if($stat['item']){
					$statdata['item'] = $db->fetch_all("SELECT d.productname,d.subtype,d.amountunit,SUM(d.amount*d.number) AS num,SUM(d.subtotal) AS totalprice
						FROM {$tpre}orderdetail d
							LEFT JOIN {$tpre}order o ON d.orderid=o.id
						WHERE $condition
						GROUP BY d.productname,d.subtype,d.amountunit");
				}else{
					$statdata['item'] = array();
				}
			}

			//查询各个订单的详细内容（每种商品的购买数量、单项价格等）
			if($orders){
				$orderids = array();
				foreach($orders as &$o){
					$orderids[] = $o['id'];
				}
				unset($o);

				$orderids = implode(',', $orderids);

				//取得所有订单的物品列表
				$order_details = array();
				$query = $db->query("SELECT d.id,d.productname,d.subtype,d.amount,d.amountunit,d.number,d.orderid,d.state,d.subtotal
					FROM {$tpre}orderdetail d
					WHERE d.orderid IN ($orderids)");
				while($d = $query->fetch_assoc()){
					$order_details[$d['orderid']][] = $d;
				}

				foreach($orders as &$o){
					$o['detail'] = !empty($order_details[$o['id']]) ? $order_details[$o['id']] : array();
					is_array($o['detail']) || $o['detail'] = array();
				}
				unset($o, $order_details);
			}

		//高级查找
		}else{
			$display_status = array_keys(Order::$Status);
			unset($display_status[Order::Canceled]);

			$time_start = rmktime(0, 0, 0, rdate(TIMESTAMP, 'm'), rdate(TIMESTAMP, 'd') - 1, rdate(TIMESTAMP, 'Y'));
			$time_end = $time_start + 24 * 3600;
		}

		if($action == 'list'){
			if($template_format == 'html'){
				$query_string = array();
				if($display_status){
					$query_string['display_status'] = implode(',', $display_status);
				}

				$vars = array(
					'time_start', 'time_end',
					'stat',
					'mobile', 'addressee',
					'userid',
					'adminid',
					'tradestate',
					'paymentmethod',
					'bankaccountid',
				);
				foreach($vars as $var){
					if(isset($$var)){
						$query_string[$var] = $$var;
					}
				}

				$query_string = http_build_query($query_string);

				$bankaccounts = array(0 => '');
				$query = $db->query("SELECT id,remark FROM {$tpre}bankaccount");
				while($row = $query->fetch_row()){
					$bankaccounts[$row[0]] = $row[1];
				}

				include view('list');
			}else{
				if($template_format == 'ticket' || $template_format == 'barcode'){
					$ticketconfig = readdata('ticket');
					foreach($orders as &$o){
						$o['dateline'] = rdate($o['dateline']);
						$o['tradetime'] = rdate($o['tradetime']);
					}
					unset($o);
				}
				include view('list_'.$template_format);
			}
		}else{
			include view('search');
		}
	}

	public function mark_unsortedAction(){
		if(empty($_GET['orderid'])) exit('permission denied');

		global $_G;
		$order = new Order($_GET['orderid']);

		if($order->exists()){
			if($_G['admin']->isSuperAdmin() && $order->status != Order::Unsorted){
				$order->status = Order::Unsorted;
				$order->addLog($_G['admin'], Order::StatusChanged, Order::Unsorted);
			}
		}

		empty($_GET['ajaxform']) || exit('1');
		empty($_SERVER['HTTP_REFERER']) || redirect($_SERVER['HTTP_REFERER']);
	}

	public function mark_sortedAction(){
		if(empty($_GET['orderid'])) exit('permission denied');

		global $_G;
		$order = new Order($_GET['orderid']);

		if($order->exists()){
			if($order->status == Order::Unsorted || $_G['admin']->isSuperAdmin()){
				$order->status = Order::Sorted;
				$order->addLog($_G['admin'], Order::StatusChanged, Order::Sorted);
			}
		}

		empty($_GET['ajaxform']) || exit('1');
		empty($_SERVER['HTTP_REFERER']) || redirect($_SERVER['HTTP_REFERER']);
	}

	public function ticketAction($ticket_type = 'ticket'){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$orderid = isset($_GET['orderid']) ? intval($_GET['orderid']) : 0;
		if($orderid > 0){
			$order = new Order($orderid);

			if($order->id <= 0){
				exit('the order has been canceled');
			}

			$ordernum = $order->getUserOrderNum();
			$order = $order->toReadable();
			$order['ordernum'] = &$ordernum;

			$ticketconfig = readdata('ticket');

			include view('list_'.$ticket_type);
		}
	}

	public function barcodeAction(){
		$this->ticketAction('barcode');
	}

	public function cancelAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$orderid = isset($_GET['orderid']) ? intval($_GET['orderid']) : 0;
		if($orderid > 0){
			if(empty($_GET['confirm'])){
				showmsg('confirm_to_cancel_order', 'confirm');
			}

			$new_status = Order::Canceled;
			$db->query("UPDATE {$tpre}order SET status=$new_status WHERE id=$orderid");
			if($db->affected_rows > 0){
				$order = new Order($orderid);
				$order->addLog($_G['admin'], Order::StatusChanged, Order::Canceled);
				$order->cancel();

				if($order->bankaccountid){
					$bankaccount = new BankAccount;
					$bankaccount->id = $order->bankaccountid;

					$db->query('START TRANSACTION');
					if($bankaccount->updateAmount(-$order->totalprice)){
						$bankaccount->addLog(
							BankAccount::OPERATION_ORDER_OUTCOME,
							-$order->totalprice,
							lang('common', 'order').lang('common', 'order_canceled'),
							BankAccount::OPERATOR_SYSTEM,
							$order->id
						);
						$db->query('COMMIT');
					}
					$db->query('ROLLBACK');
				}
			}
			empty($_COOKIE['http_referer']) || redirect($_COOKIE['http_referer']);
		}
	}

	public function detail_outofstockAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		if(!$_G['admin']->hasPermission('order_sort_w')){
			exit('access denied');
		}

		$detailid = isset($_GET['detailid']) ? intval($_GET['detailid']) : 0;
		if($detailid <= 0){
			exit('access denied');
		}

		$state = !empty($_REQUEST['state']) ? 1 : 0;
		$order_unsorted = Order::Unsorted;
		$db->query("UPDATE {$tpre}orderdetail d
			SET d.state=$state
			WHERE d.id=$detailid AND (SELECT o.status FROM {$tpre}order o WHERE o.id=d.orderid)=$order_unsorted");

		$result = array();
		if($db->affected_rows){
			$orderid = $db->result_first("SELECT orderid FROM {$tpre}orderdetail WHERE id=$detailid");
			$db->query("UPDATE {$tpre}order o SET o.totalprice=(SELECT SUM(d.subtotal) FROM {$tpre}orderdetail d WHERE d.orderid=o.id AND d.state=0) WHERE o.id=$orderid");
			$result['totalprice'] = $db->result_first("SELECT totalprice FROM {$tpre}order WHERE id=$orderid");

			$order = new Order($orderid);
			$order->addLog($_G['admin'], $state ? Order::DetailOutOfStock : Order::DetailInStock, $detailid);
		}

		echo json_encode($result);
	}

	public function updateTradeStateAction(){
		if(empty($_GET['orderid']))
			exit('access denied');

		$orderid = intval($_GET['orderid']);
		if($orderid <= 0)
			exit('invalid order id');

		require module('alipay/config');

		$parameter = array(
			'service' => 'single_trade_query',
			'partner' => $alipay_config['partner'],
			'out_trade_no' => 'O'.$orderid,
			'_input_charset' => $alipay_config['input_charset'],
		);

		$alipaySubmit = new AlipaySubmit($alipay_config);
		$html_text = $alipaySubmit->buildRequestHttp($parameter);

		$doc = new XML;
		$doc->loadXML($html_text, 'alipay');
		$xml = $doc->toArray();
		if(isset($xml['is_success']) && $xml['is_success'] == 'T'){
			if(isset($xml['response']['trade'])){
				$trade = $xml['response']['trade'];

				$arguments = array(
					//商户订单号
					$trade['out_trade_no'],

					//支付宝交易号
					$trade['trade_no'],

					//交易状态
					$trade['trade_status'],
				);

				runhooks('alipay_notified', $arguments);
			}

			showmsg('successfully_updated_order_trade_state', 'refresh');
		}

		showmsg('order_not_exist_failed_to_update_order_trade_state', 'refresh');
	}

}

?>
