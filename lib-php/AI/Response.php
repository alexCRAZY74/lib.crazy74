<?php

declare(strict_types=1);

namespace AI;

use console;

class Response {

	public const ?string FORMAT = null;

	public bool $success = false;
	public mixed $answer = null;
	public mixed $error = null;
	protected array $data = [];

	public function getFormat(): ?array {
		if (is_string(static::FORMAT) && static::FORMAT !== '') {
			return [static::FORMAT];
		}
		return null;
	}

	public function get(): string {
		return implode("\r\n", $this->data);
	}

	public function toArray(): array {
		return $this->data;
	}

	public function ParseAnswerText(string $text): void {
		$this->data[] = $text;
	}

	public function Process(mixed $parsedResponse): void {
		$debug = false;
		if ($debug) {
			console::groupFunc();
			console::log('$parsedResponse', $parsedResponse);
		}

		$this->success = false;

		if (is_array($parsedResponse)) {
			$textList = \array_var::get_array($parsedResponse, 'text_list');
			if (!empty($textList)) {
				foreach ($textList as $textValue) {
					if (is_string($textValue)) {
						$this->success = true;
						$this->ParseAnswerText($textValue);
					}
				}
			}

			if (array_key_exists('error', $parsedResponse)) {
				$this->error = \array_var::get($parsedResponse, 'error');
			}

			if (array_key_exists('raw', $parsedResponse)) {
				$this->answer = \array_var::get($parsedResponse, 'raw');
			}
		}

		if ($debug) {
			console::log('$this', $this);
			console::groupEnd();
		}
	}
}