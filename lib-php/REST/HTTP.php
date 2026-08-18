<?php

declare(strict_types=1);

namespace REST;

use console;

class HTTP {

	public const COMMAND_AS_PATH = 'path_ext';
	public const COMMAND_AS_JSON_FILE = 'json_ext';
	public const COMMAND_IN_PARAMS = 'default';

	public ?string $webhookUrl = null;
	public bool $verifySSLCertificate = false;
	public bool $postAsJSON = false;
	public string $CommandType = self::COMMAND_IN_PARAMS;
	public ?string $token = null;

	public function request(mixed ...$arguments): ?array {
		$result = null;
		$debug = false;
		$debugecho = $debug;

		if ($debugecho) {
			console::groupFunc();
		}

		$cURL = curl_init();
		if ($cURL === false) {
			if ($debugecho) {
				console::groupEnd();
			}
			return ['error' => 'cURL initialization failed'];
		}

		$url = '';
		if (is_string($this->webhookUrl) && !empty($this->webhookUrl)) {
			$url = match ($this->CommandType) {
				static::COMMAND_AS_PATH => $this->webhookUrl . (string) (!empty($arguments) ? array_shift($arguments) : ''),
				static::COMMAND_AS_JSON_FILE => $this->webhookUrl . (string) (!empty($arguments) ? array_shift($arguments) : '') . '.json',
				default => $this->webhookUrl,
			};

			if ($debug) {
				console::log('$url', $url);
			}

			curl_setopt($cURL, CURLOPT_URL, $url);
		}

		$postData = '';
		if ($debug) {
			console::log('$arguments', $arguments);
		}

		if (!empty($arguments)) {
			if ($this->postAsJSON) {
				$postData = json_encode($arguments[0], JSON_UNESCAPED_UNICODE);
			} else {
				$postData = http_build_query($arguments[0]);
			}

			if ($debug) {
				console::text('$postData', $postData);
			}

			curl_setopt($cURL, CURLOPT_POST, true);
			curl_setopt($cURL, CURLOPT_POSTFIELDS, $postData);
		}

		$headers = [];
		if (is_string($this->token) && !empty($this->token)) {
			$headers[] = 'Authorization: Bearer ' . $this->token;
		}

		if ($this->postAsJSON) {
			$headers[] = 'Content-Type: application/json';
		}

		if ($debug) {
			console::log('$headers', $headers);
		}

		curl_setopt($cURL, CURLOPT_HTTPHEADER, $headers);

		if ($this->verifySSLCertificate) {
			curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, true);
		} else {
			curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, false);
		}

		curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);

		$answer = curl_exec($cURL);

		if ($debug) {
			console::text('$answer', $answer);
		}

		if ($debugecho) {
			$rawAnswer = is_string($answer) ? $answer : '';
			console::text('data', "\$url: {$url}\r\n\$headers: " . json_encode($headers) . "\r\n\$postData: {$postData}\r\n\$answer: {$rawAnswer}");
		}

		$curlError = curl_error($cURL);
		if ($curlError !== '') {
			if ($debug) {
				console::log('curl_error($cURL)', $curlError);
			}
			$result = ['error' => $curlError];
		} else {
			$decoded = is_string($answer) ? json_decode($answer, true) : null;
			$result = is_array($decoded) ? $decoded : ['raw' => $answer];
		}

		curl_close($cURL);

		if ($debug) {
			console::log('$this', $this);
			console::log('return', $result);
		}

		if ($debugecho) {
			console::groupEnd();
		}

		return $result;
	}
}