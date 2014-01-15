<?php

class MessagesWidget extends CWidget
{
	/**
	 * @var array Keys must be queue component names and values must be arrays of NfyMessage objects.
	 */
    public $messages = array();

	public $whiteIcons = false;

	protected function countMessages()
	{
		$count = 0;
		foreach($this->messages as $queueName => $messages) {
			$count += count($messages);
		}
		return $count;
	}
    
	public function createMenuItem()
	{
		$count = $this->countMessages();
		return array(
			'url' => '/nfy/queue',
			'label' => ($count > 0 ? ('<span class="label label-warning">' . $count . '</span>') : ''),
			'visible' => !Yii::app()->user->isGuest,
			'icon' => ($this->whiteIcons ? 'white ' : '').'comment',
			'id' => $this->getId(),
		);
	}

	public function run()
	{
		$elements = '';
		
		$cnt = 0;
		$extraCss = '';

		Yii::import('nfy.controllers.QueueController');
		if ($this->owner instanceof QueueController) {
			$queueController = $this->owner;
		} else {
			$queueController = new QueueController('queue', Yii::app()->getModule('nfy'));
		}
		
		foreach($this->messages as $queueName => $messages) {
			foreach($messages as $message) {
				$text = addcslashes($message->body, "'\r\n");
				$detailsUrl = $queueController->createMessageUrl($queueName, $message);
				
				$extraCss = (++$cnt % 2) === 0 ? 'even' : 'odd';
				$elements .= "<div class=\"messagePopoverItem {$extraCss}\" onclick=\"window.location=\\'{$detailsUrl}\\'; return false;\">{$text}</div>";
			}
		}
		
		$label = Yii::t('NfyModule.app', 'Mark all as read');
		//! @todo fix this
		$deleteUrl = $this->owner->createUrl('/nfy/message/mark');
		$widgetId = $this->getId();
		
		$js = <<<JavaScript
$('#{$widgetId}').popover({
    html: true,
    trigger: 'manual',
    placement: 'bottom',
    selector: '[rel=popover]',
    title: 'Wiadomości',
    content: function() { 
        var ret = '<div class="messagePopoverContainer">{$elements}</div>';
        ret += '<div class="messagePopoverMarkAll"><a href="$deleteUrl">{$label}</a></div>';
        return ret;
    }
});

$('body').click(function(e) {
    var obj = $('div.popover');
    if(obj !== null && obj.length > 0 && (obj.is(':visible') || !obj.is(':hidden'))) {
        $('#{$widgetId}').popover('hide');
    }
});

$('#{$widgetId}').hover(function(e) {
    var obj = $('div.popover');
    if(obj === null || obj.length <= 0 || !obj.is(':visible') || obj.is(':hidden')) {
        obj = $('div.nav-collapse li.dropdown.open');
        if(obj === null || obj.length <= 0) {
            $(this).popover('show');
        }
    }
});

JavaScript;

        $css = <<<CSS
div.messagePopoverItem {
    cursor: pointer;
    font-size: 10px;
    padding: 2px 10px 2px 10px;
    word-wrap: break-word;
}

div.messagePopoverItem.even {
    background: rgb(250, 250, 250);
}

div.messagePopoverItem:hover {
    background: rgb(238, 238, 238);
}
                    
div.messagePopoverItem p {
    padding: 2px;
    margin: 0;
}

div.messagePopoverContainer {
    overflow-y: auto;
    max-height: 400px;
}

div.messagePopoverMarkAll {
    text-align: center;
    background: rgb(247, 247, 247);
    border-top: 1px solid rgb(235, 235, 235);
    padding-top: 3px;
    padding-bottom: 3px;
}

div.messagePopoverMarkAll a {
    font-size: 10px;
    text-decoration: none;
}

.popover {
    color: #444444;
    background: white;
    text-shadow: none;
    padding: 0;
    margin: 0;
    min-width: 250px;
    max-width: 400px;
}

.popover * {
    text-shadow: none;
    text-align: center;
}

.popover-content {
    padding: 0;
}

.popover-content * {
    text-align: left;
}
CSS;
		Yii::app()->clientScript->registerCss(__CLASS__.'#popup', $css);
		Yii::app()->clientScript->registerScript(__CLASS__.'#popup', $js, CClientScript::POS_READY);
    }
}
