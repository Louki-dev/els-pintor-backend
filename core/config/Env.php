<?php

namespace core\config;

use core\misc\Utilities;

class Env
{

    protected static $default = [
        "IV" => "",
        "KEY" => "",
        "database" => [
            "username" => "elspintor_db",
            "password" => "elspintor.",
            "database" => "elspintor_db",
            "host" => "localhost",
            "port" => "3306",
        ],
        "env" => "DEV"
    ];
    private static $envFile = "/.env";
    const VERSION = 'v1';

    public function __construct()
    {
        if (!file_exists(dirname(__FILE__) . '/' . self::$envFile)) {
            $this->generateEnvFile();
        }
    }

    public function generateEnvFile()
    {
        $file = dirname(__FILE__) . '/' . self::$envFile;
        $fileHandler = fopen($file, "w");

        self::$default['IV'] = Utilities::randomizer(16);
        self::$default['KEY'] = Utilities::randomizer(16);

        fwrite($fileHandler, json_encode(self::$default, JSON_PRETTY_PRINT));
        fclose($fileHandler);
        chmod($file, 0775);
        return true;
    }

    public function getEnvFile()
    {
       return json_decode(file_get_contents(dirname(__FILE__) . '/' . self::$envFile), true);
    }

    // "sk":"9x4M^,sgND5KQYA8"
    
    // "IV": "kw9yD8muzDs3VcqC",
    // "KEY": "UtMspn4Ywt7xUOI4",
    // "database": {
    //     "username": "u940543816_elspintor",
    //     "password": "1qaz!QAZ",
    //     "database": "u940543816_elspintor_db",
    //     "host": "localhost",
    //     "port": "3306"
    // },
    // "env": "DEV"
}
