<?php
/* @var $this QueueController */
/* @var $queue NfyQueueInterface */
/* @var $queue_name string */
/* @var $dbMessage NfyDbMessage */
/* @var $message NfyMessage */
/* @var $authItems array */

$this->breadcrumbs=array(
	Yii::t('NfyModule.app', 'Queues')=>array('index'),
	$queue->label=>array('messages', 'queue_name'=>$queue_name, 'subscriber_id'=>$message->subscriber_id),
	$message->id,
);

?>
<h1><?php echo Yii::t('NfyModule.app', 'Message {id}', array('{id}'=>$message->id)); ?> <small><?php echo $message->created_on; ?></small></h1>

<div style="margin-bottom: 10px; word-break: break-all; white-space: normal;">
    <div>
        <?php echo $message->body === null ? '<i>'.Yii::t('NfyModule.app', 'No message body').'</i>' : $message->body; ?>
    </div>
</div>
<div>
<?php if ((int)$message->status === NfyMessage::AVAILABLE): ?>
	<form method="post" action="<?php echo $this->createMessageUrl($queue_name, $message); ?>">
		<?php echo CHtml::submitButton(Yii::t('NfyModule.app', 'Mark as read'), array('name'=>'delete')); ?>
	</form>
<?php endif; ?>
    <?php echo CHtml::link(CHtml::encode(Yii::t('NfyModule.app', 'Back to messages list')), array('messages', 'queue_name'=>$queue_name, 'subscriber_id'=>$message->subscriber_id)); ?>
</div>

<?php if ($queue instanceof NfyDbQueue): ?>
<?php if (!Yii::app()->user->checkAccess('nfy.message.read.subscribed', array(), true, false) && ($otherMessages=$dbMessage->subscriptionMessages(array(
    'with'=>'subscription.subscriber',
    'order'=>$dbMessage->getDbConnection()->getSchema()->quoteSimpleTableName('subscriptionMessages').'.deleted_on, '.$dbMessage->getDbConnection()->getSchema()->quoteSimpleTableName('subscriber').'.username',
))) != array()): ?>
<h3><?php echo Yii::t('NfyModule.app', 'Other recipients'); ?>:</h3>
<ul>
<?php foreach($otherMessages as $otherMessage): ?>
    <li><?php echo $otherMessage->deleted_on.' '.$otherMessage->subscription->subscriber; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; // access granted and not empty ?>
<?php endif; // $queue instanceof NfyDbQueue ?>
