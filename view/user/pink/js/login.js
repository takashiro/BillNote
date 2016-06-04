var action = 'login';
var no_alert = false;

$(function(){
	$('#login_form').submit(function(e){
		var data = {};
		var arr = $(e.target).serializeArray();
		for(var i = 0; i < arr.length; i++){
			data[arr[i].name] = arr[i].value;
		}

		var invalid_message = '';

		if(data.account == ''){
			invalid_message += '请填写用户名。';
		}else if(data.account.length < 4 || data.account.length > 15){
			invalid_message += '用户名长度应在4 - 15个字符之间。';
		}

		if(data.password == ''){
			invalid_message += '请填写密码。';
		}else if(data.password.length < 6){
			invalid_message += '密码至少6位。';
		}

		if(action == 'register'){
			if(data.password2 == undefined || data.password2 == ''){
				invalid_message += '请再次输入密码进行确认。';
			}else if(data.password2 != data.password){
				invalid_message += '您两次输入的密码不一致。';
			}
		}

		if(invalid_message != ''){
			e.preventDefault();

			if(no_alert){
				no_alert = false;
			}else{
				alert(invalid_message);
			}
		}
	});

	$('#login_button').click(function(){
		if(action != 'login'){
			action = 'login';
			no_alert = true;

			$('#action_text').html('登录');

			$('.for_register').each(function(){
				$(this).data('hidden_html', $(this).html());
				$(this).html('');
			});
		}
	});

	$('#register_button').click(function(){
		if(action != 'register'){
			action = 'register';
			no_alert = true;

			$('#action_text').html('注册');
			$('#login_button').hide();

			$('.for_register').each(function(){
				$(this).html($(this).data('hidden_html'));
			});
		}
	});

	$('.for_register').each(function(){
		$(this).data('hidden_html', $(this).html());
		$(this).html('');
	});
});
