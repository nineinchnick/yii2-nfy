<?php

namespace nineinchnick\nfy\components;

use Yii;
use nineinchnick\nfy\models;
use yii\base\Exception;
use yii\base\NotSupportedException;

/**
 * Saves sent messages and tracks subscriptions in a database.
 */
class DbQueue extends Queue
{
    /**
     * @inheritdoc
     * @throws NotSupportedException
     */
    public function init()
    {
        parent::init();
        if ($this->blocking) {
            throw new NotSupportedException(Yii::t('app', 'DbQueue does not support blocking.'));
        }
    }

    /**
     * Creates an instance of DbMessage model. The passed message body may be modified, @see formatMessage().
     * This method may be overriden in extending classes.
     * @param  string    $body message body
     * @return models\DbMessage
     */
    protected function createMessage($body)
    {
        $message = new models\DbMessage();
        $message->setAttributes([
            'queue_id'  => $this->id,
            'timeout'   => $this->timeout,
            'sender_id' => Yii::$app->has('user') ? Yii::$app->user->getId() : null,
            'status'    => Message::AVAILABLE,
            'body'      => $body,
        ], false);

        return $this->formatMessage($message);
    }

    /**
     * Formats the body of a queue message. This method may be overriden in extending classes.
     * @param  models\DbMessage $message
     * @return models\DbMessage $message
     */
    protected function formatMessage($message)
    {
        return $message;
    }

    /**
     * @param Subscription[] $subscriptions
     * @param models\DbMessage $queueMessage
     * @return bool
     */
    private function sendToSubscriptions($subscriptions, $queueMessage)
    {
        $success = true;
        foreach ($subscriptions as $subscription) {
            $subscriptionMessage = clone $queueMessage;
            $subscriptionMessage->subscription_id = $subscription->id;
            $subscriptionMessage->message_id = $queueMessage->id;
            if ($this->beforeSendSubscription($subscriptionMessage, $subscription->subscriber_id) !== true) {
                continue;
            }

            if (!$subscriptionMessage->save()) {
                Yii::error(Yii::t('app', "Failed to save message '{msg}' in queue {queue_label} for the subscription {subscription_id}.", [
                    'msg'             => $queueMessage->body,
                    'queue_label'     => $this->label,
                    'subscription_id' => $subscription->id,
                ]) . ' ' . print_r($subscriptionMessage->getErrors(), true), 'nfy');
                $success = false;
            }

            $this->afterSendSubscription($subscriptionMessage, $subscription->subscriber_id);
        }
        return $success;
    }

