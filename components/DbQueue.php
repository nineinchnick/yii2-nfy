<?php

namespace nineinchnick\nfy\components;

/**
 * Saves sent messages and tracks subscriptions in a database.
 */
class DbQueue extends Queue
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		if ($this->blocking) {
			throw new CException(Yii::t('NfyModule.app', 'Not implemented. DbQueue does not support blocking.'));
		}
	}

	/**
	 * Creates an instance of NfyDbMessage model. The passed message body may be modified, @see formatMessage().
	 * This method may be overriden in extending classes.
	 * @param string $body message body
	 * @return NfyDbMessage
	 */
	protected function createMessage($body)
	{
		$message = new NfyDbMessage;
		$message->setAttributes(array(
			'queue_id'		=> $this->id,
			'timeout'		=> $this->timeout,
			'sender_id'		=> Yii::app()->hasComponent('user') ? Yii::app()->user->getId() : null,
			'status'		=> NfyMessage::AVAILABLE,
			'body'			=> $body,
		), false);
		return $this->formatMessage($message);
	}

	/**
	 * Formats the body of a queue message. This method may be overriden in extending classes.
	 * @param NfyDbMessage $message
	 * @return NfyDbMessage $message
	 */
	protected function formatMessage($message)
	{
		return $message;
	}

	/**
	 * @inheritdoc
	 */
	public function send($message, $category=null) {
		$queueMessage = $this->createMessage($message);

        if ($this->beforeSend($queueMessage) !== true) {
			Yii::log(Yii::t('NfyModule.app', "Not sending message '{msg}' to queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_INFO, 'nfy');
            return;
        }

		$success = true;

		$subscriptions = NfyDbSubscription::model()->current()->withQueue($this->id)->matchingCategory($category)->findAll();
        
        $trx = $queueMessage->getDbConnection()->getCurrentTransaction() !== null ? null : $queueMessage->getDbConnection()->beginTransaction();
        
		// empty($subscriptions) && 
        if (!$queueMessage->save()) {
			Yii::log(Yii::t('NfyModule.app', "Failed to save message '{msg}' in queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_ERROR, 'nfy');
            return false;
        }

		foreach($subscriptions as $subscription) {
			$subscriptionMessage = clone $queueMessage;
			$subscriptionMessage->subscription_id = $subscription->id;
			$subscriptionMessage->message_id = $queueMessage->id;
            if ($this->beforeSendSubscription($subscriptionMessage, $subscription->subscriber_id) !== true) {
                continue;
            }

			if (!$subscriptionMessage->save()) {
				Yii::log(Yii::t('NfyModule.app', "Failed to save message '{msg}' in queue {queue_label} for the subscription {subscription_id}.", array(
					'{msg}' => $queueMessage->body,
					'{queue_label}' => $this->label,
					'{subscription_id}' => $subscription->id,
				)), CLogger::LEVEL_ERROR, 'nfy');
				$success = false;
			}
            
            $this->afterSendSubscription($subscriptionMessage, $subscription->subscriber_id);
		}

        $this->afterSend($queueMessage);

		if ($trx !== null) {
			$trx->commit();
		}

		Yii::log(Yii::t('NfyModule.app', "Sent message '{msg}' to queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_INFO, 'nfy');

		return $success;
	}

	/**
	 * @inheritdoc
	 */
	public function peek($subscriber_id=null, $limit=-1, $status=NfyMessage::AVAILABLE)
	{
		$pk = NfyDbMessage::model()->tableSchema->primaryKey;
		$messages = NfyDbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->withStatus($status, $this->timeout)->findAll(array('index'=>$pk, 'limit'=>$limit));
		return NfyDbMessage::createMessages($messages);
	}

	/**
	 * @inheritdoc
	 */
	public function reserve($subscriber_id=null, $limit=-1)
	{
		return $this->receiveInternal($subscriber_id, $limit, self::GET_RESERVE);
	}

	/**
	 * @inheritdoc
	 */
	public function receive($subscriber_id=null, $limit=-1)
	{
		return $this->receiveInternal($subscriber_id, $limit, self::GET_DELETE);
	}

	/**
	 * Perform message extraction.
	 */
	protected function receiveInternal($subscriber_id=null, $limit=-1, $mode=self::GET_RESERVE)
	{
		$pk = NfyDbMessage::model()->tableSchema->primaryKey;
		$trx = NfyDbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyDbMessage::model()->getDbConnection()->beginTransaction();
		$messages = NfyDbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->available($this->timeout)->findAll(array('index'=>$pk, 'limit'=>$limit));
		if (!empty($messages)) {
			$now = new DateTime('now', new DateTimezone('UTC'));
			if ($mode === self::GET_DELETE) {
				$attributes = array('status'=>NfyMessage::DELETED, 'deleted_on'=>$now->format('Y-m-d H:i:s'));
			} elseif ($mode === self::GET_RESERVE) {
				$attributes = array('status'=>NfyMessage::RESERVED, 'reserved_on'=>$now->format('Y-m-d H:i:s'));
			}
			NfyDbMessage::model()->updateByPk(array_keys($messages), $attributes);
		}
		if ($trx !== null) {
			$trx->commit();
		}
		return NfyDbMessage::createMessages($messages);
	}

	/**
	 * @inheritdoc
	 */
	public function delete($message_id, $subscriber_id=null)
	{
        $trx = NfyDbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyDbMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyDbMessage::model()->tableSchema->primaryKey;
		$messages = NfyDbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->reserved($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		$now = new DateTime('now', new DateTimezone('UTC'));
		NfyDbMessage::model()->updateByPk($message_ids, array('status'=>NfyMessage::DELETED, 'deleted_on'=>$now->format('Y-m-d H:i:s')));
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}

	/**
	 * @inheritdoc
	 */
	public function release($message_id, $subscriber_id=null)
	{
        $trx = NfyDbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyDbMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyDbMessage::model()->tableSchema->primaryKey;
		$messages = NfyDbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->reserved($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		NfyDbMessage::model()->updateByPk($message_ids, array('status'=>NfyMessage::AVAILABLE));
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}

	/**
	 * Releases timed-out messages.
	 * @return array of released message ids
	 */
	public function releaseTimedout()
	{
        $trx = NfyDbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyDbMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyDbMessage::model()->tableSchema->primaryKey;
		$messages = NfyDbMessage::model()->withQueue($this->id)->timedout($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		NfyDbMessage::model()->updateByPk($message_ids, array('status'=>NfyMessage::AVAILABLE));
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}

	/**
	 * @inheritdoc
	 */
	public function subscribe($subscriber_id, $label=null, $categories=null, $exceptions=null)
	{
		$trx = NfyDbSubscription::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyDbSubscription::model()->getDbConnection()->beginTransaction();
        $subscription = NfyDbSubscription::model()->withQueue($this->id)->withSubscriber($subscriber_id)->find();
		if ($subscription === null) {
			$subscription = new NfyDbSubscription;
			$subscription->setAttributes(array(
				'queue_id' => $this->id,
				'subscriber_id' => $subscriber_id,
				'label' => $label,
			));
		} else {
			$subscription->is_deleted = 0;
			NfyDbSubscriptionCategory::model()->deleteAllByAttributes(array('subscription_id'=>$subscription->primaryKey));
		}
		if (!$subscription->save())
			throw new CException(Yii::t('NfyModule.app', 'Failed to subscribe {subscriber_id} to {queue_label}', array('{subscriber_id}'=>$subscriber_id, '{queue_label}'=>$this->label)));
		$this->saveSubscriptionCategories($categories, $subscription->primaryKey, false);
		$this->saveSubscriptionCategories($exceptions, $subscription->primaryKey, true);
		if ($trx !== null) {
			$trx->commit();
		}
		return true;
	}

	protected function saveSubscriptionCategories($categories, $subscription_id, $are_exceptions=false)
	{
		if ($categories === null)
			return true;
		if (!is_array($categories))
			$categories = array($categories);
		foreach($categories as $category) {
			$subscriptionCategory = new NfyDbSubscriptionCategory;
			$subscriptionCategory->setAttributes(array(
				'subscription_id'	=> $subscription_id,
				'category'			=> str_replace('*', '%', $category),
				'is_exception'		=> $are_exceptions ? 1 : 0,
			));
			if (!$subscriptionCategory->save())
				throw new CException(Yii::t('NfyModule.app', 'Failed to save category {category} for subscription {subscription_id}', array('{category}'=>$category, '{subscription_id}'=>$subscription_id)));
		}
		return true;
	}

	/**
	 * @inheritdoc
	 * @param boolean @permanent if false, the subscription will only be marked as removed and the messages will remain in the storage; if true, everything is removed permanently
	 */
	public function unsubscribe($subscriber_id, $permanent=true)
	{
		$trx = NfyDbSubscription::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyDbSubscription::model()->getDbConnection()->beginTransaction();
        $subscription = NfyDbSubscription::model()->withQueue($this->id)->withSubscriber($subscriber_id)->find();
		if ($subscription !== null) {
			if ($permanent)
				$subscription->delete();
			else
				$subscription->saveAttributes(array('is_deleted'=>1));
		}
		if ($trx !== null) {
			$trx->commit();
		}
	}

	/**
	 * @inheritdoc
	 */
	public function isSubscribed($subscriber_id)
	{
        $subscription = NfyDbSubscription::model()->current()->withQueue($this->id)->withSubscriber($subscriber_id)->find();
        return $subscription !== null;
	}

	/**
	 * @param mixed $subscriber_id
	 * @return array|NfyDbSubscription
	 */
	public function getSubscriptions($subscriber_id=null)
	{
		NfyDbSubscription::model()->current()->withQueue($this->id)->with(array('categories'));
		$dbSubscriptions = $subscriber_id===null ? NfyDbSubscription::model()->findAll() : NfyDbSubscription::model()->findByAttributes(array('subscriber_id'=>$subscriber_id));
		return NfyDbSubscription::createSubscriptions($dbSubscriptions);
	}

	/**
	 * Removes deleted messages from the storage.
	 * @return array of removed message ids
	 */
	public function removeDeleted()
	{
        $trx = NfyDbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyDbMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyDbMessage::model()->tableSchema->primaryKey;
		$messages = NfyDbMessage::model()->withQueue($this->id)->deleted()->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		NfyDbMessage::model()->deleteByPk($message_ids);
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}
}
