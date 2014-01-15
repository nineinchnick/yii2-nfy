<?php

/**
 *
 */
class SubscriptionForm extends CFormModel
{
    public $label;
    public $categories;
    public $exceptions;
    
	public function rules()
	{
        return array(
            array('label, categories, exceptions', 'filter', 'filter'=>'trim'),
            array('label, categories, exceptions', 'default', 'setOnEmpty'=>true, 'value' => null),
            array('categories, exceptions', 'prepare'),
        );
    }

	public function prepare($attribute, $params)
	{
		if ($this->$attribute === null)
			return true;

		$values = array_map(function($v){return trim($v);}, explode(',',$this->$attribute));
		if (!empty($values))
			$this->$attribute = $values;
		return true;
	}

	public function attributeLabels()
	{
		return array(
			'label' => Yii::t('NfyModule.app', 'Label'),
			'categories' => Yii::t('NfyModule.app', 'Categories'),
			'exceptions' => Yii::t('NfyModule.app', 'Exceptions'),
		);
	}
}
