var ninja = {
	poppings: false,
	popstop: false,
	kettle: false,

	start: function(){
		ninja.stop = false;
		ninja.timers = [
			window.setInterval(ninja.spin, 50), 
		];
		ninja.stars = [];
		ninja.star_angle = [];
		ninja.star_rate = [];
		
		for(var z = 0; z < 5; z++) {
			var unit = $('<div class="ninjastar" style="z-index:80;background:transparent url(/plugins/effectpack1/ninja/ninja.png) no-repeat scroll left top; height:162px; position: fixed;width: 152px;">').appendTo('body');
			ninja.stars.push(unit);
			ninja.star_angle.push(0);
			ninja.star_rate.push(Math.random()*5 + 5);
		}
		ninja.throwall();
	},
	
	throwall: function() {
		for(var z in ninja.stars) {
			ninja.throwone(z);
		}
	},
	
	throwone: function(z) {
		var unit = ninja.stars[z];
		unit.css({left: -170});
		unittop = Math.floor(Math.random() * $(window).height() / 2 + ($(window).height() / 6));
		unit.css('top', unittop);
		window.setTimeout(
			function(){
				unit.animate({left: $(window).width()}, 'linear', function(){$(this).remove();});
			},
			100 + (50 * z)
		);
	},
	
	getTransformProperty: function(element) {
		var properties = [
			'transform',
			'WebkitTransform',
			'MozTransform',
			'msTransform',
			'OTransform'
		];
		var p;
		while (p = properties.shift()) {
			if (typeof element.style[p] != 'undefined') {
				return p;
			}
		}
		return false;
	},
	
	spin: function (){
		for(var z in ninja.stars) {
			var div = ninja.stars[z][0];
			var property = ninja.getTransformProperty(div);
			ninja.star_angle[z] = (ninja.star_angle[z] + ninja.star_rate[z]) % 360
			div.style[property] = 'rotate(' + (ninja.star_angle[z]) + 'deg)';
		}
	},
	
	dostop: function () {
		ninja.stop = true;
		for(var z in ninja.timers) {
			window.clearInterval(ninja.timers[z]);
		}
		$('.ninjastar').remove();
	}

}
