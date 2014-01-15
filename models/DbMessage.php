<?php

namespace nineinchnick\usr\models;

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
	public function tableName()
	{
		return '{{%messages}}';
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

	public function getSubscription()
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
			$now = new DateTime('now', new DateTimezone('UTC'));
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

	public function scopes()
	{
        $t = $this->getTableAlias(true);
		return [
			'deleted' => ['condition'=>"$t.status=".Message::DELETED],
		];
	}

	public function available($timeout=null)
	{
		return $this->withStatus(Message::AVAILABLE,$timeout);
	}

	public function reserved($timeout=null)
	{
		return $this->withStatus(Message::RESERVED,$timeout);
	}

	public function timedout($timeout=null)
	{
		if ($timeout === null) {
			$this->getDbCriteria()->mergeWith(['condition'=>'1=0']);
			return $this;
		}
		$now = new DateTime("-$timeout seconds", new DateTimezone('UTC'));
        $t = $this->getTableAlias(true);
		$criteria = [
			'condition' => "($t.status=".Message::RESERVED." AND $t.reserved_on <= :timeout)",
			'params' => [':timeout'=>$now->format('Y-m-d H:i:s')],
		];
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
	}

	public function withStatus($statuses, $timeout=null)
	{
		if (!is_array($statuses))
			$statuses = [$statuses];
        $t = $this->getTableAlias(true);
		$now = new DateTime("-$timeout seconds", new DateTimezone('UTC'));
		$criteria = new CDbCriteria;
		$conditions = [];
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
							$criteria->params = [':timeout'=>$now->format('Y-m-d H:i:s')];
						}
						break;
					case Message::RESERVED:
						if ($timeout !== null) {
							$conditions[] = "($t.status=$status AND $t.reserved_on > :timeout)";
							$criteria->params = [':timeout'=>$now->format('Y-m-d H:i:s')];
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
		if (!empty($conditions)) {
			$criteria->addCondition('('.implode(') OR (', $conditions).')', 'OR');
			$this->getDbCriteria()->mergeWith($criteria);
		}
        return $this;
	}

	public function withQueue($queue_id)
	{
        $t = $this->getTableAlias(true);
		$pk = $this->tableSchema->primaryKey;
        $this->getDbCriteria()->mergeWith([
            'condition' => $t.'.queue_id=:queue_id',
			'params' => [':queue_id'=>$queue_id],
			'order' => "$t.$pk ASC",
        ]);
        return $this;
	}

	public function withSubscriber($subscriber_id=null)
	{
		if ($subscriber_id === null) {
			$t = $this->getTableAlias(true);
			$criteria = ['condition'=>"$t.subscription_id IS NULL"];
		} else {
			$schema = $this->getDbConnection()->getSchema();
			$criteria = [
				'together' => true,
				'with' => ['subscription' => [
					'condition' => $schema->quoteSimpleTableName('subscription').'.subscriber_id=:subscriber_id',
					'params' => [':subscriber_id'=>$subscriber_id],
				]],
			];
		}
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
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
