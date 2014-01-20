<?php

use nineinchnick\nfy\components;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var yii\web\View $this */
/* @var $queue components\QueueInterface */
/* @var $model models\SubscriptionForm */
/* @var ActiveForm $form */

$this->params['breadcrumbs'][] = ['label'=>Yii::t('app', 'Queues'), 'url'=>['index']];
$this->params['breadcrumbs'][] = $queue->label;
?>
<h1><?php echo $queue->label; ?></h1>
<div class="form">

<?php $form = ActiveForm::begin([
	'id'=>'subscription-form',
	'enableAjaxValidation'=>false,
]); ?>
	<?php echo $form->errorSummary($model); ?>

	<div class="row">
		<div class="col-lg-5">

        <?php echo $form->field($model, 'label'); ?>
        <?php echo $form->field($model, 'categories'); ?>
        <?php echo $form->field($model, 'exceptions'); ?>

			<div class="form-group">
				<?= Html::submitButton(Yii::t('app', 'Submit'), ['class' => 'btn btn-primary']) ?>
			</div>
		</div>

	</div>

<?php ActiveForm::end(); ?>

</div><!-- form -->
