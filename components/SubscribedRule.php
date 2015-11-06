<?php

namespace nineinchnick\nfy\components;

use Yii;
use yii\rbac\Rule;

/**
 * Checks if user is subscribed to a queue
 */
class SubscribedRule extends Rule
{
    public $name = 'subscribed';

    /**
     * @param string|integer $user the user ID.
     * @param \yii\rbac\Item $item the role or permission that this rule is associated with
     * @param array $params parameters passed to ManagerInterface::checkAccess().
     * @return boolean a value indicating whether the rule permits the role or permission it is associated with.
     */
    public function execute($user, $item, $params)
    {
        if (!isset($params["queue"])) {
            return false;
        }
        /** @var QueueInterface $queue */
        $queue = $params["queue"];
        return $queue->isSubscribed(Yii::$app->user->id);
    }
}