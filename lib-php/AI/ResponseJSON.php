<?php

declare(strict_types=1);

namespace AI;

class ResponseJSON extends Response {

	public const ?string FORMAT = 'Отвечай ТОЛЬКО чистым JSON без пояснений и Markdown-разметки.';

}