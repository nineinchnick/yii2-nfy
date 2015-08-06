<?php

namespace nineinchnick\nfy\models;

use Yii;

/**
 * Collection of scopes for the DbSubscription AR model.
 */
class DbSubscriptionQuery extends \yii\db\ActiveQuery
{
    public function current()
    {
        $modelClass = $this->modelClass;
        $this->andWhere($modelClass::tableName().'.is_deleted = false');

        return $this;
    }

    /**
     * @param  string              $queue_id
     * @return DbSubscriptionQuery $this
     */
    public function withQueue($queue_id)
    {
        $modelClass = $this->modelClass;
        $this->andWhere($modelClass::tableName().'.queue_id=:queue_id', [':queue_id' => $queue_id]);

        return $this;
    }

    /**
     * @param  string              $subscriber_id
     * @return DbSubscriptionQuery $this
     */
    public function withSubscriber($subscriber_id)
    {
        $modelClass = $this->modelClass;
        $this->andWhere($modelClass::tableName().'.subscriber_id=:subscriber_id', [':subscriber_id' => $subscriber_id]);

        return $this;
    }

    /**
     * @param  array|string        $categories
     * @return DbSubscriptionQuery $this
     */
    public function matchingCategory($categories)
    {
        if ($categories === null) {
            return $this;
        }
        $modelClass = $this->modelClass;
        $t = $modelClass::tableName();
        $r = DbSubscriptionCategory::tableName();

        if (!is_array($categories)) {
            $categories = [$categories];
        }
        if (empty($categories)) {
            return $this;
        }

        $this->innerJoinWith('categories');

        $i = 0;
        $conditions = ['AND'];
        $params = [];
        foreach ($categories as $category) {
            $conditions[] = [
                'OR',
                "($r.is_exception = false AND :category$i LIKE $r.category)",
                "($r.is_exception = true AND :category$i NOT LIKE $r.category)",
            ];
            $params[':category'.$i++] = $category;
        }

        $this->andWhere($conditions, $params);

        return $this;
    }
}
