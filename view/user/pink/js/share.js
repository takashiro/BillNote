function wxShare(title, link, imgUrl){
	var data = {
		'title': title,
		'link': link,
		'imgUrl': imgUrl
	};
	wx.onMenuShareTimeline(data);
	wx.onMenuShareAppMessage(data);
	wx.onMenuShareQQ(data);
	wx.onMenuShareQZone(data);
}

$(function(){
	$('.product_list > li').click(function(){
		var product_id = $(this).data('product-id');

		var title = CONFIG['sitename'] + '的' + $(this).children('.detail').children('.name').text() + '竟然只要';
		var price = $(this).children('.detail').children('ul.rule').children('li[data-price-id]');
		if(price.length > 0){
			price = price.eq(0);
			title += price.children('.price').text();
			title += price.children('.priceunit').text();
			title += price.children('.amount').text();
			title += price.children('.amountunit').text();
			title += '，买买买！';
		}

		var link = location.href + (location.href.indexOf('?') == -1 ? '?' : '&');
		link += 'referrerid=' + User.id;
		link += '&productid=' + product_id;

		var link_parts = location.href.split('/');
		var image_url = link_parts[0] + '//' + link_parts[2] + '/data/attachment/product_' + product_id + '_icon.png';

		wxShare(title, link, image_url);
	});

	wx.ready(function(){
		var link = location.href + (location.href.indexOf('?') == -1 ? '?' : '&');
		link += 'referrerid=' + User.id;

		var link_parts = location.href.split('/');
		var image_url = link_parts[0] + '//' + link_parts[2] + '/view/user/default/image/logo.png';

		wxShare(CONFIG['sitename'], link, image_url);
	});
});
