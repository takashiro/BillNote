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

class ProductTypeModule extends AdminControlPanelModule{

	public function getRequiredPermissions(){
		return array('product');
	}

	public function editAction(){
		$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
		$producttype = array();
		if(isset($_POST['name'])){
			$producttype['name'] = trim($_POST['name']);
		}
		if(isset($_POST['displayorder'])){
			$producttype['displayorder'] = intval($_POST['displayorder']);
		}


		global $db;
		$table = $db->select_table('producttype');
		if($id > 0){
			$table->update($producttype, 'id='.$id);
			$producttype['id'] = $id;
		}else{
			$table->insert($producttype);
			$producttype['id'] = $table->insert_id();
		}

		Product::RefreshCache();

		echo json_encode($producttype);
		exit;
	}

	public function deleteAction(){
		$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
		if($id > 0){
			global $db;
			$table = $db->select_table('producttype');
			$table->delete('id='.$id);
			Product::RefreshCache();

			echo $db->affected_rows;
		}else{
			echo 0;
		}
	}

	public function defaultAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$table = $db->select_table('producttype');
		$product_types = $table->fetch_all('*', '1 ORDER BY displayorder');

		include view('type');
	}
}


?>
