<?php

use yii\db\Schema;

class m140220_104548_nfy_set_unique_categories extends \yii\db\Migration
{
    public function safeUp()
    {
        $this->execute('ALTER TABLE {{nfy_subscription_categories}} ADD CONSTRAINT {{nfy_subscription_categories}}_unique_categories UNIQUE (subscription_id, category, is_exception);');
    }

    public function safeDown()
    {
        $this->execute('ALTER TABLE {{nfy_subscription_categories}} DROP CONSTRAINT {{nfy_subscription_categories}}_unique_categories;');
    }
}
