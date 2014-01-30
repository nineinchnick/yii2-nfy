<?php

namespace nineinchnick\nfy\models;

/**
 * Collection of scopes for the DbMessage AR model.
 */
class DbMessageQuery extends \yii\db\ActiveQuery
{
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
			$query->andWhere($conditions);
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
}
