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

class ProductStorage extends DBObject{
	const TABLE_NAME = 'productstorage';

	const NormalMode = 0;
	const BookingMode = 1;

	static public $Mode = array();

	public function __construct($id = 0){
		parent::__construct();
		$id = intval($id);
		if($id > 0){
			$this->fetch('*', 'id='.$id);
		}
	}

	public function updateNum($addnum, $productid = NULL){
		global $db, $tpre;

		$ext_condition = '';
		if($productid !== NULL){
			$productid = intval($productid);
			$ext_condition = ' AND productid='.$productid;
		}

		$addnum = intval($addnum);
		$is_minus = $addnum < 0;
		if($is_minus){
			$addnum = -$addnum;
			$check_booking_mode = ProductStorage::IsBookingMode() ? ' OR mode='.ProductStorage::BookingMode : '';
			$db->query("UPDATE {$tpre}productstorage SET num=num-{$addnum} WHERE id={$this->id} AND (num>=$addnum $check_booking_mode) $ext_condition");
		}else{
			$db->query("UPDATE {$tpre}productstorage SET num=num+{$addnum} WHERE id={$this->id} $ext_condition");
		}

		return $db->affected_rows > 0;
	}

	static public function ReadConfig(){
		return readdata('productstorage');
	}

	static public function WriteConfig($config){
		writedata('productstorage', $config);
	}

	static public function IsBookingMode(){
		$today_end = 24 * 3600;
		$today_now = (TIMESTAMP + TIMEZONE * 3600) % $today_end;

		$config = self::ReadConfig();
		$offset = $config['bookingtime_start'];
		$end = $config['bookingtime_end'];

		if($offset <= $end){
			return $offset <= $today_now && $today_now <= $end;
		}else{
			return $today_now <= $end || ($offset <= $today_now && $today_now <= $today_end);
		}
	}
}

ProductStorage::$Mode = array(
	ProductStorage::NormalMode => lang('common', 'product_storage_normal_mode'),
	ProductStorage::BookingMode => lang('common', 'product_storage_booking_mode'),
);

?>
