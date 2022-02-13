<?php

namespace core\misc;

use core\config\Env;

class Database
{
    CONST DEFAULT_LIMIT = 10;
    CONST DEFAULT_OFFSET = 0;
    public static $modelNamespace = "api\\v1\\models\\";
    public static $returnOverride = false;
    public static $overrideValues = null;

    public function __construct()
    {
    }

    function processQuery ($query, array $args = array())
    {
        $env = (array)(new Env())->getEnvFile();

        try {
            $pdo = new \PDO(
                'mysql:host=' . $env['database']['host'] . ';port=' . $env['database']['port'] . ';dbname=' . $env['database']['database'],
                $env['database']['username'],
                $env['database']['password'],
                array(
                    \PDO::ATTR_PERSISTENT => true,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                )
            );
            $pdo_statement = $pdo->prepare($query);

            foreach ($args as $index => $arg) {
                if (is_int($arg)) {
                    $type = \PDO::PARAM_INT;
                    $arg = filter_var(trim(urlencode($arg)), FILTER_SANITIZE_NUMBER_INT);
                } elseif (is_bool($arg)) {
                    $type = \PDO::PARAM_BOOL;
                    $arg = filter_var(trim(urlencode($arg)), FILTER_SANITIZE_NUMBER_INT);
                } elseif (is_null($arg)) {
                    $type = \PDO::PARAM_NULL;
                    $arg = NULL;
                } else {
                    $type = \PDO::PARAM_STR;
                    // $arg = filter_var(trim(urldecode($arg)), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH);
                }
    
                try {
                    $pdo_statement->bindValue($index + 1, $arg, $type);
                } catch (\Exception $e) {
                    $pdo = NULL;
                    return Utilities::responseWithException($e);
                }
            }

            try {
                $pdo_statement->execute();
    
                if ($pdo_statement->rowCount()) {
                    $output = (preg_match('/\b(update|insert|delete)\b/', strtolower($query)) === 1) ? ["response" => Defaults::SUCCESS, "last_inserted_id" => !is_null($pdo) ? $pdo->lastInsertId() : null] : $pdo_statement->fetchAll(\PDO::FETCH_ASSOC);
                }
            } catch (\PDOException $e) {
                $pdo = NULL;
                return Utilities::responseWithException($e);
            }
    
            $pdo = NULL;
            flush();
            // return Utilities::response(true, null, $output ?? null);
            return $output ?? null;
        } catch (\PDOException $e) {
            $pdo = NULL;
            return Utilities::responseWithException($e);
        }
    }

