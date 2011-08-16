var popcorn = {
	poppings: false,
	popstop: false,
	kettle: false,

	start: function(){
		popcorn.popstop = false;
		popcorn.poppings = [
			window.setTimeout(popcorn.pop, 0), 
			window.setTimeout(popcorn.pop, 2500), 
			window.setTimeout(popcorn.pop, 5000),
			window.setTimeout(popcorn.dopopstop, 9000),
		];
		popcorn.kettle = $('<div id="kettle" style="z-index:80;background:transparent url(/plugins/effectpack1/popcorn/kettle.png) repeat-x scroll left top; height:53px; position: fixed;bottom: 0px;width: 100%;">').appendTo('body');
	},
	pop: function (){

		kernel = $('<div style="z-index:80;background-image:url(/plugins/effectpack1/popcorn/kernels.png);width:80px;height:80px;overflow:hidden;position:fixed;"></div>').appendTo('body');
		kernel.css('background-position', (80 * Math.floor(6 * Math.random()))+ 'px 0px');
		//kernel.css({position: 'fixed', top: 50, left: 50});
		var arc_params= {
			center: [0 - kernel.width(), $(window).height()],
			radius: Math.random() * Math.max($(window).width(), $(window).height())
		};
		if(Math.random() < 0.5) {
			arc_params.center =  [0 - kernel.width(), $(window).height()];
			arc_params.start = 90;
			arc_params.end = 180;
			arc_params.dir = 1;
		}
		else {
			arc_params.center =  [$(window).width(), $(window).height()];
			arc_params.start = 270;
			arc_params.end = 180;
			arc_params.dir = -1;
		}
	
		kernel.animate({path : new $.path.arc(arc_params)}, 500 * (arc_params.radius /400), null, function(){$(this).remove();});
		popcorn.kettle.height(Math.min(popcorn.kettle.height()+3,240));
	
		if(!popcorn.popstop) {
			window.setTimeout(popcorn.pop, 500 * Math.random());
		}
	},
	
	dopopstop: function () {
		popcorn.popstop = true;
		window.setTimeout(function(){popcorn.kettle.fadeOut()}, 2000);
	}

}

var brains = {
	started: false,
	brain: false,
	brain2: false,
	timer: false,
	zombiemover: false,
	zombies: new Array, 

	start: function(){
		if(brains.started) return;
		brains.started = true;
		brains.brain = $('<img class="brainmatter" src="/plugins/effectpack1/brain/brain2.png" style="z-index:78;position: fixed;top: 10px;left: 50%; margin-top:198px;width:0px;height:0px;">').appendTo('body');
		brains.brain.animate({marginLeft: -189, marginTop: 0, width: 377, height: 377}, 'normal', 'easeOutElastic', brains.phase2);
	},
	
	phase2: function(){
		brains.brain2 = $('<img class="brainmatter" src="/plugins/effectpack1/brain/brain.png" style="z-index:80;position: fixed;top: 10px;left: 50%; margin-left:-15px;margin-top:183px;width:30px;height:30px;">').appendTo('body');
		brains.brain2.animate({marginLeft: -189, marginTop: 0, width: 377, height: 377}, 'normal', 'easeOutElastic', brains.phase3);
	},
	
	phase3: function(){
		brains.timer = window.setInterval(brains.pulsebrain, 2000);
		brains.addzombies();
	},
	
	pulsebrain: function(){
		brains.brain2.animate({marginLeft: -164, marginTop: 25, width: 327, height: 327}, 'normal', 'easeInOutElastic', function(){
			brains.brain2.animate({marginLeft: -189, marginTop: 0, width: 377, height: 377}, 'normal', 'easeInOutElastic');
		});
	},
	
	addzombies: function(){
		for(z=1;z<=5;z++) {
			brains.addzombie();
		}
		brains.zombiemover = window.setInterval(brains.movezombies, 80);
	},
	
	movezombies: function() {
		min = $('#command').height();
		bail = true;
		for(var z in brains.zombies) {
			pos = Math.min(parseInt(brains.zombies[z].css('bottom'))+Math.random()*5, min);
			brains.zombies[z].css('bottom', pos);
			if(pos<min) {
				bail = false;
			}
		}
		if(bail) {
			window.clearInterval(brains.zombiemover);
			$('.zombie').fadeOut('slow',function(){
				$(this).remove();
				brains.minimize();
			});
		}
	},
	
	addzombie: function(){
		var zombie = $('<div class="zombie brainmatter" style="background-image:url(/plugins/effectpack1/brain/zombies.png);overflow:hidden;width:100px;height:120px;z-index:80;position:fixed;">').appendTo('body');
		zombie.css({bottom: -120, left: $(window).width() * Math.random(), backgroundPosition: 100*Math.floor(10*Math.random()) + 'px 0px' });
		brains.zombies.unshift(zombie);
	},
	
	minimize: function(){
		window.clearTimeout(brains.timer);
		brains.brain.animate({left: 0, marginLeft: 0, marginTop: 0, width: 188, height: 188}, 'normal', 'easeInOutElastic');
		brains.brain2.animate({left: 0, marginLeft: 0, marginTop: 0, width: 188, height: 188}, 'normal', 'easeInOutElastic');
		brains.timer = window.setInterval(brains.phase4, 2000);
	},
	
	phase4: function(){
		brains.brain2.animate({marginLeft: 15, marginTop: 15, width: 158, height: 158}, 'normal', 'easeInOutElastic', function(){
			brains.brain2.animate({marginLeft: 0, marginTop: 0, width: 188, height: 188}, 'normal', 'easeInOutElastic');
		});
	},
	
	stop: function(){
		window.clearTimeout(brains.timer);
		brains.brain2.animate({marginLeft: 94, marginTop: 94, width: 0, height: 0}, 'fast', 'easeInQuad', function(){
			brains.brain.animate({marginLeft: 94, marginTop: 94, width: 0, height: 0}, 'fast', 'easeInQuad', function(){
				brains.brain2.remove();
				brains.brain.remove();
				$('.brainmatter').remove();
			});
		});
		brains.started = false;
	}
	
}

