<?php
/* @var $data NfyMessage */
/* @var $queue_name string */
?>

<div style="margin-bottom: 20px; word-break: break-all; white-space: normal;">
    <div style="<?php echo (int)$data->status !== NfyMessage::AVAILABLE ? '' : "font-weight:bold;"; ?>">
        <?php echo $data->created_on; ?>
        <?php echo CHtml::link(CHtml::encode($data->id), $this->createMessageUrl($queue_name, $data)); ?>
        <?php echo $data->body; ?>
    </div>
</div>
