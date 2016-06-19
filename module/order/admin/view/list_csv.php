<?php

if(!defined('IN_ADMINCP')) exit('access denied');

rheader('Cache-Control: no-cache, must-revalidate');
rheader('Content-Type: application/octet-stream');
rheader('Content-Disposition: attachment; filename="'.$_CONFIG['sitename'].'订单('.rdate(TIMESTAMP, 'Y-m-d His').').csv"');

//UTF-8 BOM
echo chr(0xEF), chr(0xBB), chr(0xBF);

//Header
echo '编号,往来单位,历史订单,物品,价格(', Product::$PriceUnit, '),状态,支付方式,收款账户,管理员,下单时间,留言', "\r\n";

function output_order_detail($d){
	if($d['state'] == 1){
		echo '[缺货]';
	}
	echo $d['productname'];
	if($d['subtype']){
		echo '(', $d['subtype'], ')';
	}
	echo $d['amount'] * $d['number'], $d['amountunit'];
}

//Body
foreach($orders as $o){
	echo $o['id'], ',';
	if(!empty($o['nickname'])){
		echo $o['nickname'];
	}elseif(!empty($o['account'])){
		echo $o['account'];
	}else{
		echo $o['userid'];
	}
	echo ',', $o['ordernum'], ',"';
	if($o['detail']){
		$d = current($o['detail']);
		output_order_detail($d);
		next($o['detail']);

		while($d = current($o['detail'])){
			echo "\r\n";
			output_order_detail($d);
			next($o['detail']);
		}
	}
	echo '",', $o['totalprice'], ',';
	echo isset(Order::$Status[$o['status']]) ? Order::$Status[$o['status']] : '未知', ',';
	echo isset(Wallet::$PaymentMethod[$o['paymentmethod']]) ? Wallet::$PaymentMethod[$o['paymentmethod']] : '未知', ',';
	echo $o['bankaccount'], ',';
	echo $o['adminname'], ',';
	echo rdate($o['dateline']), ',';
	echo $o['message'], "\r\n";
}
