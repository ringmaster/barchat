
var last_user_id = -1;
var last_update_time = 0;
var allmute = false;
var usermute = false;
var hideinfo = 0;
var atbottom = true;
var jsdate = 0;
var namebarmd5 = 0;
var lastpoll;
var autoindex = 0;
var autodata = false;
var historylist = new Array;
var histindex = -1;
var curhistory = '';
var masterpass = false;
var cp = false;
var lastdmuser;
var lastdm = false;
var onelineheight = 0;
var animtitle = null;
var newstart;
var intervals = {};
var namelist = [];
var kicks = {};
var smarttimeout = -1;
var queuedsounds = [];
var decorset = '';
var roomchange = {};

var map = null;
var geocoder = null;

var barchat = {};

var commandlineicons = {
	'^/en(?:crypt|code)\\s+' : '/css/images/lock.png',
	'^(d|/msg|/m|/dm|/d)\\s+' : '/css/images/user.png'
};

$.fn.selectRange = function(start, end) {
	return this.each(function() {
		if(this.setSelectionRange) {
			this.focus();
			this.setSelectionRange(start, end);
		} else if(this.createTextRange) {
			var range = this.createTextRange();
			range.collapse(true);
			range.moveEnd('character', end);
			range.moveStart('character', start);
			range.select();
		}
	});
};

$.fn.selectAll = function() {
	return this.each(function() {
		var end = $(this).val().length;
		if(this.setSelectionRange) {
			this.focus();
			this.setSelectionRange(0, end);
		} else if(this.createTextRange) {
			var range = this.createTextRange();
			range.collapse(true);
			range.moveEnd('character', end);
			range.moveStart('character', 0);
			range.select();
		}
	});
};

$.fn.getRange = function() {
	return {start: this[0].selectionStart, end: this[0].selectionEnd};
}

$.fn.toFixed = function() {
	return this.each(function(){
		$(this)
		.filter(function(){return $(this).css('position') != 'fixed';})
		.css('position', 'fixed')
		.css('top', parseInt($(this).css('top')) - $(document).scrollTop());
	});
}

$.fn.toAbsolute = function() {
	return this.each(function(){
		$(this)
		.filter(function(){return $(this).css('position') != 'absolute';})
		.css('position', 'absolute')
		.css('top', parseInt($(this).css('top')) + $(document).scrollTop());
	});
}

$.fn.fixPositioning = function() {
	return this.each(function(){
		$(this)/*.filter(function(){return $(this).css('position') != 'absolute';})*/
		.has('ui-resizable-resizing')
		.mouseenter(function(){
			$(this).css('position', 'absolute');
			$(this).css('top', parseInt($(this).css('top')) + $(document).scrollTop());
		});
		$(this)/*.filter(function(){return $(this).css('position') != 'fixed';}) */
		.not('ui-resizable-resizing')
		.mouseleave(function(){
			$(this).css('position', 'fixed');
			$(this).css('top', parseInt($(this).css('top')) - $(document).scrollTop());
		});
	});
}

