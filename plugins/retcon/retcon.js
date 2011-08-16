$(function(){
	$('.message.you td.content').live('dblclick', function(){
		if($('.retcon').length == 0) {
			var h = $(this).parents('tr').height();
			var thetext = $(this).html();
			thetext = thetext.replace(/<\/?[i|b]>/ig, '');
			var thenewtext = thetext.replace(/<|>/, '');
			if(thetext == thenewtext) {
				$(this).html('<div class="retconold" style="display:none;">' + $(this).html() + '</div><textarea class="retcon">' + thenewtext + '</textarea>').css('padding', 0);
				var msg = $(this);
				$('.retcon').height(h).width('100%').selectAll().blur(function(){
					if($('.retconold').text() != $(this).val()) {
						send('/retcon ' + get_status(this) + ' ' + $(this).val());
					}
					msg.text($(this).val()).css('padding', null);
				}).keypress(function(event){
					if(event.keyCode==13 && !event.shiftKey){
						if($('.retconold').text() != $(this).val()) {
							send('/retcon ' + get_status(this) + ' ' + $(this).val());
						}
						msg.text($(this).val()).css('padding', null);
						return false;
					}
					if(event.keyCode==27) {
						msg.text($('.retconold').text()).css('padding', null);
						return false;
					}
				}).focus();
			}
		}
	});
	$('.replaced').live('hover', function(event){
	  if (event.type == 'mouseenter') {
	    $(this).addClass('hover');
	  } else {
	    $(this).removeClass('hover');
	  }
	});
});

function retcon(status, text){
	$('.status__' + status + ' .content').html('<span class="replaced" title="this conversation was retconned">' + text + '</span>');
}