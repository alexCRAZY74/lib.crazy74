<?php
declare(strict_types=1);
namespace page;

use number;
use console;
use dates;

class quantities extends basePage {
	public function __construct() {
		console::groupFunc();
		parent::__construct();
		console::log('number::smartFormat', number::smartFormat(350877.55, 'pcs'));
		console::log('number::sizebytes', number::sizebytes(35087788554));
		console::log('number::round', number::round(3508.7788554));
		console::log('number::ending', number::ending(3508.7788554));
		console::log('dates::fmtForMysql', dates::fmtForMysql('now'));
		console::log('dates::FormatLocale', dates::FormatLocale('now'));
		console::log('dates::fmtRussian', dates::fmtRussian('now'));
		console::log('dates::fmtSmart', dates::fmtSmart('now'));
		console::groupEnd();
	}
}
