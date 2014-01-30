<?php

namespace nineinchnick\nfy\models;

/**
 * Collection of scopes for the DbSubscription AR model.
 */
class DbSubscriptionQuery extends \yii\db\ActiveQuery
{
	public static function current()
	{
		$modelClass = $this->modelClass;
		$this->andWhere($modelClass::tableName().'.is_deleted = 0');
	}

	/**
	 * @param string $queue_id
	 */
	public static function withQueue($queue_id)
	{
		$modelClass = $this->modelClass;
        $this->andWhere($modelClass::tableName().'.queue_id=:queue_id', [':queue_id'=>$queue_id]);
	}

	/**
	 * @param string $subscriber_id
	 */
	public static function withSubscriber($subscriber_id)
	{
		$modelClass = $this->modelClass;
        $this->andWhere($modelClass::tableName().'.subscriber_id=:subscriber_id', [':subscriber_id'=>$subscriber_id]);
	}

	/**
	 * @param array|string $categories
	 */
	public static function matchingCategory($categories)
	{
        if ($categories===null)
            return $this;
		$modelClass = $this->modelClass;
        $t = $modelClass::tableName();
		$r = DbSubscriptionCategory::tableName();

        if (!is_array($categories))
            $categories = [$categories];

		$this->innerJoinWith('categories');

        $i = 0;
        foreach($categories as $category) {
			$this->andWhere("($r.is_exception = 0 AND :category$i LIKE $r.category) OR ($r.is_exception = 1 AND :category$i NOT LIKE $r.category)", [':category'.$i++ => $category]);
        }
	}
}
