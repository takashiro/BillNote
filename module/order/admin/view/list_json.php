<?php

if(!defined('S_ROOT')) exit('access denied');

foreach($orders as &$o){
	$o = array(
		'id' => intval($o['id']),
		'status' => intval($o['status']),
	);
}
unset($o);

echo json_encode(array(
	'totalnum' => $pagenum,
	'page' => $page,
	'data' => $orders
));

?>
