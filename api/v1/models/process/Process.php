<?php
namespace api\v1\models\process;

use api\v1\models\globe\GlobeLabs;
use core\misc\Database;
use core\misc\Defaults;
use core\misc\Utilities;
use DateTime;

class Process
{
    
    public static function statistics()
    {
        $customers = (new Database())->processQuery("SELECT count(*) as `count`, customer_status FROM customer GROUP BY customer_status", []);
        $employees = (new Database())->processQuery("SELECT count(*) as `count`, emp_status FROM employee GROUP BY emp_status", []);
        // $todos = (new Database())->processQuery("SELECT count(*) as `count`, todo_status FROM todo GROUP BY todo_status", []);
        $todos = (new Database())->processQuery("SELECT count(*) as `count`, customer_status FROM customer GROUP BY customer_status", []);

        
        return Utilities::response(true, null, [
            "customer" => self::processStatuses($customers, 'customer_status'),
            "employee" => self::processStatuses($employees, 'emp_status'),
            // "todo" => self::processStatuses($todos, 'todo_status'),
            "todo" => self::processStatuses($todos, 'customer_status'),

        ]);
    }

    public static function processStatuses($data, $columnStatus)
    {
        $status = [];

        foreach ($data as $row) {
            $status[(string)$columnStatus.'_'.$row[$columnStatus]] = $row['count'];
            $status[(string)$columnStatus.'__'.$row[$columnStatus]] = $row['count'];

        }

        return $status;
    }

    public static function dashboard()
    {
        $search = Utilities::fetchDataFromArray($_GET, 'search');

        if (is_null($search) || $search == ''){
            $customers = (new Database())->processQuery("SELECT * FROM customer ORDER BY customer_updated_at DESC, customer_created_at DESC", []);
            $output = [];
        }else {
            $search = "%{$search}%";
            $customers = (new Database())->processQuery("SELECT * FROM customer WHERE customer_last_name like ? or customer_first_name like ? ORDER BY customer_updated_at DESC, customer_created_at DESC", [$search, $search]);
        }
        
        foreach ($customers as $customer) {
            $output[$customer['customer_status']][] = $customer;
        }

        return Utilities::response(true, null, $output);
    }

    public static function dashboardDetail()
    {
        $customerId = Utilities::fetchRequiredDataFromArray($_GET, 'customer_id');
        $customers = (new Database())->processQuery("SELECT * FROM customer WHERE customer_id = ?", [$customerId]);

        return Utilities::response(true, null, $customers);
    }

