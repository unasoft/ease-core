<?php

namespace ej\console\controllers;

use Yii;
use yii\helpers\Console;
use yii\console\Controller;

class HelpController extends \yii\console\controllers\HelpController
{
    /**
     * Displays all available commands.
     */
    protected function getDefaultHelp()
    {
        $commands = $this->getCommandDescriptions();
        $this->stdout("\nThis is ejCMS version " . \Yii::getVersion() . ".\n");
        if (!empty($commands)) {
            $this->stdout("\nThe following commands are available:\n\n", Console::BOLD);
            $len = 0;
            foreach ($commands as $command => $description) {
                $result = \Yii::$app->createController($command);
                if ($result !== false) {
                    /** @var $controller Controller */
                    list($controller, $actionID) = $result;
                    $actions = $this->getActions($controller);
                    if (!empty($actions)) {
                        $prefix = $controller->getUniqueId();
                        foreach ($actions as $action) {
                            $string = $prefix . '/' . $action;
                            if ($action === $controller->defaultAction) {
                                $string .= ' (default)';
                            }
                            if (($l = strlen($string)) > $len) {
                                $len = $l;
                            }
                        }
                    }
                } elseif (($l = strlen($command)) > $len) {
                    $len = $l;
                }
            }
            foreach ($commands as $command => $description) {
                $this->stdout('- ' . $this->ansiFormat($command, Console::FG_YELLOW));
                $this->stdout(str_repeat(' ', $len + 4 - strlen($command)));
                $this->stdout(Console::wrapText($description, $len + 4 + 2), Console::BOLD);
                $this->stdout("\n");

                $result = \Yii::$app->createController($command);
                if ($result !== false) {
                    list($controller, $actionID) = $result;
                    $actions = $this->getActions($controller);
                    if (!empty($actions)) {
                        $prefix = $controller->getUniqueId();
                        foreach ($actions as $action) {
                            $string = '  ' . $prefix . '/' . $action;
                            $this->stdout('  ' . $this->ansiFormat($string, Console::FG_GREEN));
                            if ($action === $controller->defaultAction) {
                                $string .= ' (default)';
                                $this->stdout(' (default)', Console::FG_YELLOW);
                            }
                            $summary = $controller->getActionHelpSummary($controller->createAction($action));
                            if ($summary !== '') {
                                $this->stdout(str_repeat(' ', $len + 4 - strlen($string)));
                                $this->stdout(Console::wrapText($summary, $len + 4 + 2));
                            }
                            $this->stdout("\n");
                        }
                    }
                    $this->stdout("\n");
                }
            }
            $scriptName = $this->getScriptName();
            $this->stdout("\nTo see the help of each command, enter:\n", Console::BOLD);
            $this->stdout("\n  $scriptName " . $this->ansiFormat('help', Console::FG_YELLOW) . ' '
                . $this->ansiFormat('<command-name>', Console::FG_CYAN) . "\n\n");
        } else {
            $this->stdout("\nNo commands are found.\n\n", Console::BOLD);
        }
    }
}