$(function(){
	$('#commandline').keypress(command_keypress);
	$('#commandline').keyup(processcommandstatus);
	$('#commandline').keydown(function(event){
		if(event.keyCode==9) {
			autocomplete();
			return false;
		}
		if(event.keyCode==8 && $('#commandline').val() == '' && lastdm == false) {
			$('#commandline').val('/msg ' + lastdmuser + '  ');
		}
		if(event.keyCode==8 && $('#commandline').val() == newstart && lastdm == false) {
			$('#commandline').val('');
		}

		lastdm = true;
		if(!cp && $('#commandline').height() <= 31) {
			if(event.keyCode == 38) { // up
				if(histindex == -1) {
					curhistory = $('#commandline').val();
				}
				histindex = Math.min(historylist.length-1,++histindex);
				$('#commandline').val(historylist[histindex]);
				return false;
			}
			if(event.keyCode == 40) { // down
				if(histindex == -1) {
					curhistory = $('#commandline').val();
				}
				histindex = Math.max(-1,--histindex);
				if(histindex == -1) {
					$('#commandline').val(curhistory);
				}
				else {
					$('#commandline').val(historylist[histindex]);
				}
				return false;
			}
			histindex = -1;
		}
	});
	$('#new_chan').live('keypress', function(event){if(event.keyCode==13){
		joinRoom($('#new_chan').val());
		$('#new_chan').val('');
	}});
	$('#toggle_code').click(function(event){
		toggleCodeEdit();
	});
	$('#commandline').keyup(function(event){
		$('#command_size').html($P.htmlspecialchars($('#commandline').val()).replace(/\n/g, '<br/>') + '&nbsp;');
		$('#commandline').height(Math.max(31,Math.min(200,$('#command_size').height()+15)));
		$('body').css('margin-bottom', $('#command').height() + 15);
	});
	$('#chanbar ul li a').live('mouseover', function(){
		positionSubmenu();
	});
	$('.hoverinfo').live('click', function(){
		window.clearTimeout(hideinfo);
		hideinfo = window.setTimeout(function(){deldrawer('infohovered');}, 3000);
		if($('infohovered').length) {
			deldrawer('infohovered');
		}
		else {
			adddrawer('infohovered', $(this).siblings('.info').html(), 'infohovered');
		}
		return false;
	});

	$('.toolpanel').resizable({
	//	autoHide: true,
		handles: 'w',
		distance: 5,
		alsoResize: '.toolport',
		start: function() {
			var p = $(this);
			p.attr('oldwidth', '');
			p.children('.toolport').show();
			p.removeClass('collapsed');
		},
		stop: function(){
			$(this).toFixed();
			$(this).css({right: 0, left: null});
			$('#mainscroller').css('margin-right', $(this).width() + 6);
			$('#drawer').width($(window).width() - $(this).width() - 24);
			do_scroll();
			setToolpanel();
		}
	});
	$('.toolpanel .ui-resizable-handle').mousedown(function(){
		$(this).parent().toAbsolute();
	}).mouseup(function(){
		$(this).parent().toFixed();
	}).dblclick(function(){
		var p = $(this).parent();
		if(p.attr('oldwidth') == '') {
			p.attr('oldwidth', p.width());
			p.width($(this).width());
			p.children('.toolport').hide();
			p.addClass('collapsed');
		}
		else {
			p.width(p.attr('oldwidth'));
			p.attr('oldwidth', '');
			p.children('.toolport').show();
			p.removeClass('collapsed');
		}
		$('#mainscroller').css('margin-right', p.width() + 6);
		do_scroll();
		setToolpanel();
	});
	$('#drawer').width($(window).width() - $(this).width() - 30);
	$('.toolport').sortable({
		axis: 'y',
		containment: '.toolpanel',
		handle: '.widgettitle',
		opacity: 0.6,
		stop: function(){
			$.ajax(
				{
					type: 'POST',
					url: '/ajax/sortwidgets',
					cache: false,
					data: $(this).sortable('serialize'),
					success: function() {
						reloadWidgets();
					}
				}
			);
		}
	});

	$('#files a').live('click', function() {
		at_bottom();
		$('#uploads').load('/files').slideToggle('fast', function(){
			$('body').css('margin-bottom', $('#command').height() + 15);
			do_scroll();
		});
		return false;
	});
	$('#settings a').live('click', function() {
		at_bottom();
		$('#options').load('/options').slideToggle('fast', function(){
			$('body').css('margin-bottom', $('#command').height() + 15);
			do_scroll();
		});
		return false;
	});
	$('.encoded').live('click', function() {
		if(!masterpass) {
			$(this).addClass('decodeme');
			$('<div id="masterpassdlg"><label>Master Password: <input type="password" id="masterpass"></label></div>').dialog({
				bgiframe: true,
				modal: true,
				buttons: {
					'Set Password': function() {
						masterpass = $('#masterpass').val();
						$('.decodeme').each(function(){
							$('.crypt', this).html('<textarea>' + TEAdecrypt($('.crypt', this).text(), masterpass) + '</textarea>');
							$(this).addClass('decoded');
							$('.crypt', this).find('textarea').selectAll();
						});
						$(this).dialog('close');
					}
				},
				open: function(){
					$('#masterpass').focus();
				}
			});

		}
		else {
			if($(this).hasClass('decoded')) {
				// If we wanted to re-encode something after it's decoded
				//$('.crypt', this).text(TEAencrypt($('.crypt', this).text(), masterpass));
				//$(this).removeClass('decoded');
			}
			else {
				$('.crypt', this).html('<textarea>' + TEAdecrypt($('.crypt', this).text(), masterpass) + '</textarea>');
				$(this).addClass('decoded');
				$('.crypt', this).find('textarea').selectAll();
			}
		}
	});
	$('#masterpass').live('keypress', function(event){if(event.keyCode==13){
		masterpass = $('#masterpass').val();
		$('.decodeme').each(function(){
			$('.crypt', this).html('<textarea>' + TEAdecrypt($('.crypt', this).text(), masterpass) + '</textarea>');
			$(this).addClass('decoded');
			$('.crypt', this).find('textarea').selectAll();
		});
		$('#masterpassdlg').dialog('close');
	}});
	$('.alert_inner .choice').live('click', function(){
		if($(this).hasClass('cmd_image')) {
			var img = $('img', this);
			send('/image_complete ' + $(this).attr('href') + ' ' + img.attr('src') + ' ' + $(this).siblings('.query').text());
			$(this).parents('.drawers').remove();
			return false;
		}
	});
	$('img.emoticon').live('click', function(){
		$(this).parents('.content').find('img.emoticon').each(function(){$(this).replaceWith($(this).attr('alt'));});
	});
	$(window).scroll(function(){at_bottom();});

	$('#command_size').html('&nbsp;');
	onelineheight = $('#command_size').height()+15;

	setupUpload();
	buildpicker();
	$('#commandline').focus();
	$(window).resize(do_scroll);
	allmute = true;
	setRoom(cur_chan);
	window.setTimeout(poll, 2000);
	window.setInterval(checkpoll, 3700);

	// iDevice-specific nonsense
	if(navigator.platform == 'iPad' || navigator.platform == 'iPhone' || navigator.platform == 'iPod') {
		$('#rightpanel').remove();
		$('#mainscroller').css('margin-right', 0);

		window.setInterval(function(){
			$('div#command').css('top', $(window).scrollTop() + $(window).height() - 145);
		}, 100);
	};

});

function checkpoll() {
	dnow = new Date();
	$('.checkpoll').html(Math.round((dnow.getTime() - lastpoll.getTime())/1000) + ' seconds since last poll');
	if(Math.round((dnow.getTime() - lastpoll.getTime())/1000) > 30) {
		$('.checkpoll').addClass('caution');
	}
	else {
		$('.checkpoll').removeClass('caution');
	}
	if(lastpoll.getTime() + 45000 < dnow.getTime()) {
		play('bass');
		window.setTimeout(poll, 10);
	}
	if(lastpoll.getTime() + 120000 < dnow.getTime()) {
		location.reload();
	}
}

function toggleCodeEdit(usevalue, edittype){
	browserok = !jQuery.browser.webkit;
	if(usevalue != undefined) browserok = false;
	if(usevalue != undefined && cp) {
		if(cp.tagName != undefined) {
			cp.setCode(usevalue);
		}
		else {
			cp.val(usevalue);
		}
		return;
	}
	if(cp) {
		if(cp.tagName != undefined) {
			$('#richedit').hide();
			$('#commandline').val(cp.getCode());
			$(cp).remove();
			$('#commandline').show();
			$('#commandline').attr('disabled', null);
		}
		else {
			$('#richedit').hide();
			$('#commandline').val(cp.val());
			$(cp).remove();
			$('#commandline').show();
			$('#commandline').attr('disabled', null);
		}
		cp = null;
	}
	else {
		if(browserok) {
			cp = new CodePress($('#commandline')[0]);
			$(cp).appendTo('#editor').css({position: 'relative', visibility: null, height: 200, border: 'none'});
			$('#commandline').hide();
			$('#editorcontrols').show();
			if(usevalue != undefined) {
				if(edittype != undefined) {
					typeval = edittype;
					decorset = edittype;
					switch(edittype) {
						case 'decorcss':
							typeval = 'css';
							break;
						case 'decorhtm':
							typeval = 'html';
							break;
					}
					cp.edit('commandline', typeval);
					$('#editorcontrols').hide();
				}
				window.setTimeout(function(){cp.setCode(usevalue);}, 500);
			}
			else {
				cp.edit('commandline', $('#language').val());
			}
			window.setTimeout(function(){cp.toggleAutoComplete();}, 500);
			$('#richedit').show();
		}
		else {
			cp = $('<textarea id="codeeditor"></textarea>');
			$(cp).appendTo('#editor').css({position: 'relative', visibility: null, height: 200, border: 'none', width: '100%'});
			$('#commandline').hide();
			$('#editorcontrols').show();
			if(usevalue != undefined) {
				if(edittype != undefined) {
					decorset = edittype;
					$('#editorcontrols').hide();
				}
				cp.val(usevalue);
			}
			else {
				cp.val($('#commandline').val());
			}
			$('#richedit').show();
		}
	}
	$('body').css('margin-bottom', $('#command').height() + 15);
	do_scroll();

}

