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
		return '{{%subscriptions}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['queue_id', 'subscriber_id'], 'required', 'except'=>'search'],
			['subscriber_id', 'numerical', 'integerOnly'=>true],
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
		return $this->hasMany(DbSubscriptionCategory::className(), ['id' => 'subscription_id']);
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

	public function scopes()
	{
        $t = $this->getTableAlias(true);
		return [
			'current' => ['condition' => "$t.is_deleted = 0"],
		];
	}

	public function withQueue($queue_id)
	{
        $t = $this->getTableAlias(true);
        $this->getDbCriteria()->mergeWith([
            'condition' => $t.'.queue_id=:queue_id',
			'params' => [':queue_id'=>$queue_id],
        ]);
        return $this;
	}

	public function withSubscriber($subscriber_id)
	{
        $t = $this->getTableAlias(true);
        $this->getDbCriteria()->mergeWith([
            'condition' => $t.'.subscriber_id=:subscriber_id',
			'params' => [':subscriber_id'=>$subscriber_id],
        ]);
        return $this;
	}

	public function matchingCategory($categories)
	{
        if ($categories===null)
            return $this;
        $t = $this->getTableAlias(true);
		$r = $this->dbConnection->schema->quoteTableName('categories');

        if (!is_array($categories))
            $categories = [$categories];

        $criteria = new CDbCriteria;
		$criteria->with = ['categories'=>[
			'together'=>true,
			'select'=>null,
			'distinct'=>true,
		]];

        $i = 0;
        foreach($categories as $category) {
			$criteria->addCondition("($r.is_exception = 0 AND :category$i LIKE $r.category) OR ($r.is_exception = 1 AND :category$i NOT LIKE $r.category)");
			$criteria->params[':category'.$i++] = $category;
        }
        
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
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
