<?php

use yii\helpers\Html;
use nineinchnick\nfy\components;

/* @var yii\web\View $this */
/* @var $model components\Queue */
/* @var $key mixed */
/* @var $index string name of current queue application component */
/* @var $widget yii\widgets\ListView */
?>

<h3><?= Html::encode($model->label); ?> <small><?= Html::a(Yii::t('app','View messages'), ['messages', 'queue_name'=>$key, 'subscriber_id'=>Yii::$app->user->getId()])?></small></h3>
<div style="margin-bottom: 20px;">
</div>
