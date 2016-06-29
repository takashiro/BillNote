<?php

if(!defined('IN_ADMINCP')) exit('access denied');

class UserWalletStatModule extends AdminControlPanelModule{

	public function defaultAction(){
		global $db, $tpre;

		if($_POST){
			$time_start = isset($_POST['time_start']) ? strtotime($_POST['time_start']) : '';
			$time_end = isset($_POST['time_end']) ? strtotime($_POST['time_end']) : '';

			$initvalues = array();
			$query = $db->query("SELECT l.uid,u.nickname,SUM(l.delta) AS initvalue
				FROM {$tpre}userwalletlog l
					LEFT JOIN {$tpre}user u ON u.id=l.uid
				WHERE dateline<=$time_start
				GROUP BY uid");
			while($l = $query->fetch_assoc()){
				$initvalues[$l['uid']] = $l['initvalue'];
			}
			unset($initlist);

			$statlist = $db->fetch_all("SELECT l.uid,u.nickname,SUM(l.delta) AS endvalue
				FROM {$tpre}userwalletlog l
					LEFT JOIN {$tpre}user u ON u.id=l.uid
				WHERE dateline<=$time_end AND EXISTS (SELECT uid
					FROM {$tpre}userwalletlog
					WHERE dateline>=$time_start AND dateline<=$time_end LIMIT 1)
				GROUP BY uid");
			foreach($statlist as &$l){
				$l['initvalue'] = isset($initvalues[$l['uid']]) ? $initvalues[$l['uid']] : 0.0;
			}
			unset($l, $initvalues);

			$time_start = rdate($time_start);
			$time_end = rdate($time_end);
		}else{
			$statlist = array();
			$time_start = $time_end = '';
		}

		extract($GLOBALS, EXTR_SKIP);
		include view('walletstat');
	}

}
