<?php

namespace ej\console\controllers;


use Yii;
use yii\db\Query;
use yii\db\Connection;
use yii\db\Schema;
use yii\helpers\Console;
use yii\base\ActionEvent;
use ej\helpers\FileHelper;
use yii\console\Exception;
use yii\console\Controller;
use ej\helpers\ArrayHelper;


class MigrateController extends Controller
{
    /**
     * The name of the dummy migration that marks the beginning of the whole migration history.
     */
    const BASE_MIGRATION = 'm000000_000000_base';
    /**
     * @var string the default command action.
     */
    public $defaultAction = 'up';
    /**
     * @var string the directory storing the migration classes. This can be either
     * a path alias or a directory.
     */
    public $migrationPath = '@core/migrations';
    /**
     * @var array additional aliases of migration directories
     */
    public $migrationLookup = [
        '@core/modules/*/migrations',
        '@app/backend/migrations',
        '@app/console/migrations',
        '@app/frontend/migrations',
        '@app/backend/modules/*/migrations',
        '@app/frontend/modules/*/migrations'
    ];
    /**
     * @var string the name of the table for keeping applied migration information.
     */
    public $migrationTable = '{{%migration}}';
    /**
     * @var string
     */
    public $migrationDir = 'migrations';
    /**
     * @var string the template file for generating new migrations.
     * This can be either a path alias (e.g. "@app/migrations/template.php")
     * or a file path.
     */
    public $templateFile = '@core/templates/migration.php';
    /**
     * @var array
     */
    public $generatorTemplateFiles = [
        'create_table'    => '@yii/views/createTableMigration.php',
        'drop_table'      => '@yii/views/dropTableMigration.php',
        'add_column'      => '@yii/views/addColumnMigration.php',
        'drop_column'     => '@yii/views/dropColumnMigration.php',
        'create_junction' => '@yii/views/createTableMigration.php',
    ];
    /**
     * @var boolean indicates whether the table names generated should consider
     * the `tablePrefix` setting of the DB connection. For example, if the table
     * name is `post` the generator wil return `{{%post}}`.
     * @since 2.0.8
     */
    public $useTablePrefix = true;
    /**
     * @var
     */
    public $alias;
    /**
     * @var array
     */
    public $fields = [];

