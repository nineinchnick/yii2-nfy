<?php

use yii\helpers\Html;

/* @var yii\web\View $this */
/* @var $dataProvider ArrayDataProvider */
/* @var $queue QueueInterface */
/* @var $queue_name string */
/* @var $model models\MessageForm */
/* @var $authItems array */

$this->title = Yii::t('app', 'Queues');
$this->params['breadcrumbs'][] = ['label'=>$this->title, 'url'=>['index']];
$this->params['breadcrumbs'][] = $queue->label;
?>
<h1><?php echo $queue->label; ?></h1>

<?php if ($authItems['nfy.message.read']): ?>
<p>
<?php echo yii\widgets\ListView::widget([
    'dataProvider' => $dataProvider,
    'itemView'=>'_message_item',
	'viewParams' => array('queue_name' => $queue_name),
	'layout' => "{summary}\n{pager}\n{items}",
    'pager' => array(
        'class' => 'LinkPager',
        'prevPageLabel' => Yii::t('app', 'Newer'),
        'nextPageLabel' => Yii::t('app', 'Older'),
    ),
]); ?>
</p>
<?php endif; ?>

<?php if ($authItems['nfy.message.create']): ?>
<?php echo $this->render('_message_form', array('model'=>$model)); ?>
<?php endif; ?>
