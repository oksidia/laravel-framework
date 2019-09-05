<?php namespace Illuminate\Encryption;

class Encrypter {

	/**
	 * Create a new encrypter instance.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function __construct($key)
	{
		$this->key = (string) $key;
	}

	/**
	 * @deprecated
	 */
	public function encrypt($value)
	{
		throw new \Exception("Deprecated");
	}

	/**
	 * @deprecated
	 */
	public function decrypt($payload)
	{
		throw new \Exception("Deprecated");
	}

	/**
	 * Set the encryption key.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function setKey($key)
	{
		$this->key = (string) $key;
	}

	/**
	 * @deprecated
	 */
	public function setCipher($cipher)
	{
		throw new \Exception("Deprecated");
	}

	/**
	 * @deprecated
	 */
	public function setMode($mode)
	{
		throw new \Exception("Deprecated");
	}

}
