(function( notificationsPoller, $, undefined ) {
	"use strict";

	var _method_poll = 'poll',
	    _method_push = 'push';

	var _defaultSettings = {
		url: null,
		baseUrl: null,
		method: _method_poll,
		// time in miliseconds, how often to check for new messages
		pollInterval: 3000,
		// true if requests are cross domain
		xDomain: false,
		websocket: {}
	};
	var _settings;
	/**
	 * @var object in polling mode: active ajax request
	 */
	var _jqxhr;
	/**
	 * @var object in polling mode: active timer
	 */
	var _timer;
	/**
	 * @var object in push mode: socket.io
	 */
	var _socket;
	/**
	 * @var array messages stack
	 */
	var _messages = [];
	/**
	 * @var boolean did user agreed to receive notifications
	 */
	var _ready = false;

	notificationsPoller.wrapApi = function() {
		// from: https://gist.github.com/jhthorsen/5813059
		// added call to $.wnf when webkitNotification is not available
		if(!window.Notification) {
			if(window.webkitNotifications) {
				window.Notification = function(title, args) {
					var n = window.webkitNotifications.createNotification(args.iconUrl || '', title, args.body || '');
					$.each(['onshow', 'onclose'], function(k, i) { if(args[k]) this[k] = args[k]; });
					n.ondisplay = function() { if(this.onshow) this.onshow(); };
					n.show();
					return n;
				};
				window.Notification.permission = webkitNotifications.checkPermission() ? 'default' : 'granted';
				window.Notification.requestPermission = function(cb) {
					webkitNotifications.requestPermission(function() {
						window.Notification.permission = webkitNotifications.checkPermission() ? 'denied' : 'granted';
						cb(window.Notification.permission);
					});
				};
				window.Notification.prototype.close = function() { if(this.onclose) this.onclose(); };

				// since requestPermission won't work when called from init lets display a fallback popup to ask for permission
				$.wnf({notification: {
					autoclose: true,
					ntitle: 'Enable system notifications',
					nbody: '<a href="#" onclick="return notificationsPoller.ask();">Enable system notifications</a>'
				}});
			} else {
				window.Notification = function(title, args) {
					var config = {
						/*position: 'bottom-right',
						autoclose: false,
						expire: null,*/
						notification: { ntitle: title, nbody: args.body, icon: args.iconUrl || '', tag: args.tag || '' }
					};
					if (args.onshow) config.onShowFn = args.onshow;
					if (args.onclose) config.onCloseFn = args.onclose;
					$.wnf( config );
					return this;
				};
				window.Notification.permission = 'granted';
				window.Notification.requestPermission = function(cb) { cb('granted'); };
				window.Notification.prototype.close = function() { if(this.onclose) this.onclose(); };
			}
		}
	};

	notificationsPoller.init = function(settings) {
		notificationsPoller.wrapApi();
		notificationsPoller.ask();

		_settings = $.extend({}, _defaultSettings, settings);

		if (_settings.method === _method_poll) {
            if('onopen' in _settings.websocket) {
                _settings.websocket['onopen'](null)(null);
            }
			notificationsPoller.poll();
		} else {
			_socket = new WebSocket(_settings.url);
			for(var i in _settings.websocket) {
				if (typeof _settings.websocket[i] === 'function') {
					_socket[i] = _settings.websocket[i](_socket);
				}
			}
			window.WEB_SOCKET_SWF_LOCATION = _settings.baseUrl + '/js/WebSocketMain'+(_settings.xDomain ? 'Insecure' : '')+'.swf';
		}
	};

	notificationsPoller.ask = function() {
		if (!window.Notification.permission!=='granted') {
			window.Notification.requestPermission(function(){
				_ready = true;
			});
		} else {
			_ready = true;
		}
		// callable from anchor tag's onclick event
		return false;
	};

	notificationsPoller.poll = function() {
		_jqxhr = $.ajax({
			url: _settings.url,
            cache: false,
			success: notificationsPoller.process,
			error: notificationsPoller.error
		});
	};

	notificationsPoller.process = function(data) {
		if (typeof data === 'undefined' || typeof data.messages === 'undefined' || data.messages.length === 0) {
			_timer = window.setTimeout(notificationsPoller.poll, _settings.pollInterval);
			return false;
		}
		for (var i = 0; i < data.messages.length; i++) {
			notificationsPoller.addMessage(data.messages[i]);
		}

		notificationsPoller.display();
		_timer = window.setTimeout(notificationsPoller.poll, _settings.pollInterval);
	};

	notificationsPoller.addMessage = function(message) {
		_messages.push(message);
	};

	notificationsPoller.display = function() {
        if('onmessage' in _settings.websocket) {
            for (var i = 0; i < _messages.length; i++) {
                var ret = _settings.websocket['onmessage'](null)(_messages[i]);
                
                if(typeof ret !== 'undefined' && !ret) {
                    delete _messages[i];
                }
            }
        }

		if (!_ready)
			return false;

		while(_messages.length) {
			var msg = _messages.shift();
            
            if(typeof msg === 'undefined') {
                continue;
            }
            
			new window.Notification(msg.title, {body: msg.body});
			if (typeof msg.sound !== 'undefined') {
				notificationsPoller.sound(msg.sound);
			}
		}
	};

	notificationsPoller.sound = function(url) {
		//$("<embed src='"+url+"' hidden='true' autostart='true' loop='false' class='playSound'>").appendTo('body');
		$("<audio></audio>").attr({ 'src':url, 'autoplay':'autoplay' }).appendTo("body");
	};

	notificationsPoller.error = function() {
        if('onerror' in _settings.websocket) {
            _settings.websocket['onerror'](null)(null);
        }
		console.log('Failed to check new messages at '+_settings.url);
	};
}( window.notificationsPoller = window.notificationsPoller || {}, jQuery ));
