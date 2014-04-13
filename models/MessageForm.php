<?php

namespace nineinchnick\nfy\models;

use Yii;
use yii\base\Model;

/**
 * MessageForm is the model behind the message form.
 */
class MessageForm extends Model
{
    public $content;
    public $category;

    public function rules()
    {
        return [
            [['content', 'category'], 'filter', 'filter'=>'trim'],
            [['content', 'category'], 'default'],
            ['content', 'required'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'content' => Yii::t('app', 'Message content'),
            'category' => Yii::t('app', 'Message category'),
        ];
    }
}
