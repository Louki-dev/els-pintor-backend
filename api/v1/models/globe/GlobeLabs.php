<?php
namespace api\v1\models\globe;

use core\misc\Utilities;
use core\misc\Defaults;
use core\misc\Database;
use api\v1\models\process\Process;

class GlobeLabs {

    private const SMS_SHORT_CODE = "225650607"; // 21589712 (Cross-telco: 225659712)
    private const SMS_URL = "https://devapi.globelabs.com.ph/smsmessaging/v1/outbound/{senderAddress}/requests?access_token={access_token}"; // Check GlobeLabs documentation for more details

    public static function redirectUrl()
    {
        $method = Utilities::fetchRequiredDataFromArray($_SERVER, 'REQUEST_METHOD');

        if ($method === 'GET') {
            self::optIn();
        } elseif ($method === 'POST') {
            self::optOut();
        }
    }

    public static function optIn()
    {
        /*
        * Opt-In Process
        * Must store the details somewhere for you to be able to send SMS
        */
        $currentDate = Utilities::getCurrentDate();
        $subscriberNumber = trim(Utilities::fetchRequiredDataFromArray($_GET, 'subscriber_number'));
        $accessToken = trim(Utilities::fetchRequiredDataFromArray($_GET, 'access_token'));    

        $checkNumber = (new Database())->processQuery("SELECT opt_in_mobile_number FROM opt_in WHERE opt_in_mobile_number =?", [$subscriberNumber]);

        if (!empty($checkNumber)) {
            $optIn = (new Database())->processQuery("UPDATE opt_in set opt_in_token = ?, opt_in_updated_at = ? where opt_in_mobile_number = ?", [
                $accessToken,
                $currentDate,
                $subscriberNumber
            ]);
        } else {
            $optIn = (new Database())->processQuery("INSERT INTO opt_in (opt_in_mobile_number, opt_in_token, opt_in_created_at) values (?,?,?)", [
                $subscriberNumber,
                $accessToken,
                $currentDate
            ]);
        }

        Process::updateEmployeeStatus($subscriberNumber, 1);

        if (!empty($optIn['response']) && $optIn['response'] == Defaults::SUCCESS) {
            return Utilities::response(true, null, null);
        }

        return Utilities::response(false, ["error" => "Unable to complete process. Please try again."], null);
    }

    public static function optOut()
    {
        /*
        * Opt-Out Process
        * You may remove data from database since it is unusable
        */
        $currentDate = Utilities::getCurrentDate();
        $entityBody = json_decode(file_get_contents('php://input'), 1);
        $subscribedObj = Utilities::fetchRequiredDataFromArray($entityBody, 'unsubscribed');
        // Utilities::dd($subscribedObj);
        $subscriberNumber = Utilities::fetchRequiredDataFromArray($subscribedObj, 'subscriber_number');
        $accessToken = Utilities::fetchRequiredDataFromArray($subscribedObj, 'access_token');

        $optOut = (new Database())->processQuery("UPDATE opt_in set opt_in_updated_at = ?, opt_out_at = ? where opt_in_mobile_number = ? and opt_in_token = ?", [
            $currentDate,
            $currentDate,
            $subscriberNumber,
            $accessToken
        ]);

        Process::updateEmployeeStatus($subscriberNumber, 0);

        if (!empty($optOut['response']) && $optOut['response'] == Defaults::SUCCESS) {
            return Utilities::response(true, null, null);
        }

        return Utilities::response(false, ["error" => "Unable to complete process. Please try again."], null);
    }


    public static function sendSms($subscriberNumber, $accessToken, $message)
    {
        $url = preg_replace(
            ['/{senderAddress}/', '/{access_token}/'],
            [substr(self::SMS_SHORT_CODE, -4), trim($accessToken)],
            self::SMS_URL
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'message' => $message,
            'address' => $subscriberNumber,
        ]); 

        try {
            $result  = curl_exec ($ch);
           return $result;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

}

?>