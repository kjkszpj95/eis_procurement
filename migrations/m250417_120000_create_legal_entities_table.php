<?php

use yii\db\Migration;

class m250417_120000_create_legal_entities_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%legal_entities}}', [
            'id' => $this->primaryKey(),
            'inn' => $this->string(12)->comment('ИНН/Аналог ИНН'), 
            'ogrn' => $this->string(15)->comment('ОГРН'), 
            'kpp' => $this->string(9)->comment('КПП'),
            'name' => $this->string(1000)->notNull()->comment('Наименование ЮЛ'),
            'participant_type' => $this->string(100)->comment('Тип участника'),
            'court' => $this->string(1000)->comment('Суд'),
            'case_number' => $this->string(1000)->comment('Номер дела'),
            'decision_date' => $this->date()->comment('Дата вынесения постановления'),
            'effective_date' => $this->date()->comment('Дата вступления в законную силу постановления о назначении административного наказания'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->createIndex('idx-legal_entities-inn', '{{%legal_entities}}', 'inn');
        $this->createIndex('idx-legal_entities-ogrn', '{{%legal_entities}}', 'ogrn');
        $this->createIndex('idx-legal_entities-case_number', '{{%legal_entities}}', 'case_number');
    }

    public function safeDown()
    {
        $this->dropTable('{{%legal_entities}}');
    }
}