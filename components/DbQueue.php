<?php

namespace nineinchnick\nfy\components;

use nineinchnick\nfy\models;

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
			throw new CException(Yii::t('app', 'Not implemented. DbQueue does not support blocking.'));
		}
	}

	/**
	 * Creates an instance of DbMessage model. The passed message body may be modified, @see formatMessage().
	 * This method may be overriden in extending classes.
	 * @param string $body message body
	 * @return DbMessage
	 */
	protected function createMessage($body)
	{
		$message = new models\DbMessage;
		$message->setAttributes(array(
			'queue_id'		=> $this->id,
			'timeout'		=> $this->timeout,
			'sender_id'		=> Yii::app()->hasComponent('user') ? Yii::app()->user->getId() : null,
			'status'		=> components\Message::AVAILABLE,
			'body'			=> $body,
		), false);
		return $this->formatMessage($message);
	}

	/**
	 * Formats the body of a queue message. This method may be overriden in extending classes.
	 * @param DbMessage $message
	 * @return DbMessage $message
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
			Yii::log(Yii::t('app', "Not sending message '{msg}' to queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_INFO, 'nfy');
            return;
        }

		$success = true;

		$subscriptions = models\DbSubscription::model()->current()->withQueue($this->id)->matchingCategory($category)->findAll();
        
        $trx = $queueMessage->getDbConnection()->getCurrentTransaction() !== null ? null : $queueMessage->getDbConnection()->beginTransaction();
        
		// empty($subscriptions) && 
        if (!$queueMessage->save()) {
			Yii::log(Yii::t('app', "Failed to save message '{msg}' in queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_ERROR, 'nfy');
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
				Yii::log(Yii::t('app', "Failed to save message '{msg}' in queue {queue_label} for the subscription {subscription_id}.", array(
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

		Yii::log(Yii::t('app', "Sent message '{msg}' to queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_INFO, 'nfy');

		return $success;
	}

	/**
	 * @inheritdoc
	 */
	public function peek($subscriber_id=null, $limit=-1, $status=components\Message::AVAILABLE)
	{
		$pk = models\DbMessage::model()->tableSchema->primaryKey;
		$messages = models\DbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->withStatus($status, $this->timeout)->findAll(array('index'=>$pk, 'limit'=>$limit));
		return models\DbMessage::createMessages($messages);
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
		$pk = models\DbMessage::model()->tableSchema->primaryKey;
		$trx = models\DbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : models\DbMessage::model()->getDbConnection()->beginTransaction();
		$messages = models\DbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->available($this->timeout)->findAll(array('index'=>$pk, 'limit'=>$limit));
		if (!empty($messages)) {
			$now = new DateTime('now', new DateTimezone('UTC'));
			if ($mode === self::GET_DELETE) {
				$attributes = array('status'=>components\Message::DELETED, 'deleted_on'=>$now->format('Y-m-d H:i:s'));
			} elseif ($mode === self::GET_RESERVE) {
				$attributes = array('status'=>components\Message::RESERVED, 'reserved_on'=>$now->format('Y-m-d H:i:s'));
			}
			models\DbMessage::model()->updateByPk(array_keys($messages), $attributes);
		}
		if ($trx !== null) {
			$trx->commit();
		}
		return models\DbMessage::createMessages($messages);
	}

	/**
	 * @inheritdoc
	 */
	public function delete($message_id, $subscriber_id=null)
	{
        $trx = models\DbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : models\DbMessage::model()->getDbConnection()->beginTransaction();
		$pk = models\DbMessage::model()->tableSchema->primaryKey;
		$messages = models\DbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->reserved($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		$now = new DateTime('now', new DateTimezone('UTC'));
		models\DbMessage::model()->updateByPk($message_ids, array('status'=>components\Message::DELETED, 'deleted_on'=>$now->format('Y-m-d H:i:s')));
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
        $trx = models\DbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : models\DbMessage::model()->getDbConnection()->beginTransaction();
		$pk = models\DbMessage::model()->tableSchema->primaryKey;
		$messages = models\DbMessage::model()->withQueue($this->id)->withSubscriber($subscriber_id)->reserved($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		models\DbMessage::model()->updateByPk($message_ids, array('status'=>components\Message::AVAILABLE));
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
        $trx = models\DbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : models\DbMessage::model()->getDbConnection()->beginTransaction();
		$pk = models\DbMessage::model()->tableSchema->primaryKey;
		$messages = models\DbMessage::model()->withQueue($this->id)->timedout($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		models\DbMessage::model()->updateByPk($message_ids, array('status'=>components\Message::AVAILABLE));
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
		$trx = models\DbSubscription::model()->getDbConnection()->getCurrentTransaction() !== null ? null : models\DbSubscription::model()->getDbConnection()->beginTransaction();
        $subscription = models\DbSubscription::model()->withQueue($this->id)->withSubscriber($subscriber_id)->find();
		if ($subscription === null) {
			$subscription = new models\DbSubscription;
			$subscription->setAttributes(array(
				'queue_id' => $this->id,
				'subscriber_id' => $subscriber_id,
				'label' => $label,
			));
		} else {
			$subscription->is_deleted = 0;
			models\DbSubscriptionCategory::model()->deleteAllByAttributes(array('subscription_id'=>$subscription->primaryKey));
		}
		if (!$subscription->save())
			throw new CException(Yii::t('app', 'Failed to subscribe {subscriber_id} to {queue_label}', array('{subscriber_id}'=>$subscriber_id, '{queue_label}'=>$this->label)));
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
			$subscriptionCategory = new models\DbSubscriptionCategory;
			$subscriptionCategory->setAttributes(array(
				'subscription_id'	=> $subscription_id,
				'category'			=> str_replace('*', '%', $category),
				'is_exception'		=> $are_exceptions ? 1 : 0,
			));
			if (!$subscriptionCategory->save())
				throw new CException(Yii::t('app', 'Failed to save category {category} for subscription {subscription_id}', array('{category}'=>$category, '{subscription_id}'=>$subscription_id)));
		}
		return true;
	}

	/**
	 * @inheritdoc
	 * @param boolean @permanent if false, the subscription will only be marked as removed and the messages will remain in the storage; if true, everything is removed permanently
	 */
	public function unsubscribe($subscriber_id, $permanent=true)
	{
		$trx = models\DbSubscription::model()->getDbConnection()->getCurrentTransaction() !== null ? null : models\DbSubscription::model()->getDbConnection()->beginTransaction();
        $subscription = models\DbSubscription::model()->withQueue($this->id)->withSubscriber($subscriber_id)->find();
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
        $subscription = models\DbSubscription::model()->current()->withQueue($this->id)->withSubscriber($subscriber_id)->find();
        return $subscription !== null;
	}

	/**
	 * @param mixed $subscriber_id
	 * @return array|models\DbSubscription
	 */
	public function getSubscriptions($subscriber_id=null)
	{
		models\DbSubscription::model()->current()->withQueue($this->id)->with(array('categories'));
		$dbSubscriptions = $subscriber_id===null ? models\DbSubscription::model()->findAll() : models\DbSubscription::model()->findByAttributes(array('subscriber_id'=>$subscriber_id));
		return models\DbSubscription::createSubscriptions($dbSubscriptions);
	}

	/**
	 * Removes deleted messages from the storage.
	 * @return array of removed message ids
	 */
	public function removeDeleted()
	{
        $trx = models\DbMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : models\DbMessage::model()->getDbConnection()->beginTransaction();
		$pk = models\DbMessage::model()->tableSchema->primaryKey;
		$messages = models\DbMessage::model()->withQueue($this->id)->deleted()->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		models\DbMessage::model()->deleteByPk($message_ids);
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}
}
