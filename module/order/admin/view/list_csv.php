<?php

if(!defined('IN_ADMINCP')) exit('access denied');

rheader('Cache-Control: no-cache, must-revalidate');
rheader('Content-Type: application/octet-stream');
rheader('Content-Disposition: attachment; filename="'.$_CONFIG['sitename'].'订单('.rdate(TIMESTAMP, 'Y-m-d His').').csv"');

//UTF-8 BOM
echo chr(0xEF), chr(0xBB), chr(0xBF);

//Header
echo '编号';
foreach($address_format as $name){
	echo ',', $name;
}
echo ',地址,收件人,电话,历史订单,物品,价格(', Product::$PriceUnit, '),物流状态,收货方式,下单时间,付款方式,付款状态,付款时间,留言', "\r\n";

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
	echo $o['id'];
	$maxi = count($address_format);
	for($i = 0; $i < $maxi; $i++){
		echo ',';
		if(isset($o['address'][$i]['name']))
			echo $o['address'][$i]['name'];
	}
	echo ',', $o['extaddress'], ',', $o['addressee'], ',"', $o['mobile'],'",', $o['ordernum'], ',"';
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
	echo isset(Order::$DeliveryMethod[$o['deliverymethod']]) ? Order::$DeliveryMethod[$o['deliverymethod']] : '?', ',';
	echo rdate($o['dateline']), ',';
	echo isset(Wallet::$PaymentMethod[$o['paymentmethod']]) ? Wallet::$PaymentMethod[$o['paymentmethod']] : '未知', ',';
	if($o['paymentmethod'] != Wallet::ViaCash){
		if(empty($o['tradestate'])){
			echo '等待付款';
		}else{
			echo isset(Wallet::$TradeState[$o['tradestate']]) ? Wallet::$TradeState[$o['tradestate']] : '未知';
		}
	}
	echo ',';
	echo rdate($o['tradetime']), ',', $o['message'], "\r\n";
}

?>