    /**
     * @inheritdoc
     */
    public function send($message, $category = null)
    {
        $queueMessage = $this->createMessage($message);

        if ($this->beforeSend($queueMessage) !== true) {
            Yii::info(Yii::t('app', "Not sending message '{msg}' to queue {queue_label}.", [
                'msg' => $queueMessage->body,
                'queue_label' => $this->label,
            ]), 'nfy');

            return;
        }

        $success = true;

        $subscriptions = models\DbSubscription::find()->current()->withQueue($this->id)->matchingCategory($category)->all();

        $trx = $queueMessage->getDb()->transaction !== null ? null : $queueMessage->getDb()->beginTransaction();

        // empty($subscriptions) &&
        if (!$queueMessage->save()) {
            Yii::error(Yii::t('app', "Failed to save message '{msg}' in queue {queue_label}.", [
                'msg' => $queueMessage->body,
                'queue_label' => $this->label,
            ]) . ' ' . print_r($queueMessage->getErrors(), true), 'nfy');

            return false;
        }

        if (!$this->sendToSubscriptions($subscriptions, $queueMessage)) {
            $success = false;
        }

        $this->afterSend($queueMessage);

        if ($trx !== null) {
            $trx->commit();
        }

        Yii::info(Yii::t('app', "Sent message '{msg}' to queue {queue_label}.", [
            'msg' => $queueMessage->body,
            'queue_label' => $this->label,
        ]), 'nfy');

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function peek($subscriber_id = null, $limit = -1, $status = Message::AVAILABLE)
    {
        $primaryKey = models\DbMessage::primaryKey();
        $messages = models\DbMessage::find()
            ->withQueue($this->id)
            ->withSubscriber($subscriber_id)
            ->withStatus($status, $this->timeout)
            ->limit($limit)
            ->indexBy($primaryKey[0])
            ->all();

        return models\DbMessage::createMessages($messages);
    }

    /**
     * @inheritdoc
     */
    public function reserve($subscriber_id = null, $limit = -1)
    {
        return $this->receiveInternal($subscriber_id, $limit, self::GET_RESERVE);
    }

    /**
     * @inheritdoc
     */
    public function receive($subscriber_id = null, $limit = -1)
    {
        return $this->receiveInternal($subscriber_id, $limit, self::GET_DELETE);
    }

    /**
     * Perform message extraction.
     * @param mixed $subscriber_id
     * @param int $limit
     * @param int $mode one of: self::GET_DELETE, self::GET_RESERVE or self::GET_PEEK
     * @return models\DbMessage[]
     * @throws \yii\db\Exception
     */
    protected function receiveInternal($subscriber_id = null, $limit = -1, $mode = self::GET_RESERVE)
    {
        $primaryKey = models\DbMessage::primaryKey();
        $trx = models\DbMessage::getDb()->transaction !== null ? null : models\DbMessage::getDb()->beginTransaction();
        $messages = models\DbMessage::find()
            ->withQueue($this->id)
            ->withSubscriber($subscriber_id)
            ->available($this->timeout)
            ->limit($limit)
            ->indexBy($primaryKey[0])
            ->all();
        if (!empty($messages)) {
            $now = new \DateTime('now', new \DateTimezone('UTC'));
            if ($mode === self::GET_DELETE) {
                $attributes = ['status' => Message::DELETED, 'deleted_on' => $now->format('Y-m-d H:i:s')];
            } elseif ($mode === self::GET_RESERVE) {
                $attributes = ['status' => Message::RESERVED, 'reserved_on' => $now->format('Y-m-d H:i:s')];
            }
            if (isset($attributes)) {
                models\DbMessage::updateAll($attributes, ['in', models\DbMessage::primaryKey(), array_keys($messages)]);
            }
        }
        if ($trx !== null) {
            $trx->commit();
        }

        return models\DbMessage::createMessages($messages);
    }

    /**
     * @inheritdoc
     */
    public function delete($message_id, $subscriber_id = null)
    {
        $trx = models\DbMessage::getDb()->transaction !== null ? null : models\DbMessage::getDb()->beginTransaction();
        $primaryKey = models\DbMessage::primaryKey();
        $message_ids = models\DbMessage::find()
            ->withQueue($this->id)
            ->withSubscriber($subscriber_id)
            ->select($primaryKey)
            ->andWhere(['in', $primaryKey, $message_id])
            ->column();
        $now = new \DateTime('now', new \DateTimezone('UTC'));
        models\DbMessage::updateAll([
            'status' => Message::DELETED,
            'deleted_on' => $now->format('Y-m-d H:i:s'),
        ], ['in', $primaryKey, $message_ids]);
        if ($trx !== null) {
            $trx->commit();
        }

        return $message_ids;
    }

    /**
     * @inheritdoc
     */
    public function release($message_id, $subscriber_id = null)
    {
        $trx = models\DbMessage::getDb()->transaction !== null ? null : models\DbMessage::getDb()->beginTransaction();
        $primaryKey = models\DbMessage::primaryKey();
        $message_ids = models\DbMessage::find()
            ->withQueue($this->id)
            ->withSubscriber($subscriber_id)
            ->reserved($this->timeout)
            ->select($primaryKey)
            ->andWhere(['in', $primaryKey, $message_id])
            ->column();
        models\DbMessage::updateAll(['status' => Message::AVAILABLE], ['in', $primaryKey, $message_ids]);
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
        $trx = models\DbMessage::getDb()->transaction !== null ? null : models\DbMessage::getDb()->beginTransaction();
        $primaryKey = models\DbMessage::primaryKey();
        $message_ids = models\DbMessage::find(
        )->withQueue($this->id)
        ->timedout($this->timeout)
        ->select($primaryKey)
        ->column();
        models\DbMessage::updateAll(['status' => Message::AVAILABLE], ['in', $primaryKey, $message_ids]);
        if ($trx !== null) {
            $trx->commit();
        }

        return $message_ids;
    }

    /**
     * @inheritdoc
     */
    public function subscribe($subscriber_id, $label = null, $categories = null, $exceptions = null)
    {
        $trx = models\DbSubscription::getDb()->transaction !== null ? null : models\DbSubscription::getDb()->beginTransaction();
        $subscription = models\DbSubscription::find()->withQueue($this->id)->withSubscriber($subscriber_id)->one();
        if ($subscription === null) {
            $subscription = new models\DbSubscription();
            $subscription->setAttributes([
                'queue_id'      => $this->id,
                'subscriber_id' => $subscriber_id,
                'label'         => $label,
            ]);
        } else {
            $subscription->is_deleted = 0;
            models\DbSubscriptionCategory::deleteAll('subscription_id=:subscription_id', [
                ':subscription_id' => $subscription->primaryKey,
            ]);
        }
        if (!$subscription->save()) {
            throw new Exception(Yii::t('app', 'Failed to subscribe {subscriber_id} to {queue_label}', [
                'subscriber_id' => $subscriber_id,
                'queue_label'   => $this->label,
            ]));
        }
        $this->saveSubscriptionCategories($categories, $subscription->primaryKey, false);
        $this->saveSubscriptionCategories($exceptions, $subscription->primaryKey, true);
        if ($trx !== null) {
            $trx->commit();
        }

        return true;
    }

    protected function saveSubscriptionCategories($categories, $subscription_id, $are_exceptions = false)
    {
        if ($categories === null) {
            return true;
        }
        if (!is_array($categories)) {
            $categories = [$categories];
        }
        foreach ($categories as $category) {
            $subscriptionCategory = new models\DbSubscriptionCategory();
            $subscriptionCategory->setAttributes([
                'subscription_id' => $subscription_id,
                'category'        => str_replace('*', '%', $category),
                'is_exception'    => $are_exceptions ? 1 : 0,
            ]);
            if (!$subscriptionCategory->save()) {
                throw new Exception(Yii::t('app', 'Failed to save category {category} for subscription {subscription_id}', [
                    'category' => $category,
                    'subscription_id' => $subscription_id,
                ]));
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     * @param boolean $permanent if false, the subscription will only be marked as removed
     *                           and the messages will remain in the storage;
     *                           if true, everything is removed permanently
     */
    public function unsubscribe($subscriber_id, $categories = null, $permanent = true)
    {
        $trx = models\DbSubscription::getDb()->transaction !== null ? null : models\DbSubscription::getDb()->beginTransaction();
        $subscription = models\DbSubscription::find()
            ->withQueue($this->id)
            ->withSubscriber($subscriber_id)
            ->matchingCategory($categories)
            ->one();
        if ($subscription !== null) {
            $canDelete = true;
            if ($categories !== null) {
                // it may be a case when some (but not all) categories are about to be unsubscribed
                // if that happens and this subscription ends up with some other categories, only given categories
                // should be deleted, not the whole subscription
                $primaryKey = models\DbSubscriptionCategory::primaryKey();
                models\DbSubscriptionCategory::deleteAll([
                    reset($primaryKey) => array_map(function ($c) { return $c->id; }, $subscription->categories)
                ]);
                $canDelete = models\DbSubscriptionCategory::find()->where([
                    'subscription_id' => $subscription->id,
                ])->count() <= 0;
            }

            if ($canDelete) {
                if ($permanent) {
                    $subscription->delete();
                } else {
                    $subscription->is_deleted = 1;
                    $subscription->update(true, ['is_deleted']);
                }
            }
        }
        if ($trx !== null) {
            $trx->commit();
        }
    }

    /**
     * @inheritdoc
     */
    public function isSubscribed($subscriber_id, $category = null)
    {
        $subscription = models\DbSubscription::find()
            ->current()
            ->withQueue($this->id)
            ->withSubscriber($subscriber_id)
            ->matchingCategory($category)
            ->one();

        return $subscription !== null;
    }

    /**
     * @param  mixed                       $subscriber_id
     * @return array|models\DbSubscription
     */
    public function getSubscriptions($subscriber_id = null)
    {
        /** @var $query \yii\db\ActiveQuery */
        $query = models\DbSubscription::find()
            ->current()
            ->withQueue($this->id)
            ->with(['categories']);
        if ($subscriber_id !== null) {
            $dbSubscriptions = $query->andWhere('subscriber_id=:subscriber_id', [':subscriber_id' => $subscriber_id]);
        }
        $dbSubscriptions = $query->all();

        return models\DbSubscription::createSubscriptions($dbSubscriptions);
    }

    /**
     * Removes deleted messages from the storage.
     * @return array of removed message ids
     */
    public function removeDeleted()
    {
        $trx = models\DbMessage::getDb()->transaction !== null ? null : models\DbMessage::getDb()->beginTransaction();
        $primaryKey = models\DbMessage::primaryKey();
        $message_ids = models\DbMessage::find()
            ->withQueue($this->id)
            ->deleted()
            ->select($primaryKey)
            ->column();
        models\DbMessage::deleteAll(['in', $primaryKey, $message_ids]);
        if ($trx !== null) {
            $trx->commit();
        }

        return $message_ids;
    }
}
