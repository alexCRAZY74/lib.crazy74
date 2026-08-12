<?php
class worktimes {
  var $ignore = false;
  var $data = array();
  function start($id,$title = NULL,$forceIgnore = false) {
    if (!$this->ignore || $forceIgnore) {
      if ($title != NULL) {
        $this->data[$id]['title'] = $title;
      } else {
        $this->data[$id]['title'] = $id;
      }
      $this->data[$id]['start'] = microtime(true);
    }
  }
  function stop($id = false) {
    if ($id === false) {
      foreach($this->data as $key=>$v) {
        if (!isset($this->data[$key]['stop'])) $this->stop($key);
      }
      return;
    }
    if (isset($this->data[$id])) {
      $this->data[$id]['stop'] = microtime(true);
      $this->data[$id]['result'] = ($this->data[$id]['stop'] - $this->data[$id]['start']);
    }
  }
  function getstrtime($tt) {
    $tv = $tt;
    $um = 'ms';
    $dd = 3;
    if ($tv < 1) $dd = 6;
    if ($tv > 1000) {
      $um = 'sec';
      $tv = $tv/1000;
      if ($tv > 60) {
        $um = 'min';
        $tv = $tv/60;
      }
    }
    return round($tv,$dd).' '.$um;
  }
	function get_item($key) {
		$result = '';
		if (!empty($this->data) && isset($this->data[$key])) {
			$item = &$this->data[$key];
			if (!isset($item['result']))				$this->stop ($key);
			$result = $item['title'].": ".$this->getstrtime($item['result']*1000);
		}
		return $result;
	}
  function get_list(){
    $result = array();
    if (!empty($this->data)) {
      $data = $this->data;
      uasort($data,function($a,$b){
        //\debug::outecho('getCategories',$a,$b);
        if ($a['result'] == $b['result']) {
            return 0;
        }
        return ($a['result'] > $b['result']) ? -1 : 1;
      });
      foreach($data as $key=>$item) {
        //$result[] = $item['title'].": ".$this->getstrtime($item['result']*1000);
        $result[] = $this->get_item($key);
      }
    }
    return $result;
  }
}
