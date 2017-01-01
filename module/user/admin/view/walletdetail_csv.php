<?php

rheader('Cache-Control: no-cache, must-revalidate');
rheader('Content-Type: application/octet-stream');
rheader('Content-Disposition: attachment; filename="'.$_CONFIG['sitename'].'账目详情('.rdate(TIMESTAMP, 'Y-m-d His').').csv"');

//UTF-8 BOM
echo chr(0xEF), chr(0xBB), chr(0xBF);

echo '用户ID,用户昵称,时间,类型,金额,账户余额,订单号', "\n";
while($l = $logs->fetch_assoc()){
	echo $l['uid'], ',';
	echo $l['nickname'], ',';
	echo rdate($l['dateline']), ',';
	echo Wallet::$LogType[$l['type']], ',';
	echo $l['delta'], ',';
	echo $l['current'], ',';
	if ($l['type'] == Wallet::RechargeLog){
		echo '-';
	}elseif($l['orderid']){
		echo $l['orderid'];
	}
	echo "\n";
}