function addAlert(update, target){
	var classes = 'alert';
	classes += ' chan__' + update.channel.replace(/\W+/g, '_');
	if(update.cssclass != '') {
		classes += ' ' + update.cssclass;
	}
	id = 'status__' + update.status;
	classes += ' user_' + update.user_id;
	classes += ' username_' + update.username;
	if($('.'+id, target).length == 0) {
		$(target).append($('<tr class="' + classes + ' ' + id + '"><td class="content" colspan="2">' + update.data + '</td>'));
		if(update.js!='') {
			try{
				eval(update.js);
			}
			finally {}
		}
	}
	last_user_id = -1;
}

function addChat(update, target){
	var classes = 'message';
	re = new RegExp('\\b('+username+'|'+nickname+')\\b', 'gi');
	var msg = update.data;
	if(update.user_id == user_id) {
		classes += ' you';
	}
	else {
		msg = msg.replace(re, '<em>$1</em>');
	}
	classes += ' user_' + update.user_id;
	classes += ' chan__' + update.channel.replace(/\W+/g, '_');
	classes += ' username_' + update.username;
	var remoteusername = update.username;
	if(update.nickname != '' && update.nickname != null) {
		remoteusername = '<span title="' + update.username + '">' + update.nickname + '<span>';
	}
	if(update.user_id == last_user_id) {
		classes += ' nousername';
	}
	else {
		classes += ' hasusername';
	}
	if(update.type == 'emote') {
		classes += ' emote';
		if(update.nickname == null) {
			msg = update.username + ' ' + msg;
		}
		else {
			msg = update.nickname + ' ' + msg;
		}
	}
	if(update.cssclass != '') {
		classes += ' ' + update.cssclass;
	}
	id = 'status__' + update.status;
	if($('.'+id, target).length == 0) {
		if(msg != '') {
			$(target).append($('<tr class="' + classes + ' ' + id + '"><td class="user">' + remoteusername + '</td><td class="content">' + msg + '</td>'));
		}
		floatimg($('#notices tr:last-child'));
		if(update.js != '' && target == '#notices') {
			try{
				eval(update.js);
			}
			finally {}
		}

		if(update.user_id != user_id && classes.search(/quiet/i) == -1 && update.active == 1) {
			if(update.data.match(re)) {
				queueplay('buzz', true);
			}
			else {
				if($('.roomsquelch_' + update.channel).hasClass('off')) {
					queueplay('ding');
				}
			}
		}
	}
	last_user_id = update.user_id;
}

function addDirect(update, target){
	var classes = 'direct';
	var msg = update.data;
	if(update.user_id == user_id) {
		classes += ' you';
	}
	else {
		lastdmuser = update.username;
	}
	var remoteusername = update.username;
	if(update.nickname != '' && update.nickname != null) {
		remoteusername = '<span title="' + update.username + '">' + update.nickname + '<span>';
	}
	if(update.user_id == 0) {
		remoteusername = '<em>system</em>';
	}
	if(update.cssclass != '') {
		classes += ' ' + update.cssclass;
	}
	id = 'status__' + update.status;
	last_user_id = -1;
	if($('.'+id, target).length == 0) {
		if(msg != '') {
			$(target).append($('<tr class="' + classes + ' ' + id + '"><td class="user">' + remoteusername + '</td><td class="content">' + msg + '</td>'));
		}
		if(update.js!='') {
			eval(update.js);
		}
		if(update.user_to == user_id && update.user_id != update.user_to) {
			queueplay('buzz', true);
		}
	}
}

function addSystem(update, target){
	var classes = 'system';
	var msg = update.data;
	if(update.user_id == user_id) {
		classes += ' you';
	}
	var remoteusername = update.username;
	if(update.nickname != '' && update.nickname != null) {
		remoteusername = '<span title="' + update.username + '">' + update.nickname + '<span>';
	}
	if(update.cssclass != '') {
		classes += ' ' + update.cssclass;
	}
	id = 'status__' + update.status;
	if($('.'+id, target).length == 0) {
		if(msg != '') {
			$(target).append($('<tr class="' + classes + ' ' + id + '"><td class="content" colspan="2"><div class="inner">' + msg + '</div></td>'));
			last_user_id = -1;
		}
		if(update.js!='') {
			eval(update.js);
		}
		if(update.user_to == user_id && update.user_id != update.user_to) {
			queueplay('buzz');
		}
	}
}

function addTime(time, target){
	var time = $P.strtotime(time);
	time = Math.floor(time / 300);
	time = time * 300;
	if(last_update_time < time) {
		date = '';
		classes = 'datetime';
		daynow = Math.floor(time / 86400);
		daylast = Math.floor(last_update_time / 86400);
		classes += (daynow != daylast) ? ' showdate' : ' hidedate';
		$(target).append($('<tr class="' + classes + '"><td class="date">' + $P.date('M j', time) + '</td><td class="time">' + $P.date('g:i A', time) + '</td>'));
		$('#notices tr.datetime').show();
		$('#notices tr.datetime').each(function(){if($(this).nextAll(':visible').first().hasClass('datetime')) {$(this).hide();}});
		last_user_id = -1;
	}
	last_update_time = time;
}

