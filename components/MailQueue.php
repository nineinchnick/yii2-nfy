<?php

namespace nineinchnick\nfy\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\di\Instance;
use yii\mail\MailerInterface;
use yii\mail\MessageInterface;

/**
 * Sends messages via email. Recipients are determined using subscribers id.
 * Subscriptions are tracked using a different queue.
 */
class MailQueue extends Queue
{
    /** @var QueueInterface queue used to track subscriptions */
    public $subscriptionQueue;
    /** @var MailerInterface mailer through which messages are sent and which credentials are used to check email */
    public $mailer = 'mailer';
    /** @var callable a callable to fetch recipients email using a subscriber id */
    public $recipientCallback;
    /** @var callable a callable to compose a new email using the message body */
    public $composeCallback;
    /** @var bool set to true to also send the message to the subscription queue */
    public $sendToSubscriptionQueue = false;

    /**
     * @inheritdoc
     * @throws NotSupportedException
     */
    public function init()
    {
        parent::init();
        if ($this->blocking) {
            throw new NotSupportedException(Yii::t('app', 'MailQueue does not support blocking.'));
        }
        $this->subscriptionQueue = Instance::ensure($this->subscriptionQueue, 'nineinchnick\nfy\components\QueueInterface');
        $this->mailer = Instance::ensure($this->mailer, 'yii\mail\MailerInterface');
        if (!is_callable($this->recipientCallback)) {
            throw new InvalidConfigException(Yii::t('app', 'MailQueue requires a valid callback for recipientCallback.'));
        }
        if ($this->composeCallback === null) {
            $this->composeCallback = [$this, 'createMessage'];
        }
    }

    /**
     * Creates an instance of a Message.
     * This method may be overriden in extending classes.
     * @param  string    $body message body
     * @return MessageInterface
     */
    protected function createMessage($body)
    {
        return $this->mailer->compose('nfy/message', ['message' => $body])
            ->setSubject(Yii::t('app', 'Notification from {app}', ['app' => Yii::$app->name]));
    }

    /**
     * @inheritdoc
     */
    public function send($message, $category = null)
    {
        if ($this->beforeSend($message) !== true) {
            Yii::info(Yii::t('app', "Not sending message '{msg}' to queue {queue_label}.", [
                '{msg}' => $message,
                '{queue_label}' => $this->label,
            ]), 'nfy');

            return false;
        }

        $success = true;

        if ($this->sendToSubscriptionQueue) {
            $this->subscriptionQueue->send($message, $category);
        }

        $mailMessage = call_user_func($this->composeCallback, $message);

        foreach ($this->getSubscriptions() as $subscription) {
            if ($this->beforeSendSubscription($message, $subscription->subscriber_id) !== true) {
                continue;
            }

            if ($category !== null && !$subscription->matchCategory($category)) {
                continue;
            }

            if (!$mailMessage->setTo(call_user_func($this->recipientCallback, $subscription->subscriber_id))->send()) {
                Yii::error(Yii::t('app', "Failed to save message '{msg}' in queue {queue_label} for the subscription {subscription_id}.", [
                    '{msg}'             => $message,
                    '{queue_label}'     => $this->label,
                    '{subscription_id}' => $subscription->subscriber_id,
                ]), 'nfy');
                $success = false;
            }

            $this->afterSendSubscription($message, $subscription->subscriber_id);
        }

        $this->afterSend($message);

        Yii::info(Yii::t('app', "Sent message '{msg}' to queue {queue_label}.", [
            '{msg}' => $message,
            '{queue_label}' => $this->label,
        ]), 'nfy');

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function peek($subscriber_id = null, $limit = -1, $status = Message::AVAILABLE)
    {
        throw new NotSupportedException(Yii::t('app', 'MailQueue does not support peeking.'));
    }

    /**
     * @inheritdoc
     */
    public function reserve($subscriber_id = null, $limit = -1)
    {
        throw new NotSupportedException(Yii::t('app', 'MailQueue does not support reserving.'));
    }

    /**
     * @inheritdoc
     */
    public function receive($subscriber_id = null, $limit = -1)
    {
        throw new NotSupportedException(Yii::t('app', 'MailQueue does not support receiving.'));
    }

    /**
     * @inheritdoc
     */
    public function delete($message_id, $subscriber_id = null)
    {
        throw new NotSupportedException(Yii::t('app', 'MailQueue does not support deleting.'));
    }

    /**
     * @inheritdoc
     */
    public function release($message_id, $subscriber_id = null)
    {
        throw new NotSupportedException(Yii::t('app', 'MailQueue does not support releasing.'));
    }

    /**
     * @inheritdoc
     */
    public function releaseTimedout()
    {
        throw new NotSupportedException(Yii::t('app', 'MailQueue does not support releasing.'));
    }

    /**
     * @inheritdoc
     */
    public function subscribe($subscriber_id, $label = null, $categories = null, $exceptions = null)
    {
        return $this->subscriptionQueue->subscribe($subscriber_id, $label, $categories, $exceptions);
    }

    /**
     * @inheritdoc
     */
    public function unsubscribe($subscriber_id, $categories = null)
    {
        return $this->subscriptionQueue->unsubscribe($subscriber_id, $categories);
    }

    /**
     * @inheritdoc
     */
    public function isSubscribed($subscriber_id, $category = null)
    {
        return $this->isSubscribed($subscriber_id, $category);
    }

    /**
     * @inheritdoc
     */
    public function getSubscriptions($subscriber_id = null)
    {
        return $this->subscriptionQueue->getSubscriptions($subscriber_id);
    }
}
