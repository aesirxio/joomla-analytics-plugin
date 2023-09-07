<?php

namespace Aesirx\System\AesirxAnalytics\Cli;

class Env
{
	protected $data = [];

	public function __construct(
		string $license,
		string $user,
		string $password,
		string $dbName,
		string $prefix,
		string $host,
		string $port = null
	)
	{
		$this->data = [
			'DBUSER' => $user,
			'DBPASS' => $password,
			'DBNAME' => $dbName,
			'DBTYPE' => 'mysql',
			'LICENSE' => $license,
			'DBPREFIX' => $prefix,
			'DBHOST' => $host,
		];

		if ($port)
		{
			$this->data['DBPORT'] = $port;
		}
	}

	public function getData(): array
	{
		return $this->data;
	}
}