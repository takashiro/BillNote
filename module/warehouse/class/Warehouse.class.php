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

class Warehouse extends DBObject{

	static public function Name($id){
		$names = self::Names();
		return $names[$id];
	}

	static public function RefreshCache(){
		writecache('warehouse_namemap', null);
	}

	static private $NameMap = null;
	static public function Names(){
		if(self::$NameMap === null){
			self::$NameMap = readcache('warehouse_namemap');
			if(self::$NameMap === null){
				global $db;
				$table = $db->select_table('warehouse');
				$list = $table->fetch_all('id,name');
				self::$NameMap = array();
				foreach($list as $option){
					self::$NameMap[$option['id']] = $option['name'];
				}
				writecache('warehouse_namemap', self::$NameMap);
			}
		}
		return self::$NameMap;
	}

}
