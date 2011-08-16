var facepunch = {
	start: function(){
		facepunch.stop = false;
		facepunch.pow_rate = 0;
		facepunch.pow = $('<img class="pow" style="z-index:80;background:transparent no-repeat scroll left top; height:196px; position: fixed;width: 287px;" src="/plugins/effectpack1/facepunch/pow.png"/>').appendTo('body');

		facepunch.throwone();
	},
	
	throwone: function() {
		facepunch.pow.css('top', Math.floor($(window).height() / 2)).css('left', Math.floor($(window).width()/2)).width(1).height(1);
		window.setTimeout(
			function(){
				facepunch.pow.animate(
					{
						left: Math.floor($(window).width()/2) - 143, 
						top: Math.floor($(window).height() / 2 - 98),
						width:287,
						height:196
					}, 
					'linear', 
					function(){$(this).remove();}
				);
			},
			100
		);
	},
	
	dostop: function () {
		facepunch.stop = true;
		$('.pow').remove();
	}

}
