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

class AdminMainModule extends AdminControlPanelModule{

	public function editAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$product_types = Product::Types();

		$id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

		$admin = new Administrator($id);
		if($admin->isSuperAdmin()){
			showmsg('illegal_operation', 'back');
		}
		$id = $admin->id;

		if($_POST){
			if(!empty($_POST['password']) && $_POST['password'] != $_POST['password2']){
				showmsg('two_different_passwords', 'back');
			}

			if($id <= 0){
				@$new_admin = array(
					'account' => $_POST['account'],
					'password' => $_POST['password'],
				);
				$new_id = Administrator::Register($new_admin);
				if($new_id <= 0){
					showmsg('duplicated_account', 'back');
				}

				$admin = new Administrator($new_id);
			}

			$admin->clearPermission();
			if(!empty($_POST['p']) && is_array($_POST['p'])){
				foreach($_POST['p'] as $permission => $value){
					if($_G['admin']->hasPermission($permission)){
						$admin->setPermission($permission, true);
					}
				}
			}

			if(!empty($_POST['password'])){
				$admin->pwmd5 = rmd5($_POST['password']);
			}

			if(isset($_POST['nickname'])){
				$admin->nickname = trim($_POST['nickname']);
			}

			if(isset($_POST['realname'])){
				$admin->realname = trim($_POST['realname']);
			}

			if(isset($_POST['mobile'])){
				$admin->mobile = trim($_POST['mobile']);
			}

			showmsg('edit_succeed', $mod_url);
		}

		$a = $admin->toArray();

		include view('edit');
	}

	public function deleteAction(){
		$id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
		if($id <= 0){
			showmsg('illegal_operation');
		}

		if(!empty($_GET['confirm'])){
			Administrator::Delete($id);
			redirect($mod_url);
		}else{
			showmsg('confirm_to_delete_administrator', 'confirm');
		}
	}

	public function listAction(){
		extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		$limit = 20;
		$offset = ($page - 1) * $limit;
		$table = $db->select_table('administrator');
		$admins = $table->fetch_all('*', "1 LIMIT $offset,$limit");
		$pagenum = $table->result_first('COUNT(*)');
		include view('list');
	}

	public function defaultAction(){
		$this->listAction();
	}
}

?>
