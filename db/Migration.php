<?php

namespace ease\db;

use core\helpers\ArrayHelper;
use yii\console\Exception;
use yii\db\ColumnSchemaBuilder;
use yii\db\Expression;
use yii\db\Query;

class Migration extends \yii\db\Migration
{
    /**
     * @var
     */
    public $tableOptions;

    /**
     *
     */
    public function init()
    {
        parent::init();

        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $this->tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
    }

    /**
     * Creates a tinytext column for MySQL, or text column for others.
     *
     * @return ColumnSchemaBuilder the column instance which can be further customized.
     */
    public function tinyText()
    {
        if ($this->db->getDriverName() == 'mysql') {
            return $this->db->getSchema()->createColumnSchemaBuilder('tinytext');
        }

        return $this->text();
    }

    /**
     * Creates a mediumtext column for MySQL, or text column for others.
     *
     * @return ColumnSchemaBuilder the column instance which can be further customized.
     */
    public function mediumText()
    {
        if ($this->db->getDriverName() == 'mysql') {
            return $this->db->getSchema()->createColumnSchemaBuilder('mediumtext');
        }

        return $this->text();
    }

    /**
     * Creates a longtext column for MySQL, or text column for others.
     *
     * @return ColumnSchemaBuilder the column instance which can be further customized.
     */
    public function longText()
    {
        if ($this->db->getDriverName() == 'mysql') {
            return $this->db->getSchema()->createColumnSchemaBuilder('longtext');
        }

        return $this->text();
    }

    /**
     * Creates an enum column for MySQL, or a string column with a check constraint for others.
     *
     * @param string $columnName The column name
     * @param string[] $values   The allowed column values
     *
     * @return ColumnSchemaBuilder the column instance which can be further customized.
     */
    public function enum($columnName, $values)
    {
        // Quote the values
        $schema = $this->db->getSchema();
        $values = array_map([$schema, 'quoteValue'], $values);

        if ($this->db->getDriverName() == 'mysql') {
            return $this->db->getSchema()->createColumnSchemaBuilder('enum', $values);
        }

        return $this->string()->check($schema->quoteColumnName($columnName) . ' in (' . implode(',', $values) . ')');
    }

    /**
     * @param $data
     * @param bool $remove
     * @param string $sourceMessageTable
     * @param string $messageTable
     *
     * @throws Exception
     */
    public function processI18n($data, $remove = false, $sourceMessageTable = '{{%i18n_source}}', $messageTable = '{{%i18n_message}}')
    {
        if (!is_array($data) || empty($data)) {
            return;
        }

        list($new, $changed) = $this->uniqueMessages($data, $sourceMessageTable, $messageTable);

        if ($remove === true) {
            $currentMessages = [];
            $rows = (new Query)->select(['id', 'category', 'message'])->from($sourceMessageTable)->all($this->db);

            foreach ($rows as $row) {
                $currentMessages[$row['category']][$row['message']] = $row['id'];
            }

            foreach ($data as $category => $rows) {
                $ids = [];
                foreach ($rows as $source => $languages) {
                    if (isset($currentMessages[$category][$source])) {
                        $id = $currentMessages[$category][$source];
                        $ids[] = $id;
                        $delete = [];
                        foreach ($languages as $language => $translation) {
                            if (isset($currentMessages[$category][$source])) {
                                $delete[] = $language;
                            }
                        }

                        $this->delete($messageTable, ['id' => $id, 'language' => $delete]);

                        if (count($ids)) {
                            $this->execute("DELETE {$sourceMessageTable} FROM {$sourceMessageTable} 
LEFT JOIN {$messageTable} ON {$sourceMessageTable}.`id`={$messageTable}.`id` 
WHERE {$sourceMessageTable}.id IN ('" . implode("','", $ids) . "') AND {$messageTable}.`id` IS NULL");
                        }
                    }
                }
            }

            return;
        }

        foreach ($new as $category => $rows) {
            foreach ($rows as $source => $translations) {
                if (!empty($translations)) {
                    $deleteLanguages = [];
                    if (isset($changed[$category][$source])) {
                        foreach ($changed[$category][$source] as $idPK => $lngs) {
                            $last_id = $idPK;

                            foreach ($lngs as $lng => $msg) {
                                if (in_array($lng, array_keys($translations), true)) {
                                    $deleteLanguages[] = $lng;
                                }
                            }
                        }
                    } else {
                        $this->insert($sourceMessageTable, ['category' => $category, 'message' => $source]);
                        $last_id = $this->db->getLastInsertID();
                    }

                    if (!isset($last_id) || !$last_id) {
                        throw new Exception('Invalid message id.');
                    }

                    if (!empty($deleteLanguages)) {
                        $this->delete($messageTable, ['and', 'id=:last_id', ['in', 'language', $deleteLanguages]], [':last_id' => $last_id]);
                    }

                    foreach ($translations as $language => $translation) {
                        $this->insert($messageTable, ['id' => $last_id, 'language' => $language, 'translation' => $translation]);
                    }
                }
            }
        }
    }

    /**
     * @param $data
     * @param $sourceMessageTable
     * @param $messageTable
     *
     * @return array
     */
    protected function uniqueMessages($data, $sourceMessageTable, $messageTable)
    {
        $currentMessages = [];
        $rows = (new Query)->select(['id', 'category', 'message'])->from($sourceMessageTable)->all($this->db);

        foreach ($rows as $row) {
            $currentMessages[$row['category']][$row['id']] = $row['message'];
        }

        $currentTranslations = [];
        $rows = (new Query)->select('*')->from($messageTable)->all($this->db);
        foreach ($rows as $row) {
            $currentTranslations[$row['id']][$row['language']] = $row['translation'];
        }

        $merge = [];
        foreach ($currentMessages as $category => $message) {
            foreach ($message as $id => $source) {
                if (isset($currentTranslations[$id])) {
                    $merge[$category][$source] = $currentTranslations[$id];
                } else {
                    $merge[$category][$source] = [];
                }
            }
        }

        $new = [];
        $changed = [];
        foreach ($data as $category => $messages) {
            foreach ($messages as $source => $message) {
                if (isset($merge[$category][$source])) {
                    if ($msgs = array_diff($message, $merge[$category][$source])) {
                        if (!empty($new[$category][$source])) {
                            $new[$category][$source] = array_merge($new[$category][$source], $msgs);
                        } else {
                            $new[$category][$source] = $msgs;
                        }

                        if (isset($currentMessages[$category])) {
                            foreach ($currentMessages[$category] as $id => $msg) {
                                if ($source === $msg) {
                                    $changed[$category][$source][$id] = $msgs;
                                }
                            }
                        }
                    }
                } else {
                    $new[$category][$source] = $message;
                }
            }
        }

        return [$new, $changed];
    }
}