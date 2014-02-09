<?php

namespace nineinchnick\nfy;

use Yii;

/**
 * @author Jan Was <jan@was.net.pl>
 */
class Module extends \yii\base\Module
{
	public $defaultController = 'queue';
	/**
	 * @var string Name of user model class.
	 */
	public $userClass = 'app\models\User';
	/**
	 * @var string if not null a sound will be played along with displaying a notification
	 */
	public $soundUrl;
	/**
	 * @var integer how many milliseconds to wait for new messages on the server side;
	 * zero or null disables long polling
	 */
	public $longPolling = 1000;
	/**
	 * @var integer how many times can messages be polled in a single action call
	 */
	public $maxPollCount = 30;
	/**
	 * @var array list of queue application components that will be displayed in the index action of the default controller.
	 */
	public $queues = array();

	public function init()
	{
		parent::init();
		\Yii::setAlias('@nfy', dirname(__FILE__));
		\Yii::$app->i18n->translations['nfy'] = [
			'class' => 'yii\i18n\PhpMessageSource',
			'sourceLanguage' => 'en-US',
			'basePath' => '@nfy/messages',
		];
		\Yii::$app->i18n->translations['auth'] = [
			'class' => 'yii\i18n\PhpMessageSource',
			'sourceLanguage' => 'en-US',
			'basePath' => '@nfy/messages',
		];
		if (\Yii::$app instanceof yii\console\Application) {
			$this->controllerMap['nfy'] = 'nineinchnick\nfy\commands\NfyController';
		}
	}
}