var status = 0;

function poll() {
	lastpoll = new Date();
	$.ajax(
		{
			type: 'POST',
			url: '/presence/poll',
			cache: false,
			dataType: 'json',
			data: {s: status, chan: cur_chan},
			success: function(data, textStatus){
				processPoll(data, textStatus, true);
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
			  // typically only one of textStatus or errorThrown
			  // will have info
			  window.setTimeout(poll, 1000);
			}
		}
	);
}

var swfu;
var swfu_settings;
function setupUpload(){
	swfu_settings = {
		flash_url : "/js/swfupload.swf",
		post_params: {"sp_key_md5" : sp_key_md5},
		file_size_limit : "100 MB",
		file_types : "*.*",
		file_types_description : "All Files",
		file_upload_limit : 100,
		file_queue_limit : 0,
		file_post_name : "uploaded",
		custom_settings : {
			progressTarget : "fsUploadProgress",
			cancelButtonId : "btnCancel"
		},
		debug: false,

		// Button settings
		button_image_url: "/css/images/upload_button.png",
		button_width: "55",
		button_height: "19",
		button_placeholder_id: "spanButtonPlaceHolder",
		button_text: '<span class="upload_button"></span>',
		button_text_style: ".theFont { font-size: 16; }",
		button_text_left_padding: 12,
		button_text_top_padding: 3,
		button_cursor : SWFUpload.CURSOR.HAND,
		button_action : SWFUpload.BUTTON_ACTION.SELECT_FILES,

		// The event handler functions are defined in handlers.js
		//*
		file_queued_handler : function(file){},
		file_queue_error_handler : function(file, errorCode, message){},
		file_dialog_complete_handler : function(numFilesSelected, numFilesQueued){ this.startUpload(); },
		upload_start_handler : function(file){},
		upload_progress_handler : function(file, bytesLoaded, bytesTotal){},
		upload_error_handler : function(file, errorCode, message){alert(file + "\nError: " + message);},
		upload_success_handler : function(file, serverData){eval(serverData);/*console.log('upload success', file, serverData);*/},
		upload_complete_handler : function(file){/*console.log('upload complete', file);*/},
		queue_complete_handler : function(numFilesUploaded){/*console.log('queue complete', numFilesUploaded);*/}
		//*/
	};

}

function getFlashMovie(movieName) {
	var isIE = navigator.appName.indexOf("Microsoft") != -1;
	return (isIE) ? window[movieName] : document[movieName];
}

function isVisible(elem)
{
	var docViewTop = $(window).scrollTop();
	var docViewBottom = docViewTop + $(window).height() - 40;

	var elemTop = $(elem).offset().top;
	var elemBottom = elemTop + $(elem).height();

	return ((elemBottom >= docViewTop) && (elemTop <= docViewBottom));
}

function send(value) {
	lastdm = false;
	var encodes = value.match(/^\/en(?:crypt|code)\s+/i);
	if(encodes != null) {
		value = encode(value.substr(encodes[0].length));
		if(value) {
			value = '/encode ' + value;
		}
		else {
			return false;
		}
	}
	commandstatus('/css/images/spinner.gif');
	$.ajax(
		{
			type: 'POST',
			url: '/presence/send',
			cache: false,
			dataType: 'json',
			data: {msg: value, chan: cur_chan},
			success: function(data, textStatus){
				atbottom = true;
				do_scroll();
				$(window).scrollTop($(document).height());
				if(data.js != '') {
					eval(data.js);
				}
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
			}
		}
	);
}

function joinRoom(room){
	send('/join ' + room);
}

function partRoom(room){
	send('/part ' + room);
}

function setRoom(room, el){
	for(fn in roomchange) {
		if(jQuery.isFunction(roomchange[fn])) {
			roomchange[fn](room, cur_chan);
		}
	}

	if(room == null) {
		room = cur_chan;
	}
	$('.inroom').removeClass('active');
	$('#tab__' + room).addClass('active');
	if(el != null) {
		$(el).parents('.inroom').addClass('active');
	}
	$('#notices tr').remove();

	cur_chan = room;

	myRoom = cur_chan.replace(/\W/g,"-");
	$('#office').removeClass();
	if (myRoom) {
		$('#office').addClass(myRoom);
	}

	last_user_id = -1;
	last_update_time = 0;
	allmute = true;
	$.ajax(
		{
			type: 'POST',
			url: '/presence/setchan',
			cache: false,
			dataType: 'json',
			data: {s: status, chan: cur_chan},
			success: function(data, textStatus){
				processPoll(data, textStatus, false);
				positionSubmenu();
				allmute = false;
				setvolume();
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
			}
		}
	);
}

function processPoll(data, textStatus, repoll){
	status = data.status;
	at_bottom();
	var hadupdate = (data.namebarmd5 != namebarmd5) && (namebarmd5 != 0);
	namebarmd5 = data.namebarmd5;
	$('#chanbar').html(data.chanbar);
	$('#chanbar a').removeAttr('href').css('cursor', 'pointer');
	$('.widget.names .widgetcontent').html(data.namebar);
	setDecor(data.decor);
	namelist = data.names;
	setvolume();
	positionSubmenu();
	if(jsdate == 0) {
		jsdate = data.jsdate;
	}
	else {
		if(jsdate != data.jsdate) {
			location.reload();
		}
	}
	if(data.useportal) {
		$('#notices').hide();
		$('#portal .portal').hide();
		for(var pid in data.useportal) {
			var portal = data.useportal[pid];
			if($('#' + portal.id).length) {
				$('#' + portal.id).show();
			}
			else {
				$('#portal').append('<div id="' + portal.id + '" class="portal ' + portal.classes + '"></div>');
				$('#' + portal.id).html(portal.content);
			}
		}
	}
	else {
		$('#notices').show();
		$('#portal .portal').hide();
	}
	hadupdate = processUpdates(data.updates, data.channels) || hadupdate;
	processDrawers(data.drawers);
	if(data.sups > 0) {
		new_favicon(data.sups);
	}
	if(hadupdate) {
		do_scroll();
	}
	if(repoll) {
		window.setTimeout(poll, 1000);
	}
	processcommandstatus();
	processsmartstatus();
}

