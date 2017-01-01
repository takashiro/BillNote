<?php

/***********************************************************************
Elf Web App
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

class UserWalletDetailModule extends AdminControlPanelModule{

	public function getRequiredPermissions(){
		return array('user');
	}

	public function defaultAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$query_string = array();
		$condition = array();

		if(isset($_GET['userid'])){
			$userid = intval($_GET['userid']);
			$condition[] = 'l.uid='.$userid;
			$query_string['userid'] = $userid;
		}

		if(!empty($_GET['tradeid'])){
			$tradeid = trim($_GET['tradeid']);
			$condition[] = 'l.tradeid=\''.addslashes($tradeid).'\'';
			$query_string['tradeid'] = $tradeid;
			unset($_GET['time_start'], $_GET['time_end']);
		}elseif(!empty($_GET['logid'])){
			$logid = intval($_GET['logid']);
			$condition[] = 'l.id='.$logid;
			$query_string['logid'] = $logid;
			unset($_GET['time_start'], $_GET['time_end']);
		}

		if(!empty($_GET['time_start'])){
			$time_start = rstrtotime($_GET['time_start']);
			$condition[] = "l.dateline>=$time_start";
		}else{
			$time_start = '';
		}

		if(!empty($_GET['time_end'])){
			$time_end = rstrtotime($_GET['time_end']);
			$condition[] = "l.dateline<=$time_end";
		}else{
			$time_end = '';
		}

		$condition = empty($condition) ? '1' : implode(' AND ', $condition);

		$format = $_GET['format'] ?? 'html';
		if($format == 'html'){
			$limit = 20;
			$offset = ($page - 1) * $limit;
			$limit_sql = 'LIMIT '.$offset.','.$limit;
		}else{
			$limit_sql = '';
		}

		$logs = $db->query("SELECT l.*, u.nickname,
				(SELECT SUM(delta) FROM {$tpre}userwalletlog WHERE uid=l.uid AND id<=l.id) AS current
			FROM {$tpre}userwalletlog l
				LEFT JOIN {$tpre}user u ON u.id=l.uid
			WHERE $condition
			ORDER BY l.id DESC
			$limit_sql");
		if($format == 'html'){
			if($time_start){
				$time_start = rdate($time_start);
				$query_string['time_start'] = $time_start;
			}
			if($time_end){
				$time_end = rdate($time_end);
				$query_string['time_end'] = $time_end;
			}

			$pagenum = $db->result_first("SELECT COUNT(*)
				FROM {$tpre}userwalletlog l
				WHERE $condition");

			include view('walletdetail');
		}else{
			include view('walletdetail_csv');
		}
	}

}
