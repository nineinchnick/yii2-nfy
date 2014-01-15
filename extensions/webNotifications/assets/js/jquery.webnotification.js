/*
*   wnf v. 0.1
*   Web Notification Fallback
*   
*/

(function($) {
    
    $.wnf = function(options) {

        var F = 'function',
            S = "string";

        // returns the complete markup to generate the notification
        var getNotification = function() {

            var id = '',
                n = _self.settings.notification;

            // check if the notification has a tag
            if (!!n.tag && typeof n.tag === S) {

                id = 'wn-' + n.tag;

            }

            return '<div id="' + id + '" class="wn-box"><div class="wn-head"><span class="wn-host">' + window.location.host + '</span><span class="wn-close"></span></div>' + getBody() + '</div>';

        }

        // returns the markup for the body of the notification
        var getBody = function(extra) {

            var img = '',
                n = _self.settings.notification;

            // check if the notification has an icon
            if (!!n.icon && typeof n.icon === S) {
                                
                img = '<img src="' + n.icon + '" class="wn-icon" />';

            }

            return '<div class="wn-body ' + extra + '">' + img + '<div class="wn-message ' + n.dir + '"><h5>' + n.ntitle + '</h5><p>' + n.nbody + '</p></div><div class="clear"></div></div>';

        }

        // attach event listener: click on the notification
        var bindOnClickFn = function($b, fn) {

            if (typeof fn === F) {
                        
                $b.find('.wn-body').last().click(function() { 

                    fn();

                });

            }

        }

        // remove the notification
        var removeNotification = function($notification, $sep) {

            if (typeof $sep != 'undefined') {
                
                $sep.animate({ opacity: 0 }, 750, function() {
                    
                    $sep.remove();
                
                });

            }

            $notification.animate({ opacity: 0 }, 750, function() {

                $notification.remove();

                // execute onCloseFn callback
                if (typeof _self.settings.onCloseFn === F) {
                    
                    _self.settings.onCloseFn();

                }

            });

        }


        var _self = this,
            defaults = {
                position: 'bottom right',
                autoclose: false,
                expire: 0,
                notification: {
                    ntitle: '',
                    nbody: '', 
                    icon: '',
                    tag: '',
                    dir: 'rtl'
                },
                onShowFn: $.noop,
                onClickFn: $.noop,
                onCloseFn: $.noop
            };

        _self.settings = {};

        (function() {

            _self.settings = $.extend({}, defaults, options);

            var s = _self.settings,
                n = s.notification,
                p = s.position,
                newNotification,
                $notContainer,
                $notBox;


            if (!n.ntitle || !n.nbody) {
                
                // throw an exception when required parameters are missing
                throw 'Title, and message of the notification are required parameters.';

            }

            // $notContainer is the fixed box that contains all the notification with a specific position
            $notContainer = $('#wn-' + p.replace(/ /g, ''));
            
            // $notBox is the box that contains the notification with a specific tag
            $notBox = $('#wn-' + n.tag);


            if ($notContainer.length === 0) {

                // create container for notification
                $notContainer = $('<div id="wn-' + p.replace(/ /g, '') + '" class="wn-container ' + p + '"></div>').appendTo('body');

            }
            
            /* two cases:
                a) the notification does not have any tag, or does not still exist another notification with its same tag.
                b) already exists a notification with the same tag. */
            
            if (!(!!_self.settings.notification.tag && typeof _self.settings.notification.tag === S) || $notBox.length === 0) {
                /* (A) */

                // create notification, and append it to the DOM, but with display: none
                newNotification = getNotification();
                $notContainer.append(newNotification);

                // display the notification
                $notContainer.find('.wn-box').last().animate({ opacity: 1 }, 750, function() {

                    var $box = $(this);

                    // attach event listener: click on close notification
                    $box.find('.wn-head .wn-close').click(function() {

                        removeNotification($box);

                    });

                    // attach click on the notification
                    bindOnClickFn($box, s.onClickFn);

                    // execute onShowFn callback
                    if (typeof s.onShowFn === F) {
                        
                        s.onShowFn();

                    }

                    // set autoclose
                    if (s.autoclose) {

                        setTimeout(function() {

                            if ($box.find('table.wn-sep').length == 0) {
                            
                                removeNotification($box);
                            
                            }
                            else {
                                
                                var $firstNotification = $box.find('.wn-body').first();
                                removeNotification($firstNotification, $firstNotification.next('table.wn-sep'));
                            
                            }
                            
                        }, s.expire);

                    }

                });

            }
            else {
                /* (B) */

                // create the new notification, and insert it after the notification with its same tag, but with display: none
                newNotification = '<table class="wn-sep"><tr><td><hr/></td><td class="circle"><span class="wn-close"></span></td><td><hr/></td></tr></table>' + getBody('hidden');
                $notBox.append(newNotification);

                // display the notification
                $notBox.find('table.wn-sep').last().animate({ opacity: 1 }, 750);
                $notBox.find('.wn-body').last().animate({ opacity: 1 }, 750, function() {

                    // shortcut selector for the trigger element of closing of the last notification
                    var $remover = $notBox.find('.wn-close').last();

                    // attach event listener: click on close notification                
                    $remover.click(function() {

                        var $sep = $(this).parents('table.wn-sep');
                        removeNotification($sep.next('.wn-body'), $sep);

                    });

                    // attach click on the notification
                    bindOnClickFn($notBox, s.onClickFn);

                    // execute onShowFn callback
                    if (typeof s.onShowFn === F) {

                        s.onShowFn();

                    }

                    // set autoclose
                    if (s.autoclose) {

                        setTimeout(function() {

                            removeNotification($remover.parents('table.wn-sep').next('.wn-body'), $remover.parents('table.wn-sep'));

                        }, s.expire);

                    }

                });

            }

        }());

    }

})(jQuery);