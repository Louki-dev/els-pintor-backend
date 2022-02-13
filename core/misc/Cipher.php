<?php

namespace core\misc;

use core\config\Env;

class Cipher extends Env
{
	private $cipher = 'AES-128-CBC';
	private $option = OPENSSL_RAW_DATA;
	private $cipherKey;
	private $cipherIv;

	public function __construct()
	{
		$env = (new Env())->getEnvFile();

		if (empty($env["KEY"])) {
			Utilities::responseWithException("Mising Encryption Key.");
		}

		if (empty($env["IV"])) {
			Utilities::responseWithException("Mising Illustration Vector.");
		}

		$this->cipherKey = $env['KEY'];
		$this->cipherIv = $env['IV'];
	}

	public function encrypt($text)
	{
		return base64_encode(openssl_encrypt($text, $this->cipher, $this->cipherKey, $this->option, $this->cipherIv));
	}

	public function decrypt($text)
	{
		return openssl_decrypt(base64_decode($text), $this->cipher, $this->cipherKey, $this->option, $this->cipherIv);
	}
}