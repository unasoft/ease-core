<?php

namespace core\migrations;


use core\db\Migration;
use core\models\Page;

class m161219_060618_init extends Migration
{
    /**
     * @inhertidoc
     */
    public function up()
    {
        $this->createTable('{{%domains}}', [
            'domain_id'      => $this->primaryKey(),
            'name'           => $this->string(255),
            'host_attack'    => $this->smallInteger(1)->defaultValue(1),
            'access_backend' => $this->smallInteger(1)->defaultValue(0),
            'sort_order'     => $this->smallInteger(5),
        ]);

        $this->createTable('{{%sites}}', [
            'site_id'    => $this->primaryKey(),
            'domain_id'  => $this->integer(11),
            'code'       => $this->string(32),
            'name'       => $this->string(64),
            'sort_order' => $this->smallInteger(5),
            'is_default' => $this->smallInteger(1)->defaultValue(0),
        ]);

        $this->addForeignKey('fk-domain-id', '{{%sites}}', 'domain_id', '{{%domains}}', 'domain_id', 'CASCADE');

        $this->createTable('{{%site_config}}', [
            'config_id' => $this->primaryKey(),
            'site_id'   => $this->integer(),
            'key'       => $this->string(255),
            'value'     => $this->text(),
        ]);

        $this->addForeignKey('fk-site-config', '{{%site_config}}', 'site_id', '{{%sites}}', 'site_id', 'CASCADE');

        $this->createTable('{{%page}}', [
            'page_id'          => $this->primaryKey(),
            'title'            => $this->string(255),
            'page_layout'      => $this->string(255),
            'slug'             => $this->string(100),
            'meta_title'       => $this->string(255),
            'meta_keywords'    => $this->text(),
            'meta_description' => $this->text(),
            'content_heading'  => $this->string(255),
            'content'          => $this->mediumText(),
            'creation_time'    => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'update_time'      => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'page_view'        => $this->string(),
            'is_active'        => $this->boolean()->defaultValue(false),
            'is_default'       => $this->boolean()->defaultValue(false),
        ]);

        $this->createTable('{{%site_page}}', [
            'page_id' => $this->integer(11),
            'site_id' => $this->integer(11)
        ]);

        $this->addForeignKey('fk-page', '{{%site_page}}', 'page_id', '{{%page}}', 'page_id', 'CASCADE');
        $this->addForeignKey('fk-page_site', '{{%site_page}}', 'site_id', '{{%sites}}', 'site_id', 'CASCADE');

        $this->createTable('{{%rewrites}}', [
            'url_rewrite_id' => $this->primaryKey(),
            'pattern'        => $this->string(255),
            'route'          => $this->string(75),
            'suffix'         => $this->string(20),
            'normalizer'     => $this->text(),
            'description'    => $this->string(255),
            'order'          => $this->smallInteger(),
            'site_id'        => $this->integer()
        ]);

        $this->addForeignKey('fk-rewrites-page_id', '{{%rewrites}}', 'site_id', '{{%sites}}', 'site_id', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropForeignKey('fk-domain-id', '{{%sites}}');
        $this->dropForeignKey('fk-site-config', '{{%site_config}}');
        $this->dropForeignKey('fk-page', '{{%site_page}}');
        $this->dropForeignKey('fk-page_site', '{{%site_page}}');

        $this->dropTable('{{%rewrites}}');
        $this->dropTable('{{%site_config}}');
        $this->dropTable('{{%sites}}');
        $this->dropTable('{{%domains}}');

        $this->dropTable('{{%site_page}}');
        $this->dropTable('{{%page}}');
    }
}
