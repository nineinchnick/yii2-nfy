# Notifications

This is a module for [Yii 2.0 framework](http://www.yiiframework.com/) that provides:

* a generic queue component
* a Publish/Subscribe message delivery pattern
* a SQL database queue implementation
* a configurable way to send various notifications, messages and tasks to a queue
* a basic widget to read such items from queue and display them to the user as system notifications
* a basic widget to put in a navbar that displays notifications and/or messages in a popup
* a basic CRUD to manage and/or debug queues or use as a simple messanger

Messages could be passed directly as strings or created from some objects, like Active Records. This could be used to log all changes to the models, exactly like the [audittrail2](http://www.yiiframework.com/extension/audittrail2) extension.

When recipients are subscribed to a channel, message delivery can depend on category filtering, much like in logging system provided by the framework.

A simple SQL queue implementation is provided if a MQ server is not available or not necessary.

## Installation

1. Install [Yii2](https://github.com/yiisoft/yii2/tree/master/apps/basic) using your preferred method
2. Install package via [composer](http://getcomposer.org/download/)
  * Run `php composer.phar require nineinchnick/yii2-nfy "dev-master"` OR add to composer.json require section `"nineinchnick/yii2-nfy": "dev-master"`
  * If Redis queue will be used, also install "yiisoft/yii2-redis"
3. Update config file *config/web.php* as shown below. Check out the Module for more available options.


Enable module in configuration. Do it in both main and console configs, because some settings are used in migrations.

Copy migrations to your migrations folder and adjust dates in file and class names. Then apply migrations.

Define some queues as application components and optionally enable the module, see the next section.

~~~php
$config = [
    // .........
	'aliases' => [
		'@nineinchnick/nfy' => '@vendor/nineinchnick/yii2-nfy',
	],
	'modules' => [
		'nfy' => [
			'class' => 'nineinchnick\nfy\Module',
		],
	],
	'components' => [
		'dbmq' => [
			'class' => 'nineinchnick\nfy\components\DbQueue',
			'id' => 'queue',
			'label' => 'Notifications',
			'timeout' => 30,
		],
		'sysvmq' => [
			'class' => 'nineinchnick\nfy\components\SysVQueue',
			'id' => 'a',
			'label' => 'IPC queue',
		],
		'redismq' => [
			'class' => 'nineinchnick\nfy\components\RedisQueue',
			'id' => 'mq',
			'label' => 'Redis queue',
			'redis' => 'redis',
		],
		// ..........
	],
]
~~~

Then you can send and receive messages through this component:

~~~php
// send one message 'test'
Yii::$app->dbmq->send('test');
// receive all available messages without using subscriptions and immediately delete them from the queue
$messages = $queue->receive();
~~~

Or you could subscribe some users to it:

~~~php
Yii::$app->queue->subscribe(Yii:$app->user->getId());
// send one message 'test'
Yii::$app->queue->send('test');
// receive all available messages for current user and immediately delete them from the queue
$messages = $queue->receive(Yii:$app->user->getId());
// if there are any other users subscribed, they will receive the message independently
~~~

## Module parameters

By specifying the users model class name in the _userClass_ property proper table name and primary key column name will be used in migrations.

## Display notifications

Put anywhere in your layout or views or controller.

~~~php
$this->widget('nfy.extensions.webNotifications.WebNotifications', array('url'=>$this->createUrl('/nfy/default/poll', array('id'=>'queueComponentId'))));
~~~

## Receiving messages

By configuring the WebNotifications widget messages could be read by:

* polling using ajax (repeating requests at fixed interval) an action that checks a queue and returns new items
* connect to a web socket and wait for new items

## Changelog

### 0.95 - 2014-01-15

Initial release after porting from Yii 1.


