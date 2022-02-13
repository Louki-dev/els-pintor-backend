<?php

use core\config\Env;
use core\misc\Defaults;
use core\misc\Utilities;

ob_start();
ini_set('memory_limit', '1024M');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Autoloader
{
    private static $extensions = [
        '.php',
        '.class.php',
        '.data.php',
    ];
    private static $versions = [
        'v1',
    ];
    const REQUEST_BASE_KEY = "request_params";
    const ENV_DEV = "DEV";

    
    public function __construct()
    {
        $this->register();
        $env = (new Env())->getEnvFile();

        if (!empty($env['env']) && $env['env'] == self::ENV_DEV) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: *');
        }

        if (!empty($_REQUEST) && isset($_REQUEST[self::REQUEST_BASE_KEY])) {
            $this->processRoutes();
        }
    }

    public function register()
    {
        foreach (self::$extensions as $ext) {
            spl_autoload_register(function ($class) use ($ext) {
                if ($this->fileExist($class, $ext, false)) return true;
            });
        }

        return false;
    }

    public function fileExist($class, $ext, $caseSensitive = true)
    {
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $class) . $ext;

        if (file_exists($fileName)) {
            require_once($fileName);
            return true;
        }
        
        if ($caseSensitive) return false;

        foreach (glob(dirname($fileName) . '/*', GLOB_NOSORT) as $file) {
            if (strtolower($file) == strtolower($fileName)) {
                require_once($file);
                return true;
            }
        }

        return false;
    }

    private function processRoutes()
    {
        $this->checkController();
        return;
    }

    private function checkController()
    {
        $request = (string) $_REQUEST[self::REQUEST_BASE_KEY];
        $requestArr = array_merge(array_filter(preg_split("/[\/\=?]+/", $request)));

        try {
            list($api, $ver, $controller, $action) = $requestArr;
        
            foreach (self::$versions as $version) {
                $fileExist = $this->fileExist("api/{$version}/controllers/" . str_replace("-", "", ucwords($controller, "-")), '.php', false);
    
                if (($fileExist ?? false) !== false) {
                    try {
                        $class = "api\\{$version}\\controllers\\" . str_replace("-", "", ucwords($controller, "-"));
                        $obj = new $class();
                        $function = "action" . str_replace("-", "", ucwords($action, "-"));
                        is_callable(array($obj, $function)) ? $obj->{$function}() : Utilities::responseWithException(Defaults::ERROR_404);
                    } catch (\Exception $e) {
                        Utilities::responseWithException($e);
                    }
                } else {
                    Utilities::responseWithException(Defaults::ERROR_404);
                }
            }
        } catch (Exception $e) {
            Utilities::responseWithException(Defaults::ERROR_404);
        }
    }

}

$autoloader = new Autoloader();
