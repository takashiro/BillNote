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

	$('.product_type_input select').change();
});
