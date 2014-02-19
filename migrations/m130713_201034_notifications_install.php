<?php

use yii\db\Schema;

class m130713_201034_notifications_install extends \yii\db\Migration
{
	public function safeUp()
	{
		$nfy = Yii::$app->getModule('nfy');
		$userClass = $nfy->userClass;
		$userTable = $userClass::tableName();
		$userPk = $userClass::primaryKey();
		$userPkType = $userClass::getTableSchema()->getColumn($userPk[0])->dbType;
		$schema = $userClass::getDb()->schema;
		$driver = $userClass::getDb()->driverName;

		$this->createTable('{{%nfy_subscriptions}}', array(
			'id'		=> 'pk',
			'queue_id'	=> 'string NOT NULL',
			'label'		=> 'string',
			'subscriber_id'=>$userPkType.' NOT NULL REFERENCES '.$schema->quoteTableName($userTable).' ('.$userPk[0].') ON DELETE CASCADE ON UPDATE CASCADE',
			'created_on' => 'timestamp',
			'is_deleted' => 'boolean NOT NULL DEFAULT '.($driver==='sqlite' ? '0' : 'false'),
		));
		$this->createTable('{{%nfy_subscription_categories}}', array(
			'id'=>'pk',
			'subscription_id'=>'integer NOT NULL REFERENCES '.$schema->quoteTableName('{{nfy_subscriptions}}').' (id) ON DELETE CASCADE ON UPDATE CASCADE',
			'category'=>'string NOT NULL',
			'is_exception'=>'boolean NOT NULL DEFAULT '.($driver==='sqlite' ? '0' : 'false'),
		));
		$this->createTable('{{%nfy_messages}}', array(
			'id'			=> 'pk',
			'queue_id'		=> 'string NOT NULL',
			'created_on'	=> 'timestamp NOT NULL',
			'sender_id'		=> $userPkType.' REFERENCES '.$schema->quoteTableName($userTable).' ('.$userPk[0].') ON DELETE CASCADE ON UPDATE CASCADE',
			'message_id'	=> 'integer',
			'subscription_id' => 'integer REFERENCES '.$schema->quoteTableName('{{nfy_subscriptions}}').' (id) ON DELETE CASCADE ON UPDATE CASCADE',
			'status'		=> 'integer NOT NULL',
			'timeout'		=> 'integer',
			'reserved_on'	=> 'timestamp',
			'deleted_on'	=> 'timestamp',
			'mimetype'		=> 'string NOT NULL DEFAULT \'text/plain\'',
			'body'			=> 'text',
		));

		$prefix = $this->db->tablePrefix;
		$this->createIndex($prefix.'nfy_messages_queue_id_idx', '{{%nfy_messages}}', 'queue_id');
		$this->createIndex($prefix.'nfy_messages_sender_id_idx', '{{%nfy_messages}}', 'sender_id');
		$this->createIndex($prefix.'nfy_messages_message_id_idx', '{{%nfy_messages}}', 'message_id');
		$this->createIndex($prefix.'nfy_messages_status_idx', '{{%nfy_messages}}', 'status');
		$this->createIndex($prefix.'nfy_messages_reserved_on_idx', '{{%nfy_messages}}', 'reserved_on');
		$this->createIndex($prefix.'nfy_messages_subscription_id_idx', '{{%nfy_messages}}', 'subscription_id');

		$this->createIndex($prefix.'nfy_subscriptions_queue_id_idx', '{{%nfy_subscriptions}}', 'queue_id');
		$this->createIndex($prefix.'nfy_subscriptions_subscriber_id_idx', '{{%nfy_subscriptions}}', 'subscriber_id');
		$this->createIndex($prefix.'nfy_subscriptions_queue_id_subscriber_id_idx', '{{%nfy_subscriptions}}', 'queue_id,subscriber_id', true);
		$this->createIndex($prefix.'nfy_subscriptions_is_deleted_idx', '{{%nfy_subscriptions}}', 'is_deleted');

		$this->createIndex($prefix.'nfy_subscription_categories_subscription_id_idx', '{{%nfy_subscription_categories}}', 'subscription_id');
		$this->createIndex($prefix.'nfy_subscription_categories_subscription_id_category_idx', '{{%nfy_subscription_categories}}', 'subscription_id,category', true);
	}

	public function safeDown()
	{
		$this->dropTable('{{%nfy_messages}}');
		$this->dropTable('{{%nfy_subscription_categories}}');
		$this->dropTable('{{%nfy_subscriptions}}');
	}
}

