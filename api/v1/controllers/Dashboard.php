<?php
namespace api\v1\controllers;

use api\v1\models\process\Process;
use core\misc\Database;
use core\misc\Defaults;
use core\misc\Utilities;

class Dashboard
{

    public function __construct() 
    {
        $headers = Utilities::getHeaders();
        $authorization = '';
        $uid = '';
        
        foreach ($headers as $key => $valueHeader) {
            // $authorization = $key == 'Authorization' ? 'Authorization' : 'authorization';
            $uid = $key == 'Userid' ? 'Userid' : 'userid';
            // $uid = $key == 'userid' ? 'userid' : 'Userid';

        }
                
		$auth = Utilities::fetchRequiredDataFromArray($headers, 'Authorization');
		$userId = Utilities::fetchRequiredDataFromArray($headers, $uid);

        $tokenObj = (new Database())->processQuery("SELECT * FROM token WHERE token_user_id = ? AND token_token = ?", [$userId, $auth]);
        
        if (empty($tokenObj)) {
            return Utilities::responseWithException(Defaults::ERROR_401);
        }
    }
    
    public function actionStatistics()
    {
        return Process::statistics();
    }

    public function actionDashboard()
    {
        return Process::dashboard();
    }

    public function actionDashboardDetail()
    {
        return Process::dashboardDetail();
    }

    public function actionUpdateCustomer()
    {
        return Process::updateCustomer();
    }

    public function actionUpdateCustomerDates()
    {
        return Process::updateCustomerDates();
    }
    
    public function actionDeleteCustomer()
    {
        return Process::deleteCustomer();
    }

    //=====================

    public function actionGetEmployeeList()
    {
        return Process::getEmployeeList();
    }

    public function actionGetEmployeeTask()
    {
        return Process::getEmployeeTask();
    }

    public function actionGetAssignEmployee()
    {
        return Process::getAssignEmployee();
    }

    public function actionGetActiveEmployeeList()
    {
        return Process::getActiveEmployeeList();
    }

    public function actionGetActiveEmployeeListDashboard()
    {
        return Process::getActiveEmployeeListDashboard();
    }

    public function actionCreateEmployee()
    {
        return Process::createEmployee();
    }

    public function actionDeleteEmployee()
    {
        return Process::deleteEmployee();
    }
    
    public function actionDeleteEmployeeCheck()
    {
        return Process::deleteEmployeeCheck();
    }

    public function actionUpdateEmployee()
    {
        return Process::updateEmployee();
    }

    public function actionGetEmployee()
    {
        return Process::getEmployee();
    }

    //=====================

    public function actionGetTodoList()
    {
        return Process::getTodoList();
    }
    
    public function actionGetCompletedList()
    {
        return Process::getCompletedList();
    }
    
    public function actionGetTodoDetail()
    {
        return Process::getTodoDetail();
    }

    public function actionCreateTodo()
    {
        return Process::createTodo();
    }

    public function actionUpdateTodo()
    {
        return Process::updateTodo();
    }

    public function actionDeleteTodo()
    {
        return Process::deleteTodo();
    }
    
    //=====================
    
    public function actionDashboardMessage()
    {
        return Process::dashboardMessage();
    }

    public function actionCreateMessage()
    {
        return Process::createMessage();
    }

    public function actionGetMessage()
    {
        return Process::getSentMessages();
    }

    public function actionGetMessageDetail()
    {
        return Process::getSentMessagesDetail();
    }


    public function actionDeleteSentMessages()
    {
        return Process::deleteSentMessage();
    }

    public function actionCreateContects()
    {
        return Process::createContacts();
    }

    //==========================

    public function actionCreateService()
    {
        return Process::createService();
    }

    public function actionGetServiceList()
    {
        return Process::getServiceList();
    }
    
    public function actionCreateProduct()
    {
        return Process::createProduct();
    }

    public function actionGetProductList()
    {
        return Process::getProductList();
    }

    public function actionUpdateService()
    {
        return Process::updateService();
    }

    public function actionDeleteService()
    {
        return Process::deleteService();
    }
    
    public function actionServiceDetail()
    {
        return Process::serviceDetail();
    }

    public function actionUpdateProduct()
    {
        return Process::updateProduct();
    }

    public function actionDeleteProduct()
    {
        return Process::deleteProduct();
    }

    public function actionGetUser()
    {
        return Process::getUser();
    }

    public function actionChangeUser()
    {
        return Process::changeUser();
    }

    public function actionChangePass()
    {
        return Process::changePass();
    }

    public function actionCheckAdmin()
    {
        return Process::checkAdmin();
    }

    public function actionCheckPass()
    {
        return Process::checkPass();
    }

    public function actionCheckDate()
    {
        return Process::checkDate();
    }
}
