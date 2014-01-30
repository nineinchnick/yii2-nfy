<?php

namespace nineinchnick\nfy\models;

/**
 * Collection of scopes for the DbSubscription AR model.
 */
class DbSubscriptionQuery extends \yii\db\ActiveQuery
{
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
}
