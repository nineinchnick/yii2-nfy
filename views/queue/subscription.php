<?php
/* @var $this DefaultController */
/* @var $queue NfyQueueInterface */
/* @var $model SubscriptionForm */
/* @var $form CActiveForm */

$this->breadcrumbs=array(
	Yii::t('NfyModule.app', 'Queues')=>array('index'),
	$queue->label,
);
?>
<h1><?php echo $queue->label; ?></h1>
<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'subscription-form',
	'enableAjaxValidation'=>false,
)); ?>
	<?php echo $form->errorSummary($model); ?>

	<div class="row">
        <?php echo $form->label($model, 'label'); ?>
        <?php echo $form->textField($model, 'label'); ?>
	</div>

	<div class="row">
        <?php echo $form->label($model, 'categories'); ?>
        <?php echo $form->textField($model, 'categories'); ?>
	</div>

	<div class="row">
        <?php echo $form->label($model, 'exceptions'); ?>
        <?php echo $form->textField($model, 'exceptions'); ?>
	</div>

    <br/>
    
	<div class="row buttons">
		<?php echo CHtml::submitButton(Yii::t('NfyModule.app', 'Submit')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->
