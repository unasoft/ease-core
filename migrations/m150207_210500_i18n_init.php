<?php

namespace core\migrations;


use core\db\Migration;

/**
 * Initializes i18n messages tables.
 *
 *
 *
 * @author Dmitry Naumenko <d.naumenko.a@gmail.com>
 * @since  2.0.7
 */
class m150207_210500_i18n_init extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->createTable('{{%i18n_source}}', [
            'id'       => $this->primaryKey(),
            'category' => $this->string(),
            'message'  => $this->text(),
        ], $this->tableOptions);

        $this->createTable('{{%i18n_message}}', [
            'id'          => $this->integer()->notNull(),
            'language'    => $this->string(16)->notNull(),
            'translation' => $this->text(),
        ], $this->tableOptions);

        $this->addPrimaryKey('pk_message_id_language', '{{%i18n_message}}', ['id', 'language']);
        $this->addForeignKey('fk_message_source_message', '{{%i18n_message}}', 'id', '{{%i18n_source}}', 'id', 'CASCADE', 'RESTRICT');
        $this->createIndex('idx_source_message_category', '{{%i18n_source}}', 'category');
        $this->createIndex('idx_message_language', '{{%i18n_message}}', 'language');

        $this->processI18n(require(__DIR__ . '/data/m150207_210500_i18n_init.php'));
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropForeignKey('fk_message_source_message', '{{%i18n_message}}');
        $this->dropTable('{{%i18n_message}}');
        $this->dropTable('{{%i18n_source}}');
    }
}