function fasthash(s) {
	var hash = 0;
	for(var z = 0; z < s.length; z++) {
		hash = hash ^ s.charCodeAt(z);
		hash = (hash >>> 1) | hash << 31;
	}
	return hash;
}

function setDecor(decor) {
	if(decor == undefined) return;
	while($('#decorstyle').length) {
		$('#decorstyle').remove();
	}
	if('css' in decor) {
		$('<link id="decorstyle" href="/options/css/' + cur_chan + '?' + fasthash(decor.css) +'" rel="stylesheet" type="text/css" />').appendTo('head');
		do_scroll();
	}
	if('htm' in decor) {
		$('#office').html(decor.htm);
	}
	else {
		$('#office').html('');
	}
}

var updateHandlers = {
	primaryHandler: function (update, updates, channels, target) {
		hadupdate = false;
		inroom = false;
		for(var k in channels) {
			if(channels[k] == update.channel) {
				inroom = true;
			}
		}
		if(update.channel == cur_chan || update.channel == '' || (update.channel != cur_chan && target != '#notices')) {
			if(typeof(update.inchannel) != 'undefined' && update.inchannel != lastinchannel) {
				$(target).append($('<tr class="searchchannel" id="search_' + update.inchannel + '" ><td colspan="2" class="content">In Channel: ' + update.inchannel + '</td>'));
			}
			lastinchannel = update.inchannel;
			if(update.data == null) update.data = '';
			if(update.data != '' && target == '#notices') addTime(update.msgtime, target);
			var encodes = update.data.match(/^\/encode\s+(.+)$/i);
			if(encodes != null) {
				update = dodecode(encodes[1], update);
			}
			switch(update.type) {
				case 'message':
				case 'emote':
				case 'status':
					addChat(update, target);
					break;
				case 'notice':
					addAlert(update, target);
					break;
				case 'part':
				case 'join':
					addAlert(update, target);
					break;
				case 'direct':
					addDirect(update, target);
					break;
				case 'system':
				case 'choice':
					addSystem(update, target);
					break;
				default:
					if(jQuery.isFunction(fn = eval('barchat.add'+update.type))) {
						fn(update, target);
					}
			}
			hadupdate = true;
		}
		else {
			re = new RegExp('\\b'+username+'\\b', 'gi');
			if(inroom && update.user_id != user_id) {
				switch(update.type) {
					case 'message':
					case 'emote':
					case 'notice':
						if($('.roomsquelch_' + update.channel).hasClass('off')) {
							if(update.data.match(re)) {
								queueplay('buzz', true);
							}
							else {
								queueplay('ding');
							}
						}
				}
			}
			if(!inroom && update.data.match(re)) {
				switch(update.type) {
					case 'message':
					case 'emote':

					case 'notice':
						update.data = '<div class="slash">' + update.username + ' is talking about you in <a href="#" onclick="joinRoom(\'' + update.channel + '\');return false;">' + update.channel + '</a>:</div>' + update.data;
						addDirect(update, target);
						queueplay('buzz');
						break;
				}
			}
		}
		return hadupdate;
	}
};

function processUpdates(updates, channels, target){
	var hadupdate = false;
	if(target == null) {
		target = '#notices';
	}

	var lastinchannel = '';
	for(var u in updates) {
		var update = updates[u];
		for(var h in updateHandlers) {
			hadupdate = updateHandlers[h](update, updates, channels, target) || hadupdate;
		}
	}
	unqueueplay();
	return hadupdate;
}

function toggler(e){
	$('.toggle.off', e).removeClass('off').addClass('onn');
	$('.toggle.on', e).removeClass('on').addClass('off');
	$('.toggle.onn', e).removeClass('onn').addClass('on');
}

function squelchRoom(room, squelch) {
	$.ajax(
		{
			type: 'POST',
			url: '/ajax/squelchroom',
			cache: false,
			data: {squelch: squelch, room: room}
		}
	);

}

function queueplay(sound, override) {
	for(var i in queuedsounds) {
		if(queuedsounds[i][0] == sound) {
			return;
		}
	}
	queuedsounds.push([sound,override]);
}

function unqueueplay() {
	for(var i in queuedsounds) {
		play(queuedsounds[i][0], queuedsounds[i][1]);
	}
	queuedsounds = [];
}

function play(sound, override){
	if(override == null) override = false;
	dosound = true;
	if(allmute == true || (usermute && !override)) {
		return;
	} else {
		switch(sound) {
			case 'bass':
				sound = '/effects/bass.mp3'; break;
			case 'buzz':
				sound = '/effects/buzz.mp3'; break;
			case 'ding':
				sound = '/effects/ding2.mp3'; break;
		}
		var snd = new Audio(sound);
		if(snd.canPlayType('audio/mpeg;')) {
			snd.load();
			snd.play();
		}
		else if($('embed[name=ding]').length > 0) {
			getFlashMovie('ding').playsound(sound);
		}
	}
}

function at_bottom(){
	var bottom = $('#notices tr:last-child');
	if(bottom.length) {
		atbottom = isVisible(bottom[bottom.length -1]);
	}
	else {
		atbottom = true;
	}
}

function do_scroll(){
	$('#drawer').width($(window).width() - $('#rightpanel').width() - 30);
	$('.content img:visible:not(.noresize)').filter(function(index){return $(this).parents('.map').length == 0;}).each(function(){
		$(this).width('auto');
		if($(this).width() > 0) {
			$(this).width(Math.min($(this).width(),$(window).width() - $('#notices tr td.user').width() - $('.toolpanel').width() -30) );
			if($(this).height() > 200) {
				$(this).width('auto');
				$(this).height(200);
			}
		}
	});
	$('.content object, .content embed').each(function(){
		if($(this).attr('oldwidth') == undefined) {
			$(this).attr('oldwidth', $(this).width());
			$(this).attr('oldheight', $(this).height());
		}
		oldwidth = $(this).attr('oldwidth');
		oldheight = $(this).attr('oldheight');

		$(this).width(Math.min(oldwidth,$(window).width() - $('#notices tr td.user').width() - $('.toolpanel').width() -30) );
		$(this).height($(this).width() / oldwidth * oldheight );
	});
	$('.toolpanel').css({top: 0, position: 'fixed', height: $(window).height() - $('#command').height() - parseInt($('#command').css('padding-top'))});
	if (ios == false) {
		$('#mainscroller .portal').height($(window).height() - $('#command').height() - 20);
	}
	if(atbottom) {
		$('#mainscroller').scrollTop($(document).height());
		$(window).scrollTop($(document).height());
	}
	else {
// Was just getting annoying.
//		$('#more_arrow').css('bottom', $('#command').height()).show('fast', function(){$('#more_arrow div').effect("bounce", {times:3 }, 300, function(){$('#more_arrow').fadeOut('slow')});});
	}
}

