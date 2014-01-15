<?php
/* @var $this DefaultController */
/* @var $queues array of NfyQueue */

$this->pageTitle = Yii::t('NfyModule.app', 'Queues');
$this->breadcrumbs=array(
	Yii::t('NfyModule.app', 'Queues')=>array('index'),
	Yii::t('NfyModule.app', 'List'),
); ?>
<h1><?php echo Yii::t('NfyModule.app', 'Queues'); ?></h1>

<p>
<?php $this->widget('zii.widgets.CListView', array(
    'dataProvider' => new CArrayDataProvider($queues),
    'itemView' => $subscribedOnly ? '_queue_messages' : '_queue_subscriptions',
)); ?>
</p>
