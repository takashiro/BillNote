$(function(){
	$('#print_button').click(function(e){
		e.preventDefault();

		if($('#orderid_or_mobile').val() == ''){
			alert('请输入订单号或手机号。');
		}else{
			var input = $('#orderid_or_mobile');

			var parameters;
			if (input.val().length == 11){
				parameters = '&mobile=' + parseInt(input.val(), 10);
			}else{
				parameters = '&orderid=' + parseInt(input.val(), 10);
			}

			parameters += '&time_start=' + escape($('#time_start').val());
			parameters += '&time_end=' + escape($('#time_end').val());

			var url = 'admin.php?mod=order:ticketprinter&auto_receive=1' + parameters;
			var new_window = window.open(url, '打印提货单', 'width=320, height=500, status=no, menubar=no, alwaysraised=yes');
			new_window.focus();

			input.val('');
			input.focus();
		}
	});

	$('#orderid_or_mobile').focus();

	$('.number_board button').click(function(){
		var input = $('#orderid_or_mobile');
		input.val(input.val() + $(this).text());
	});

	$('#backspace_button').click(function(){
		var input = $('#orderid_or_mobile');
		var content = input.val();
		input.val(content.substr(0, content.length - 1));
	});

	$('#clear_button').click(function(){
		var input = $('#orderid_or_mobile');
		input.val('');
	});
});
