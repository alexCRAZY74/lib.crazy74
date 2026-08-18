<?php

declare(strict_types=1);

namespace AI;

abstract class GoogleType extends API {

	public const array MODELS = [];
	public const array API_KEYS = [];
	public const string SESSION_KEY = '__GoogleType__key_num__';

	public string|int|false $model = false;

	public function __construct(string|int|false $model = false) {
		$this->SetModel($model);
		if ($this->model === false) {
			$this->SetModel(0);
		}
	}

	public function SetModel(string|int|false $model): void {
		if (is_string($model) && $model !== '') {
			$this->model = $model;
		} elseif (is_numeric($model)) {
			$list = array_keys(static::MODELS);
			$index = (int) $model;
			if (isset($list[$index])) {
				$this->model = $list[$index];
			}
		}
	}

	protected function MakeREST(): ?\REST\HTTP {
		$keys = static::API_KEYS;
		if (empty($keys)) {
			return null;
		}

		$sessKey = static::SESSION_KEY;
		$keyNum = (int) \array_var::get($_SESSION, $sessKey, -1) + 1;
		if ($keyNum >= count($keys)) {
			$keyNum = 0;
		}

		$_SESSION[$sessKey] = $keyNum;
		$apikey = $keys[$keyNum];

		$http = new \REST\HTTP();
		$model = (string) $this->model;
		$http->webhookUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apikey}";
		$http->CommandType = \REST\HTTP::COMMAND_IN_PARAMS;
		$http->verifySSLCertificate = false;
		$http->postAsJSON = true;

		return $http;
	}

	protected function MakeDATA(Request $request, Response $response): array {
		$data = parent::MakeDATA($request, $response);
		$data['generationConfig'] = [
			'responseMimeType' => 'application/json',
		];

		return $data;
	}

	protected function ParseAnswer(mixed $answer): array {
		$parsedResponse = [
			'text_list' => [],
			'error' => null,
			'raw' => $answer,
		];

		if (is_array($answer)) {
			$candidates = \array_var::get_array($answer, 'candidates');
			if (!empty($candidates)) {
				foreach ($candidates as $candidate) {
					if (is_array($candidate)) {
						$parts = \array_var::get_array($candidate, ['content', 'parts']);
						if (!empty($parts)) {
							foreach ($parts as $part) {
								if (is_array($part)) {
									$text = \array_var::get($part, 'text');
									if ($text !== null && $text !== '') {
										$parsedResponse['text_list'][] = (string) $text;
									}
								}
							}
						}
					}
				}
			}

			if (array_key_exists('error', $answer)) {
				$parsedResponse['error'] = \array_var::get($answer, 'error');
			}
		}

		return $parsedResponse;
	}
}