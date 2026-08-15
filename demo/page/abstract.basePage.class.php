<?php
namespace page;
use \console;

abstract class basePage extends \TwigPage {
  function __construct(){
		$debug = true;
		console::groupFunc();
    parent::__construct();
		$this->actionURL = \App::urlServer().'/index.php';
    $parts = explode('\\',  get_class($this));
    array_shift($parts);
    $this->PageID = implode("_", $parts);
    $scriptName = "/demo/js/page.".implode(".", $parts).".js";
    //\debug::outecho('test',\lang::getSection('pages'));
    if ($debug) console::log('$scriptName',$scriptName);
    if (file_exists(__DIR_ROOT_.$scriptName)) {
      $this->pageJS = $scriptName;
    }
		if ($debug) console::log('$this', $this);
		console::groupEnd();
  }
}
