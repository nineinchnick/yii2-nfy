<?php

namespace nineinchnick\nfy;

use yii\base\Widget;

/**
 * Poll messages and display them as notifications, optionally playing a sound.
 *
 * If native web notifications are not available, the WNF (http://wnf.brunoscopelliti.com/) plugin is used.
 *
 * @author Jan Was <janek.jan@gmail.com>
 */
class WebNotifications extends Widget
{
    const METHOD_POLL = 'poll';
    const METHOD_PUSH = 'push';

    /**
     * @var string url of an ajax action or a websocket
     */
    public $url;
    /**
     * @var string poll for ajax polling, push for websockets
     */
    public $method = self::METHOD_POLL;
    /**
     * @var integer interval in miliseconds how often a new request is fired in poll mode
     */
    public $pollInterval = 3000;
    /**
     * @var array holds websocket JS callbacks as strings prefixed with js:, possible keys are:
     *            onopen, onclose, onmessage, onerror. Callbacks shoould be a function returning a function, like:
     *            'js:function (socket) {return function (e) {console.log(e);};}'
     */
    public $websocket = [];

    /**
     * Registers required JS libraries and CSS files.
     * @param  string $method use either METHOD_POLL or METHOD_PULL constants
     * @return string base URL for assets
     */
    public static function initClientScript($method = self::METHOD_POLL)
    {
        $bu = Yii::app()->assetManager->publish(dirname(__FILE__).'/assets/');
        $cs = Yii::app()->clientScript;
        $cs->registerCoreScript('jquery');
        $cs->registerCssFile($bu.'/css/webnotification.min.css');
        $cs->registerScriptFile($bu.'/js/jquery.webnotification'.(YII_DEBUG ? '' : '.min').'.js');
        if ($method == self::METHOD_PUSH) {
            $cs->registerScriptFile($bu.'/js/sockjs-0.3'.(YII_DEBUG ? '' : '.min').'.js');
        }
        $cs->registerScriptFile($bu.'/js/main.js');

        return $bu;
    }

    public function run()
    {
        $bu = self::initClientScript($this->method);
        $options = [
            'url' => $this->url,
            'baseUrl' => $bu,
            'method' => $this->method,
            'pollInterval' => $this->pollInterval,
            'websocket' => $this->websocket,
        ];
        $options = CJavaScript::encode($options);
        $script = "notificationsPoller.init({$options});";
        Yii::app()->clientScript->registerScript(__CLASS__.'#'.$this->id, $script, CClientScript::POS_END);
    }
}