    /**
     * @var Connection|string the DB connection object or the application
     * component ID of the DB connection.
     */
    public $db = 'db';

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        return array_merge(
            parent::options($actionId),
            ['migrationPath', 'migrationLookup', 'migrationTable', 'db', 'alias'], // global for all actions
            ($actionId == 'create') ? ['templateFile'] : [] // action create
        );
    }

    /**
     * @inheritdoc
     * @since 2.0.8
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'f' => 'fields',
            'p' => 'migrationPath',
            't' => 'migrationTable',
            'F' => 'templateFile',
            'P' => 'useTablePrefix',
            'a' => 'alias',
        ]);
    }

    /**
     * @param \yii\base\Action $action
     *
     * @return bool
     * @throws Exception
     */
    public function beforeAction($action)
    {
        $event = new ActionEvent($action);
        $this->trigger(self::EVENT_BEFORE_ACTION, $event);

        if ($event->isValid) {
            if ($action->id !== 'create') {
                if (is_string($this->db)) {
                    $this->db = \Yii::$app->get($this->db);
                }

                $this->checkDatabaseConnection();

                if (!$this->db instanceof Connection) {
                    throw new Exception(
                        "The 'db' option must refer to the application component ID of a DB connection."
                    );
                }
            }
            $version = Yii::getYiiVersion();
            $this->stdout("Migration Tool (based on Yii v{$version})\n\n", Console::BOLD);
            if (isset($this->db->dsn)) {
                $this->stdout("Database Connection: " . $this->db->dsn . "\n", Console::FG_BLUE);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $limit
     */
    public function actionUp($limit = 0)
    {
        $migrations = $this->getNewMigrations();
        if (empty($migrations)) {
            $this->stdout("No new migration found. Your system is up-to-date.\n");
            return;
        }
        $total = count($migrations);
        $limit = (int)$limit;
        if ($limit > 0) {
            $migrations = array_slice($migrations, 0, $limit);
        }
        $n = count($migrations);
        if ($n === $total) {
            $this->stdout("Total $n new " . ($n === 1 ? 'migration' : 'migrations') . " to be applied:\n");
        } else {
            $this->stdout("Total $n out of $total new " . ($total === 1 ? 'migration' : 'migrations') . " to be applied:\n");
        }
        $this->stdout("\nMigrations:\n");
        foreach ($migrations as $migration => $alias) {
            $this->stdout("    " . $migration . " (" . $alias . ")\n");
        }
        if ($this->confirm('Apply the above ' . ($n === 1 ? 'migration' : 'migrations') . "?")) {
            foreach ($migrations as $migration => $alias) {
                if (!$this->migrateUp($migration, $alias)) {
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n");
                    return;
                }
            }
            $this->stdout("\nMigrated up successfully.\n", Console::FG_GREEN);
        }
    }

    /**
     * @param int $limit
     *
     * @return int|void
     * @throws Exception
     */
    public function actionDown($limit = 1)
    {
        $limit = (int)$limit;
        if ($limit < 1) {
            throw new Exception("The step argument must be greater than 0.");
        }
        $migrations = $this->getMigrationHistory($limit);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n");
            return;
        }
        $n = count($migrations);
        $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be reverted:\n");
        foreach ($migrations as $migration => $info) {
            $this->stdout("    $migration (" . $info['alias'] . ")\n");
        }
        $this->stdout("\n");
        if ($this->confirm('Revert the above ' . ($n === 1 ? 'migration' : 'migrations') . "?")) {
            $reverted = 0;
            foreach ($migrations as $migration => $info) {
                if (!$this->migrateDown($migration, $info['alias'])) {
                    $this->stdout("\n$reverted from $n " . ($reverted === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

                    return self::EXIT_CODE_ERROR;
                }
                $reverted++;
            }
            $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_GREEN);
            $this->stdout("\nMigrated down successfully.\n", Console::FG_GREEN);
        }
    }

    /**
     * Redoes the last few migrations.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ~~~
     * yii migrate/redo     # redo the last applied migration
     * yii migrate/redo 3   # redo the last 3 applied migrations
     * ~~~
     *
     * @param integer $limit the number of migrations to be redone. Defaults to 1,
     *                       meaning the last applied migration will be redone.
     *
     * @throws Exception if the number of the steps specified is less than 1.
     */
    public function actionRedo($limit = 1)
    {
        $limit = (int)$limit;
        if ($limit < 1) {
            throw new Exception("The step argument must be greater than 0.");
        }
        $migrations = $this->getMigrationHistory($limit);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n");
            return;
        }
        $n = count($migrations);
        $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be redone:\n");
        foreach ($migrations as $migration => $info) {
            $this->stdout("    $migration\n");
        }
        $this->stdout("\n");
        if ($this->confirm('Redo the above ' . ($n === 1 ? 'migration' : 'migrations') . "?")) {
            foreach ($migrations as $migration => $info) {
                if (!$this->migrateDown($migration, $info['alias'])) {
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n");
                    return;
                }
            }
            foreach (array_reverse($migrations) as $migration => $info) {
                if (!$this->migrateUp($migration, $info['alias'])) {
                    $this->stdout("\nMigration failed. The rest of the migrations migrations are canceled.\n");
                    return;
                }
            }
            $this->stdout("\nMigration redone successfully.\n");
        }
    }

    /**
     * Upgrades or downgrades till the specified version.
     *
     * Can also downgrade versions to the certain apply time in the past by providing
     * a UNIX timestamp or a string parseable by the strtotime() function. This means
     * that all the versions applied after the specified certain time would be reverted.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ~~~
     * yii migrate/to 101129_185401                      # using timestamp
     * yii migrate/to m101129_185401_create_user_table   # using full name
     * yii migrate/to 1392853618                         # using UNIX timestamp
     * yii migrate/to "2014-02-15 13:00:50"              # using strtotime() parseable string
     * ~~~
     *
     * @param string $version either the version name or the certain time value in the past
     *                        that the application should be migrated to. This can be either the timestamp,
     *                        the full name of the migration, the UNIX timestamp, or the parseable datetime
     *                        string.
     *
     * @throws Exception if the version argument is invalid.
     */
    public function actionTo($version)
    {
        if (preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)) {
            $this->migrateToVersion('m' . $matches[1]);
        } elseif ((string)(int)$version == $version) {
            $this->migrateToTime($version);
        } elseif (($time = strtotime($version)) !== false) {
            $this->migrateToTime($time);
        } else {
            throw new Exception(
                "The version argument must be either a timestamp (e.g. 101129_185401),\n the full name of a migration (e.g. m101129_185401_create_user_table),\n a UNIX timestamp (e.g. 1392853000), or a datetime string parseable\nby the strtotime() function (e.g. 2014-02-15 13:00:50)."
            );
        }
    }

    /**
     * Modifies the migration history to the specified version.
     *
     * No actual migration will be performed.
     *
     * ~~~
     * yii migrate/mark 101129_185401                      # using timestamp
     * yii migrate/mark m101129_185401_create_user_table   # using full name
     * ~~~
     *
     * @param string $version the version at which the migration history should be marked.
     *                        This can be either the timestamp or the full name of the migration.
     *
     * @throws Exception if the version argument is invalid or the version cannot be found.
     */
    public function actionMark($version)
    {
        $originalVersion = $version;
        if (preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)) {
            $version = 'm' . $matches[1];
        } else {
            throw new Exception(
                "The version argument must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table)."
            );
        }
        // try mark up
        $migrations = $this->getNewMigrations();
        $i = 0;
        foreach ($migrations as $migration => $alias) {
            $stack[$migration] = $alias;
            if (strpos($migration, $version . '_') === 0) {
                if ($this->confirm("Set migration history at $originalVersion?")) {
                    $command = $this->db->createCommand();
                    foreach ($stack AS $applyMigration => $applyAlias) {
                        $command->insert(
                            $this->migrationTable,
                            [
                                'version'    => $applyMigration,
                                'alias'      => $applyAlias,
                                'apply_time' => time(),
                            ]
                        )->execute();
                    }
                    $this->stdout("The migration history is set at $originalVersion.\nNo actual migration was performed.\n");
                }
                return;
            }
            $i++;
        }
        // try mark down
        $migrations = array_keys($this->getMigrationHistory(-1));
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version . '_') === 0) {
                if ($i === 0) {
                    $this->stdout("Already at '$originalVersion'. Nothing needs to be done.\n");
                } else {
                    if ($this->confirm("Set migration history at $originalVersion?")) {
                        $command = $this->db->createCommand();
                        for ($j = 0; $j < $i; ++$j) {
                            $command->delete(
                                $this->migrationTable,
                                [
                                    'version' => $migrations[$j],
                                ]
                            )->execute();
                        }
                        $this->stdout("The migration history is set at $originalVersion.\nNo actual migration was performed.\n");
                    }
                }
                return;
            }
        }
        throw new Exception("Unable to find the version '$originalVersion'.");
    }

    /**
     * Displays the migration history.
     *
     * This command will show the list of migrations that have been applied
     * so far. For example,
     *
     * ~~~
     * yii migrate/history     # showing the last 10 migrations
     * yii migrate/history 5   # showing the last 5 migrations
     * yii migrate/history 0   # showing the whole history
     * ~~~
     *
     * @param integer $limit the maximum number of migrations to be displayed.
     *                       If it is 0, the whole migration history will be displayed.
     */
    public function actionHistory($limit = 10)
    {
        $limit = (int)$limit;
        $migrations = $this->getMigrationHistory($limit);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n");
        } else {
            $n = count($migrations);
            if ($limit > 0) {
                $this->stdout("Showing the last $n applied " . ($n === 1 ? 'migration' : 'migrations') . ":\n");
            } else {
                $this->stdout("Total $n " . ($n === 1 ? 'migration has' : 'migrations have') . " been applied before:\n");
            }
            foreach ($migrations as $version => $info) {
                $this->stdout("    (" . date('Y-m-d H:i:s', $info['apply_time']) . ') ' . $version . "\n");
            }
        }
    }

    /**
     * Displays the un-applied new migrations.
     *
     * This command will show the new migrations that have not been applied.
     * For example,
     *
     * ~~~
     * yii migrate/new     # showing the first 10 new migrations
     * yii migrate/new 5   # showing the first 5 new migrations
     * yii migrate/new 0   # showing all new migrations
     * ~~~
     *
     * @param integer $limit the maximum number of new migrations to be displayed.
     *                       If it is 0, all available new migrations will be displayed.
     */
    public function actionNew($limit = 10)
    {
        $limit = (int)$limit;
        $migrations = $this->getNewMigrations();
        if (empty($migrations)) {
            $this->stdout("No new migrations found. Your system is up-to-date.\n");
        } else {
            $n = count($migrations);
            if ($limit > 0 && $n > $limit) {
                $migrations = array_slice($migrations, 0, $limit);
                $this->stdout("Showing $limit out of $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n");
            } else {
                $this->stdout("Found $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n");
            }
            foreach ($migrations as $migration => $alias) {
                $this->stdout("    " . $migration . " (" . $alias . ")" . "\n");
            }
        }
    }

    /**
     * @param $name
     *
     * @throws Exception
     */
    public function actionCreate($name)
    {
        if (!preg_match('/^[\w\\\\]+$/', $name)) {
            throw new Exception('The migration name should contain letters, digits, underscore and/or backslash characters only.');
        }

        list($namespace, $className) = $this->generateClassName($name);
        $migrationPath = $this->findMigrationPath($namespace);

        $file = FileHelper::getAlias($migrationPath) . DIRECTORY_SEPARATOR . $className . '.php';
        if ($this->confirm("Create new migration '$className' ? (" . $migrationPath . ")")) {
            $content = $this->generateMigrationSourceCode([
                'name'      => $name,
                'className' => $className,
                'namespace' => $namespace,
            ]);
            FileHelper::createDirectory(FileHelper::getAlias($migrationPath));
            file_put_contents($file, $content);
            $this->stdout("New migration created successfully.\n", Console::FG_GREEN);
        }
    }

    /**
     * Upgrades with the specified migration class.
     *
     * @param string $class the migration class name
     *
     * @return boolean whether the migration is successful
     */
    protected function migrateUp($class, $alias)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }
        $this->stdout("*** applying $class\n");
        $start = microtime(true);
        $migration = $this->createMigration($class, $alias);
        if ($migration->up() !== false) {
            $this->db->createCommand()->insert(
                $this->migrationTable,
                [
                    'version'    => $class,
                    'alias'      => $alias,
                    'apply_time' => time(),
                ]
            )->execute();
            $time = microtime(true) - $start;
            $this->stdout("*** applied $class (time: " . sprintf("%.3f", $time) . "s)\n\n");
            return true;
        } else {
            $time = microtime(true) - $start;
            $this->stdout("*** failed to apply $class (time: " . sprintf("%.3f", $time) . "s)\n\n");
            return false;
        }
    }

    /**
     * Downgrades with the specified migration class.
     *
     * @param string $class the migration class name
     *
     * @return boolean whether the migration is successful
     */
    protected function migrateDown($class, $alias)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }
        $this->stdout("*** reverting $class\n");
        $start = microtime(true);
        $migration = $this->createMigration($class, $alias);
        if ($migration->down() !== false) {
            $this->db->createCommand()->delete(
                $this->migrationTable,
                [
                    'version' => $class,
                ]
            )->execute();
            $time = microtime(true) - $start;
            $this->stdout("*** reverted $class (time: " . sprintf("%.3f", $time) . "s)\n\n", Console::FG_GREEN);
            return true;
        } else {
            $time = microtime(true) - $start;
            $this->stdout("*** failed to revert $class (time: " . sprintf("%.3f", $time) . "s)\n", Console::FG_RED);
            return false;
        }
    }

    /**
     * @param $class
     * @param $alias
     *
     * @return mixed
     */
    protected function createMigration($class, $alias)
    {
        $class = trim($class, '\\');

        if (strpos($class, '\\') === false) {
            $namespace = $this->aliasToNamespace($alias) . '\\' . $class;
            if (!class_exists($namespace)) {
                $file = $class . '.php';
                require_once(\FileHelper::getAlias($alias) . DIRECTORY_SEPARATOR . $file);
            } else {
                $class = $namespace;
            }
        }

        return new $class(['db' => $this->db]);
    }

    /**
     * Migrates to the specified apply time in the past.
     *
     * @param integer $time UNIX timestamp value.
     */
    protected function migrateToTime($time)
    {
        $count = 0;
        $migrations = array_values($this->getMigrationHistory(-1));
        while ($count < count($migrations) && $migrations[$count] > $time) {
            ++$count;
        }
        if ($count === 0) {
            $this->stdout("Nothing needs to be done.\n");
        } else {
            $this->actionDown($count);
        }
    }

    /**
     * @inheritdoc
     */
    protected function migrateToVersion($version)
    {
        $originalVersion = $version;
        // try migrate up
        $migrations = $this->getNewMigrations();
        $i = 0;
        foreach ($migrations as $migration => $alias) {
            if (strpos($migration, $version . '_') === 0) {
                $this->actionUp($i + 1);
                return self::EXIT_CODE_NORMAL;
            }
            $i++;
        }
        // try migrate down
        $migrations = array_keys($this->getMigrationHistory(null));
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version . '_') === 0) {
                if ($i === 0) {
                    $this->stdout("Already at '$originalVersion'. Nothing needs to be done.\n");
                } else {
                    $this->actionDown($i);
                }
                return self::EXIT_CODE_NORMAL;
            }
        }
        throw new Exception("Unable to find the version '$originalVersion'.");
    }

    /**
     * @inheritdoc
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $query = new Query();
        $rows = $query->select(['version', 'alias', 'apply_time'])
            ->from($this->migrationTable)
            ->orderBy('migrate_id DESC')
            ->limit($limit)
            ->createCommand($this->db)
            ->queryAll();
        $history = ArrayHelper::map($rows, 'version', 'apply_time');
        foreach ($rows AS $row) {
            $history[$row['version']] = ['apply_time' => $row['apply_time'], 'alias' => $row['alias']];
        }
        unset($history[self::BASE_MIGRATION]);
        return $history;
    }

    /**
     * @inheritdoc
     */
    protected function createMigrationHistoryTable()
    {
        $tableName = $this->db->schema->getRawTableName($this->migrationTable);
        $this->stdout("Creating migration history table \"$tableName\"...");
        $this->db->createCommand()->createTable($this->migrationTable, [
            'migrate_id' => $this->db->getSchema()->createColumnSchemaBuilder(Schema::TYPE_PK),
            'version'    => 'varchar(180) NOT NULL',
            'alias'      => 'varchar(180) NOT NULL DEFAULT \'\'',
            'apply_time' => 'integer',
        ])->execute();
        $this->db->createCommand()->insert($this->migrationTable, [
            'version'    => self::BASE_MIGRATION,
            'apply_time' => time(),
        ])->execute();
        $this->stdout("done.\n");
    }

    /**
     * Returns the migrations that are not applied.
     * @return array list of new migrations, (key: migration version; value: alias)
     */
    protected function getNewMigrations()
    {
        $applied = [];
        foreach ($this->getMigrationHistory(-1) as $class => $info) {
            $applied[trim($class, '\\')] = $info['alias'];
        }

        $this->migrationLookup = ArrayHelper::merge([$this->migrationPath], $this->migrationLookup);

        $migrations = [];
        $this->stdout("\nLookup:\n");
        foreach ($this->collectMigrationPaths() AS $alias) {
            $dir = FileHelper::getAlias($alias);
            if (!is_dir($dir)) {
                $label = $this->ansiFormat('[warn]', Console::FG_YELLOW);
                $this->stdout(" {$label}  " . $alias . "\n");
                Yii::warning("Migration lookup directory '{$alias}' not found", __METHOD__);
                continue;
            }

            $handle = opendir($dir);
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (preg_match('/^(m(\d{6}_?\d{6})\D.*?)\.php$/is', $file, $matches) && is_file(
                        $path
                    ) && (!isset($applied[$matches[1]]) || $applied[$matches[1]] !== $alias)
                ) {
                    $migrations[$matches[1]] = $alias;
                }
            }
            closedir($handle);
            $label = $this->ansiFormat('[ok]', Console::FG_GREEN);
            $this->stdout(" {$label}    " . $alias . "\n");
        }

        $this->stdout("\n");
        return $migrations;
    }

    /**
     * @inheritdoc
     */
    protected function collectMigrationPaths()
    {
        $lookup = [];
        foreach ($this->migrationLookup as $path) {
            if (strpos($path, '*') === false) {
                $lookup[] = $path;
                continue;
            }

            $prefix = '';
            $length = 0;
            if (strncmp($path, '@', 1) === 0) {
                $n = strpos($path, '/');
                $prefix = substr($path, 0, $n) . DIRECTORY_SEPARATOR;

                $length = strlen(FileHelper::getAlias($prefix));
            }

            $paths = glob(FileHelper::getAlias($path), GLOB_ONLYDIR);

            if ($paths === false) {
                throw new \RuntimeException('glob() returned error while searching in \'' . $path . '\'');
            }

            foreach ($paths as $alias) {
                if ($length) {
                    $alias = $prefix . substr($alias, $length);
                }

                $lookup[] = $alias;
            }
        }

        return $lookup;
    }

    /**
     * @param $name
     *
     * @return array
     */
    private function generateClassName($name)
    {
        $namespace = null;
        $name = trim($name, '\\');

        if (strpos($name, '\\') !== false) {
            $namespace = substr($name, 0, strrpos($name, '\\'));
            $name = substr($name, strrpos($name, '\\') + 1);
        } elseif ($this->alias) {
            $namespace = $this->aliasToNamespace($this->alias);
        }

        if ($namespace === null) {
            $class = 'm' . gmdate('ymd_His') . '_' . $name;
        } else {
            $class = 'M' . gmdate('ymdHis') . ucfirst($name);
        }

        return [$namespace, $class];
    }

    /**
     * @param $alias
     *
     * @return mixed
     */
    private function aliasToNamespace($alias)
    {
        return str_replace(['@', '/'], ['', '\\'], $alias);
    }

    /**
     * @param $namespace
     *
     * @return string
     */
    private function namespaceToAlias($namespace)
    {
        return '@' . str_replace('\\', '/', $namespace);
    }

    /**
     * @param $namespace
     *
     * @return string
     */
    private function findMigrationPath($namespace)
    {
        if (empty($namespace)) {
            return $this->migrationPath;
        }
        return $this->getNamespacePath($namespace);
    }

    /**
     * @param $namespace
     *
     * @return mixed
     */
    private function getNamespacePath($namespace)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $this->normalizeNamespacePath($namespace));
    }

    /**
     * @param $namespace
     *
     * @return string
     */
    private function normalizeNamespacePath($namespace)
    {
        return '@' . str_replace('\\', '/', $namespace);
    }

    /**
     * @param $params
     *
     * @return string
     */
    protected function generateMigrationSourceCode($params)
    {
        $parsedFields = $this->parseFields();
        $fields = $parsedFields['fields'];
        $foreignKeys = $parsedFields['foreignKeys'];

        $name = $params['name'];

        $templateFile = $this->templateFile;
        $table = null;
        if (preg_match('/^create_junction(?:_table_for_|_for_|_)(.+)_and_(.+)_tables?$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['create_junction'];
            $firstTable = mb_strtolower($matches[1], \Yii::$app->charset);
            $secondTable = mb_strtolower($matches[2], \Yii::$app->charset);

            $fields = array_merge(
                [
                    [
                        'property'   => $firstTable . '_id',
                        'decorators' => 'integer()',
                    ],
                    [
                        'property'   => $secondTable . '_id',
                        'decorators' => 'integer()',
                    ],
                ],
                $fields,
                [
                    [
                        'property' => 'PRIMARY KEY(' .
                            $firstTable . '_id, ' .
                            $secondTable . '_id)',
                    ],
                ]
            );

            $foreignKeys[$firstTable . '_id'] = $firstTable;
            $foreignKeys[$secondTable . '_id'] = $secondTable;
            $table = $firstTable . '_' . $secondTable;
        } elseif (preg_match('/^add_(.+)_columns?_to_(.+)_table$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['add_column'];
            $table = mb_strtolower($matches[2], \Yii::$app->charset);
        } elseif (preg_match('/^drop_(.+)_columns?_from_(.+)_table$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['drop_column'];
            $table = mb_strtolower($matches[2], \Yii::$app->charset);
        } elseif (preg_match('/^create_(.+)_table$/', $name, $matches)) {
            $this->addDefaultPrimaryKey($fields);
            $templateFile = $this->generatorTemplateFiles['create_table'];
            $table = mb_strtolower($matches[1], \Yii::$app->charset);
        } elseif (preg_match('/^drop_(.+)_table$/', $name, $matches)) {
            $this->addDefaultPrimaryKey($fields);
            $templateFile = $this->generatorTemplateFiles['drop_table'];
            $table = mb_strtolower($matches[1], \Yii::$app->charset);
        }

        foreach ($foreignKeys as $column => $relatedTable) {
            $foreignKeys[$column] = [
                'idx'          => $this->generateTableName("idx-$table-$column"),
                'fk'           => $this->generateTableName("fk-$table-$column"),
                'relatedTable' => $this->generateTableName($relatedTable),
            ];
        }

        return $this->renderFile(FileHelper::getAlias($templateFile), array_merge($params, [
            'table'       => $this->generateTableName($table),
            'fields'      => $fields,
            'foreignKeys' => $foreignKeys,
        ]));
    }

    /**
     * If `useTablePrefix` equals true, then the table name will contain the
     * prefix format.
     *
     * @param string $tableName the table name to generate.
     *
     * @return string
     * @since 2.0.8
     */
    protected function generateTableName($tableName)
    {
        if (!$this->useTablePrefix) {
            return $tableName;
        }
        return '{{%' . $tableName . '}}';
    }

    /**
     * Parse the command line migration fields
     * @return array parse result with following fields:
     *
     * - fields: array, parsed fields
     * - foreignKeys: array, detected foreign keys
     *
     * @since 2.0.7
     */
    protected function parseFields()
    {
        $fields = [];
        $foreignKeys = [];

        foreach ($this->fields as $index => $field) {
            $chunks = preg_split('/\s?:\s?/', $field, null);
            $property = array_shift($chunks);

            foreach ($chunks as $i => &$chunk) {
                if (strpos($chunk, 'foreignKey') === 0) {
                    preg_match('/foreignKey\((\w*)\)/', $chunk, $matches);
                    $foreignKeys[$property] = isset($matches[1])
                        ? $matches[1]
                        : preg_replace('/_id$/', '', $property);

                    unset($chunks[$i]);
                    continue;
                }

                if (!preg_match('/^(.+?)\(([^(]+)\)$/', $chunk)) {
                    $chunk .= '()';
                }
            }
            $fields[] = [
                'property'   => $property,
                'decorators' => implode('->', $chunks),
            ];
        }

        return [
            'fields'      => $fields,
            'foreignKeys' => $foreignKeys,
        ];
    }

    /**
     * Adds default primary key to fields list if there's no primary key specified
     *
     * @param array $fields parsed fields
     *
     * @since 2.0.7
     */
    protected function addDefaultPrimaryKey(&$fields)
    {
        foreach ($fields as $field) {
            if ($field['decorators'] === 'primaryKey()' || $field['decorators'] === 'bigPrimaryKey()') {
                return;
            }
        }
        array_unshift($fields, ['property' => 'id', 'decorators' => 'primaryKey()']);
    }

    /**
     * @param string $string
     *
     * @return void
     */
    public function stdout($string)
    {
        if ($this->isColorEnabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        echo $string;
    }

    /**
     * @inheritdoc
     */
    protected function checkDatabaseConnection()
    {
        try {
            $this->db->open();
        } catch (\Exception $e) {
            $this->stdout("  -- Error Establishing a Database Connection." . PHP_EOL, Console::FG_RED);
            exit(-1);
        }
    }
}