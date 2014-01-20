<?php

use yii\helpers\Html;
use nineinchnick\nfy\components\Message;

/* @var yii\web\View $this */
/* @var $queue components\QueueInterface */
/* @var $queue_name string */
/* @var $dbMessage models\DbMessage */
/* @var $message components\Message */
/* @var $authItems array */

$this->params['breadcrumbs'][] = ['label'=>Yii::t('app', 'Queues'), 'url'=>['index']];
$this->params['breadcrumbs'][] = ['label'=>$queue->label, 'url'=>['messages', 'queue_name'=>$queue_name, 'subscriber_id'=>$message->subscriber_id]];
$this->params['breadcrumbs'][] = $message->id;

?>
<h1><?php echo Yii::t('app', 'Message {id}', array('id'=>$message->id)); ?> <small><?php echo $message->created_on; ?></small></h1>

<div style="margin-bottom: 10px; word-break: break-all; white-space: normal;">
    <div>
        <?php echo $message->body === null ? '<i>'.Yii::t('app', 'No message body').'</i>' : $message->body; ?>
    </div>
</div>
<div>
<?php if ((int)$message->status === Message::AVAILABLE): ?>
	<form method="post" action="<?php echo $this->context->createMessageUrl($queue_name, $message); ?>">
		<?php echo Html::submitButton(Yii::t('app', 'Mark as read'), array('name'=>'delete')); ?>
	</form>
<?php endif; ?>
    <?php echo Html::a(Html::encode(Yii::t('app', 'Back to messages list')), array('messages', 'queue_name'=>$queue_name, 'subscriber_id'=>$message->subscriber_id)); ?>
</div>

<?php if ($queue instanceof nineinchnick\nfy\components\DbQueue): ?>
<?php if (!Yii::$app->user->checkAccess('nfy.message.read.subscribed', array(), true, false) && ($otherMessages=$dbMessage->getSubscriptionMessages()->joinWith('subscription.subscriber')->orderBy($dbMessage->getDb()->getSchema()->quoteSimpleTableName('nfy_messages').'.deleted_on, '.$dbMessage->getDb()->getSchema()->quoteSimpleTableName('users').'.username')->all()) != array()): ?>
<h3><?php echo Yii::t('app', 'Other recipients'); ?>:</h3>
<ul>
<?php foreach($otherMessages as $otherMessage): ?>
    <li><?php echo $otherMessage->deleted_on.' '.($otherMessage->subscription !== null ? $otherMessage->subscription->subscriber : ''); ?></li>
<?php endforeach; ?>
</ul>
<?php endif; // access granted and not empty ?>
<?php endif; // $queue instanceof DbQueue ?>
