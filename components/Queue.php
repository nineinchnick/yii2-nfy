<?php

namespace nineinchnick\nfy\components;

/**
 * The NfyQueue class acts like the CLogger class. Instead of collecting the messages,
 * it instantly processes them, similar to CLogRouter calling collectLogs on each route on log flush event.
 */
abstract class Queue extends \yii\base\Component implements QueueInterface
{
	/**
	 * @var string $id Id of the queue, required. Should be set to the component id.
	 */
	public $id;
	/**
	 * @var string $label Human readable name of the queue, required.
	 */
	public $label;
	/**
	 * @var integer $timeout Number of seconds after which a reserved message is considered timed out and available again.
	 * If null, reserved messages never time out.
	 */
	public $timeout;
	/**
	 * @var boolean $blocking If true, when fetching messages, waits until a new message is sent if there are none in the queue. Does not determine blocking on sending.
	 */
	public $blocking = false;

	/**
	 * @inheritdoc
	 */
    public function beforeSend($message)
	{
		if($this->hasEventHandler('onBeforeSend'))
		{
			$event=new CModelEvent($this, array('message'=>$message));
			$this->onBeforeSend($event);
			return $event->isValid;
		}
		else
			return true;
	}
	/**
	 * @inheritdoc
	 */
    public function afterSend($message)
	{
		$this->onAfterSend(new CEvent($this, array('message'=>$message)));
	}
	/**
	 * @inheritdoc
	 */
    public function beforeSendSubscription($message, $subscriber_id)
	{
		if($this->hasEventHandler('onBeforeSendSubscription'))
		{
			$event=new CModelEvent($this, array('message'=>$message, 'subscriber_id'=>$subscriber_id));
			$this->onBeforeSendSubscription($event);
			return $event->isValid;
		}
		else
			return true;
	}
	/**
	 * @inheritdoc
	 */
    public function afterSendSubscription($message, $subscriber_id)
	{
		$this->onAfterSendSubscription(new CEvent($this, array('message'=>$message, 'subscriber_id'=>$subscriber_id)));
	}
	/**
	 * This event is raised before the message is sent to the queue.
	 * @param CModelEvent $event the event parameter
	 */
    public function onBeforeSend($event)
	{
		$this->raiseEvent('onBeforeSend',$event);
	}
	/**
	 * This event is raised after the message is sent to the queue.
	 * @param CEvent $event the event parameter
	 */
    public function onAfterSend($event)
	{
		$this->raiseEvent('onAfterSend',$event);
	}
	/**
	 * This event is raised before the message is sent to a subscription.
	 * @param CModelEvent $event the event parameter
	 */
    public function onBeforeSendSubscription($event)
	{
		$this->raiseEvent('onBeforeSendSubscription',$event);
	}
	/**
	 * This event is raised after the message is sent to a subscription.
	 * @param CEvent $event the event parameter
	 */
    public function onAfterSendSubscription($event)
	{
		$this->raiseEvent('onAfterSendSubscription',$event);
	}
}
