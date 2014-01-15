<?php

/**
 *
 */
class MessageForm extends CFormModel
{
    public $content;
    public $category;
    
	public function rules()
	{
        return array(
            array('content, category', 'filter', 'filter'=>'trim'),
            array('content, category', 'default', 'setOnEmpty'=>true, 'value' => null),
            array('content', 'required'),
        );
    }

	public function attributeLabels()
	{
		return array(
			'content' => Yii::t('NfyModule.app', 'Message content'),
			'category' => Yii::t('NfyModule.app', 'Message category'),
		);
	}
}
