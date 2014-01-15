<?php
/* @var $data NfyQueue */
/* @var $index string name of current queue application component */
?>

<h3><?php echo CHtml::encode($data->label); ?> <small><?php echo CHtml::link(Yii::t('NfyModule.app','View messages'), array('messages', 'queue_name'=>$index, 'subscriber_id'=>Yii::app()->user->getId()))?></small></h3>
<div style="margin-bottom: 20px;">
</div>
