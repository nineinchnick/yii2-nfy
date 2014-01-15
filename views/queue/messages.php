<?php
/* @var $this QueueController */
/* @var $dataProvider CArrayDataProvider */
/* @var $queue NfyQueueInterface */
/* @var $queue_name string */
/* @var $model MessageForm */
/* @var $authItems array */

$this->breadcrumbs=array(
	Yii::t('NfyModule.app', 'Queues')=>array('index'),
	$queue->label,
);
?>
<h1><?php echo $queue->label; ?></h1>

<?php if ($authItems['nfy.message.read']): ?>
<p>
<?php $this->widget('zii.widgets.CListView', array(
    'dataProvider'=>$dataProvider,
    'itemView'=>'_message_item',
	'viewData' => array('queue_name' => $queue_name),
	'template' => "{summary}\n{pager}\n{items}",
    'pager' => array(
        'class' => 'CLinkPager',
        'prevPageLabel' => Yii::t('NfyModule.app', 'Newer'),
        'nextPageLabel' => Yii::t('NfyModule.app', 'Older'),
    ),
)); ?>
</p>
<?php endif; ?>

<?php if ($authItems['nfy.message.create']): ?>
<?php echo $this->renderPartial('_message_form', array('model'=>$model)); ?>
<?php endif; ?>
