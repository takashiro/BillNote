
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

$(function(){
	$('#orderlist').on('click', '.mark_sorted, .mark_to_delivery_station, .mark_delivering, .mark_in_delivery_station, .mark_received, .mark_rejected', function(e){
		var a = $(e.target);
		var href = a.attr('href');
		var td = a.parent().parent();
		$.post(href + '&ajax=1', [], function(data){
			if(a.hasClass('mark_sorted')){
				td.html(lang['order_sorted']);

				if(admin.hasPermission('order_to_station')){
					var button = $('<a></a>');

					button.attr('class', 'mark_to_delivery_station');
					button.attr('href', href.replace('mark_sorted', 'mark_todp'));
					button.html('[' + lang['order_to_delivery_station'] + ']');

					var div = $('<div></div>');
					div.append(button);
					td.append(div);
				}

				var tr = td.parent();
				tr.find('a.delete').remove();
				tr.find('ul.order_detail').addClass('disabled');

			}else if(a.hasClass('mark_to_delivery_station')){
				td.html(lang['order_to_delivery_station']);

				if(admin.hasPermission('order_deliver_w')){
					var button = $('<a></a>');

					if(td.data('deliverymethod') == Order.StationDelivery){
						button.attr('class', 'mark_in_delivery_station');
						button.attr('href', href.replace('mark_todp', 'mark_indp'));
						button.html('[' + lang['order_in_delivery_station'] + ']');
					}else{
						button.attr('class', 'mark_delivering');
						button.attr('href', href.replace('mark_todp', 'mark_delivering'));
						button.html('[' + lang['order_delivering'] + ']');
					}

					var div = $('<div></div>');
					div.append(button);
					td.append(div);
				}

			}else if(a.hasClass('mark_delivering') || a.hasClass('mark_in_delivery_station')){
				td.html(a.hasClass('mark_delivering') ? lang['order_delivering'] : lang['order_in_delivery_station']);

				if(admin.hasPermission('order_deliver_w')){
					var data = {};
					data['mark_received'] = '[' + lang['order_received'] + ']';
					data['mark_rejected'] = '[' + lang['order_rejected'] + ']';

					for(var action in data){
						var button = $('<a></a>');
						button.attr('class', action);
						if(a.hasClass('mark_delivering')){
							button.attr('href', href.replace('mark_delivering', action));
						}else{
							button.attr('href', href.replace('mark_indp', action));
						}
						button.html(data[action]);

						var div = $('<div></div>');
						div.append(button);
						td.append(div);
					}
				}
			}else if(a.hasClass('mark_received')){
				td.html(lang['order_received']);
			}else if(a.hasClass('mark_rejected')){
				td.html(lang['order_rejected']);
			}
		});

		return false;
	});

	function batchProcessOrder(mod_action){
		var basic_url = location.href;
		$('.mpage').remove();

		var orderlist = $('#orderlist');
		orderlist.html('处理中……');

		var progress_bar = $('<div></div>');
		var progress_value = $('<span>0</span>');
		var progress_total = $('<span>0</span>');
		progress_bar.append(progress_value);
		progress_bar.append(' / ');
		progress_bar.append(progress_total);
		orderlist.append(progress_bar);

		var orders = [];

		var progress = 0;
		function process_order(i){
			if(i >= orders.length){
				location.href = basic_url;
				return;
			}

			var get_data = {'mod' : 'order', 'action' : mod_action, 'orderid' : orders[i], 'ajaxform' : 1};
			$.get('admin.php', get_data, function(){
				progress++;
				progress_value.html(progress);
				process_order(i + 1);
			}, 'text');
		}

		var page = 1;
		function fetch_order(){
			$.post(basic_url, {'format' : 'json', 'page' : page}, function(response){
				if(response.data.length <= 0){
					process_order(0);
				}else{
					for(var i = 0; i < response.data.length; i++){
						var order = response.data[i];
						if(mod_action == 'mark_indp'){
							if(order.status != Order.ToDeliveryStation || order.deliverymethod != Order.StationDelivery)
								continue;
						}else if(mod_action == 'mark_delivering'){
							if(order.status != Order.ToDeliveryStation || order.deliverymethod != Order.HomeDelivery)
								continue;
						}else if(mod_action == 'mark_sorted'){
							if(order.status != Order.Unsorted)
								continue;
						}else if(mod_action == 'mark_todp'){
							if(order.status != Order.Sorted)
								continue;
						}

						orders.push(order.id);
						progress_total.html(orders.length);
					}
					page++;
					fetch_order();
				}
			}, 'json');
		}
		fetch_order();
	}

	$('button.batch_process').click(function(){
		batchProcessOrder($(this).data('action'));
	});

	$('ul.order_detail').on('dblclick', 'li', function(e){
		var li = $(e.target);
		var ul = li.parent();
		if(ul.hasClass('disabled')){
			return false;
		}

		var data = {
			'detailid' : li.data('primaryvalue'),
			'state' : li.hasClass('outofstock') ? 0 : 1
		};

		var message = 'confirm_to_mark_detail_' + (data.state ? 'out_of' : 'in') + '_stock';
		if(confirm(lang[message])){
			$.get(mod_url + '&action=detail_outofstock', data, function(result){
				if(data.state == 0){
					li.removeClass('outofstock');
				}else{
					li.addClass('outofstock');
				}

				if(result.totalprice !== undefined){
					var td = ul.parent();
					var tr = td.parent();
					tr.find('.totalprice').html(result.totalprice);
				}
			}, 'json');
		}
	});

	$('#condition_form').submit(function(){
		var button = $(this).find('button[type="submit"]:focus');
		$(this).attr('target', button.hasClass('new_window') ? '_blank' : '_self');
	});

	$('a.print').click(function(e){
		e.preventDefault();

		var url = $(this).attr('href');
		window.open(url, 'ticket', 'width=330,status=no,titlebar=no,toolbar=no,location=no,menubar=no', false);
	});

	var popupticket = location.href.split('popupticket=');
	if(popupticket.length > 1){
		popupticket = parseInt(popupticket[1], 10);
		if(!isNaN(popupticket)){
			var url = mod_url + '&action=ticket&orderid=' + popupticket;
			window.open(url, 'ticket', 'width=330,status=no,titlebar=no,toolbar=no,location=no,menubar=no', false);
		}
	}
});