function showAddress(address, e) {
  if (GBrowserIsCompatible()) {
    var map = new GMap2(e);
    map.setCenter(new GLatLng(37.4419, -122.1419), 13);
    map.addControl(new GSmallMapControl());
    map.addControl(new GMapTypeControl());
    geocoder = new GClientGeocoder();

    geocoder.getLatLng(
      address,
      function(point) {
        if (!point) {
          alert('"' + address + '" not found');
        } else {
          map.setCenter(point, 13);
          var marker = new GMarker(point);
          map.addOverlay(marker);
          marker.openInfoWindowHtml(address);
        }

      }
    );
  }
}

function bareffect(effectfn){
	if(allmute == true) {
		return;
	}
	effectfn();
}

function effect_woodshed(){
	play('/effects/woodshed/chainsaw.mp3');
	for(z=1;z<13;z++){
		window.setTimeout(
			function(){
				$('body').append('<img src="/effects/woodshed/splatter_' + Math.ceil(Math.random()*13) + '.png" class="splatter" style="position:fixed;top:'+(Math.random()*$(window).height())+'px;left:'+(Math.random()*$(window).width())+'px;">');
			},
			Math.random() * 4000 + 6000
		);
	}
	window.setTimeout(
		function(){
			$('.splatter').fadeOut();
		},
		11000
	);
	window.setTimeout(
		function(){
			$('.splatter').remove();
		},
		12000
	);
}

function get_status(e){
	var id = $(e).parents('tr').attr('class');
	var re = /status__(\d+)$/;
	var s = re.exec(id);
	return s[1];
}


function setmute(setting){
	switch(setting){
		case 'mute':
			allmute = true;
			usermute = true;
			break;
		case 'squelch':
			allmute = false;
			usermute = true;
			break;
		case 'ding':
			allmute = false;
			usermute = false;
			break;
	}
	setvolume();
}


function setvolume(){
	if(allmute) {
		$('#v_ding').css('background-image', 'url(/css/images/status_busy.png)');
	}
	else if(usermute) {
		$('#v_ding').css('background-image', 'url(/css/images/status_away.png)');
	}
	else {
		$('#v_ding').css('background-image', 'url(/css/images/status_online.png)');
	}
}

function floatimg(el){
	$('.dragfloat', el)
	.draggable({
		revert: true,
		revertDuration: 0,
		stop: function(event, ui){
			drg = $(this);
			var iwidth = drg.find('img').width();
			var iheight = drg.find('img').height();

			if(drg.hasClass('screencast')) {
				html = '<img src="' + drg.find('img').attr('src') + '">';
			}
			else {
				html = '<img src="' + drg.attr('href') + '">';
			}

			z = $($(html))
			.css({
				width: iwidth,
				height: iheight
			})
			.appendTo('body')
			.resizable({
				aspectRatio: true,
				handles: 'n, e, s, w, ne, se, sw, nw',
				stop: function(event, ui) {
					$(ui.element).css('position', 'fixed');
					$(ui.element).css('top', parseInt($(ui.element).css('top')) - $(document).scrollTop());
					$(this).attr('ressizing', '');
				},
				start: function(){ $(this).attr('resizing', 'yes'); }
			})
			.parent('.ui-wrapper')
			.draggable({
				stack: {group: '.dragout'},
			})
			.css({
				position: 'fixed',
				top: ui.offset.top - $(document).scrollTop() - iheight + 4,
				left: ui.offset.left
			})
			.addClass('dragout')
			.dblclick(function(){$(this).empty().remove();})
			.click(function(){
				$('.dragout').css('z-index', 0);
				$(this).css('z-index', 10);

			})
			.mouseenter(function(){
				if($(this).attr('resizing') == '')  {
					$(this).css('position', 'absolute').css('top', parseInt($(this).css('top')) + $(document).scrollTop());
				}
			})
			.mouseleave(function(){
				if($(this).attr('resizing') == '')  {
					$(this).css('position', 'fixed').css('top', parseInt($(this).css('top')) - $(document).scrollTop());
				}
			})
			;
		}
	});
}

function autocomplete() {
	var commandline = $('#commandline').val();
	commandline = commandline.substr(0, $('#commandline').getRange().start);
	if(!autodata) {
		$.ajax(
			{
				type: 'POST',
				url: '/autocomplete',
				cache: false,
				dataType: 'json',
				data: {cmd: commandline, chan: cur_chan},
				success: function(data, textStatus){
					autodata = data;
					autoindex = 0;
					nextautocomplete();
				},
			}
		);
	}
	else {
		nextautocomplete();
	}
}

function nextautocomplete() {
	if(autodata.length == 1) {
		$('#commandline').animate({backgroundColor: '#ff0000'}, 50).animate({backgroundColor: 'white'}, 50)
		.animate({backgroundColor: '#ff0000'}, 50).animate({backgroundColor: 'white'}, 50)
		.animate({backgroundColor: '#ff0000'}, 50).animate({backgroundColor: 'white'}, 50);
		return;
	}
	autoindex++;
	if(autoindex >= autodata.length) {
		autoindex = 0;
	}
	auto = autodata[autoindex];
	if(auto != undefined) {
		marker = auto.indexOf(String.fromCharCode(9));
		auto = auto.replace(/\t/, '');
		$('#commandline').val(auto);
		if(marker >= 0) {
			$('#commandline').selectRange(marker, $('#commandline').val().length);
		}
	}
}

