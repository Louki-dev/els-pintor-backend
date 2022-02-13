<?php
namespace api\v1\controllers;

use api\v1\models\user\Tokenizer;
use core\misc\Utilities;


class Auth
{

    public function actionLogin()
    {
        return Tokenizer::login();
    }

    public function actionCheckToken()
    {
        return Tokenizer::checkToken();
    }

    public function actionCheckEmail()
    {
        return Tokenizer::checkEmail();
    }

    public function actionCheckKey()
    {
        return Tokenizer::checkKey();
    }

    public function actionGetUser()
    {
        return Tokenizer::getUser();
    }

    public function actionResetPass()
    {
        return Tokenizer::resetPass();
    }

    public function actionCreateKey()
    {
        return Tokenizer::createKey();
    }

    public function actionCheckVcode()
    {
        return Tokenizer::checkVcode();
    }
}
