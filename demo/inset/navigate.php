<?php
declare(strict_types=1);

namespace inset;

class navigate {

	public array|null $items = null;

	public function __construct() {
		\console::groupFunc();

		$list = \lang::getSection('pages');
		if (is_array($list) && !empty($list)) {
			$this->items = array_keys($list);
		}

		\console::groupEnd();
	}
}
