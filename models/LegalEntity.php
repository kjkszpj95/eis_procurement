<?php
namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;

class LegalEntity extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%legal_entities}}';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['decision_date', 'effective_date'], 'safe'],
            [['inn', 'ogrn', 'kpp', 'name', 'participant_type', 'court', 'case_number'], 'string', 'max' => 1000],
        ];
    }

    public function attributeLabels()
    {
        return [
            'inn' => 'ИНН/Аналог ИНН',
            'ogrn' => 'ОГРН',
            'kpp' => 'КПП',
            'name' => 'Наименование ЮЛ',
            'participant_type' => 'Тип участника',
            'court' => 'Суд',
            'case_number' => 'Номер дела',
            'decision_date' => 'Дата вынесения постановления',
            'effective_date' => 'Дата вступления в законную силу постановления о назначении административного наказания',
        ];
    }

    public static function getHeaderToAttributeMap()
    {
        $labels = (new static())->attributeLabels();
        $map = [];
        foreach ($labels as $attribute => $label) {
            $map[$label] = $attribute;
        }
        return $map;
    }
}