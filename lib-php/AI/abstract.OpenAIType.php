<?php

declare(strict_types=1);

namespace AI;

abstract class OpenAIType extends API {

	public const array MODELS = [];
	public const array API_KEYS = [];
	public const string API_URL = '';
	public const string SESSION_KEY = '__OpenAIType__key_num__';

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
		$http->webhookUrl = static::API_URL;
		$http->CommandType = \REST\HTTP::COMMAND_IN_PARAMS;
		$http->verifySSLCertificate = false;
		$http->postAsJSON = true;
		$http->token = $apikey;

		return $http;
	}

	protected function MakeDATA(&$request, &$response): array {
		$data = [
			'model' => $this->model,
			'messages' => [],
		];

		if (is_a($request, 'AI\Request') && is_a($response, 'AI\Response')) {
			$dumpFormat = $response->getFormat();
			if (is_array($dumpFormat) && !empty($dumpFormat)) {
				foreach ($dumpFormat as $value) {
					if (is_string($value) && $value !== '') {
						$data['messages'][] = [
							'role' => 'system',
							'content' => $value,
						];
					}
				}
			}

			$dumpRequest = $request->get();
			if (is_array($dumpRequest) && !empty($dumpRequest)) {
				foreach ($dumpRequest as $value) {
					if ($value !== null && $value !== '') {
						$data['messages'][] = [
							'role' => 'user',
							'content' => (string) $value,
						];
					}
				}
			}
		}

		return $data;
	}

	protected function ParseAnswer($answer): array {
		$parsedResponse = [
			'text_list' => [],
			'error' => null,
			'raw' => $answer,
		];

		if (is_array($answer)) {
			$choices = \array_var::get_array($answer, 'choices');
			if (!empty($choices)) {
				foreach ($choices as $choice) {
					if (is_array($choice)) {
						$textContent = \array_var::get($choice, ['message', 'content']);
						if ($textContent !== null && $textContent !== '') {
							$parsedResponse['text_list'][] = (string) $textContent;
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