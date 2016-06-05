$(function(){
	var ProductTypes = [];
	for (var product_id in ProductList){
		var product = ProductList[product_id];
		ProductTypes[product.type] = true;
	}

	$('.product_type_input select option').each(function(){
		var option = $(this);
		if(ProductTypes[option.val()] == undefined){
			option.remove();
		}
	});

	$('#product_list').on('change', '.product_type_input select', function(){
		var name_select = $(this).parent().parent().find('.product_name_input select');
		name_select.html('');

		var type_id = $(this).val();
		for(var product_id in ProductList){
			var product = ProductList[product_id];
			if(product.type == type_id){
				var option = $('<option></option>');
				option.attr('value', product.id);
				option.text(product.name);
				name_select.append(option);
			}
		}
		name_select.change();
	});

	var warehouse_options = $('.warehouse_input select').html();
	$('#product_list').on('change', '.product_name_input select', function(){
		var name_select = $(this);
		var tr = name_select.parent().parent();

		var warehouse_input = tr.find('.warehouse_input select');
		warehouse_input.html(warehouse_options);
		warehouse_input.children().each(function(){
			var option = $(this);
			var warehouse_id = option.val();
			var product_id = name_select.val();
			var product = ProductList[product_id];

			var keep = false;
			if(product != undefined){
				for(var i = 0; i < product.storages.length; i++){
					var storage = product.storages[i];
					if(storage.warehouseid == warehouse_id){
						keep = true;
						break;
					}
				}
			}
			if(!keep){
				option.remove();
			}
		});
		warehouse_input.change();
	});

	$('#product_list').on('change', '.warehouse_input select', function(){
		var warehouse_input = $(this);
		var warehouse_id = warehouse_input.val();
		var tr = warehouse_input.parent().parent();
		var name_select = tr.find('.product_name_input select');
		var product_id = name_select.val();
		var product = ProductList[product_id];
		if(product != undefined){
			var num = tr.children('td.warehouse_num');
			for(var i = 0; i < product.storages.length; i++){
				var storage = product.storages[i];
				if(storage.warehouseid == warehouse_id){
					num.text(storage.num);
					return;
				}
			}
			num.text('0');
		}
	});

	function calculate_total_price(){
		var total_price = 0;
		$('#product_list tbody tr:not(:last-child)').each(function(){
			var tds = $(this).children('td');
			var subtotal = tds.eq(5);
			var subtotal = parseFloat(subtotal.text());
			if(!isNaN(subtotal)){
				total_price += subtotal;
			}
		});
		$('#totalprice').text(total_price.toFixed(2));
	}

	$('#product_list').on('click', 'button.add, button.delete', calculate_total_price);

	$('#product_list').on('blur', 'input.price_input', function(){
		var price_input = $(this);
		var tr = price_input.parent().parent().parent();
		var number_input = tr.find('input.number_input');
		var subtotal_input = tr.find('input.subtotal_input');
		var subtotal = parseFloat(price_input.val()) * parseInt(number_input.val(), 10);
		subtotal_input.val(isNaN(subtotal) ? '' : subtotal);
		calculate_total_price();
	});

	$('#product_list').on('blur', 'input.number_input', function(){
		var number_input = $(this);
		var tr = number_input.parent().parent().parent();
		var subtotal_input = tr.find('input.subtotal_input');
		var price_input = tr.find('input.price_input');
		var subtotal = parseFloat(price_input.val()) * parseInt(number_input.val(), 10);
		subtotal_input.val(isNaN(subtotal) ? '' : subtotal);
		calculate_total_price();
	});

	$('#product_list').on('blur', 'input.subtotal_input', function(){
		var subtotal_input = $(this);
		var tr = subtotal_input.parent().parent().parent();
		var number_input = tr.find('input.number_input');
		var price_input = tr.find('input.price_input');
		var price = parseFloat(subtotal_input.val()) / parseInt(number_input.val(), 10);
		if(isNaN(price)){
			price_input.val('');
		}else{
			price_input.val(price.toFixed(2));
		}
		calculate_total_price();
	});

	$('.product_type_input select').change();

	$('#paymentmethod').change(function(){
		var paymentmethod = $(this).val();
		$('#bankaccountid').html('');
		$('#bankaccountid').val(0);
		for(var i = 0; i < BankAccountList.length; i++){
			var account = BankAccountList[i];
			if (account.orderpaymentmethod == paymentmethod) {
				var option = $('<option></option>');
				option.attr('value', account.id);
				option.html(account.remark);
				$('#bankaccountid').append(option);
			}
		}
	});
	$('#paymentmethod').change();

	var popupticket = location.href.split('popupticket=');
	if(popupticket.length > 1){
		popupticket = parseInt(popupticket[1], 10);
		if(!isNaN(popupticket)){
			var url = 'admin.php?mod=order&action=ticket&orderid=' + popupticket;
			window.open(url, 'ticket', 'width=330,status=no,titlebar=no,toolbar=no,location=no,menubar=no', false);
		}
	}
});
