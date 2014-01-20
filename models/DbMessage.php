<?php

namespace nineinchnick\nfy\models;

use nineinchnick\nfy\components\Message;

/**
 * This is the model class for table "{{nfy_messages}}".
 *
 * @property integer $id
 * @property integer $queue_id
 * @property string $created_on
 * @property integer $sender_id
 * @property integer $message_id
 * @property integer $subscription_id
 * @property integer $status
 * @property integer $timeout
 * @property string $reserved_on
 * @property string $deleted_on
 * @property string $mimetype
 * @property string $body
 *
 * The followings are the available model relations:
 * @property DbMessage $mainMessage
 * @property DbMessage[] $subscriptionMessages
 * @property DbSubscription $subscription
 * @property Users $sender
 */
class DbMessage extends \yii\db\ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%nfy_messages}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['queue_id', 'sender_id', 'body'], 'required', 'except'=>'search'],
			[['sender_id', 'subscription_id', 'timeout'], 'numerical', 'integerOnly'=>true],
			[['message_id', 'subscription_id', 'timeout'], 'numerical', 'integerOnly'=>true, 'on'=>'search'],
			['status', 'numerical', 'integerOnly'=>true, 'on'=>'search'],
			['mimetype', 'safe', 'on'=>'search'],
		];
	}

	public function getMainMessage()
	{
		return $this->hasOne(DbMessage::className(), ['message_id' => 'id']);
	}

	public function getSender()
	{
		return $this->hasOne(Yii::$app->getModule('nfy')->userClass, ['sender_id' => 'id']);
	}

	public function getSubscription()
	{
		return $this->hasOne(DbSubscription::className(), ['subscription_id' => 'id']);
	}

	public function getMessages()
	{
		return $this->hasMany(DbMessage::className(), ['id' => 'message_id']);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('models', 'ID'),
			'queue_id' => Yii::t('models', 'Queue ID'),
			'created_on' => Yii::t('models', 'Created On'),
			'sender_id' => Yii::t('models', 'Sender ID'),
			'message_id' => Yii::t('models', 'Message ID'),
			'subscription_id' => Yii::t('models', 'Subscription ID'),
			'status' => Yii::t('models', 'Status'),
			'timeout' => Yii::t('models', 'Timeout'),
			'reserved_on' => Yii::t('models', 'Reserved On'),
			'deleted_on' => Yii::t('models', 'Deleted On'),
			'mimetype' => Yii::t('models', 'MIME Type'),
			'body' => Yii::t('models', 'Message Body'),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function beforeSave($insert) {
		if ($insert && $this->created_on === null) {
			$now = new \DateTime('now', new \DateTimezone('UTC'));
			$this->created_on = $now->format('Y-m-d H:i:s');
		}
		return parent::beforeSave($insert);
	}

	public function __clone()
	{
		$this->primaryKey = null;
		$this->subscription_id = null;
		$this->isNewRecord = true;
	}

	/**
	 * @param ActiveQuery $query
	 */
	public static function deleted($query)
	{
		$modelClass = $query->modelClass;
		$query->andWhere($modelClass::tableName().'.status = '.Message::DELETED);
	}

	/**
	 * @param ActiveQuery $query
	 * @param integer $timeout
	 */
	public static function available($query, $timeout=null)
	{
		self::withStatus($query, Message::AVAILABLE, $timeout);
	}

	/**
	 * @param ActiveQuery $query
	 * @param integer $timeout
	 */
	public static function reserved($query, $timeout=null)
	{
		self::withStatus($query, Message::RESERVED, $timeout);
	}

	/**
	 * @param ActiveQuery $query
	 * @param integer $timeout
	 */
	public static function timedout($query, $timeout=null)
	{
		if ($timeout === null) {
			$query->andWhere('1=0');
			return;
		}
		$now = new \DateTime($timeout === null ? '' : "-$timeout seconds", new \DateTimezone('UTC'));
		$modelClass = $query->modelClass;
        $t = $modelClass::tableName();
		$query->andWhere("($t.status=".Message::RESERVED." AND $t.reserved_on <= :timeout)", [':timeout'=>$now->format('Y-m-d H:i:s')]);
	}

	/**
	 * @param ActiveQuery $query
	 * @param array|string $statuses
	 * @param integer $timeout
	 */
	public static function withStatus($query, $statuses, $timeout=null)
	{
		if (!is_array($statuses))
			$statuses = [$statuses];
		$modelClass = $query->modelClass;
        $t = $modelClass::tableName();
		$now = new \DateTime($timeout === null ? '' : "-$timeout seconds", new \DateTimezone('UTC'));
		$conditions = ['or'];
		// test for two special cases
		if (array_diff($statuses, [Message::AVAILABLE, Message::RESERVED]) === []) {
			// only not deleted
			$conditions[] = "$t.status!=".Message::DELETED;
		} elseif (array_diff($statuses, [Message::AVAILABLE, Message::RESERVED, Message::DELETED]) === []) {
			// pass - don't add no conditions
		} else {
			// merge all statuses
			foreach($statuses as $status) {
				switch($status) {
					case Message::AVAILABLE:
						$conditions[] = "$t.status=".$status;
						if ($timeout !== null) {
							$conditions[] = "($t.status=".Message::RESERVED." AND $t.reserved_on <= :timeout)";
							$query->addParams([':timeout'=>$now->format('Y-m-d H:i:s')]);
						}
						break;
					case Message::RESERVED:
						if ($timeout !== null) {
							$conditions[] = "($t.status=$status AND $t.reserved_on > :timeout)";
							$query->addParams([':timeout'=>$now->format('Y-m-d H:i:s')]);
						} else {
							$conditions[] = "$t.status=".$status;
						}
						break;
					case Message::DELETED:
						$conditions[] = "$t.status=".$status;
						break;
				}
			}
		}
		if ($conditions !== ['or']) {
			$query->where($conditions);
		}
	}

	/**
	 * @param ActiveQuery $query
	 * @param string $queue_id
	 */
	public static function withQueue($query, $queue_id)
	{
		$modelClass = $query->modelClass;
        $t = $modelClass::tableName();
		$pk = $modelClass::primaryKey();
		$query->andWhere($t.'.queue_id=:queue_id', [':queue_id'=>$queue_id]);
		$query->orderBy = ["$t.{$pk[0]}"=>'ASC'];
	}

	/**
	 * @param ActiveQuery $query
	 * @param string $subscriber_id
	 */
	public static function withSubscriber($query, $subscriber_id=null)
	{
		if ($subscriber_id === null) {
			$modelClass = $query->modelClass;
			$t = $modelClass::tableName();
			$query->andWhere("$t.subscription_id IS NULL");
		} else {
			$query->innerJoinWith('subscription');
			$query->andWhere(DbSubscription::tableName().'.subscriber_id=:subscriber_id', [':subscriber_id'=>$subscriber_id]);
		}
	}

	public static function createMessages($dbMessages)
	{
		if (!is_array($dbMessages)) {
			$dbMessages = [$dbMessages];
		}
		$result = [];
		foreach($dbMessages as $dbMessage) {
			$attributes = $dbMessage->getAttributes();
			$attributes['subscriber_id'] = $dbMessage->subscription_id === null ? null : $dbMessage->subscription->subscriber_id;
			unset($attributes['queue_id']);
			unset($attributes['subscription_id']);
			unset($attributes['mimetype']);
			$message = new Message;
			$message->setAttributes($attributes);
			$result[] = $message;
		}
		return $result;
	}
}