    public static function genericSave ($tableName, $tablePrefix, $columns, $primaryColumn, $isSoftDelete = true)
    {
        $data = [];
		$output = null;
        $resultStatus = false;

		foreach ($columns as $column => $option) {
            if ($option === 'o' && (!isset(self::$overrideValues[$column]) && !isset($_POST[$column])))
                continue;
            else {
                $data[$column] = ($option === 'o') ? Utilities::fetchDataFromArray((self::$overrideValues ?? $_POST), $column) : Utilities::fetchRequiredDataFromArray((self::$overrideValues ?? $_POST), $column);
            }
        }

		if (!empty($data)) {
			if (!empty($data[$primaryColumn])) {
				$recordExist = self::genericCheckIfExist($tableName, $primaryColumn, $data[$primaryColumn]);
				if (!empty($recordExist)) {
					$queryData = [];
					$query = "UPDATE " . $tableName . " SET ";

					foreach ($data as $key => $value) {
						if ($key == $primaryColumn || in_array($key, [$tablePrefix . "created_at", $tablePrefix . "created_by"]))
                            continue;

						$query .= (empty($queryData)) ? "$key = ?" : ", $key = ?";
						$queryData[] = $value;
					}

					if ($isSoftDelete) {
						if (empty($data[$tablePrefix."updated_at"])) {
							$query .= ", " . $tablePrefix . "updated_at = NOW()";
						}

						if (empty($data[$tablePrefix."updated_by"])) {
							$query .= ", " . $tablePrefix . "updated_by = ?";
							$queryData[] = Defaults::SYSTEM;
						}
					}

					// WHERE <-- here -->empty
					$query .= " WHERE " . $primaryColumn . " = ?";
					$queryData[] = $data[$primaryColumn];

					$output = (new Database())->processQuery($query, $queryData);
					$resultStatus = true;
				} else {
					return Utilities::responseWithException("Unable to locate `" . $primaryColumn . "`.");
				}
			} else {
				$insertQuery = "INSERT INTO " . $tableName . " (" . $primaryColumn;
				$insertValue = " VALUES (?";
				$insertData[] = Utilities::randomizer(16);

				foreach ($data as $key => $value) {
					if ($key == $primaryColumn)
						continue;

					$insertQuery .= ", $key";
                    
                    if ($key === $tablePrefix . "created_at")
                        $insertValue .= ", NOW()";
                    else {
                        $insertValue .= ", ?";
                        $insertData[] = ($key === $tablePrefix . "created_by") ? Defaults::SYSTEM : $value;
                    }
                }
                
				if ($isSoftDelete) {
					if (empty($data[$tablePrefix."created_by"]) && !in_array($tablePrefix . "created_by", array_keys($data))) {
                        $insertQuery .= ", " . $tablePrefix . "created_by";
                        $insertValue .= ", ?";
                        $insertData[] = Defaults::SYSTEM;
                    }
                    
					if (empty($data[$tablePrefix."created_at"]) && !in_array($tablePrefix . "created_at", array_keys($data))) {
                        $insertQuery .= ", " . $tablePrefix . "created_at";
                        $insertValue .= ", NOW()";
					}
				}

				$insertQuery .= ")";
				$insertValue .= ")";
				$output = (new Database())->processQuery($insertQuery . " " . $insertValue, $insertData);
				$resultStatus = true;
			}
		}

        if (self::$returnOverride) {
            return [
                "status" => $resultStatus,
                "data" => $output,
            ];
        } else
		    return Utilities::response($resultStatus, null, $output);
    }

    public static function genericCheckIfExist($tableName, $primaryColumn, $code)
	{
		return (new Database())->processQuery("SELECT * FROM " . $tableName . " where " . $primaryColumn. " = ?", [$code]);
    }

