<?php

namespace nineinchnick\nfy\models;

/**
 * This is the model class for table "{{nfy_subscriptions}}".
 *
 * @property integer $id
 * @property integer $queue_id
 * @property string $label
 * @property integer $subscriber_id
 * @property string $created_on
 * @property boolean $is_deleted
 *
 * The followings are the available model relations:
 * @property DbMessage[] $messages
 * @property Users $subscriber
 * @property DbSubscriptionCategory[] $categories
 */
class DbSubscription extends \yii\db\ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName()
	{
		return '{{%nfy_subscriptions}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['queue_id', 'subscriber_id'], 'required', 'except'=>'search'],
			['subscriber_id', 'number', 'integerOnly'=>true],
			['is_deleted', 'boolean'],
			['label', 'safe'],
		];
	}

	public function getMessages()
	{
		return $this->hasMany(DbMessage::className(), ['id' => 'subscription_id']);
	}

	public function getSubscriber()
	{
		return $this->hasOne(Yii::$app->getModule('nfy')->userClass, ['subscriber_id' => 'id']);
	}

	public function getCategories()
	{
		return $this->hasMany(DbSubscriptionCategory::className(), ['subscription_id' => 'id']);
	}

	public function getMessagesCount()
	{
		return 0; //! @todo implement
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return [
			'id' => Yii::t('models', 'ID'),
			'queue_id' => Yii::t('models', 'Queue ID'),
			'label' => Yii::t('models', 'Label'),
			'subscriber_id' => Yii::t('models', 'Subscriber ID'),
			'created_on' => Yii::t('models', 'Created On'),
			'is_deleted' => Yii::t('models', 'Is Deleted'),
		];
	}

	public function beforeSave($insert) {
		if ($insert && $this->created_on === null) {
			$now = new DateTime('now', new DateTimezone('UTC'));
			$this->created_on = $now->format('Y-m-d H:i:s');
		}
		return parent::beforeSave($insert);
	}

	/**
	 * @param ActiveQuery $query
	 */
	public static function current($query)
	{
		$modelClass = $query->modelClass;
		$query->andWhere($modelClass::tableName().'.is_deleted = 0');
	}

	/**
	 * @param ActiveQuery $query
	 * @param string $queue_id
	 */
	public static function withQueue($query, $queue_id)
	{
		$modelClass = $query->modelClass;
        $query->andWhere($modelClass::tableName().'.queue_id=:queue_id', [':queue_id'=>$queue_id]);
	}

	/**
	 * @param ActiveQuery $query
	 * @param string $subscriber_id
	 */
	public static function withSubscriber($query, $subscriber_id)
	{
		$modelClass = $query->modelClass;
        $query->andWhere($modelClass::tableName().'.subscriber_id=:subscriber_id', [':subscriber_id'=>$subscriber_id]);
	}

	/**
	 * @param ActiveQuery $query
	 * @param array|string $categories
	 */
	public static function matchingCategory($query, $categories)
	{
        if ($categories===null)
            return $this;
		$modelClass = $query->modelClass;
        $t = $modelClass::tableName();
		$r = DbSubscriptionCategory::tableName();

        if (!is_array($categories))
            $categories = [$categories];

		$query->innerJoinWith('categories');

        $i = 0;
        foreach($categories as $category) {
			$query->andWhere("($r.is_exception = 0 AND :category$i LIKE $r.category) OR ($r.is_exception = 1 AND :category$i NOT LIKE $r.category)", [':category'.$i++ => $category]);
        }
	}

	public static function createSubscriptions($dbSubscriptions)
	{
		if (!is_array($dbSubscriptions)) {
			$dbSubscriptions = [$dbSubscriptions];
		}
		$result = [];
		foreach($dbSubscriptions as $dbSubscription) {
			$attributes = $dbSubscription->getAttributes();
			unset($attributes['id']);
			unset($attributes['queue_id']);
			unset($attributes['is_deleted']);
			$subscription = new Subscription;
			$subscription->setAttributes($attributes);
			foreach($dbSubscription->categories as $category) {
				if ($category->is_exception) {
					$subscription->categories[] = $category->category;
				} else {
					$subscription->exceptions[] = $category->category;
				}
			}
			$result[] = $subscription;
		}
		return $result;
	}
}
