<?php

if (!defined('IN_ADMINCP')) exit('access denied');

class ProductSaleReportModule extends AdminControlPanelModule{

	public function defaultAction(){
		$condition = array('o.status!='.Order::Canceled);

		if(isset($_POST['time_start'])){
			$time_start = rstrtotime($_POST['time_start']);
		}else{
			$time_start = rmktime(0, 0, 0, rdate(TIMESTAMP, 'm'), rdate(TIMESTAMP, 'd'), rdate(TIMESTAMP, 'Y'));
		}
		$condition[] = 'o.dateline>='.$time_start;

		if(isset($_POST['time_end'])){
			$time_end = rstrtotime($_POST['time_end']);
		}else{
			$time_end = rmktime(0, 0, 0, rdate(TIMESTAMP, 'm'), rdate(TIMESTAMP, 'd') + 1, rdate(TIMESTAMP, 'Y'));
		}
		$condition[] = 'o.dateline<'.$time_end;

		$condition = $condition ? implode(' AND ', $condition) : '1';

		global $db, $tpre;
		$items = $db->fetch_all("SELECT SUM(d.amount * d.number) AS amount,d.amountunit,d.productid,d.productname,d.subtype,(d.subtotal/d.amount/d.number) AS unitprice
			FROM {$tpre}orderdetail d
				LEFT JOIN {$tpre}order o ON o.id=d.orderid
			WHERE $condition
			GROUP BY d.productid,d.productname,d.subtype,d.amountunit,unitprice");
		foreach($items as &$item){
			$item['totalprice'] = $item['unitprice'] * $item['amount'];
		}
		unset($item);

		extract($GLOBALS, EXTR_SKIP);
		$time_start = rdate($time_start, 'Y-m-d H:i:s');
		$time_end = rdate($time_end, 'Y-m-d H:i:s');

		include view('salereport');
	}

}
