<?php

use yii\db\Schema;

class m140220_104548_nfy_set_unique_categories extends \yii\db\Migration
{
    public function safeUp()
    {
        $prefix = $this->db->tablePrefix;
        $this->createIndex($prefix.'nfy_subscription_categories_unique_idx', '{{%nfy_subscription_categories}}', 'subscription_id, category, is_exception', true);
    }

    public function safeDown()
    {
        $prefix = $this->db->tablePrefix;
        $this->dropIndex($prefix.'nfy_subscription_categories_unique_idx', '{{%nfy_subscription_categories}}');
    }
}
