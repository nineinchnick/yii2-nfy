<?php

use yii\helpers\Html;
use nineinchnick\nfy\components;
use nineinchnick\nfy\models;

/* @var yii\web\View $this */
/* @var $model components\Queue */
/* @var $key mixed */
/* @var $index string name of current queue application component */
/* @var $widget yii\widgets\ListView */
/* @var $subscriptions models\DbSubscription[] */
$supportSubscriptions = true;
try {
    $subscriptions = $model->getSubscriptions();
} catch (CException $e) {
    $supportSubscriptions = false;
}
?>

<h3><?= Html::encode($model->label); ?> <small><?= Html::a(Yii::t('app','View all messages'), ['messages', 'queue_name'=>$key])?></small></h3>
<?php if ($supportSubscriptions): ?>
<p>
    <?= Html::a(Yii::t('app', 'Subscribe'), ['subscribe', 'queue_name'=>$key]) ?> /
    <?= Html::a(Yii::t('app', 'Unsubscribe'), ['unsubscribe', 'queue_name'=>$key]) ?>
</p>
<?php endif; ?>
<?php if (!empty($subscriptions)): ?>
    <p>
        <?php echo Yii::t('app', 'Subscriptions'); ?>:
    </p>
    <ul>
<?php foreach($subscriptions as $subscription): ?>
        <li>
            <?= Html::a(
                Html::encode($subscription->label),
                ['messages', 'queue_name'=>$key, 'subscriber_id'=>$subscription->subscriber_id],
                ['title'=>implode("\n",$subscription->categories)]
            ) ?>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
