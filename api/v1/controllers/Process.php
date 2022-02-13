<?php
namespace api\v1\controllers;

use api\v1\models\process\Process as ProcessProcess;
use core\misc\Utilities;

use core\misc\Database;

class Process
{
 
    public function actionDd()
    {

        $optIn = (new Database())->processQuery("SELECT opt_in_mobile_number FROM opt_in WHERE opt_in_mobile_number =?", ['945d2567866']);     
        var_dump($optIn);
    }
    public function actionCreateInquiry()
    {
        return ProcessProcess::createInquiry();
    }

    public function actionGetServices()
    {
        return ProcessProcess::getServices();
    }

    public function actionGetProducts()
    {
        return ProcessProcess::getProducts();
    }
}