    public static function updateCustomer()
    {
        $customerId = Utilities::fetchRequiredDataFromArray($_POST, 'customer_id');
        $status = Utilities::fetchRequiredDataFromArray($_POST, 'status');
        // $assignEmp = Utilities::fetchRequiredDataFromArray($_POST, 'assignEmp'); 

        $currentData = Utilities::getCurrentDate();
        
        $customer = (new Database())->processQuery("UPDATE customer SET customer_status = ?, customer_updated_at = ? WHERE customer_id = ?", [$status, $currentData, $customerId]);
        // $employee = (new Database())->processQuery("UPDATE employee SET emp_work_status = ? WHERE emp_id = ?", [1, $assignEmp]);
        return Utilities::response(((!empty($customer['response']) && $customer['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function updateCustomerDates()
    {

        $customerId = Utilities::fetchRequiredDataFromArray($_POST, 'customer_id');
        $started = Utilities::formatDate(Utilities::fetchRequiredDataFromArray($_POST, 'started'), 'Y-m-d H:i:s');
        $duedate = Utilities::formatDate(Utilities::fetchRequiredDataFromArray($_POST, 'duedate'), 'Y-m-d H:i:s');
        $contract = Utilities::imgDataUploader(Utilities::fetchRequiredDataFromArray($_POST, 'contract'));
        $customer = (new Database())->processQuery("UPDATE customer SET  customer_start_date = ?, customer_due_date = ? , customer_contract = ? WHERE customer_id = ?", [$started, $duedate, $contract['content']['path'], $customerId]);

        return Utilities::response(((!empty($customer['response']) && $customer['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function deleteCustomer()
    {
        $customerId = Utilities::fetchRequiredDataFromArray($_POST, 'customer_id');
        // $customer = (new Database())->processQuery("DELETE FROM customer WHERE customer_id = ?", [$customerId]);

        $checkCustomer = (new Database())->processQuery("SELECT * from `customer` WHERE customer_id = ?", [$customerId]);

        if (!empty($checkCustomer)) { 
            $customer = (new Database())->processQuery("UPDATE `customer` SET  customer_status = ? WHERE customer_id = ?", [3, $customerId]);
            
            if ((!empty($customer['response']) && $customer['response'] == Defaults::SUCCESS)) {
                foreach($checkCustomer as $assign){
                    $asgn = $assign['customer_employee'];
                    (new Database())->processQuery("UPDATE `employee` SET emp_work_status = ?  WHERE emp_id = ?", [0, $asgn]);
                }
            }
        }

        return Utilities::response(true, null, null);


        // return Utilities::response(((!empty($customer['response']) && $customer['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function dashboardMessage()
    {
        $message = Utilities::fetchRequiredDataFromArray($_POST, 'message_content');
        $numbers = Utilities::fetchRequiredDataFromArrayAsArray($_POST, 'message_numbers');
        $currentData = Utilities::getCurrentDate();
        $customerId = Utilities::fetchRequiredDataFromArray($_POST, 'customer_id');
        $output = [];

        $params = "(".str_repeat('?,', count($numbers) - 1).'?)';       
        $checkEmployee = (new Database())->processQuery("SELECT * FROM `employee` INNER JOIN  `opt_in` on `opt_in_mobile_number` = `emp_mobile_number` WHERE `emp_status` = 1 and `opt_in_mobile_number` in $params", $numbers);

        if (!empty($checkEmployee)) {
            $insertMessage = (new Database())->processQuery("INSERT INTO `message` (message_content, message_created_at, message_is_sent) VALUES (?,?,?)", [$message, $currentData, 1]);

            if ((!empty($insertMessage['response']) && $insertMessage['response'] == Defaults::SUCCESS)) {
        
                foreach ($checkEmployee as $employee) {
                    
                    $mn = $employee['opt_in_mobile_number'];
                    $tkn = $employee['opt_in_token'];
                    $emp = $employee['emp_id'];

                    (new Database())->processQuery("INSERT INTO `sent_message` (sent_message_message, sent_message_mobile, sent_created_at) VALUES (?, ?, ?)", [$insertMessage['last_inserted_id'], $mn, $currentData]);
                    (new Database())->processQuery("UPDATE `employee` SET emp_work_status = ? WHERE emp_mobile_number = ? ", [1, $mn]);
                    (new Database())->processQuery("UPDATE `customer` SET customer_employee = ? WHERE customer_id = ? ", [$emp, $customerId]);
                    // $output[] = GlobeLabs::sendSms($mn, $tkn, $message);
                }
            }
        }

        return Utilities::response(true, null,  $output);

    }
    // ============================================================

    public static function getEmployeeList()
    {
        $search = Utilities::fetchDataFromArray($_GET, 'search');
        // $offset = is_null(Utilities::fetchDataFromArray($_GET, 'offset')) ? 0 : (int) Utilities::fetchDataFromArray($_GET, 'offset') ;
        // $limit =   is_null(Utilities::fetchDataFromArray($_GET, 'limit')) ? 10 : (int) Utilities::fetchDataFromArray($_GET, 'limit') ;

        if (is_null($search) || $search == ''){
            $total = (new Database())->processQuery("SELECT count(*) as `count` FROM employee  ORDER BY emp_last_name ASC", []);
            $employees = (new Database())->processQuery("SELECT * FROM employee WHERE emp_status = ? OR emp_status = ? ORDER BY  emp_last_name ASC", [0, 1]);
        }else {
            $search = "%{$search}%";

            $total = (new Database())->processQuery("SELECT count(*) as `count` FROM employee WHERE emp_last_name like ? or emp_first_name like ? ORDER BY  emp_last_name ASC", [$search, $search]);

            $employees = (new Database())->processQuery("SELECT * FROM employee WHERE emp_last_name like ? or emp_first_name like ?  ORDER BY  emp_last_name ASC", [$search, $search]);
        }
        
        return Utilities::response(true, null, ["employees" => $employees, "count" => isset($total) && count(['count']) > 0 ? reset($total)['count'] : 0]);
    }

    public static function getEmployeeTask()
    {
        $empId = Utilities::fetchDataFromArray($_GET, 'empId');
        $search = Utilities::fetchDataFromArray($_GET, 'search');
        // $offset = is_null(Utilities::fetchDataFromArray($_GET, 'offset')) ? 0 : (int) Utilities::fetchDataFromArray($_GET, 'offset') ;
        // $limit =   is_null(Utilities::fetchDataFromArray($_GET, 'limit')) ? 10 : (int) Utilities::fetchDataFromArray($_GET, 'limit') ;

        if (is_null($search) || $search == ''){

            $total = (new Database())->processQuery("SELECT count(*) as `count` FROM `customer` WHERE customer_employee = ?", [$empId]);
            $customers = (new Database())->processQuery("SELECT * FROM `customer` WHERE customer_employee = ? ORDER BY customer_updated_at DESC", [$empId]);
        }else {
            $search = "%{$search}%";
            $total = (new Database())->processQuery("SELECT count(*) as `count` FROM `customer` WHERE (customer_last_name like ? or customer_first_name like ?) and customer_employee = ?", [$search, $search, $empId]);
            $customers = (new Database())->processQuery("SELECT * FROM `customer` WHERE (customer_last_name like ? or customer_first_name like ?) and customer_employee = ?  ORDER BY  customer_last_name ASC", [$search, $search, $empId]);
        }
        
        return Utilities::response(true, null, ["customers" => $customers, "count" => isset($total) && count(['count']) > 0 ? reset($total)['count'] : 0]);

    }

    public static function getAssignEmployee(){

        $empId = Utilities::fetchDataFromArray($_GET, 'empId');

        $employee = (new Database())->processQuery("SELECT * FROM `employee` WHERE emp_id = ? ", [$empId]);
        
        return Utilities::response(true, null, ["employee" => $employee, "count" => count($employee)]);

    }

    public static function getActiveEmployeeListDashboard()
    {
        $employees = (new Database())->processQuery("SELECT * FROM employee  WHERE emp_status = ? and emp_work_status = ? ORDER BY emp_last_name ASC", [1, 0]);
        
        return Utilities::response(true, null, ["employees" => $employees, "count" => count($employees)]);
    }

    public static function getActiveEmployeeList()
    {
        $employees = (new Database())->processQuery("SELECT * FROM employee  WHERE emp_status = ? ORDER BY emp_last_name ASC", [1]);
        
        return Utilities::response(true, null, ["employees" => $employees, "count" => count($employees)]);
    }


    public static function createEmployee()
    {
        $fname = Utilities::fetchRequiredDataFromArray($_POST, 'fname');
        $lname = Utilities::fetchRequiredDataFromArray($_POST, 'lname');
        $email = strtolower(trim(Utilities::fetchRequiredDataFromArray($_POST, 'email')));
        $mobile = substr(preg_replace( '/[^0-9]/', '', Utilities::fetchRequiredDataFromArray($_POST, 'mobile')), -10, 10);
        $employees = (new Database())->processQuery("SELECT * FROM employee WHERE emp_mobile_number = ? OR emp_email = ?", [$mobile, $email]);

        $count = strlen(Utilities::fetchRequiredDataFromArray($_POST, 'mobile'));
        $currentData = Utilities::getCurrentDate();

        if ($count < 11){
            return Utilities::response(false, ["error" => "Mobile number must have 11 digits!"], "");
        }
        // if (count($employees) > 0) {
        //     return Utilities::response(false, ["error" => "Account already exist. Unable to complete process."], null);
        // }

        // $output = (new Database())->processQuery("INSERT INTO employee (emp_first_name, emp_last_name, emp_mobile_number, emp_email, emp_created_at) VALUES (?,?,?,?,now())", [$fname, $lname, $mobile, $email]);

        // return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        
        if (empty($employees)) {
            $output = (new Database())->processQuery("INSERT INTO employee (emp_first_name, emp_last_name, emp_mobile_number, emp_email, emp_created_at) VALUES (?,?,?,?,?)", [$fname, $lname, $mobile, $email, $currentData]);

            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        }else{
            return Utilities::response(false, ["error" => "Account already exist. Unable to complete process."], null);
        }
    }

    public static function updateEmployee()
    {
        $empId = Utilities::fetchRequiredDataFromArray($_POST, 'emp_id');
        $fname = Utilities::fetchRequiredDataFromArray($_POST, 'fname');
        $lname = Utilities::fetchRequiredDataFromArray($_POST, 'lname');
        $email = strtolower(trim(Utilities::fetchRequiredDataFromArray($_POST, 'email')));
        $mobile = substr(preg_replace( '/[^0-9]/', '', Utilities::fetchRequiredDataFromArray($_POST, 'mobile')), -10, 10);
        $employees = (new Database())->processQuery("SELECT * FROM employee WHERE (emp_mobile_number = ? OR emp_email = ?) AND emp_id = ? AND emp_status = ?", [$mobile, $email, $empId, 0]);
        $status_3 = (new Database())->processQuery("SELECT * FROM employee WHERE emp_id = ? and emp_status = ?", [$empId, 3]);
        $check = (new Database())->processQuery("SELECT * FROM employee WHERE emp_mobile_number = ? OR emp_email = ?", [$mobile, $email]);
        $currentData = Utilities::getCurrentDate();
        $count = strlen(Utilities::fetchRequiredDataFromArray($_POST, 'mobile'));


        if ($count != 11){
            return Utilities::response(false, ["error" => "Mobile number must have 11 digits!"], "");
        }
        if (!empty($status_3)) {
            return Utilities::response(false, ["error" => "This employee was removed. Unable to complete process."], null);
        }
        if (empty($employees)) {
            return Utilities::response(false, ["error" => "E-mail/Mobile Number already in use or Employee is already Active. Unable to complete process."], null);
        }
        
        if ($check == $employees){
            $output = (new Database())->processQuery("UPDATE employee SET emp_first_name = ?, emp_last_name = ?, emp_mobile_number = ?, emp_email = ?, emp_updated_at = ? WHERE emp_id = ? and emp_status = ?", [$fname, $lname, $mobile, $email, $currentData, $empId, 0]);

            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
            
        }else{
            return Utilities::response(false, ["error" => "E-mail/Mobile Number already in use."], null);
        }

        // $output = (new Database())->processQuery("UPDATE employee SET emp_first_name = ?, emp_last_name = ?, emp_mobile_number = ?, emp_email = ?, emp_updated_at = now() WHERE emp_id = ? and emp_status = 0", [$fname, $lname, $mobile, $email, $empId]);

        // return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function updateEmployeeStatus($mobileNumber, $status)
    {
        $currentData = Utilities::getCurrentDate();
        $output = (new Database())->processQuery("UPDATE employee SET emp_status = ?, emp_updated_at = ? WHERE emp_mobile_number = ?", [$status, $currentData, $mobileNumber]);
        return ((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false);
    }

    public static function getEmployee()
    {
        $empId = Utilities::fetchRequiredDataFromArray($_POST, 'emp_id');
        $employee = (new Database())->processQuery("SELECT * FROM employee WHERE emp_id = ?", [$empId]);

        return Utilities::response(true, null, (reset($employee) ?? null));
    }

    public static function deleteEmployee()
    {
        $empId = Utilities::fetchRequiredDataFromArray($_POST, 'emp_id');
        $employee = (new Database())->processQuery("SELECT emp_mobile_number FROM employee WHERE emp_id = ? LIMIT 1", [$empId]);

        if (!empty($employee)) {
            // $output = (new Database())->processQuery("DELETE FROM employee WHERE emp_id = ?", [$empId]);
            // $deleteMessage = (new Database())->processQuery("DELETE FROM sent_message WHERE sent_message_mobile = ?", [$employee[0]['emp_mobile_number']]);
            $output = (new Database())->processQuery("UPDATE employee SET emp_status = ? WHERE emp_id = ?", [3, $empId]);
            
            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        } else {
            return Utilities::response(false, "Cannot find the employee.", "");
        }
    }

    public static function deleteEmployeeCheck()
    {
        $empId = Utilities::fetchRequiredDataFromArray($_POST, 'emp_id');
        $employee = (new Database())->processQuery("SELECT emp_mobile_number FROM employee WHERE emp_id = ? and emp_status = 3", [$empId]);

        if (!empty($employee)) {   
            return Utilities::response(false, ["error" => "This employee was already removed."], "");    
        }else{
            return Utilities::response(true, null, $employee);
        }
        
    }
    
    // ============================================================

    public static function getTodoList()
    {
        $search = Utilities::fetchDataFromArray($_GET, 'search');

        if (is_null($search) || $search == ''){
            // $todos = (new Database())->processQuery("SELECT * FROM todo ORDER BY todo_deadline ASC, todo_updated_at DESC", []);
            $todos = (new Database())->processQuery("SELECT * FROM customer WHERE customer_status = ? ORDER BY customer_due_date ASC", [1]);

            $output = [];
        }else {
            $search = "%{$search}%";
            // $todos = (new Database())->processQuery("SELECT * FROM todo WHERE todo_title like ? ORDER BY todo_deadline ASC, todo_updated_at DESC", [$search]);
            $todos = (new Database())->processQuery("SELECT * FROM customer WHERE customer_last_name like ? OR customer_first_name like ? ORDER BY customer_due_date ASC, customer_completed_at DESC", [$search, $search]);

        }
        foreach ($todos as $todo) {
            $output[$todo['customer_status']][] = $todo;
        }
        return Utilities::response(true, null,$output);
    }
    
    public static function getCompletedList()
    {
        $search = Utilities::fetchDataFromArray($_GET, 'search');

        if (is_null($search) || $search == ''){
            // $todos = (new Database())->processQuery("SELECT * FROM todo ORDER BY todo_deadline ASC, todo_updated_at DESC", []);
            $todos = (new Database())->processQuery("SELECT * FROM customer WHERE customer_status = ? ORDER BY customer_completed_at DESC", [4]);

            $output = [];
        }else {
            $search = "%{$search}%";
            // $todos = (new Database())->processQuery("SELECT * FROM todo WHERE todo_title like ? ORDER BY todo_deadline ASC, todo_updated_at DESC", [$search]);
            $todos = (new Database())->processQuery("SELECT * FROM customer WHERE customer_last_name like ? OR customer_first_name like ? ORDER BY customer_due_date ASC, customer_completed_at DESC", [$search, $search]);

        }
        foreach ($todos as $todo) {
            $output[$todo['customer_status']][] = $todo;
        }
        return Utilities::response(true, null,$output);
    }

    public static function getTodoDetail()
    {
        $todoId = Utilities::fetchRequiredDataFromArray($_POST, 'todo_id');
        // $todo = (new Database())->processQuery("SELECT * FROM todo WHERE todo_id =? LIMIT 1 ", [$todoId]);
        $todo = (new Database())->processQuery("SELECT * FROM customer WHERE customer_id =? LIMIT 1 ", [$todoId]);


        return Utilities::response(true, null, $todo);
    }

    public static function createTodo()
    {
        $title = Utilities::fetchRequiredDataFromArray($_POST, 'title');
        $description = Utilities::fetchRequiredDataFromArray($_POST, 'description');
        $address = Utilities::fetchRequiredDataFromArray($_POST, 'address');
        $deadline = Utilities::formatDate(Utilities::fetchRequiredDataFromArray($_POST, 'deadline'), 'Y-m-d H:i:s');
        $currentData = Utilities::getCurrentDate();
        $date = date('Y-m-d H:i:s');
        
        if ($deadline < $date){
            return Utilities::response(false, "Unable to process.", "");
        }else{
            $output = (new Database())->processQuery("INSERT INTO todo (todo_title, todo_description, todo_address, todo_deadline, todo_created_at) VALUES (?,?,?,?,?)", [$title, $description, $address, $deadline, $currentData]);

            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        }
        
    }

    public static function updateTodo()
    {
        $todoId = Utilities::fetchRequiredDataFromArray($_POST, 'todo_id');
        $status = Utilities::fetchRequiredDataFromArray($_POST, 'status');
        $currentData = Utilities::getCurrentDate();
        // $todo = (new Database())->processQuery("UPDATE todo SET todo_status = ?, todo_updated_at = ? WHERE todo_id = ?", [$status, $currentData, $todoId]);
        $assign_emp = (new Database())->processQuery("SELECT * from `customer` WHERE customer_id = ?", [$todoId]);

        foreach($assign_emp as $assign){
            $asgn = $assign['customer_employee'];
        }

        $todo = (new Database())->processQuery("UPDATE customer SET customer_status = ?, customer_completed_at = ? WHERE customer_id = ?", [$status, $currentData, $todoId]);
        $emp = (new Database())->processQuery("UPDATE `employee` SET emp_work_status = ?  WHERE emp_id = ?", [0, $asgn]);



        return Utilities::response(((!empty($todo['response']) && $todo['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function deleteTodo()
    {
        $todoId = array_map(function($payload) {return (int) $payload;}, Utilities::fetchRequiredDataFromArrayAsArray($_POST, 'todo_id'));
        $params = "(".str_repeat('?,', count($todoId) - 1).'?)';
        // $todo = (new Database())->processQuery("DELETE FROM todo WHERE todo_id in $params", $todoId);
        // $todo = (new Database())->processQuery("UPDATE todo SET todo_status = 3 WHERE todo_id in $params", $todoId);
        $assign_emp = (new Database())->processQuery("SELECT * from `customer` WHERE customer_id in $params", $todoId);

        if (!empty($assign_emp)) { 
            $removeTodo = (new Database())->processQuery("UPDATE customer SET customer_status = 3 WHERE customer_id in $params", $todoId);
            
            if ((!empty($removeTodo['response']) && $removeTodo['response'] == Defaults::SUCCESS)) {
                foreach($assign_emp as $assign){
                    $asgn = $assign['customer_employee'];
                    (new Database())->processQuery("UPDATE `employee` SET emp_work_status = ?  WHERE emp_id = ?", [0, $asgn]);
                }
            }
        }

        return Utilities::response(true, null, null);
        // return Utilities::response(((!empty($todo['response']) && $todo['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }


    
    // ============================================================

    public static function createMessage()
    {
        $message = Utilities::fetchRequiredDataFromArray($_POST, 'message_content');
        $numbers = Utilities::fetchRequiredDataFromArrayAsArray($_POST, 'message_numbers');
        $currentData = Utilities::getCurrentDate();
        $output = [];

        $params = "(".str_repeat('?,', count($numbers) - 1).'?)';       
        $checkEmployee = (new Database())->processQuery("SELECT * FROM `employee` INNER JOIN  `opt_in` on `opt_in_mobile_number` = `emp_mobile_number` WHERE `emp_status` = 1 and `opt_in_mobile_number` in $params", $numbers);

        if (!empty($checkEmployee)) {
            $insertMessage = (new Database())->processQuery("INSERT INTO `message` (message_content, message_created_at, message_is_sent) VALUES (?,?,?)", [$message, $currentData, 1]);

            if ((!empty($insertMessage['response']) && $insertMessage['response'] == Defaults::SUCCESS)) {
        
                foreach ($checkEmployee as $employee) {
                    
                    $mn = $employee['opt_in_mobile_number'];
                    $tkn = $employee['opt_in_token'];

                    (new Database())->processQuery("INSERT INTO `sent_message` (sent_message_message, sent_message_mobile, sent_created_at) VALUES (?, ?, ?)", [$insertMessage['last_inserted_id'], $mn, $currentData]);
                    // $output[] = GlobeLabs::sendSms($mn, $tkn, $message);
                }
            }
        }

        return Utilities::response(true, null,  $output);

    }

    public static function getSentMessages()
    {
        $search = Utilities::fetchDataFromArray($_GET, 'search');
        
        if (is_null($search) || $search == ''){
            $messages = (new Database())->processQuery("SELECT * FROM sent_message LEFT JOIN employee ON emp_mobile_number = sent_message_mobile LEFT JOIN `message` ON message_id = sent_message_message WHERE sent_message_status = 0 ORDER BY sent_created_at DESC", []);
            $total = (new Database())->processQuery("SELECT COUNT(*) as `count` FROM sent_message LEFT JOIN employee ON emp_mobile_number = sent_message_mobile LEFT JOIN `message` ON message_id = sent_message_message ORDER BY sent_created_at DESC ", []);

        }else {
            $search = "%{$search}%";
            $messages = (new Database())->processQuery("SELECT * FROM sent_message LEFT JOIN employee ON emp_mobile_number = sent_message_mobile LEFT JOIN `message` ON message_id = sent_message_message WHERE (emp_last_name like ? or emp_first_name like ?) and sent_message_status = 0 ORDER BY  sent_created_at DESC", [$search, $search]);
            $total = (new Database())->processQuery("SELECT COUNT(*) as `count` FROM sent_message LEFT JOIN employee ON emp_mobile_number = sent_message_mobile LEFT JOIN `message` ON message_id = sent_message_message WHERE emp_last_name like ? or emp_first_name like ? ORDER BY sent_created_at DESC ", [$search, $search]);
        }
        return Utilities::response(true, null, ['count' => isset($total) && count(['count']) > 0? reset($total)['count'] : 0, 'messages' => $messages]);
    }

    public static function deleteSentMessage()
    {
        $sentMessageId = array_map(function($payload) {return (int) $payload;}, Utilities::fetchRequiredDataFromArrayAsArray($_POST, 'sent_message_id'));
        $params = "(".str_repeat('?,', count($sentMessageId) - 1).'?)';
        // $sentMessage = (new Database())->processQuery("DELETE FROM sent_message WHERE sent_message_id in $params", $sentMessageId);
        $sentMessage = (new Database())->processQuery("UPDATE `sent_message` SET sent_message_status = 1 WHERE sent_message_id in $params", $sentMessageId);
        return Utilities::response(((!empty($sentMessage['response']) && $sentMessage['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function getSentMessagesDetail()
    {
        $sentMessageId = Utilities::fetchRequiredDataFromArray($_GET, 'sent_message_id');
        $messages = (new Database())->processQuery("SELECT * FROM sent_message LEFT JOIN employee ON emp_mobile_number = sent_message_mobile LEFT JOIN `message` ON message_id = sent_message_message WHERE sent_message_id = ? ", [$sentMessageId]);
        return Utilities::response(true, null, $messages);
    }

    public static function createContacts()
    {
        $contact = Utilities::fetchRequiredDataFromArray($_POST, 'emp_status');
        $contactId = Utilities::fetchRequiredDataFromArray($_POST, 'emp_id');
        $currentData = Utilities::getCurrentDate();
        $output = (new Database())->processQuery("UPDATE `employee` SET emp_status = ?, emp_updated_at = ? WHERE emp_id = ?", [$contact, $currentData, $contactId]);

        return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    // ============================================================

    public static function createInquiry()
    {
        $cFname = Utilities::fetchRequiredDataFromArray($_POST, 'customer_first_name');
        $cLname = Utilities::fetchRequiredDataFromArray($_POST, 'customer_last_name');
        $cMn = Utilities::fetchRequiredDataFromArray($_POST, 'customer_mobile_number');
        $cEmail = strtolower(trim(Utilities::fetchRequiredDataFromArray($_POST, 'customer_email')));
        $cAddress = Utilities::fetchRequiredDataFromArray($_POST, 'customer_address');
        $cInq = Utilities::fetchRequiredDataFromArray($_POST, 'customer_inquiry_details'); 
        $currentData = Utilities::getCurrentDate();
        $count = strlen(Utilities::fetchRequiredDataFromArray($_POST, 'customer_mobile_number'));

        
        if ($count < 11){
            return Utilities::response(false, "Mobile number must have 11 digits!", "");
        }

        
        $output = (new Database())->processQuery("INSERT INTO customer (customer_first_name, customer_last_name, customer_mobile_number, customer_email, customer_address, customer_inquiry_details, customer_created_at) VALUES (?,?,?,?,?,?,?)", [$cFname, $cLname, $cMn, $cEmail, $cAddress, $cInq, $currentData]);

        return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    // ============================================================

    public static function createService()
    {
        $serv_name = Utilities::fetchRequiredDataFromArray($_POST, 'serv_name');
        $serv_price = Utilities::fetchRequiredDataFromArray($_POST, 'serv_price');
        $serv_description = Utilities::fetchRequiredDataFromArray($_POST, 'serv_description');
        $serv_image = Utilities::imageDataUploader(Utilities::fetchRequiredDataFromArray($_POST, 'serv_image'));
        $currentData = Utilities::getCurrentDate();
        if ($serv_image['status']===false)
        {
            return Utilities::response(false, $serv_image['error'], null);
        }

        $output = (new Database())->processQuery("INSERT INTO services (service_title, service_price, service_description, service_image, service_created_at) VALUES (?,?,?,?,?)", [$serv_name, $serv_price, $serv_description, $serv_image['content']['path'], $currentData]);

        return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function getServiceList()
    {
        $search = Utilities::fetchDataFromArray($_GET, 'search');
        // $offset = is_null(Utilities::fetchDataFromArray($_GET, 'offset')) ? 0 : (int) Utilities::fetchDataFromArray($_GET, 'offset') ;
        // $limit =   is_null(Utilities::fetchDataFromArray($_GET, 'limit')) ? 10 : (int) Utilities::fetchDataFromArray($_GET, 'limit') ;

        $total = (new Database())->processQuery("SELECT count(*) as `count` FROM services  ORDER BY service_created_at DESC", []);

        if (is_null($search) || $search == ''){
            $services = (new Database())->processQuery("SELECT * FROM services WHERE service_status = ?  ORDER BY service_created_at DESC", [0]);
        }else {
            $search = "%{$search}%";
            $services = (new Database())->processQuery("SELECT * FROM services WHERE service_title like ? and service_status = ?  ORDER BY service_created_at DESC", [$search, 0]);
        }
        return Utilities::response(true, null, ['services' => $services, 'count' => isset($total) && count(['count']) > 0? reset($total)['count'] : 0]);
    }

    public static function createProduct()
    {
        $prod_name = Utilities::fetchRequiredDataFromArray($_POST, 'prod_name');
        $prod_price = Utilities::fetchRequiredDataFromArray($_POST, 'prod_price');
        $prod_image = Utilities::imageDataUploader(Utilities::fetchRequiredDataFromArray($_POST, 'prod_image'));
        $currentData = Utilities::getCurrentDate();
        if ($prod_image['status']===false)
        {
            return Utilities::response(false, $prod_image['error'], null);
        }

        $output = (new Database())->processQuery("INSERT INTO products (product_name, product_price, product_image, product_created_at) VALUES (?,?,?,?)", [$prod_name, $prod_price, $prod_image['content']['path'], $currentData]);

        return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);       
    }

    public static function getProductList()
    {
        $search = Utilities::fetchDataFromArray($_GET, 'search');
        // $offset = is_null(Utilities::fetchDataFromArray($_GET, 'offset')) ? 0 : (int) Utilities::fetchDataFromArray($_GET, 'offset') ;
        // $limit =   is_null(Utilities::fetchDataFromArray($_GET, 'limit')) ? 10 : (int) Utilities::fetchDataFromArray($_GET, 'limit') ;

        $total = (new Database())->processQuery("SELECT COUNT(*) as `count` FROM products  ORDER BY product_created_at DESC", []);

        if (is_null($search) || $search == ''){
            $products = (new Database())->processQuery("SELECT * FROM products WHERE product_status = ? ORDER BY product_created_at DESC", [0]);
        }else {
            $search = "%{$search}%";
            $products = (new Database())->processQuery("SELECT * FROM products WHERE product_name like ? and product_status = ? ORDER BY product_created_at DESC", [$search, 0]);
        }
        return Utilities::response(true, null, ["products" => $products, 'count' => isset($total) && count(['count']) > 0? reset($total)['count'] : 0]);
    }

    public static function updateService()
    {
        $serv_id = Utilities::fetchRequiredDataFromArray($_POST, 'serviceId');
        $serv_name = Utilities::fetchRequiredDataFromArray($_POST, 'serviceName');
        $serv_price = Utilities::fetchRequiredDataFromArray($_POST, 'servicePrice');
        // $serv_image = Utilities::checkImage($_FILES, 'serviceImage');
        $serv_description = Utilities::fetchRequiredDataFromArray($_POST, 'serviceDescription');
        $currentData = Utilities::getCurrentDate();

        $output = (new Database())->processQuery("UPDATE services SET service_title = ?, service_description = ?, service_price = ?, service_updated_at = ? WHERE service_id = ?", [$serv_name, $serv_description, $serv_price, $currentData, $serv_id]);

        return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function deleteService()
    {
        $serv_id = Utilities::fetchRequiredDataFromArray($_POST, 'serviceId');
        $service = (new Database())->processQuery("SELECT * FROM services WHERE service_id = ?", [$serv_id]);

        if (!empty($service)) {
            // $output = (new Database())->processQuery("DELETE FROM services WHERE service_id = ?", [$serv_id]);
            $output = (new Database())->processQuery("UPDATE services set service_status = 1 WHERE service_id = ?", [$serv_id]);

            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        } else {
            return Utilities::response(false, "Cannot find the service.", "");
        }
    }   
    public static function serviceDetail()
    {
        $serviceId = Utilities::fetchRequiredDataFromArray($_GET, 'service_id');
        $services = (new Database())->processQuery("SELECT * FROM services WHERE service_id = ?", [$serviceId]);

        return Utilities::response(true, null, $services);
    }
 

    public static function updateProduct()
    {
        $pId = Utilities::fetchRequiredDataFromArray($_POST, 'pId');
        $pName = Utilities::fetchRequiredDataFromArray($_POST, 'pName');
        $pPrice = Utilities::fetchRequiredDataFromArray($_POST, 'pPrice');
        // $pImage = Utilities::fetchRequiredDataFromArray($_POST, 'pImage');
        $currentData = Utilities::getCurrentDate();

        $output = (new Database())->processQuery("UPDATE products SET product_name = ?, product_price = ?, product_updated_at = ? WHERE product_id = ?", [$pName, $pPrice, $currentData, $pId]);

        return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function deleteProduct()
    {
        $pId = Utilities::fetchRequiredDataFromArray($_POST, 'pId');
        $product = (new Database())->processQuery("SELECT * FROM products WHERE product_id = ?", [$pId]);

        if (!empty($product)) {
            // $output = (new Database())->processQuery("DELETE FROM products WHERE product_id = ?", [$pId]);
            $output = (new Database())->processQuery("UPDATE products set product_status = 1 WHERE product_id = ?", [$pId]);

            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        } else {
            return Utilities::response(false, "Cannot find the product.", "");
        }
    }  

    // ========================================

    public static function getUser()
    {
        $user = (new Database())->processQuery("SELECT * FROM users", []);

        // return Utilities::response(((!empty($user['response']) && $user['response'] == Defaults::SUCCESS) ? true : false), null, null);
        return Utilities::response(true, null, $user);
    }

    public static function changeUser()
    {
        $user_ID = Utilities::fetchRequiredDataFromArray($_POST, 'user_ID');
        $user_username = Utilities::fetchRequiredDataFromArray($_POST, 'user_username');
        $user_email = Utilities::fetchRequiredDataFromArray($_POST, 'user_email');
        $user = md5(Utilities::fetchRequiredDataFromArray($_POST, 'check_pass'));
        $newpass = Utilities::fetchDataFromArray($_POST, 'new_pass');
        $confirmpass = Utilities::fetchDataFromArray($_POST, 'pass_word');
        $change_password = md5(Utilities::fetchDataFromArray($_POST, 'pass_word'));
        $check = (new Database())->processQuery("SELECT * FROM users WHERE user_password = ?", [$user]);
        $count = strlen(Utilities::fetchDataFromArray($_POST, 'pass_word'));
        $currentData = Utilities::getCurrentDate();

        if(empty($newpass && $confirmpass && $change_password && $count)){
            $output = (new Database())->processQuery("UPDATE users SET user_username = ?, user_email = ?, user_updated_at = ? WHERE `user_id` = ?", [$user_username, $user_email, $currentData, $user_ID]);
    
            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        }else{
            if ($count < 8){
                return Utilities::response(false, "Password must be at least eight characters long.", "");
            }
            if ($newpass == $confirmpass) {
                if (!empty($check)){
                    $output = (new Database())->processQuery("UPDATE users SET user_username = ?, user_email = ?, user_password = ?, user_updated_at = ? WHERE `user_id` = ?", [$user_username, $user_email, $change_password, $currentData, $user_ID]);
        
                    return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
                }else{
                    return Utilities::response(false, "Invalid current password.", "");
                }
            }else{
                return Utilities::response(false, "New Password does not matched.", "");
            }
        }
        
        // $output = (new Database())->processQuery("UPDATE users SET user_username = ?, user_updated_at = now() WHERE `user_id` = ?", [$user_username, $user_ID]);

        // return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function changePass()
    {
        $pass_id = Utilities::fetchRequiredDataFromArray($_POST, 'pass_id');
        $change_password = md5(Utilities::fetchRequiredDataFromArray($_POST, 'pass_word'));
        $confirmpass = Utilities::fetchRequiredDataFromArray($_POST, 'pass_word');
        $oldpass = md5(Utilities::fetchRequiredDataFromArray($_POST, 'old_pass'));
        $newpass = Utilities::fetchRequiredDataFromArray($_POST, 'new_pass');
        $check = (new Database())->processQuery("SELECT * FROM users WHERE user_password = ?", [$oldpass]);
        $count = strlen(Utilities::fetchRequiredDataFromArray($_POST, 'pass_word'));
        $currentData = Utilities::getCurrentDate();


        if ($count < 8){
            return Utilities::response(false, "Password must be at least eight characters long.", "");
        }

        if ($newpass == $confirmpass) {
            if (!empty($check)){
                $output = (new Database())->processQuery("UPDATE users SET user_password = ?, user_updated_at = ? WHERE `user_id` = ?", [$change_password, $currentData, $pass_id]);
    
                return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
            }else{
                return Utilities::response(false, "Invalid current password.", "");
            }
        }else{
            return Utilities::response(false, "Unable to complete process. Unmatched new password.", "");
        }
        
       
    }

    // =========================================
    public static function getServices()
    {
        $gservices = (new Database())->processQuery("SELECT * FROM services WHERE service_status = ?", [0]);

        return Utilities::response(true, null, $gservices);
    }
    
    public static function getProducts()
    {
        $pproducts = (new Database())->processQuery("SELECT * FROM products  WHERE product_status = ?", [0]);
        
        return Utilities::response(true, null, $pproducts);
    }

   

    public static function checkAdmin()
    {
        $user = md5(Utilities::fetchRequiredDataFromArray($_POST, 'check_pass'));
        $check_admin = (new Database())->processQuery("SELECT * FROM users WHERE user_password = ?", [$user]);

        if (!empty($check_admin)){
            return Utilities::response(true, null, $check_admin);
        }else{
            return Utilities::response(false, "Invalid password. Please try again.", "");
        }
        
    }

    public static function checkPass()
    {
        $user = md5(Utilities::fetchRequiredDataFromArray($_POST, 'old_pass'));
        $check_admin = (new Database())->processQuery("SELECT * FROM users WHERE user_password = ?", [$user]);
        $confirmpass = Utilities::fetchRequiredDataFromArray($_POST, 'pass_word');
        $newpass = Utilities::fetchRequiredDataFromArray($_POST, 'new_pass');
        $count = strlen(Utilities::fetchRequiredDataFromArray($_POST, 'pass_word'));

        if ($count < 8){
            return Utilities::response(false, ["error" =>"Password must be at least eight characters long."], "");
        }

        if ($newpass == $confirmpass) {
            if (!empty($check_admin)){
                return Utilities::response(true, null, $check_admin);
            }else{
                return Utilities::response(false, ["error" =>"Invalid current password. Please try again."], "");
            }
        }else{
            return Utilities::response(false, ["error" =>"Unable to complete process. Unmatched password."], "");
        }
      
    }

    public static function checkDate()
    {
        $started = Utilities::formatDate(Utilities::fetchRequiredDataFromArray($_GET, 'started'), 'Y-m-d H:i:s');
        $deadline = Utilities::formatDate(Utilities::fetchRequiredDataFromArray($_GET, 'deadline'), 'Y-m-d H:i:s');
  
        $date = date('Y-m-d H:i:s');
        
        if ($deadline < $date){
            return Utilities::response(false, "Due date must be later than today!", "");
        }
        if ($started < $date){
            return Utilities::response(false, "Start date must be later than today!", "");
        }

        if ($started > $deadline){
            return Utilities::response(false, "Start date must not be later than due date!", "");
        }

        
        return Utilities::response(true, null, null);
 
    }
}