    public static function genericGetData($tableName = null, $tablePrefix = null, $joinObj = null, $search = null, $genericType = false)
    {
        $tableName = $tableName ?? Utilities::fetchRequiredDataFromArray($_POST, 'resource');
        $tablePrefix = $tablePrefix ?? Utilities::fetchRequiredDataFromArray($_POST, 'prefix');
        // $offset = empty(Utilities::fetchDataFromArray($_POST, "offset")) ? self::DEFAULT_OFFSET : (int) Utilities::fetchDataFromArray($_POST, "offset");
        $offset = empty(Utilities::fetchDataFromArray($_POST, "offset")) ? self::DEFAULT_OFFSET : (int) Utilities::fetchDataFromArray($_POST, "offset");
        $limit = empty(Utilities::fetchDataFromArray($_POST, "limit")) ? self::DEFAULT_LIMIT : (int) Utilities::fetchDataFromArray($_POST, "limit");
        $sort = Utilities::fetchDataFromArray($_POST, "sort") ?? "ASC";
        $search = $search ?? Utilities::fetchDataFromArrayAsArray($_POST, 'search');
        $joinObj = $joinObj ?? Utilities::fetchDataFromArray($_POST, 'join');

        if (!empty($joinObj)) {
            $joinSelect = [];
            $joinQuery = "";

            if (!empty($joinObj)) {
                foreach ($joinObj as $join) {
                    if (!empty($join["select"])) {
                        $selects[]= $join["select"];
                    }

                    $joinQuery .= " LEFT JOIN " . $join["table"] . " ON " . $tableName . "." . $join["target"] . " = " . $join["table"] . "." . $join["source"]; 

                }
                
                array_walk_recursive($selects, function ($value) use (&$joinSelect){
                    $joinSelect[] = $value;
                });

                $joinSelect = (!empty($joinSelect)) ? "," . implode(",", $joinSelect) : "";
            
            }
        }

        $query = "SELECT $tableName.* " . ($joinSelect ?? "") . " FROM $tableName " . ($joinQuery ?? "") . " WHERE " . $tablePrefix . "deleted_at IS NULL AND " . $tablePrefix . "deleted_by IS NULL";
        $data = [];
        $searchQuery = "";

        if (!empty($search)) {
			foreach ($search AS $index => $s) {
				$searchQuery .= " AND " . $s["column"] . " " . $s["operand"] . " ? ";
				$data[] = $s["value"];
			}
        }

        $query .= $searchQuery;


        try {
            $class = "api\\v1\\models\\" . Utilities::stringToClass($tableName);
            $object = new $class();
            $order = "ORDER BY ";

            if ($genericType === true && in_array("{$tablePrefix}title", array_values($object::$columns))) {
                $order .= " {$tablePrefix}title {$sort} ";
            } else if ($genericType === true && in_array("{$tablePrefix}name", array_values($object::$columns))) {
                $order .= " {$tablePrefix}name {$sort} ";
            } else {
                $order .= " {$tablePrefix}created_at {$sort} ";
            }
        } catch (\PDOException $e) {
            // do something
        }

        return $output = [
			"total" => (int) (new Database())->processQuery("SELECT COUNT(*) as `count` " . ($joinSelect ?? "") . " FROM $tableName " . ($joinQuery ?? "") . " WHERE " . $tablePrefix . "deleted_at IS NULL AND " . $tablePrefix . "deleted_by IS NULL $searchQuery", $data)[0]["count"],
			"data" => (new Database())->processQuery("$query $order LIMIT  ?, ?", array_merge($data, [$offset, $limit])) ?? []
		];
    }

    public static function softDelete ()
    {
        $resource = Utilities::fetchRequiredDataFromArray($_POST, 'resource');
		$search = Utilities::fetchRequiredDataFromArrayAsArray($_POST, 'search');
		$data = [];
		$className = self::$modelNamespace . implode('', array_map('ucfirst', explode('_', $resource)));
		$class = new $className;
		$tableName = $class::$tableName;
		$tablePrefix = $class::$tablePrefix;
        $query = "UPDATE $tableName SET " . $tablePrefix . "deleted_at = NOW(), " . $tablePrefix . "deleted_by = ? ";
        $data[] = Defaults::SYSTEM;

        if (empty(self::getDetails($resource, $search))) {
            return Utilities::responseWithException("No records found.");
        }

		if (count($search) > 0) {
			$query .= " WHERE ";

			foreach ($search AS $index => $s) {
				$query .= $s["column"] . " " . $s["operand"] . " ? " . (($index != array_key_last($search)) ? " AND " : " ");
				$data[] = $s["value"];
			}
		}

		return Utilities::response(true, null, (new Database())->processQuery($query, $data));
    }

    public static function getDetails ($resource = null, $search = null)
	{
		$resource = $resource ?? Utilities::fetchRequiredDataFromArray($_POST, 'resource');
		$search = $search ?? Utilities::fetchRequiredDataFromArrayAsArray($_POST, 'search');
		$data = [];
		$className = self::$modelNamespace . implode('', array_map('ucfirst', explode('_', $resource)));
		$class = new $className;
		$tableName = $class::$tableName;
		$tablePrefix = $class::$tablePrefix;
		$query = "SELECT * FROM $tableName";
		
		if (count($search) > 0) {
			$query .= " WHERE ";

			foreach ($search AS $index => $s) {
				$query .= $s["column"] . " " . $s["operand"] . " ? AND ";
				$data[] = $s["value"];
			}
		}

		$query .= $tablePrefix . "deleted_at IS NULL AND " . $tablePrefix . "deleted_by IS NULL";

        return (new Database())->processQuery($query, $data);
	}
    
}