function queuehistory(cmd) {
	if(historylist.indexOf(cmd) != 0) {
		historylist.unshift(cmd);
		while(historylist.length > 20) {
			historylist.pop();
		}
	}
}

function processcommandstatus() {
	for(var i in commandlineicons) {
		re = new RegExp(i);
		if($('#commandline').val().search(re, 'i') == 0) {
			commandstatus(commandlineicons[i]);
			return;
		}
	}
	commandstatus(false);
}

function commandstatus(img){
	if(img) {
		$('#commandline_status').css({backgroundImage: "url('" + img + "')", backgroundPosition: "2px 2px", backgroundRepeat: "no-repeat", width: 20});
	}
	else {
		$('#commandline_status').css({backgroundImage: "none", width: 0});
	}

}

function encode(value){
	if(!masterpass) {
		$('<div id="masterpassdlg"><label>Master Password: <input type="password" id="masterpass" onload="this.focus();"></label></div>').dialog({
			bgiframe: true,
			modal: true,
			buttons: {
				'Set Password': function() {
					masterpass = $('#masterpass').val();
					send('/encode ' + value)
					$(this).dialog('close');
				},
			},
			open: function(){
				$('#masterpass').focus();
			}
		});
		return false;
	}
	else {
		return TEAencrypt(value, masterpass);
	}
}

function dodecode(value, update){
	update.data = '<div class="encoded"><span class="crypt">' + value + '</span><span class="message">Click to reveal</span></value>';
	return update;
}

function dosearch(criteria){
	//$('#mainscroller').css({height: '40%', overflowY: 'scroll', position: 'relative', top: '50%'});
	send('/search ' + criteria);
}

function send_click(){
	if(cp) {
		browserok = !jQuery.browser.webkit;
		browserok =	cp.getCode != undefined;
		if(browserok) {
			tosend = cp.getCode();
			cp.edit('commandline', $('#language').val());
		}
		else {
			tosend = cp.val();
			cp.val('');
		}
		switch(decorset) {
			case 'css':
				send('/decor css ' + tosend);
				toggleCodeEdit();
				break;
			case 'htm':
				send('/decor htm ' + tosend);
				toggleCodeEdit();
				break;
			default:
				send('/hilite ' + $('#language').val() + ' ' + tosend);
		}
		decorset = '';
		$('#commandline').val('');
	}
	else {
		queuehistory($('#commandline').val());
		send($('#commandline').val());
		namer = '';
		for(var i in namelist) { namer += ((namer == '') ? '' : '|') + namelist[i].username + '|' + namelist[i].nickname; }
		dmr = new RegExp('^(d|\\/msg|\\/m|\\/dm|\\/d)\\s+(' + namer + ')', 'i');
		if((parts = $('#commandline').val().match(dmr)) == null) {
			newstart = '';
		}
		else {
			newstart =  '/msg ' + parts[2] + ' ';
		}
		$('#commandline').val(newstart).focus();

	}
}

function command_keypress(event){
	if(event.keyCode!=252 && event.keyCode!=9) {
		autodata = false;
		autoindex = 0;
	}
	if(event.keyCode==13 && !event.shiftKey){
		send_click();
		return false;
	}
}

function new_favicon(ct) {
	$('#favicon').remove();
	$('head').append('<link rel="shortcut icon" id="favicon" href="/css/images/animated_favicon.gif" type="image/gif" />');
	$('body').mousedown(reset_favicon).keypress(reset_favicon);
	$('#commandline').focus(reset_favicon);
}

function reset_favicon() {
	$('#favicon').remove();
	$('head').append('<link rel="shortcut icon" id="favicon" href="/css/images/favicon.ico" type="image/x-icon" />');
	$('body').unbind('mousedown').unbind('keypress');
}

var loader = {
	loaded: [],
	js: function(url, dep, fn, noteffect) {
		if(jQuery.inArray('js_' + dep, this.loaded) == -1) {
			if(noteffect || !allmute) {
				$('<script type="text/javascript" src="' + url + '"></script>').load(fn).appendTo('head');
			}
			else {
				$('<script type="text/javascript" src="' + url + '"></script>').load().appendTo('head');
			}
			this.loaded.unshift('js_' + dep);
		}
		else {
			fn();
		}
	},
	css: function(url, dep, fn, noteffect) {
		if(jQuery.inArray('css_' + dep, this.loaded) == -1) {
			if(noteffect || !allmute) {
				$('<link rel="stylesheet" type="text/css" href="' + url + '">').load(fn).appendTo('head');
			}
			else {
				$('<link rel="stylesheet" type="text/css" href="' + url + '">').load().appendTo('head');
			}
			this.loaded.unshift('css_' + dep);
		}
		else {
			fn();
		}
	}
};

function adddrawer(status, message, cssclass){
	re = new RegExp('\\{\\$drawer_id\\}', 'g');
	message = message.replace(re, status);
	newitem = $('<div class="alert drawers ' + cssclass + '" id="drawer_' + status + '"><div class="alert_inner">' + message + '</div></div>');
	$('#drawer').show().prepend(newitem);
	newitem.show();
	newitem.data('hash', fasthash(message));
}

function cleardrawer(){
	$('#drawer .alert').hide();
}

function deldrawer(status){
	$('#drawer_' + status).remove();
	if($('#drawer tr').length == 0) {
		$('#drawer').hide();
	}
	return false;
}

function closedrawer(drawerid){
	deldrawer(drawerid);
	send('/closedrawer ' + drawerid);
	return false;
}

function setToolpanel(){
	$.ajax(
		{
			type: 'POST',
			url: '/ajax/toolpanel',
			cache: false,
			data: {value: $('.toolpanel').width()}
		}
	);
}

function getToolpanel(width){
	if(width >10) {
		$('.toolpanel').width(width);
		$('.toolport').show();
		$('.toolpanel').removeClass('collapsed');
		$('#mainscroller').css('margin-right', $('.toolpanel').width() + 6);
	}
}

function reloadWidgets(){
	$('.toolport').load('/ajax/widgets');
}

