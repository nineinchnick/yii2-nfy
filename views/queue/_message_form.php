<?php
/* @var $model MessageForm */
/* @var $form CActiveForm */
?>
<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'message-form',
	'enableAjaxValidation'=>false,
)); ?>
	<?php echo $form->errorSummary($model); ?>

	<div class="">
        <?php echo $form->label($model, 'category'); ?>
		<?php echo $form->textField($model, 'category'); ?>
	</div>

	<div class="">
        <?php echo $form->label($model, 'content'); ?>
		<?php echo $form->textArea($model,'content', array('style'=>'width: 600px;', 'rows'=>5)); ?>
	</div>

    <br/>
    
	<div class="buttons">
		<?php echo CHtml::submitButton(Yii::t('NfyModule.app', 'Submit')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->
