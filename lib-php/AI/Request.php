<?php

declare(strict_types=1);

namespace AI;

class Request {

	protected array $defines = [];
	protected array $data = [];

	public function __construct(array $def = []) {
		if (!empty($def)) {
			$this->defines = $def;
		}
	}

	public function Add(mixed $val): void {
		$this->data[] = $val;
	}

	public function AddDefine(mixed $val): void {
		$this->defines[] = $val;
	}

	public function AddTrainer(mixed $val): void {
		$this->defines[] = $val;
	}

	public function AddTrainerFile(
		string $file,
		string $title = '',
		string $location = ''
	): bool {
		if (!is_readable($file)) {
			return false;
		}

		$content = file_get_contents($file);
		if (!is_string($content) || $content === '') {
			return false;
		}

		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$contentType = match ($ext) {
			'json' => 'application/json',
			'md', 'markdown' => 'text/markdown',
			default => 'text/plain',
		};

		$header = "Content-Type: {$contentType}; charset: UTF-8;\r\n";
		if ($title !== '') {
			$header .= "Content-Title: {$title};\r\n";
		}
		if ($location !== '') {
			$header .= "Content-Location: {$location};\r\n";
		}

		$this->AddTrainer($header . "\r\n" . $content);
		return true;
	}

	public function get(): array {
		return array_merge($this->defines, $this->data);
	}
}