<?php

namespace ease\console\controllers;


use yii\console\Exception;
use yii\helpers\Console;
use yii\console\Controller;
use core\modules\user\helpers\User;
use yii\base\InvalidCallException;
use core\modules\user\Run as UserModule;
use core\modules\user\models\Identity;
use yii\base\InvalidParamException;

class UserController extends Controller
{
    /**
     * @inheritdoc
     */
    public function actionCreate($login, $password)
    {
        $authField = User::authFieldTypeByValue($login);

        $model = new Identity(['scenario' => Identity::SCENARIO_CREATE]);
        $model->setAttributes([
            $authField => $login,
            'password' => $password
        ]);

        if ($model->save()) {
            $this->stdout('User "' . $login . '" successful create ID "' . $model->getId() . '".' . PHP_EOL, Console::FG_GREEN);
        } else {
            $this->stdout("User Create Error!" . PHP_EOL, Console::FG_YELLOW);
            foreach ($model->getErrors() as $errors) {
                foreach ($errors as $error) {
                    $this->stdout(' - ' . $error . PHP_EOL, Console::FG_RED);
                }
            }
        }
    }

    /**
     * @param $userId
     * @param $ruleName
     *
     * @throws Exception
     */
    public function actionAssign($userId, $ruleName)
    {
        if (is_numeric($userId)) {
            $user = Identity::find()->where(['id' => $userId])->one();
        } else {
            $user = Identity::findByLogin($userId);
        }

        if (!$user) {
            throw new InvalidParamException("There is no user \"$userId\".");
        }

        $auth = \Yii::$app->getAuthManager();

        if (!($assign = $auth->getRole($ruleName))) {
            $assign = $auth->getPermission($ruleName);
        }

        if ($assign) {
            if (!$auth->getAssignment($ruleName, $user->id)) {
                $auth->assign($assign, $user->id);
                $this->stdout('Successful assign to user ' . $userId . PHP_EOL, Console::FG_GREEN);
            } else {
                $this->stdout('The roleName "' . $ruleName . '" already assigned to user: ' . $userId . PHP_EOL, Console::FG_RED);
                if ($this->confirm('You want revoke this roleName "' . $ruleName . '" to user: ' . $userId)) {
                    $auth->revoke($assign, $user->id);
                    $this->stdout('Successful revoked to user: ' . $userId . PHP_EOL, Console::FG_GREEN);
                }
            }
        } else {
            throw new Exception('Role or permission not found.');
        }
    }
}