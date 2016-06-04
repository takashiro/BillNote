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

class WareHouseMainModule extends AdminControlPanelModule{

	public function defaultAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$warehouses = $db->fetch_all("SELECT * FROM {$tpre}warehouse");

		include view('list');
	}

	public function editAction(){
		$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

		if($_POST){
			$warehouse = array();

			if(isset($_POST['name'])){
				$warehouse['name'] = htmlspecialchars(trim($_POST['name']));
			}

			global $db;
			$table = $db->select_table('warehouse');
			if($id > 0){
				$table->update($warehouse, 'id='.$id);
				$warehouse['id'] = $id;
			}else{
				$table->insert($warehouse);
				$warehouse['id'] = $table->insert_id();
			}

			Warehouse::RefreshCache();

			if(!empty($_GET['ajax'])){
				echo json_encode($warehouse);
				exit;
			}else{
				showmsg('edit_succeed', 'refresh');
			}
		}
 	}

}

?>
