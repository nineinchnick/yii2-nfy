<?php

namespace nineinchnick\nfy\widgets;

use yii\base\Widget;
use yii\web\View;

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
     * @param \yii\web\View $view
     * @param  string $method use either METHOD_POLL or METHOD_PULL constants
     * @return string base URL for assets
     */
    public static function initClientScript($view, $method = self::METHOD_POLL)
    {
        $asset = NfyAsset::register($view);
        return $asset->baseUrl;
    }

    public function run()
    {
        $baseUrl = self::initClientScript($this->view, $this->method);
        $options = [
            'url' => $this->url,
            'baseUrl' => $baseUrl,
            'method' => $this->method,
            'pollInterval' => $this->pollInterval,
            'websocket' => $this->websocket,
        ];
        $options = \yii\helpers\Json::encode($options);
        $script = "notificationsPoller.init({$options});";
        $this->view->registerJs($script, View::POS_END);
    }
}
