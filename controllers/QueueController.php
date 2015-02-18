<?php

namespace nineinchnick\nfy\controllers;

use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\helpers\Url;
use nineinchnick\nfy\components;
use nineinchnick\nfy\models;

/**
 * The default controller providing a basic queue managment interface and poll action.
 * @author Jan Was <jan@was.net.pl>
 */
class QueueController extends \yii\web\Controller
{
    public function filters()
    {
        return [
            'accessControl',
        ];
    }

    public function accessRules()
    {
        return [
            ['allow', 'actions' => ['index'], 'users' => ['@'], 'roles' => ['nfy.queue.read']],
            ['allow', 'actions' => ['messages', 'message', 'subscribe', 'unsubscribe'], 'users' => ['@']],
            ['allow', 'actions' => ['poll'], 'users' => ['@']],
            ['deny', 'users' => ['*']],
        ];
    }

    /**
     * Displays a list of queues and their subscriptions.
     */
    public function actionIndex()
    {
        /** @var CWebUser */
        $user = Yii::$app->user;
        $subscribedOnly = $user->checkAccess('nfy.queue.read.subscribed', [], true, false);
        $queues = [];
        foreach ($this->module->queues as $queueId) {
            /** @var Queue */
            $queue = Yii::$app->getComponent($queueId);
            if (!($queue instanceof components\QueueInterface) || ($subscribedOnly && !$queue->isSubscribed($user->getId()))) {
                continue;
            }
            $queues[$queueId] = $queue;
        }

        return $this->render('index', ['queues' => $queues, 'subscribedOnly' => $subscribedOnly]);
    }

    /**
     * Subscribe current user to selected queue.
     * @param string $queue_name
     */
    public function actionSubscribe($queue_name)
    {
        list($queue, $authItems) = $this->loadQueue($queue_name, ['nfy.queue.subscribe']);

        $formModel = new models\SubscriptionForm();
        if (isset($_POST['SubscriptionForm'])) {
            $formModel->attributes = $_POST['SubscriptionForm'];
            if ($formModel->validate()) {
                $queue->subscribe(Yii::$app->user->getId(), $formModel->label, $formModel->categories, $formModel->exceptions);

                return $this->redirect(['index']);
            }
        }

        return $this->render('subscription', ['queue' => $queue, 'model' => $formModel]);
    }

    /**
     * Unsubscribe current user from selected queue.
     * @param string $queue_name
     */
    public function actionUnsubscribe($queue_name)
    {
        list($queue, $authItems) = $this->loadQueue($queue_name, ['nfy.queue.unsubscribe']);
        $queue->unsubscribe(Yii::$app->user->getId());

        return $this->redirect(['index']);
    }

    /**
     * Displays and send messages in the specified queue.
     * @param string $queue_name
     * @param string $subscriber_id
     */
    public function actionMessages($queue_name, $subscriber_id = null)
    {
        if (($subscriber_id = trim($subscriber_id)) === '') {
            $subscriber_id = null;
        }
        list($queue, $authItems) = $this->loadQueue($queue_name, ['nfy.message.read', 'nfy.message.create']);
        $this->verifySubscriber($queue, $subscriber_id);

        $formModel = new models\MessageForm();
        if ($authItems['nfy.message.create'] && isset($_POST['MessageForm'])) {
            $formModel->attributes = $_POST['MessageForm'];
            if ($formModel->validate()) {
                $queue->send($formModel->content, $formModel->category);

                return $this->redirect(['messages', 'queue_name' => $queue_name, 'subscriber_id' => $subscriber_id]);
            }
        }

        $dataProvider = null;
        if ($authItems['nfy.message.read']) {
            $dataProvider = new \yii\data\ArrayDataProvider([
                'allModels' => $queue->peek($subscriber_id, 200, [components\Message::AVAILABLE, components\Message::RESERVED, components\Message::DELETED]),
                'sort' => ['attributes' => ['id'], 'defaultOrder' => ['id' => SORT_DESC]],
            ]);
            // reverse display order to simulate a chat window, where latest message is right above the message form
            $dataProvider->setModels(array_reverse($dataProvider->getModels()));
        }

        return $this->render('messages', [
            'queue' => $queue,
            'queue_name' => $queue_name,
            'dataProvider' => $dataProvider,
            'model' => $formModel,
            'authItems' => $authItems,
        ]);
    }

