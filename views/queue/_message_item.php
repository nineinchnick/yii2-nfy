<?php

use yii\helpers\Html;
use nineinchnick\nfy\components;
use nineinchnick\nfy\components\Message;

/* @var yii\web\View $this */
/* @var $model components\Message */
/* @var $queue_name string */
?>

<div style="margin-bottom: 20px; word-break: break-all; white-space: normal;">
    <div style="<?php echo (int) $model->status !== Message::AVAILABLE ? '' : "font-weight:bold;"; ?>">
        <?php echo $model->created_on; ?>
        <?php echo Html::a(Html::encode($model->id), $this->context->createMessageUrl($queue_name, $model)); ?>
        <?php echo $model->body; ?>
    </div>
</div>
