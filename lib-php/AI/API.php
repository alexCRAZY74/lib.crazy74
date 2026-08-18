<?php

declare(strict_types=1);

namespace AI;

class API {

	public function Request(Request $request, Response $response): void {
		$rest = $this->MakeREST();
		if (!is_object($rest) || !method_exists($rest, 'request')) {
			return;
		}

		$data = $this->MakeDATA($request, $response);
		$answer = $rest->request($data);
		$parsedData = $this->ParseAnswer($answer);
		$response->Process($parsedData);
	}

	protected function ParseAnswer(mixed $answer): mixed {
		return $answer;
	}

	protected function MakeDATA(Request $request, Response $response): array {
		$data = [
			'contents' => [
				[
					'parts' => [],
				],
			],
		];

		$format = $response->getFormat();
		if (is_array($format) && !empty($format)) {
			foreach ($format as $value) {
				if (is_string($value) && $value !== '') {
					$data['contents'][0]['parts'][] = ['text' => $value];
				}
			}
		}

		$reqData = $request->get();
		if (is_array($reqData) && !empty($reqData)) {
			foreach ($reqData as $value) {
				$data['contents'][0]['parts'][] = ['text' => $value];
			}
		}

		return $data;
	}

	protected function MakeREST(): ?object {
		return null;
	}
}