<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/**
 * @var yii\web\View $this
 * @var models\MessageForm $model
 * @var ActiveForm $form
 */
?>
<div class="form">

<?php $form = ActiveForm::begin([
    'id' => 'message-form',
    'enableAjaxValidation' => false,
]); ?>
    <?php echo $form->errorSummary($model); ?>

    <div class="row">
        <div class="col-lg-5">

            <?php echo $form->field($model, 'category'); ?>
            <?php echo $form->field($model, 'content')->textArea(['rows' => 6]); ?>

            <div class="form-group">
                <?= Html::submitButton(Yii::t('app', 'Submit'), ['class' => 'btn btn-primary']) ?>
            </div>
        </div>

    </div>

<?php ActiveForm::end(); ?>

</div><!-- form -->
