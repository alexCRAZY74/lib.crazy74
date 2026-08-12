<?php
$_ROOTFOLDERS = array();
//$_ROOTFOLDERS[] = dirname(__FILE__)."/";
$_ROOTFOLDERS[] = dirname(__FILE__)."/debug/";
require_once 'E:/Work/_lib_crazy74_git/lib-php/common.php';
if (App::ajax()){
  exit();
}
$pageClass = "\\page\\".(isset($_REQUEST['_page'])?$_REQUEST['_page']:'index');
$pageClass::Render();
//debug::outecho('App',App::get());