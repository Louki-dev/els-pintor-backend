<?php

namespace core\misc;

use Autoloader;

class Utilities extends \Exception
{

	public static function dd()
	{
		$output = array();

		foreach (func_get_args() as $arg) {
			array_push($output, $arg);
		}

		die(json_encode($output, JSON_PRETTY_PRINT));
	}

	public static function getCurrentDate($format = 'Y-m-d H:i:s')
	{
		return DATE($format);
	}

	public static function formatDate($date, $format)
	{
		return DATE($format, strtotime($date));
	}

	public static function randomizer($length, $type = Defaults::RAND_ALPHA_NUMERIC, $repeat = false)
	{
		$types = [
			Defaults::RAND_ALPHA_NUMERIC => implode('', array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9))),
			Defaults::RAND_ALPHA => implode('', array_merge(range('a', 'z'), range('A', 'Z'))),
			Defaults::RAND_NUMERIC => implode('', range(0, 9)),
		];
		$chars = $types[$type];
		$max = strlen($chars) - 1;

		if (($repeat && $length > $max + 1) || $length < 0) {
			return self::responseWithException("Non repetitive random string can't be longer than charset");
		}

		$rand_chars = array();

		while ($length) {
			$picked = $chars[mt_rand(0, $max)];

			if ($repeat) {
				if (!array_key_exists($picked, $rand_chars)) {
					$rand_chars[$picked] = true;
					$length--;
				}
			} else {
				$rand_chars[] = $picked;
				$length--;
			}
		}

		return implode('', $repeat ? array_keys($rand_chars) : $rand_chars);
	}

	public static function response($status, $error = null, $content = null, $additional = [])
	{
		ob_end_flush();

		if (ob_get_contents()) ob_end_clean();

		die(json_encode([
			"status" => $status,
			"error" => $error,
			"content" => $content,
			"additional" => $additional
		], JSON_PRETTY_PRINT));
	}

	public static function responseWithException($error)
	{
		$error = is_object($error) ? $error : new \Exception($error);
		throw self::response(false, ['error' => $error->getMessage()], null);
	}

	public static function fetchRequiredDataFromArray($array, $key)
	{
		if (!empty($array[$key]) && isset($array[$key]) && $array[$key] != '') {
			return $array[$key];
		} else {
			return self::responseWithException("Required input must not be empty. (`$key`).");
		}
	}

	public static function fetchRequiredDataFromArrayAsArray($array, $key)
	{
		if (!empty($array[$key])) {
			return is_array($array[$key]) ? $array[$key] : json_decode($array[$key], 1);
		} else {
			return self::responseWithException("Unable to locate `$key`.");
		}
	}

	public static function fetchDataFromArrayAsArray($array, $key)
	{
		return json_decode($array[$key] ?? null, 1);
	}

	public static function fetchDataFromArray($array, $key)
	{
		return $array[$key] ?? null;
	}

	public static function getHeaders()
	{
		return function_exists('apache_request_headers') ? apache_request_headers() : self::apache_request_headers_v2();
	}

	public static function apache_request_headers_v2()
	{
		$arh = array();
		$rx_http = '/\AHTTP_/';
		foreach ($_SERVER as $key => $val) {
			if (preg_match($rx_http, $key)) {
				$arh_key = preg_replace($rx_http, '', $key);
				$rx_matches = array();
				// do some nasty string manipulations to restore the original letter case
				// this should work in most cases
				$rx_matches = explode('_', $arh_key);

				if (count($rx_matches) > 0 and strlen($arh_key) > 2) {
					foreach ($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
					$arh_key = implode('-', $rx_matches);
				}

				$arh[strtolower($arh_key)] = $val;
			}
		}

		return ($arh);
	}

	public static function baseFunction()
	{
		$request = (string) $_REQUEST[Autoloader::REQUEST_BASE_KEY];
		$requestArr = explode("/", $request);
		return "action" . str_replace("-", "", ucwords(end($requestArr), "-"));
	}

	public static function translate($template, $keywords)
	{
		return  str_replace(array_keys($keywords), $keywords, $template);
	}

	public static function requredArrayWebHooks($param)
	{
		if (!empty($param)) {
			return !is_array($param) ? json_decode($param, 1) : $param;
		}

		return self::responseWithException("401! Unable to locate required hook params");
	}

	public static function array2csv(array &$array)
	{
		if (count($array) == 0) {
			return null;
		}

		ob_start();
		$df = fopen("php://output", 'w');
		fputcsv($df, array_keys(reset($array)));

		foreach ($array as $row) {
			fputcsv($df, $row);
		}

		fclose($df);
		return ob_get_clean();
	}

	public static function foreDownload($filename)
	{
		// disable caching
		$now = gmdate("D, d M Y H:i:s");
		header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
		header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
		header("Last-Modified: {$now} GMT");

		// force download  
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");

		// disposition / encoding on response body

		header("Content-Disposition: attachment;filename={$filename}");
		header("Content-Transfer-Encoding: binary");
		readfile("https://api-elearningcentre.firerecruitmentaustralia.com.au/tmp/{$filename}");
		exit;
	}

	public static function generateCsv($data, $filename)
	{
		$fp = fopen("tmp/{$filename}.csv", 'w');

		foreach ($data as $fields) {
			fputcsv($fp, $fields);
		}

		fclose($fp);

		return "https://api-elearningcentre.firerecruitmentaustralia.com.au/tmp/{$filename}.csv";
	}

	public static function imageUrl2base64($url)
	{
		return 'data:image/' . substr($url, strrpos($url, '.') + 1) . ';base64,' . base64_encode(file_get_contents($url));
	}

	public static function removeTmpFiles()
	{
		$fileList = glob('tmp/*');

		//Loop through the array that glob returned.
		foreach ($fileList as $filename) {
			//Simply print them out onto the screen.
			if (file_exists($filename)) {
				if (self::getCurrentDate('Y-m-d') > DATE("Y-m-d", filemtime($filename))) {
					try {
						unlink($filename);
					} catch (\Exception $e) {
						// do nothing
					}
				}
			}
		}

		self::response(true, null, ["executed_at" => self::getCurrentDate()]);
	}

	public static function validateDate($date)
	{
		return (bool)strtotime($date);
	}

	public static function stringToClass($string)
	{
		return implode('', array_map('ucfirst', explode("_", $string)));
	}

	public static function validateEmail($email)
	{
		$isValid = true;
		$atIndex = strrpos($email, "@");

		if (is_bool($atIndex) && !$atIndex) {
			$isValid = false;
		} else {
			$domain = substr($email, $atIndex + 1);
			$local = substr($email, 0, $atIndex);
			$localLen = strlen($local);
			$domainLen = strlen($domain);
			$domain_arr = explode(".", $domain);

			if ($localLen < 1 || $localLen > 64) {
				$isValid = false;
			} else if ($domainLen < 1 || $domainLen > 255) {
				$isValid = false;
			} else if ($local[0] == '.' || $local[$localLen - 1] == '.') {
				$isValid = false;
			} else if (preg_match('/\\.\\./', $local)) {
				$isValid = false;
			} else if (!preg_match('/^[A-z0-9\\+-\\.]+$/', $domain)) {
				$isValid = false;
			} else if (preg_match('/\\.\\./', $domain)) {
				$isValid = false;
			} else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local))) {
				if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local))) {
					$isValid = false;
				}
			} else if (strrpos($domain, ".") === false) {
				$isValid = false;
			} else if (end($domain_arr) == NULL) {
				$isValid = false;
			}
		}

		return $isValid;
	}

	public static function imageUploader()
	{
		$processStatus = false;
		$error = null;
		$success = null;

		if (isset($_FILES) && !empty($_FILES['image'])) {
			$path = realpath(dirname(__FILE__)) . "../../../tmp/uploads/images";

			// Utilities::dd($path);

			if (!file_exists($path)) {
				@mkdir($path, 0777, true);
			}

			$output = array();
			$uniqID = uniqid();
			$valid_formats = array("jpeg", "png", "jpg");
			$name = $_FILES['image']['name'];
			$size = $_FILES['image']['size'];
			$tmp  = $_FILES['image']['tmp_name'];
			$type = $_FILES['image']['type'];

			if ($name) {
				$ext_arr = explode(".", $name);
				$ext = end($ext_arr);

				if (in_array($ext, $valid_formats)) {
					if ($size < 100000000) { // 10MB
						try {
							if (move_uploaded_file($tmp, $path . '/' . $uniqID . '.' . $ext)) {
								$processStatus = true;
								$success = [
									"path" => "/tmp/uploads/images/{$uniqID}.{$ext}",
									"file" => [
										"name" => $name,
										"size" => $size,
										"tmp_name" => $tmp,
										"type" => $type,
									],
									"file_name" => "{$uniqID}.{$ext}",
									"extension" => $ext,
								];
							} else {
								$error = ["error" => "Unable to upload file. Please try again later."];
							}
						} catch (\Exception $e) {
							$error = ["error" => $e->getMessage()];
						}
					} else {
						$error = ["error" => "File exceeds minimum size requirements 10MB. Please try again later."];
					}
				} else {
					$error = ["error" => "Invalid file format."];
				}
			} else {
				$error = ["error" => "Please select a file."];
			}

			ob_flush();
			flush();
		}

		return [
			"status" => $processStatus,
			"error" => $error,
			"content" => $success,
		];
	}

	public static function imageDataUploader($image_data)
	{
		$processStatus = false;
		$error = null;
		$success = null;
		$uniqID = uniqid();
		$path = "tmp/uploads/images";

		list($type, $data) = explode(';', $image_data); // exploding data for later checking and validating 

		if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type)) {
			$data = substr($data, strpos($data, ',') + 1);
			$type = strtolower($type[1]); // jpg, png, gif

			if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
				$error = "invalid image type";
			} else {
				$data = base64_decode($data);

				if ($data === false) {
					$error = "base64_decode failed";
				}
			}
		} else {
			$error = "did not match data URI with image data";
		}

		if (is_null($error)) {
			if (file_put_contents("{$path}/{$uniqID}.{$type}", $data)) {
				$processStatus = true;
				$success = [
					"path" => "{$path}/{$uniqID}.{$type}",
					"file" => [
						"name" => $uniqID,
						"type" => $type,
					],
					"file_name" => "{$uniqID}.{$type}",
					"extension" => $type,
				];
			}
		}

		return [
			"status" => $processStatus,
			"error" => $error,
			"content" => $success,
		];
	}

	public static function imgDataUploader($image_data)
	{
		$processStatus = false;
		$error = null;
		$success = null;
		$uniqID = uniqid();
		$path = "tmp/uploads/contracts";

		list($type, $data) = explode(';', $image_data); // exploding data for later checking and validating 

		if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type)) {
			$data = substr($data, strpos($data, ',') + 1);
			$type = strtolower($type[1]); // jpg, png, gif

			if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
				$error = "invalid image type";
			} else {
				$data = base64_decode($data);

				if ($data === false) {
					$error = "base64_decode failed";
				}
			}
		} else {
			$error = "did not match data URI with image data";
		}

		if (is_null($error)) {
			if (file_put_contents("{$path}/{$uniqID}.{$type}", $data)) {
				$processStatus = true;
				$success = [
					"path" => "{$path}/{$uniqID}.{$type}",
					"file" => [
						"name" => $uniqID,
						"type" => $type,
					],
					"file_name" => "{$uniqID}.{$type}",
					"extension" => $type,
				];
			}
		}

		return [
			"status" => $processStatus,
			"error" => $error,
			"content" => $success,
		];
	}
}
