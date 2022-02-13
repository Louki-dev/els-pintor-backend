<?php 

namespace api\v1\models\user;

use core\misc\Utilities;
use core\misc\Database;
use core\misc\Defaults;
use api\v1\models\globe\GlobeLabs;
use mysqli;
use PDOStatement;

class Tokenizer {

    public static function login(){
        $username = trim(Utilities::fetchRequiredDataFromArray($_GET, 'username'));
        $password = trim(Utilities::fetchRequiredDataFromArray($_GET, 'password'));
        $output = [];

        $user = (new Database())->processQuery("Select * from users WHERE user_username =? AND user_password = md5(?)", [
            $username,
            $password
        ]);
        foreach ($user as $num) {
            $number = $num['user_number'];
        }

        if(!empty($user)){

            $random = Utilities::randomizer(6);
            $vcode = (new Database())->processQuery("UPDATE users SET user_vcode = ? WHERE user_username =? AND user_password = md5(?)" , [
                $random,
                $username,
                $password
            ]);
            
            $check_tkn = (new Database())->processQuery("Select * from opt_in WHERE opt_in_mobile_number = ?", [$number]);
            foreach ($check_tkn as $optin) {
                $tkn = $optin['opt_in_token'];
            }

            $message = "$random is your verification code. Don't reply to this message with your code.";
            
            // Utilities::dd($random, $number, $tkn);
            // $output[] = GlobeLabs::sendSms($number, $tkn, $message); //send vcode to the admin number
            return Utilities::response(true , null, $output);
        }else{
            return Utilities::response(false, ["error" => "Invalid username or password"], null);
        }
    }

    public static function checkVcode(){
        $vcode = trim(Utilities::fetchRequiredDataFromArray($_GET, 'vcode'));
        $check_code = (new Database())->processQuery("Select * from users WHERE user_vcode = ?", [
            $vcode
        ]);

        if(!empty($check_code)){
            $userObj = reset($check_code);
            $userId = Utilities::fetchRequiredDataFromArray($userObj, 'user_id');
            $token = (new Database())->processQuery("Select * from token WHERE token_user_id = ?", [
                $userId
            ]);

            $random = Utilities::randomizer(255);
            if(empty($token)){
                $tk = (new Database())->processQuery("INSERT INTO token (token_user_id, token_token, token_created_at) VALUES (?, ?, now())", [
                    $userId,
                    $random
                ]);
            }else{
                $tk = (new Database())->processQuery("UPDATE token SET token_token = ?, token_updated_at = now() WHERE token_user_id = ?" , [
                    $random,
                    $userId
                ]);
            }

            return Utilities::response(true , null, ["token" => $random, "user_id" => $userId]);
        }else{
            return Utilities::response(false, ["error" => "Invalid verification code"], null);
        }
    }

    public static function checkToken(){
        $headers = Utilities::getHeaders();
        $authorization = Utilities::fetchRequiredDataFromArray($headers, "Authorization" );
        $userId = Utilities::fetchRequiredDataFromArray($headers, "Userid");

        $tokenObj = (new Database())->processQuery("Select * from token WHERE token_user_id =? AND token_token = ?", [
            $userId,
            $authorization
        ]);
        
        return Utilities::response(empty($tokenObj) ? false: true, null, null);
        
        // Utilities::dd($tokenObj);
    }

    
    public static function createKey()
    {
        $key = md5(Utilities::fetchRequiredDataFromArray($_POST, 'create_key'));
        $currentData = Utilities::getCurrentDate();
        $key_id = 1;
        $output = (new Database())->processQuery("UPDATE users SET user_key = ?, user_updated_at = ? WHERE `user_id` = ?", [$key, $currentData, $key_id]);

        return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
    }

    public static function checkEmail()
    {
        $email = trim(Utilities::fetchRequiredDataFromArray($_GET, 'email'));

        $output = (new Database())->processQuery("Select * from users WHERE user_email =?", [$email]);

        if (empty($output)){
            return Utilities::response(false, ["error" => "Cannot find your email."], null);
        }

        return Utilities::response(true, null, $output);
    }

    public static function checkKey()
    {
        $key = trim(Utilities::fetchRequiredDataFromArray($_GET, 'key'));

        $output = (new Database())->processQuery("Select * from users WHERE user_key = md5(?)", [$key]);

        if (empty($output)){
            return Utilities::response(false, ["error" => "Invalid secret key. Unable to complete process."], null);
        }
        
        return Utilities::response(true, null, $output);
    }

    public static function getUser()
    {
        $user = (new Database())->processQuery("SELECT * FROM users", []);

        return Utilities::response(true, null, $user);
    }

    public static function resetPass()
    {
        $rst_id = Utilities::fetchRequiredDataFromArray($_POST, 'rst_id');
        $change_password = md5(Utilities::fetchRequiredDataFromArray($_POST, 'rst_cnfrm_pass'));
        $confirmpass = Utilities::fetchRequiredDataFromArray($_POST, 'rst_cnfrm_pass');
        $newpass = Utilities::fetchRequiredDataFromArray($_POST, 'rst_new_pass');
        $count = strlen(Utilities::fetchRequiredDataFromArray($_POST, 'rst_new_pass'));
        $currentData = Utilities::getCurrentDate();

        if ($count < 8){
            return Utilities::response(false, "Password must be at least eight characters long.", "");
        }

        if ($newpass == $confirmpass) {
    
            $output = (new Database())->processQuery("UPDATE users SET user_password = ?, user_updated_at = ? WHERE `user_id` = ?", [$change_password, $currentData, $rst_id]);

            return Utilities::response(((!empty($output['response']) && $output['response'] == Defaults::SUCCESS) ? true : false), null, null);
        }else{
            return Utilities::response(false, "Unable to complete process. Unmatched new password.", "");
        }

    }
}