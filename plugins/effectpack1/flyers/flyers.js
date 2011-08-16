var flyers = {
	wallpaper: false,
	start: function() {
		if(!flyers.wallpaper) {
			flyers.wallpaper = $('<div id="wallpaper" style="background-color: #000;width:100%;height:120%;position:fixed;top:0px;z-index:100;opacity:0.0;" onclick="$(this).remove();"><img src="/plugins/effectpack1/flyers/fly_guys.jpg" style="width:100%;height:100%;"></div>').appendTo('body');
			window.setTimeout(flyers.remover,10000);
			$(flyers.wallpaper).animate({opacity: 1.0, top: $(window).height() * -0.2}, { queue:false, duration:6000, easing: 'swing', complete: flyers.fader});
		}
	},
	fader: function() {
		$(flyers.wallpaper).animate({opacity: 0.0}, { queue:false, duration:1500, easing: 'linear', complete: flyers.remover});
	},
	remover: function() {
		$(flyers.wallpaper).remove();
		flyers.wallpaper = false;
	}
}