var redflag = {
	asplosion: false,
	start: function() {
		if(!redflag.asplosion) {
			redflag.asplosion = $('<div id="asplosion" style="background-color: none;width:100%;height:120%;position:fixed;top:0px;z-index:100;opacity:0.0;" onclick="$(this).remove();"><img src="/plugins/effectpack1/redflag/redflag.png" style="width:100%;height:100%;"></div>').appendTo('body');
			window.setTimeout(redflag.remover,10000);
			$(redflag.asplosion).animate({opacity: 1.0, top: $(window).height() * -0.2}, { queue:false, duration:3000, easing: 'swing', complete: redflag.fader});
		}
	},
	fader: function() {
		$(redflag.asplosion).animate({opacity: 0.0}, { queue:false, duration:3000, easing: 'linear', complete: redflag.remover});
	},
	remover: function() {
		$(redflag.asplosion).remove();
		redflag.asplosion = false;
	}
};

var asplode = {
	asplosion: false,
	start: function() {
		asplode.asplosion = $('<div id="asplosion" style="background-color: #ffffff;width:100%;height:120%;position:fixed;top:0px;z-index:100;"><img src="/plugins/effectpack1/asplode/asplode.png" style="width:100%;height:100%;opacity:0.0;"></div>').appendTo('body');
		window.setTimeout(asplode.cloud, 2000);
	},
	cloud: function() {
		$('img', asplode.asplosion).animate({opacity: 1.0}, { queue:false, duration:3000 });
		$(asplode.asplosion).animate({top: $(window).height() * -0.2}, { queue:false, duration:3000, easing: 'swing', complete: asplode.fader});
	},
	fader: function() {
		$(asplode.asplosion).animate({opacity: 0.0}, { queue:false, duration:3000, easing: 'linear', complete: asplode.remover});
	},
	remover: function() {
		$(asplode.asplosion).remove();
	}
};

var crickets = {
	interval: false,
	start: function() {
		crickets.interval = window.setInterval(crickets.addcricket, 3000);
		var oldprocessPoll = processPoll;
		processPoll = function(data, textStatus, repoll) {
			crickets.stop();
			processPoll = oldprocessPoll;
			oldprocessPoll(data, textStatus, repoll);
		}
	},
	addcricket: function() {
		var cricket = $('<div class="cricket" style="width:160px;height:70px;background:transparent url(/plugins/effectpack1/cricket/cricket.png) scroll no-repeat 0px 0px;position:fixed;"></div>').appendTo('body');
		cricket.css('bottom', $('#command').height()).css('left', Math.random() * ($(window).width() - 160));
		cricket.css('background-position', ((Math.random() < 0.5) ? '0px' : '-160px') + ' 0px');
		if($('.cricket').length >= 10) {
			window.clearInterval(crickets.interval);
		}
	},
	stop: function() {
		$('.cricket').remove();
		window.clearInterval(crickets.interval);
		getFlashMovie('ding').stopsound();
	}
}