function removeWidget(id){
	$.ajax(
		{
			type: 'POST',
			url: '/ajax/removewidget',
			cache: false,
			data: {value: id},
			success: function() {
				reloadWidgets();
			}
		}
	);
}

function positionSubmenu(){
	$('.submenu').each(function(){$(this).css('left', $(this).siblings('a').offset().left).css('bottom', $('#chanbar').height() + $('.dock').height() - 1);});
}

function processDrawers(drawers) {
	var drawerids = [];
	for(var d in drawers) {
		var drawer = $('#drawer_' + drawers[d].id);
		if(drawer.length == 0) {
			adddrawer(drawers[d].id, drawers[d].message, drawers[d].cssclass);
			try {
				drawer = $('#drawer_' + drawers[d].id);
				drawerdata = drawers[d];
				eval(drawers[d].js);
			}
			finally{}
		}
		else {
			re = new RegExp('\\{\\$drawer_id\\}', 'g');
			message = drawers[d].message.replace(re, drawers[d].id);
			oldhash = drawer.data('hash');
			newhash = fasthash(message);
			if(oldhash != newhash) {
				drawer.html('<div class="alert_inner">' + message + '</div>');
				drawer.data('hash', newhash);
			}
		}
		drawerids.unshift(drawers[d].id);
	}
	$('#drawer .alert').each(function(){
		if(drawerids.indexOf($(this).attr('id').substr(7)) == -1) {
			$(this).remove();
		}
	});
	if($('#drawer .alert').length == 0) {
		$('#drawer').hide();
	}
	else {
		$('#drawer').show();
	}
}

function refreshDrawers(fn) {
	$.ajax(
		{
			type: 'POST',
			url: '/presence/getdrawers',
			cache: false,
			dataType: 'json',
			data: {channel: cur_chan},
			success: function(data, textStatus){
				processDrawers(data, textStatus, true);
				if(typeof(fn) != 'undefined') {
					fn();
				}
			},
		}
	);
}

function startKick(id, channel) {
	kicks[id] = window.setTimeout(function(){
		partRoom(channel);
	}, 10000);
}

function abortKick(id) {
	window.clearTimeout(kicks[id]);
	return false;
}

function processsmartstatus() {
	if(smarttimeout != -1) {
		window.clearTimeout(smarttimeout);
		smarttimeout = -1;
	}

	var statuses = [];
	$('#notices .smartstatus:not(.processing)').each(function(){
		var link = $(this);
		statuses.push(get_status(link));
		link.addClass('processing');
	});

	if(statuses.length > 0) {
		$.ajax(
			{
				type: 'POST',
				url: '/presence/smartstatus',
				cache: false,
				dataType: 'json',
				data: {channel: cur_chan, statuses: statuses},
				success: function(data, textStatus){
					for(var status in data.html) {
						$('.status__' + status + ' td.content').html(data.html[status]);
					}
					if($('.smartstatus').length > 0) {
						smarttimeout = window.setTimeout(processsmartstatus, 5000);
					}
					do_scroll();
				},
			}
		);
	}
}

function reloadstatus(status){
	$.ajax(
		{
			type: 'POST',
			url: '/presence/smartstatus',
			cache: false,
			dataType: 'json',
			data: {channel: cur_chan, statuses: [status]},
			success: function(data, textStatus){
				for(var status in data.html) {
					$('.status__' + status + ' td.content').html(data.html[status]);
				}
				do_scroll();
			},
		}
	);
}

function thumbload(l){
	if(typeof(jQuery(l).data('img')) == 'undefined') {
		himg = jQuery(l).attr('src').replace(/-thumb_medium/,'');
		console.log(himg);
		jQuery(l)
			.css({height:128,width:160})
			.data('img',jQuery(l).attr('src'))
			.css('background-image','url('+himg+')')
			.css('position', 'absolute')
			.hover(
				function(){
					jQuery(this)
						.attr('src', '/css/images/spacer.gif')
						.width(200).height(168)
						.css('margin-left', -20)
						.css('margin-top', -20)
				},
				function(){
					jQuery(this)
						.attr('src', jQuery(this).data('img'))
						.width(160).height(128)
						.css('margin-left', 0)
						.css('margin-top', 0)
				}
			)
			.mousemove(function(e){
				jQuery(this).css('background-position', Math.floor((e.offsetX-20)/1.6) + '% ' + Math.floor((e.offsetY-20)/1.28) + '%');
			});
	}
}

function apply_karma(md5w, hword) {
	var selector = '#mainscroller .karma_' + md5w + ' .inner.active';
	if(!$(selector).length || !isVisible($(selector))) {
		$('#mainscroller .karma_' + md5w + ':not(:last)').remove();
		selector = '#mainscroller .karma_' + md5w + ':last .inner';
	}
	$(selector).load(
		'/ajax/karma/', 
		{word:hword}, 
		function(){
			$(this).addClass('active');
			do_scroll();
		}
	);
}

function buildpicker(){
	var settd = null;
	$('<img id="picker" src="/css/images/picker.png">')
		.appendTo('#mainscroller')
		.css({position: 'absolute', display: 'none', opacity: 0.3, cursor: 'pointer'})
		.click(function(){
			$(settd).toggleClass('picked');
		})
		.hover(
			function(){
				$(this).css({opacity: 1.0});
			}, 
			function(){
				$(this).css({opacity: 0.3});
			}
		);
	$('.message .content').live('mouseover', function(){
		c = $(this).offset();
		$('#picker')
			.css({left: c.left, top: c.top+ 4})
			.show();
		settd = $(this).closest('tr');
	});
	$('.notices').live('mousedown', function(){
		$('#picker').hide();
	}).live('mouseup',function(){
		$('#picker').hide();
	});
}

function picked_ids(){
	var picked_statuses = [];
	var re = /\bstatus__(\d+)\b/;
	$('.picked').each(function(){
		var id = $(this).attr('class');
		var s = re.exec(id);
		picked_statuses.push(s[1]);
	})
	return picked_statuses;	
}

function picked(){
	var picked_statuses = [];
	$('.picked').each(function(){
		picked_statuses.push($('.content', this).html());
	})
	return picked_statuses;	
}

function clearpicked() {
	$('.picked').toggleClass('.picked');
}
