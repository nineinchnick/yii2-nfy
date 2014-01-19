<?php

use nineinchnick\nfy\components;
use nineinchnick\nfy\models;

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

<h3><?php echo CHtml::encode($model->label); ?> <small><?php echo CHtml::link(Yii::t('app','View all messages'), array('messages', 'queue_name'=>$index))?></small></h3>
<?php if ($supportSubscriptions): ?>
<p>
	<?php echo CHtml::link(Yii::t('app', 'Subscribe'), array('subscribe', 'queue_name'=>$index)); ?> /
	<?php echo CHtml::link(Yii::t('app', 'Unsubscribe'), array('unsubscribe', 'queue_name'=>$index)); ?>
</p>
<?php endif; ?>
<?php if (!empty($subscriptions)): ?>
	<p>
		<?php echo Yii::t('app', 'Subscriptions'); ?>:
	</p>
	<ul>
<?php foreach($subscriptions as $subscription): ?>
		<li>
			<?php echo CHtml::link(
				CHtml::encode($subscription->label),
				array('messages', 'queue_name'=>$index, 'subscriber_id'=>$subscription->subscriber_id),
				array('title'=>implode("\n",$subscription->categories))
			); ?>
		</li>
<?php endforeach; ?>
	</ul>
<?php endif; ?>
