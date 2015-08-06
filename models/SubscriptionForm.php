<?php

namespace nineinchnick\nfy\models;

use yii\base\Model;
use Yii;

/**
 * SubscriptionForm is the model behind the message form.
 */
class SubscriptionForm extends Model
{
    public $label;
    public $categories;
    public $exceptions;

    public function rules()
    {
        return [
            [['label', 'categories', 'exceptions'], 'filter', 'filter' => 'trim'],
            [['label', 'categories', 'exceptions'], 'default'],
            [['categories', 'exceptions'], 'prepare'],
        ];
    }

    public function prepare($attribute, $params)
    {
        if ($this->$attribute === null) {
            return true;
        }

        $values = array_map(function ($v) {return trim($v);}, explode(',', $this->$attribute));
        if (!empty($values)) {
            $this->$attribute = $values;
        }

        return true;
    }

    public function attributeLabels()
    {
        return [
            'label' => Yii::t('app', 'Label'),
            'categories' => Yii::t('app', 'Categories'),
            'exceptions' => Yii::t('app', 'Exceptions'),
        ];
    }
}
