
function coffee(){
	$('body').append("<div id='mycoffee'></div>");
	$('#mycoffee').css({left: $(window).width()-200, bottom: -70}).animate(
		{bottom:102},
		5500,
		'swing',
		function(){
  		$("#mycoffee").fadeOut(function(){$(this).remove();});
		}
	);
	play('/plugins/beer/coffee.mp3');
	window.setInterval(twitch, 1000);
}

function beer(drink){
	$('body').append("<div id='my" + drink + "'></div>");
	$('#my' + drink).css({left: $(window).width()+100}).animate(
		{left:-105},
		3000,
		'easeOutQuart',
		function(){
  		$("#my" + drink).remove();
		}
	);
	play('/plugins/beer/beer.mp3');
}

function twitch(){
	$('.twitchy:not(.twitched) .content').wrapInner('<div class="twitchinner">').find('.twitchinner').vibrate();
	$('.twitchy:not(.twitched)').addClass('twitched');
}

jQuery.fn.vibrate = function (conf) {
    var config = jQuery.extend({
        speed:        30, 
        duration:    500, 
        frequency:    5000, 
        spread:        2
    }, conf);

    return this.each(function () {
        var t = jQuery(this);

        var vibrate = function () {
            var topPos    = Math.floor(Math.random() * config.spread) - ((config.spread - 1) / 2);
            var leftPos    = Math.floor(Math.random() * config.spread) - ((config.spread - 1) / 2);
            var rotate    = Math.floor(Math.random() * config.spread) - ((config.spread - 1) / 2);

            t.css({
                position:            'relative', 
                left:                leftPos + 'px', 
                top:                topPos + 'px', 
                WebkitTransform:    'rotate(' + rotate + 'deg)'  // cheers to erik@birdy.nu for the rotation-idea
            });
        };

        var doVibration = function () {
            var vibrationInterval = setInterval(vibrate, config.speed);

            var stopVibration = function () {
                clearInterval(vibrationInterval);
                t.css({
                    position:            'static', 
                    WebkitTransform:    'rotate(0deg)'
                });
            };

            setTimeout(stopVibration, config.duration);
        };

        setInterval(doVibration, config.frequency);
    });
};

$(function(){twitch();});