    /**
     * Fetches details of a single message, allows to release or delete it or sends a new message.
     * @param string $queue_name
     * @param string $subscriber_id
     * @param string $message_id
     */
    public function actionMessage($queue_name, $subscriber_id = null, $message_id = null)
    {
        if (($subscriber_id = trim($subscriber_id)) === '') {
            $subscriber_id = null;
        }
        list($queue, $authItems) = $this->loadQueue($queue_name, ['nfy.message.read', 'nfy.message.create']);
        $this->verifySubscriber($queue, $subscriber_id);

        if ($queue instanceof components\DbQueue) {
            $query = models\DbMessage::find()->withQueue($queue->id);
            if ($subscriber_id !== null) {
                $query->withSubscriber($subscriber_id);
            }

            $dbMessage = $query->andWhere(['in', models\DbMessage::tableName().'.'.models\DbMessage::primaryKey()[0], $message_id])->one();
            if ($dbMessage === null) {
                throw new NotFoundHttpException(Yii::t("app", 'Message with given ID was not found.'));
            }
            $messages = models\DbMessage::createMessages($dbMessage);
            $message = reset($messages);
        } else {
            $dbMessage = null;
            //! @todo should we even bother to locate a single message by id?
            $message = new components\Message();
            $message->setAttributes([
                'id' => $message_id,
                'subscriber_id' => $subscriber_id,
                'status' => components\Message::AVAILABLE,
            ]);
        }

        if (isset($_POST['delete'])) {
            $queue->delete($message->id, $message->subscriber_id);

            return $this->redirect(['messages', 'queue_name' => $queue_name, 'subscriber_id' => $message->subscriber_id]);
        }

        return $this->render('message', [
            'queue' => $queue,
            'queue_name' => $queue_name,
            'dbMessage' => $dbMessage,
            'message' => $message,
            'authItems' => $authItems,
        ]);
    }

    /**
     * Loads queue specified by id and checks authorization.
     * @param  string                                       $name      queue component name
     * @param  array                                        $authItems
     * @return array                                        QueueInterface object and array with authItems as keys and boolean values
     * @throws ForbiddenHttpException,NotFoundHttpException
     */
    protected function loadQueue($name, $authItems = [])
    {
        /** @var CWebUser */
        $user = Yii::$app->user;
        /** @var Queue */
        $queue = Yii::$app->getComponent($name);
        if (!($queue instanceof components\QueueInterface)) {
            throw new NotFoundHttpException(Yii::t("app", 'Queue with given ID was not found.'));
        }
        $assignedAuthItems = [];
        $allowAccess = empty($authItems);
        foreach ($authItems as $authItem) {
            $assignedAuthItems[$authItem] = $user->checkAccess($authItem, ['queue' => $queue]);
            if ($assignedAuthItems[$authItem]) {
                $allowAccess = true;
            }
        }
        if (!$allowAccess) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not authorized to perform this action.'));
        }

        return [$queue, $assignedAuthItems];
    }

    /**
     * Checks if current user can read only messages from subscribed queues and is subscribed.
     * @param  QueueInterface         $queue
     * @param  integer                $subscriber_id
     * @throws ForbiddenHttpException
     */
    protected function verifySubscriber($queue, $subscriber_id)
    {
        /** @var CWebUser */
        $user = Yii::$app->user;
        $subscribedOnly = $user->checkAccess('nfy.message.read.subscribed', [], true, false);
        if ($subscribedOnly && (!$queue->isSubscribed($user->getId()) || $subscriber_id != $user->getId())) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not authorized to perform this action.'));
        }
    }

    /**
     * @param  string                 $id         id of the queue component
     * @param  boolean                $subscribed should the queue be checked using current user's subscription
     * @throws ForbiddenHttpException
     */
    public function actionPoll($id, $subscribed = true)
    {
        $userId = Yii::$app->user->getId();
        $queue = Yii::$app->getComponent($id);
        if (!($queue instanceof components\QueueInterface)) {
            return [];
        }
        if (!Yii::$app->user->checkAccess('nfy.message.read', ['queue' => $queue])) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not authorized to perform this action.'));
        }

        Yii::$app->session->close();

        $data = [];
        $data['messages'] = $this->getMessages($queue, $subscribed ? $userId : null);

        $pollFor = $this->getModule()->longPolling;
        $maxPoll = $this->getModule()->maxPollCount;
        if ($pollFor && $maxPoll && empty($data['messages'])) {
            while (empty($data['messages']) && $maxPoll) {
                $data['messages'] = $this->getMessages($queue, $subscribed ? $userId : null);
                usleep($pollFor * 1000);
                $maxPoll--;
            }
        }

        if (empty($data['messages'])) {
            header("HTTP/1.0 304 Not Modified");
            exit();
        } else {
            header("Content-type: application/json");
            Yii::$app->getClientScript()->reset();
            echo json_encode($data);
        }
    }

    /**
     * Fetches messages from a queue and deletes them. Messages are transformed into a json serializable array.
     * If a sound is configured in the module, an url is added to each message.
     *
     * Only first 20 messages are returned but all available messages are deleted from the queue.
     *
     * @param  QueueInterface $queue
     * @param  string         $userId
     * @return array
     */
    protected function getMessages($queue, $userId)
    {
        $messages = $queue->receive($userId);

        if (empty($messages)) {
            return [];
        }

        $messages = array_slice($messages, 0, 20);
        $soundUrl = $this->getModule()->soundUrl !== null ? $this->createAbsoluteUrl($this->getModule()->soundUrl) : null;

        $results = [];
        foreach ($messages as $message) {
            $result = [
                'title' => $queue->label,
                'body' => $message->body,
            ];
            if ($soundUrl !== null) {
                $result['sound'] = $soundUrl;
            }
            $results[] = $result;
        }

        return $results;
    }

    public function createMessageUrl($queue_name, components\Message $message)
    {
        return Url::toRoute('message', ['queue_name' => $queue_name, 'subscriber_id' => $message->subscriber_id, 'message_id' => $message->id]);
    }
}
