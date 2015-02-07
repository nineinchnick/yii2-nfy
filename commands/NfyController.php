<?php

namespace nineinchnick\nfy\commands;

use Yii;
use yii\console\Controller;
use yii\rbac;

class NfyController extends Controller
{
    /**
     * nfy.queue.read
     *       |
     *       \-nfy.queue.read.subscribed
     * nfy.queue.subscribe
     * nfy.queue.unsubscribe
     *
     * nfy.message.read
     *       |
     *       \-nfy.message.read.subscribed
     *
     * nfy.message.create
     *       |
     *       \-nfy.message.create.subscribed
     */
    public function getTemplateAuthItems()
    {
        $bizRule = 'return !isset($params["queue"]) || $params["queue"]->isSubscribed($params["userId"]);';

        return [
            ['name' => 'nfy.queue.read',              'bizRule' => null, 'child' => null],
            ['name' => 'nfy.queue.read.subscribed',   'bizRule' => $bizRule, 'child' => 'nfy.queue.read'],
            ['name' => 'nfy.queue.subscribe',         'bizRule' => null, 'child' => null],
            ['name' => 'nfy.queue.unsubscribe',       'bizRule' => null, 'child' => null],
            ['name' => 'nfy.message.read',              'bizRule' => null, 'child' => null],
            ['name' => 'nfy.message.create',            'bizRule' => null, 'child' => null],
            ['name' => 'nfy.message.read.subscribed',   'bizRule' => $bizRule, 'child' => 'nfy.message.read'],
            ['name' => 'nfy.message.create.subscribed', 'bizRule' => $bizRule, 'child' => 'nfy.message.create'],
        ];
    }

    public function getTemplateAuthItemDescriptions()
    {
        return [
            'nfy.queue.read'              => Yii::t('auth', 'Read any queue'),
            'nfy.queue.read.subscribed'   => Yii::t('auth', 'Read subscribed queue'),
            'nfy.queue.subscribe'         => Yii::t('auth', 'Subscribe to any queue'),
            'nfy.queue.unsubscribe'       => Yii::t('auth', 'Unsubscribe from a queue'),
            'nfy.message.read'              => Yii::t('auth', 'Read messages from any queue'),
            'nfy.message.create'            => Yii::t('auth', 'Send messages to any queue'),
            'nfy.message.read.subscribed'   => Yii::t('auth', 'Read messages from subscribed queue'),
            'nfy.message.create.subscribed' => Yii::t('auth', 'Send messages to subscribed queue'),
        ];
    }

    public function actionCreateAuthItems()
    {
        $auth = Yii::$app->authManager;

        $newAuthItems = [];
        $descriptions = $this->getTemplateAuthItemDescriptions();
        foreach ($this->getTemplateAuthItems() as $template) {
            $newAuthItems[$template['name']] = $template;
        }
        $existingAuthItems = $auth->getItems(rbac\Item::TYPE_OPERATION);
        foreach ($existingAuthItems as $name => $existingAuthItem) {
            if (isset($newAuthItems[$name])) {
                unset($newAuthItems[$name]);
            }
        }
        foreach ($newAuthItems as $template) {
            $auth->createItem($template['name'], rbac\Item::TYPE_OPERATION, $descriptions[$template['name']], $template['bizRule']);
            if (isset($template['child']) && $template['child'] !== null) {
                $auth->addItemChild($template['name'], $template['child']);
            }
        }
    }

    public function actionRemoveAuthItems()
    {
        $auth = Yii::$app->authManager;

        foreach ($this->getTemplateAuthItems() as $template) {
            $auth->removeItem($template['name']);
        }
    }

    /**
     * @param string $queue   name of the queue component
     * @param string $message
     */
    public function actionSend($queue, $message)
    {
        $q = Yii::$app->getComponent($queue);
        if ($q === null) {
            throw new CException('Queue not found.');
        }
        $q->send($message);
    }

    /**
     * @param string $queue name of the queue component
     */
    public function actionReceive($queue, $limit = -1)
    {
        $q = Yii::$app->getComponent($queue);
        if ($q === null) {
            throw new CException('Queue not found.');
        }
        var_dump($q->receive(null, $limit));
    }

    /**
     * @param string $queue name of the queue component
     */
    public function actionPeek($queue, $limit = -1)
    {
        $q = Yii::$app->getComponent($queue);
        if ($q === null) {
            throw new CException('Queue not found.');
        }
        var_dump($q->peek(null, $limit));
    }
}
