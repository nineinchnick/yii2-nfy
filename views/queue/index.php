<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var $queues array of Queue
 */
$this->title = Yii::t('app', 'Queues');
$this->params['breadcrumbs'][] = $this->title;
?>

<h1><?= Html::encode($this->title) ?></h1>

<p>
<?php echo yii\widgets\ListView::widget([
    'dataProvider' => new yii\data\ArrayDataProvider([
        'allModels' => $queues,
        'key'=>function ($q) {return $q->id;},
        'pagination' => false,
        'sort' => ['attributes' => ['label']],
    ]),
    'itemView' => $subscribedOnly ? '_queue_messages' : '_queue_subscriptions',
    'itemOptions' => ['class' => 'item'],
]); ?>
</p>
