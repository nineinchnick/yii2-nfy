<?php

class m130713_201034_notifications_install extends CDbMigration
{
	public function safeUp()
	{
		$nfy = Yii::app()->getModule('nfy');
		$user = CActiveRecord::model($nfy->userClass);
		$userTable = $user->tableName();
		$userPk = $user->tableSchema->primaryKey;
		$userPkType = $user->tableSchema->getColumn($userPk)->dbType;
		$schema = $user->dbConnection->schema;
		$driver = $user->dbConnection->driver;

		$this->createTable('{{nfy_messages}}', array(
			'id'			=> 'pk',
			'queue_id'		=> 'string NOT NULL',
			'created_on'	=> 'timestamp NOT NULL',
			'sender_id'		=> $userPkType.' REFERENCES '.$schema->quoteTableName($userTable).' ('.$userPk.') ON DELETE CASCADE ON UPDATE CASCADE',
			'message_id'	=> 'integer',
			'subscription_id' => 'integer REFERENCES '.$schema->quoteTableName('{{nfy_subscriptions}}').' (id) ON DELETE CASCADE ON UPDATE CASCADE',
			'status'		=> 'integer NOT NULL',
			'timeout'		=> 'integer',
			'reserved_on'	=> 'timestamp',
			'deleted_on'	=> 'timestamp',
			'mimetype'		=> 'string NOT NULL DEFAULT \'text/plain\'',
			'body'			=> 'text',
		));
		$this->createTable('{{nfy_subscriptions}}', array(
			'id'		=> 'pk',
			'queue_id'	=> 'string NOT NULL',
			'label'		=> 'string',
			'subscriber_id'=>$userPkType.' NOT NULL REFERENCES '.$schema->quoteTableName($userTable).' ('.$userPk.') ON DELETE CASCADE ON UPDATE CASCADE',
			'created_on' => 'timestamp',
			'is_deleted' => 'boolean NOT NULL DEFAULT '.($driver==='sqlite' ? '0' : 'false'),
		));
		$this->createTable('{{nfy_subscription_categories}}', array(
			'id'=>'pk',
			'subscription_id'=>'integer NOT NULL REFERENCES '.$schema->quoteTableName('{{nfy_subscriptions}}').' (id) ON DELETE CASCADE ON UPDATE CASCADE',
			'category'=>'string NOT NULL',
			'is_exception'=>'boolean NOT NULL DEFAULT '.($driver==='sqlite' ? '0' : 'false'),
		));

		$this->createIndex('{{nfy_messages}}_queue_id_idx', '{{nfy_messages}}', 'queue_id');
		$this->createIndex('{{nfy_messages}}_sender_id_idx', '{{nfy_messages}}', 'sender_id');
		$this->createIndex('{{nfy_messages}}_message_id_idx', '{{nfy_messages}}', 'message_id');
		$this->createIndex('{{nfy_messages}}_status_idx', '{{nfy_messages}}', 'status');
		$this->createIndex('{{nfy_messages}}_reserved_on_idx', '{{nfy_messages}}', 'reserved_on');
		$this->createIndex('{{nfy_messages}}_subscription_id_idx', '{{nfy_messages}}', 'subscription_id');

		$this->createIndex('{{nfy_subscriptions}}_queue_id_idx', '{{nfy_subscriptions}}', 'queue_id');
		$this->createIndex('{{nfy_subscriptions}}_subscriber_id_idx', '{{nfy_subscriptions}}', 'subscriber_id');
		$this->createIndex('{{nfy_subscriptions}}_queue_id_subscriber_id_idx', '{{nfy_subscriptions}}', 'queue_id,subscriber_id', true);
		$this->createIndex('{{nfy_subscriptions}}_is_deleted_idx', '{{nfy_subscriptions}}', 'is_deleted');

		$this->createIndex('{{nfy_subscription_categories}}_subscription_id_idx', '{{nfy_subscription_categories}}', 'subscription_id');
		$this->createIndex('{{nfy_subscription_categories}}_subscription_id_category_idx', '{{nfy_subscription_categories}}', 'subscription_id,category', true);
	}

	public function safeDown()
	{
		$this->dropTable('{{nfy_subscription_categories}}');
		$this->dropTable('{{nfy_subscriptions}}');
		$this->dropTable('{{nfy_messages}}');
	}
}

