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

class ProductMainModule extends AdminControlPanelModule{

	public function defaultAction(){
		$this->listAction();
	}

	public function listAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$condition = array();
		$query_string = array();

		$product_types = Product::Types();
		if($_G['admin']->producttypes){
			$condition[] = 'type IN ('.$_G['admin']->producttypes.')';
		}

		if(!empty($_GET['productname'])){
			$productname = addslashes(trim($_GET['productname']));
			$condition[] = '(name LIKE \'%'.$productname.'%\' OR namecapital LIKE \'%'.$productname.'%\')';
			$query_string['productname'] = $productname;
		}

		if(!empty($_GET['type'])){
			$type = intval($_GET['type']);
			if(isset($product_types[$type])){
				$condition[] = 'type='.$type;
				$query_string['type'] = $type;
			}else{
				$type = 0;
			}
		}else{
			$type = 0;
		}

		$show_hidden = !empty($_GET['show_hidden']);
		if($show_hidden){
			$query_string['show_hidden'] = 1;
		}else{
			$condition[] = 'hide=0';
		}

		$limit = 20;
		$offset = ($page - 1) * $limit;

		$condition = $condition ? implode(' AND ', $condition) : '1';
		$table = $db->select_table('product');
		$products = $table->fetch_all('*', $condition.' ORDER BY hide,type,displayorder LIMIT '.$offset.','.$limit);
		$pagenum = $table->result_first('COUNT(*)', $condition);

		include view('list');
	}

	public function editAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$productid = !empty($_REQUEST['id']) ? max(0, intval($_REQUEST['id'])) : 0;

		if($_POST){
			if($productid == 0){
				if(empty($_POST['name'])){
					showmsg('please_fill_in_product_name', 'back');
				}

				$product = new Product;
			}else{
				$product = new Product($productid);
				if($_G['admin']->producttypes){
					$typeids = explode(',', $_G['admin']->producttypes);
					if(!in_array($product->type, $typeids))
						exit('permission denied');
				}
			}

			if(isset($_POST['name'])){
				$product->name = trim($_POST['name']);
			}

			if(isset($_POST['type'])){
				$typeid = intval($_POST['type']);

				$types = Product::Types();
				if($_G['admin']->producttypes){
					$types = explode(',', $_G['admin']->producttypes);
					$types = array_flip($types);
				}

				if(array_key_exists($typeid, $types)){
					$product->type = $typeid;
				}else{
					foreach($types as $typeid => $name){
						$product->type = $typeid;
						break;
					}
				}
			}

			if(isset($_POST['displayorder'])){
				$product->displayorder = intval($_POST['displayorder']);
			}

			if(isset($_POST['hide'])){
				$product->hide = !empty($_POST['hide']) ? 1 : 0;
			}

			foreach(array('text_color', 'background_color', 'icon_background') as $attr){
				if(isset($_POST[$attr])){
					$product->$attr = hexdec($_POST[$attr]);
				}
			}

			if(isset($_POST['briefintro'])){
				$product->briefintro = $_POST['briefintro'];
			}

			if(isset($_POST['introduction'])){
				$product->introduction = $_POST['introduction'];
			}

			if($productid == 0){
				$product->insert();
			}

			if(isset($_POST['name'])){
				$table = $db->select_table('productacronym');
				$table->delete('id='.$product->id);

				$acronyms = Hanzi::ToAcronym($product->name);
				foreach($acronyms as $acronym){
					$row = array(
						'id' => $product->id,
						'name' => $acronym,
					);
					$table->insert($row);
				}
			}

			$product->uploadImage('icon');
			$product->uploadImage('photo');

			if(!empty($_GET['ajax'])){
				echo json_encode($product->toArray());
			}else{
				showmsg('edit_succeed', 'refresh');
			}

		}else{
			$product = $this->getProductById($productid);
			$prices = $product->getPrices();
			$storages = $product->getStorages();
			$product = $product->toArray();

			include view('edit');
		}
	}

	public function deleteAction(){
		$id = !empty($_POST['id']) ? max(0, intval($_POST['id'])) : 0;
		if($id > 0){
			$extra = '';
			global $_G;
			if($_G['admin']->producttypes){
				$extra = 'type IN ('.$_G['admin']->producttypes.')';
			}
			Product::Delete($id, $extra);
		}
		echo 1;
	}

	public function editpriceAction(){
		if(empty($_GET['productid']))
			exit('access denied');
		$product = $this->getProductById($_GET['productid']);
		echo json_encode($product->editPrice($_POST));
	}

	public function deletepriceAction(){
		if(empty($_GET['productid']))
			exit('access denied');
		$product = $this->getProductById($_GET['productid']);
		echo json_encode($product->deletePrice($_POST['id']));
	}

	public function editstorageAction(){
		if(empty($_GET['productid']))
			exit('access denied');
		$product = $this->getProductById($_GET['productid']);
		echo json_encode($product->editStorage($_POST));
	}

	public function deletestorageAction(){
		if(empty($_GET['productid']))
			exit('access denied');
		$product = $this->getProductById($_GET['productid']);
		echo json_encode($product->deleteStorage($_POST['id']));
	}

	protected function getProductById($productid){
		global $_G;
		$product = new Product($productid);
		if($_G['admin']->producttypes){
			$typeids = explode(',', $_G['admin']->producttypes);
			if(!in_array($product->type, $typeids))
				exit('permission denied');
		}
		return $product;
	}

	public function suggestAction(){
		$query = isset($_GET['query']) ? trim($_GET['query']) : '';
		$query = addslashes($query);

		$result = array(
			'query' => $query,
			'suggestions' => array(),
		);

		if(!empty($query)){
			global $db, $tpre;

			$check_booking_mode = ProductStorage::IsBookingMode() ? ' OR mode='.ProductStorage::BookingMode : '';
			$products = $db->fetch_all("SELECT p.id,p.name
				FROM {$tpre}product p
				WHERE (p.name LIKE '%$query%'
						OR p.id IN (SELECT id FROM {$tpre}productacronym WHERE name LIKE '$query%'))
					AND EXISTS (SELECT * FROM {$tpre}productstorage WHERE productid=p.id AND (num>0 $check_booking_mode))");
			foreach($products as $p){
				$result['suggestions'][] = array(
					'value' => $p['name'],
					'data' => intval($p['id']),
				);
			}
			unset($users);
		}

		echo json_encode($result);
		exit;
	}
}

?>
