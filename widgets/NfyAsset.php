<?php

namespace nineinchnick\nfy\widgets;

use yii\web\AssetBundle;

/**
 * @author Jan Was <janek.jan@gmail.com>
 */
class NfyAsset extends AssetBundle
{
    public $sourcePath = '@vendor/nineinchnick/yii2-nfy/assets';
    public $baseUrl = '@web';
    public $css = [
        'css/webnotification.min.css',
    ];
    public $js = [
        'js/jquery.webnotification.js',
        'js/main.js',
    ];
    public $depends = [
    ];
}
