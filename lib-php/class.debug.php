<?php
function debug_out($name, $var = null) {
	if (!defined("NL"))  define("NL","\r\n");
	//echo '<div style="border: 2px solid #d96500; color: #d96500"><pre>';
	echo '<pre style="border: 2px solid #d96500; color: #d96500; background-color:#f7f4e2">';
	echo date("d.m.Y H:i:s").NL;
	$db = debug_backtrace();
	//print_r($db);
	$path = array();
	for ($ii=count($db)-1;$ii>=2;$ii--) {
		if (isset($db[$ii]['function'])) $path[] = $db[$ii]['function'];
	}
	echo $db[0]['file'].':'.$db[0]['line'].(!empty($path)?NL.implode('->',$path):'').' {{'.NL;
	echo '  '.$name;
	if (!empty($var)) {
		echo " :: «<span style=\"color:#000\">";
		print_r($var);
		echo "</span>»".NL;
	} else {
		echo NL;
	}
	echo "}}".NL;
	//echo '</pre></div>';
	echo '</pre>';
}
class php_logfile {
  var $ignore = false;
  static $noOut = false;
  static $noWriteFile = false;
  static $noWriteErrors = false;
  var $filename;
  static $instance = false;
	function __construct($filename = null) {
    $this->filename = $_SERVER["DOCUMENT_ROOT"]."/company/warehouse/__debug.log";
    if ($filename != null && file_exists($filename)) {
      $this->filename = $filename;
    }
	    if (!defined("LOG"))    define("LOG",1);
	    if (!defined("INFO"))   define("INFO",2);
	    if (!defined("WARN"))   define("WARN",3);
	    if (!defined("ERROR"))  define("ERROR",4);
	    if (!defined("NL"))  define("NL","\r\n");
	}
	static function setFile($filename){
    if (static::$instance === false) static::$instance = new self();
    if ($filename != null && file_exists($filename)) {
      static::$instance->filename = $filename;
      return true;
    }
    return false;
	}
	function debug($name, $var = null, $type = LOG) {
    global $debugecho;
    if ($debugecho) self::echoGroup (__METHOD__);
    if ($debugecho) self::outecho('$this',$this);
    if ($this->ignore)	{
      if ($debugecho) self::echoGroupEnd ();
      return;
    }
    if ($this->isoftik && !in_array($_SERVER['SERVER_NAME'], array('php7.isoftik.kz','alex.isoftik.kz'))) {
      if ($debugecho) self::echoGroupEnd ();
      return;
    }
    if ($debugecho) self::outecho('is_writable', is_writable($this->filename)?'Yes':'No');
    if (filesize($this->filename)>=(5*1024*1024)) {
      file_put_contents($this->filename,'========================== Обрезано '.\dates::fmtRussian('now','j F Y г. H:i:s').' ==========================');
    }
		$fp = fopen( $this->filename, "a+" );
		if ($fp) {
			switch($type) {
				case LOG:
					//fputs( $fp, "LOG: " );
				break;
			}
      
			fputs( $fp, (isset($_SESSION['base'])?$_SESSION['base']:'').": " );
			fputs( $fp, date("d.m.Y H:i:s")." " );
			$db = debug_backtrace();
			//fputs( $fp, print_r($db,true).NL );
			$path = array();
			$fIndex = 0;
			foreach($db as $r) {
				if (isset($r['function'])) {
					unset($r['object']);
					unset($r['args']);
					if (isset($r['file']) && $r['file'] == $_SERVER["DOCUMENT_ROOT"].'/company/warehouse/PHPDebug.php') {
						$fIndex++;
					} elseif ((isset($r['function']) && $r['function'] != 'log') && (isset($r['class']) && $r['class'] != 'php_logfile')) {
						//fputs( $fp, print_r($r,true).NL );
						array_unshift($path,
							(isset($r['line'])?"[".$r['line']."]":'').( isset($r['class']) ? $r['class'].'::' : '' ).$r['function']
						);
					}
				}
			}
			/*for ($ii=count($db)-1;$ii>=2;$ii--) {
				if (isset($db[$ii]['function'])) {
					$path[] = ( isset($db[$ii]['class']) ? $db[$ii]['class'].'::' : '' ).$db[$ii]['function']."[".$db[$ii]['line']."]";
				}
			}*/
			fputs( $fp, $db[$fIndex]['file'].':'.$db[$fIndex]['line'].(!empty($path)?NL.implode(' -> ',$path):'').' {{'.NL );
			fputs( $fp, '  '.$name );
			if (!empty($var)) {
				//fputs( $fp, " :: «".print_r($var,true)."»".NL );
        ob_start();
        //var_dump($var);
        var_export($var);
        $dump = ob_get_contents();
        ob_end_clean();
        $dump = str_replace("=> \n", "=>", $dump);
				fputs( $fp, " :: «\r\n".$dump."\r\n»".NL );
			} else {
				fputs( $fp, NL );
			}
			fputs( $fp, "}}".NL );
			fclose( $fp );
		}
    if ($debugecho) self::echoGroupEnd ();
	}
  static $timekeys = array();
  static $mem_start = array();
  static $times = array();
  static $timescount = 0;
	public static function groupFunc(){
		$title = null;
		$db = debug_backtrace();
		//static::outecho('$db', $db);
		$trace = array();
		$caller = array();
		if (is_array($db) && !empty($db)) {
			foreach($db as $row) {
				$skip = false;
				if (isset($row['class']) && in_array($row['class'], array(__CLASS__,'debug'))) {
					$skip = true;
				}
				if (!$skip) {
					unset($row['file']);
					unset($row['line']);
					unset($row['object']);
					$trace[] = $row;
				}
			}
			//debug::outecho('$trace', $trace);
			//echo '<pre>$trace = '.print_r($trace,true).'</pre>';
			if (!empty($trace)) $caller = $trace[0];
		}
		if (!empty($caller)) {
			//debug::outecho('$caller', $caller);
			$args = array();
			$title = '';
			if (isset($caller['class'])) $title .= $caller['class'].' ';
			if (isset($caller['type'])) $title .= $caller['type'].' ';
			if (isset($caller['function'])) $title .= $caller['function'];
			if (isset($caller['args']) && !empty($caller['args'])) {
				foreach ($caller['args'] as $value) {
					if (is_string($value)) {
						$args[] = '`'.static::strcut($value).'`';
					} elseif (is_numeric($value)) {
						$args[] = $value;
					} elseif (is_bool($value)) {
						$args[] = $value?'true':'false';
					} elseif (is_null($value)) {
						$args[] = 'null';
					} else {
						$args[] = '..('.gettype($value).')'.static::strcut(json_encode($value),15).'';
					}
				}
			}
			$title .= '( '.implode(', ',$args).' )';
		}
		$args = func_get_args();
		array_unshift($args , $title);
		return call_user_func_array(array(static::class, 'group'), $args);
	}
  public static function echoGroup($title = null, $border = null, $background = null){
    if (static::$noOut) return;
		return call_user_func_array(array(static::class, 'group'), func_get_args());
    if (!is_string($border) || empty($border)) {
      $border = '1px solid rgb(0,117,236)';
    }
    if (!is_string($background) || empty($background)) {
      $background = 'rgba(233,253,255,0.5)';
    }
    if (class_exists('worktimes')) {
      if (!is_a(self::$times, 'worktimes')) {
        self::$times = new worktimes();
      }
      self::$timescount++;
      $key = time().self::$timescount;
      self::$timekeys[] = $key;
      self::$mem_start[$key] = memory_get_usage(true);
      self::$times->start($key,$title);
    }
    echo '<div class="do-debug-group" style="margin:0.3rem 0; padding:0.1rem 1rem; border: '.$border.'; color: #d96500; background: '.$background.';">';
    echo "<p ".'style="margin:0; color: black; font-family: monospace; font-size:0.95rem"'.">".self::lineFile()."</p>";
    if (!empty($title)) echo "<p ".'style="margin:0; color: blue; font-family: monospace; font-size:1.2em"'.">$title</p>";
  }
  public static function echoGroupEnd(){
    if (self::$noOut) return;
		return call_user_func_array(array(static::class, 'groupEnd'), func_get_args());
    if (class_exists('worktimes') && !empty(self::$timekeys)) {
      $key = array_pop(self::$timekeys);
      if (class_exists('number')) {
        $mem = '; memory usage '.number::sizebytes( memory_get_usage(true) - self::$mem_start[$key] ).' / '.number::sizebytes( memory_get_usage(true) );
      } else {
        $mem = '';
      }
      unset(self::$mem_start[$key]);
      self::$times->stop($key);
      echo "<p ".'style="margin:0; color: black; font-family: monospace; font-size:0.9em"'.">".self::$times->get_item($key).$mem."</p>";
    }
    echo "</div>";
  }
	protected static $cssGroupLevels = array(
		array(
			'border' => '1px solid #74b0ea',
			'background-color' => '#feffff',
			'color' => '#0454a2',
		),
		array(
			'border' => '1px solid #2f39ca',
			'background-color' => '#f1faff',
			'color' => '#020352',
		),
		array(
			'border' => '1px solid #52ac63',
			'background-color' => '#ecfde9',
			'color' => '#026226',
		),
		array(
			'border' => '1px solid #ad1ab9',
			'background-color' => '#f7f1fd',
			'color' => '#52024e',
		),
		array(
			'border' => '1px solid #3c3fa8',
			'background-color' => '#e3ecfb',
			'color' => '#444b90',
		),
	);
	protected static $group_keys = array();
	protected static $GroupLevel = -1;
	public static function group($title = null, $border = null, $backgroundcolor = null) {
    if (static::$noOut) return;
		$cssBlock = array(
			'border' => '1px solid #d96500',
			'background-color' => '#f7f4e2',
			'color' => 'black',
			'margin' => '0.3rem 0',
			'padding' => '0.1rem 1rem',
			'font-family' => 'monospace',
			'font-size' => '0.9rem',
		);
		static::$GroupLevel++;
		$GroupLevel = static::$GroupLevel;
		if (!isset(static::$cssGroupLevels[$GroupLevel])) {
			//static::_echo__static('$GroupLevel',$GroupLevel,count(static::$cssGroupLevels),($GroupLevel % count(static::$cssGroupLevels)));
			$GroupLevel = $GroupLevel % count(static::$cssGroupLevels);
		}
		$cssIndex = isset(static::$cssGroupLevels[$GroupLevel])
			? $GroupLevel : count(static::$cssGroupLevels)-1;
		$cssBlock = array_merge($cssBlock, static::$cssGroupLevels[$cssIndex]);
		if (is_string($backgroundcolor) && !empty($backgroundcolor)) {
			$cssBlock['background-color'] = $backgroundcolor;
		}
		if (!empty($border)) {
			if (is_string($border)) {
				$cssBlock['border'] = $border;
			}
			if (is_array($border)) {
				$cssBlock = array_merge($cssBlock, $border);
			}
		}
		static::_html_tag_open('div', $cssBlock);
		static::_html_tag('p', static::lineFile(), array(
			'margin' => 0,
			'font-size' => '0.9rem',
		));
		$key = 'group'.time().count(static::$group_keys);
		static::$group_keys[] = $key;
    if (class_exists('worktimes')) {
      if (!is_a(static::$times, 'worktimes')) {
        static::$times = new worktimes();
      }
      static::$times->start($key,static::strcut($title));
    }
		static::$mem_start[$key] = memory_get_usage(false);
		if (is_string($title) && !empty($title)) {
			static::_html_tag('div', $title, array(
				'color' => 'blue',
				'font-size' => '1.2rem',
			));
		}
		//return call_user_func_array(array('debug', 'echoGroup'), func_get_args());
	}
	public static function groupEnd() {
    if (static::$noOut) return;
		$key = array_pop(static::$group_keys);
		$postInfo = array();
		if (class_exists('worktimes')) {
			static::$times->stop($key);
			$postInfo[] = static::$times->get_item($key);
		}
		if (class_exists('number') && isset(static::$mem_start[$key])) {
			$postInfo[] = 'memory usage '.number::sizebytes( memory_get_usage(false) - static::$mem_start[$key] ).' / '.number::sizebytes( memory_get_usage(false) );
		}
		$postInfo[] = '<small>{'.static::lineFile(true).'}</small>';
		unset(static::$mem_start[$key]);
		if (!empty($postInfo)) {
			static::_html_tag('div', implode('; ',$postInfo));
		}
		static::$GroupLevel--;
		static::_html_tag_close();
		//return call_user_func_array(array('debug', 'echoGroupEnd'), func_get_args());
	}
  public static function table($label,$data = false){
    if (self::$noOut) return;
    if ($data === false) {
      $data = $label;
      $label = false;
    }
    if (!is_array($data)) {
      self::outecho($label, (empty($data)?'empty ':'').gettype($data));
      return;
    }
    $keys = array_keys($data);
    $row = $data[$keys[0]];
    if (!is_array($row)) {
      self::outecho($label, (empty($data)?'empty ':'').gettype($data));
      return;
    }
    $keys = array_keys($row);
    foreach ($data as $k=>$row) {
      $keys = array_merge($keys,array_keys($row));
    }
    $keys = array_unique($keys);
    $caption = '';
    $db = debug_backtrace();
    if (is_string($label) && !empty($label)) {
      $caption = "<span style=\"color: blue; font-weight: bold;\">".$label."</span>";
    }
    if (isset($db[0])) {
      $f = str_replace($_SERVER["DOCUMENT_ROOT"], '', $db[0]['file']);
      if (!empty($caption)) $caption .= "<br/>";
      $caption .= "<span style=\"color: #000; white-space: nowrap;\">{$f}: {$db[0]['line']}</span>";
    }
    echo "<pre><table class=\"do-debug-table\" border=\"1\" style=\"border: 2px solid #d96500; border-collapse: collapse; background-color:#f7f4e2; color:#000\">";
    echo "<caption style=\"background-color:#f7f4e2;\">{$caption}</caption>";
    echo "<thead>";
    echo "<tr>";
    echo "<th style=\"white-space: nowrap\">key</th>";
    foreach ($keys as $key) {
      echo "<th style=\"white-space: nowrap\">`{$key}`</th>";
    }
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    foreach ($data as $k=>$row) {
      echo "<tr>";
      echo "<td>{$k}</td>";
      foreach ($keys as $key) {
        $v = isset($row[$key]) ? $row[$key] : null;
        $ss = 'style="padding: 0.3em 0.5em; vertical-align: top; border-bottom-width: 1px;"';
        if (is_string($v)) $v = "'{$v}'";
        if (is_bool($v)) $v = $v?'true':'false';
        if (is_numeric($v)) $v = "{$v}";
        if (is_array($v)) $v = "<pre><small>".static::dumpVar($v,false)."</small></pre>";
        echo "<td $ss>{$v}</td>";
      }
      echo "</tr>";
    }
    echo "</tbody>";
    echo "</table></pre>";
  }
  static function outecho(){
    if (self::$noOut) return;
    $var_dump = false;
		$var = null;
		$varName = null;
		$asText = false;
		$args = func_get_args();
		if (count($args) > 0 && $args[0] == 'dump') {
			$var_dump = true;
			array_shift($args);
			/*unset($args[0]);
			ksort($args);*/
		}
		if (count($args) > 0 && $args[0] == 'textarea') {
			$asText = true;
			array_shift($args);
			/*unset($args[0]);
			ksort($args);*/
		}
		if (count($args) == 0) return;
		if (count($args) == 1) {
			$var = $args[0];
		} else {
			if (gettype($args[0]) == 'string') {
				$varName = $args[0];
				array_shift($args);
				/*unset($args[0]);
				ksort($args);*/
			}
			if (count($args) == 1) {
				$var = $args[0];
			} else {
				$var = $args;
			}
		}
		echo '<pre class="do-debug-block" style="margin:0.3rem 0rem; padding:0.1rem 1rem; border: 2px solid #d96500; color: #d96500; background-color:#f7f4e2; display: inline-block; vertical-align:top;">';
		$path = array();
    echo "<p ".'style="margin:0; color: black; font-family: monospace; font-size:0.95rem"'.">";
		echo self::lineFile().(!empty($path)?NL.implode(' -> ',$path):'');
		echo '</p>';
		if (!empty($var) || !empty($varName)) {
			echo "::";
			if (!empty($varName)) {
				echo " <span style=\"color:blue; white-space: nowrap;\">$varName</span>";
				if (!empty($var)) echo " =";
			}
			if (!empty($var)) {
				if ($asText && is_string($var)) {
					echo "\r\n <textarea>$var</textarea>";
				} else {
          $dump = static::dumpVar($var,$var_dump);
					echo " «<span style=\"color:#000; white-space: break-spaces;\">".$dump."</span>»";
				}
			}
		}
		echo '</pre>';
	}
  protected static function dumpVar($var,$var_dump = true) {
    ob_start();
    if ($var_dump) {
      var_dump($var);
    } else {
      var_export($var);
    }
    $dump = ob_get_contents();
    ob_end_clean();
    $dump = str_replace("]=>\n", "] =>", $dump);
    $dump = str_replace("=>\n", "=>", $dump);
    $dump = str_replace("=> \n", "=>", $dump);
    return htmlspecialchars($dump);
  }
	protected static function backtrace(){
		$result = array();
		$db = debug_backtrace();
		if (is_array($db) && !empty($db)) {
			foreach($db as $row) {
				$skip = false;
				if ($row['file'] == __FILE__) {
					$skip = true;
				}
				if (!$skip) {
					$row['file'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['file']);
					unset($row['object']);
					unset($row['args']);
					$result[] = $row;
				}
			}
		}
		return $result;
	}
	protected static function _html_css_from_array($css) {
		$result = '';
		if (is_array($css) && !empty($css)) {
			$lines = array();
			foreach($css as $key => $value) {
				$lines[] = "{$key}: {$value}";
			}
			$result = implode('; ', $lines);
		}
		return $result;
	}
	static function lineFile($short = false) {
		$result = '';
		$db = static::backtrace();
		//static::outecho('$db', $db);
		if (is_array($db) && !empty($db)) {
			$result = ( $short ? basename($db[0]['file']) : $db[0]['file'] ).':'.$db[0]['line'];
		}
		return $result;
	}
	protected static function strcut($text, $maxlen = 50) {
		if (!(is_string($text) && !empty($text))) return $text;
		$teaser = $text;
		if (mb_strlen($text) > $maxlen) {
			//this finds the position of the first period after 50 characters
			$period = mb_strpos($text, '.', $maxlen);
			//this finds the position of the first space after 50 characters
			//we can use this for a clean break if a '.' isn't found.
			$space = mb_strpos($text, ' ', $maxlen);
			$period = false;
			$space = false;
			if ($period !== false) {
				//this gets the characters 0 to the period and stores it in $teaser
				$teaser = mb_substr($text, 0, $period);
			} elseif ($space !== false) {
				//this gets the characters 0 to the next space
				$teaser = mb_substr($text, 0, $space);
			} else {
				//and if all else fails, just break it poorly
				$teaser = mb_substr($text, 0, $maxlen);
			}
			$teaser = trim($teaser).'..';
		}
		return $teaser;
	}
	protected static $stackTags = array();
	protected static function _html_tag_open($tag, $css = array()){
		static::$stackTags[] = $tag;
		echo "<".$tag;
		if (is_array($css) && !empty($css)) {
			echo ' style="'.static::_html_css_from_array($css).'"';
		}
		echo ">";
	}
	protected static function _html_tag_close(){
		echo "</".array_pop(static::$stackTags).">";
	}
	protected static function _html_tag($tag, $content, $css = array()){
		static::_html_tag_open($tag,$css);
		echo $content;
		static::_html_tag_close();
	}
  static function old_lineFile(){
		$fIndex = 0;
		$db = debug_backtrace();
		foreach($db as $r) {
			if (isset($r['function'])) {
				unset($r['object']);
				unset($r['args']);
				if (isset($r['file']) && $r['file'] == __FILE__) {
					$fIndex++;
				} 
			}
		}
    $db[$fIndex]['file'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $db[$fIndex]['file']);
    $result = $db[$fIndex]['file'].':'.$db[$fIndex]['line'];
    return $result;
  }
	static function log($name, $var = null){
    if (static::$instance === false) static::$instance = new self();
    static::$instance->debug($name, $var, LOG);
	}
	static function logTrace($forEcho = false){
		$db = debug_backtrace();
		//fputs( $fp, print_r($db,true).NL );
		$path = array();
		$fIndex = 0;
		foreach($db as $r) {
			if (isset($r['function'])) {
				unset($r['object']);
				unset($r['args']);
				if ($r['file'] == $_SERVER["DOCUMENT_ROOT"].'/company/warehouse/PHPDebug.php') {
					$fIndex++;
				} elseif ($r['function'] != 'log' && !(isset($r['class']) && $r['class'] == 'php_logfile') ) {
					$path[] = $r;
				}
			}
		}
		if ($forEcho) {
			self::outecho('traceCall',$path);			
		} else {
			self::log('traceCall',$path);			
		}
	}
}
class PHPDebug {

function __construct() {
    if (!defined("LOG"))    define("LOG",1);
    if (!defined("INFO"))   define("INFO",2);
    if (!defined("WARN"))   define("WARN",3);
    if (!defined("ERROR"))  define("ERROR",4);

    //define("NL","\r\n");
    /*echo '<script type="text/javascript">'.NL;

    /// Даннкод предназначен для браузеров без консоли
    echo 'if (!window.console) console = {};';
    echo 'console.log = console.log || function(){};';
    echo 'console.warn = console.warn || function(){};';
    echo 'console.error = console.error || function(){};';
    echo 'console.info = console.info || function(){};';
    echo 'console.debug = console.debug || function(){};';
    echo '</script>';*/
    /// Конец секции для барузеров без консоли
}

function debug($name, $var = null, $type = LOG) {
    echo '<script type="text/javascript">'.NL;
    $db = debug_backtrace();
    //var_dump($db);
    //echo 'console.info("'.($db).'");'.NL;
    $groupline = '';
    foreach ($db as $ind => $item) {
		//echo 'console.log("'.$ind.': '.$item.'");'.NL;
		$groupline = $item['file'];
		$groupline = $item['line'].':'.$groupline;
	}
    echo 'console.group("PHP:'.$groupline.'");'.NL;
    //echo 'console.info("'.$_SERVER["PHP_SELF"].'");'.NL;
    //echo 'console.log("HTTP_X_REQUESTED_WITH: '.$_SERVER['HTTP_X_REQUESTED_WITH'].'");'.NL;
    switch($type) {
        case LOG:
            echo 'console.log("'.$name.'");'.NL;
        break;
        case INFO:
            echo 'console.info("'.$name.'");'.NL;
        break;
        case WARN:
            echo 'console.warn("'.$name.'");'.NL;
        break;
        case ERROR:
            echo 'console.error("'.$name.'");'.NL;
        break;
    }

    if (!empty($var)) {
        if (is_object($var) || is_array($var)) {
            $object = json_encode($var);
            echo 'var object'.preg_replace('~[^A-Z|0-9]~i',"_",$name).' = \''.str_replace("'","\'",$object).'\';'.NL;
            echo 'var val'.preg_replace('~[^A-Z|0-9]~i',"_",$name).' = eval("(" + object'.preg_replace('~[^A-Z|0-9]~i',"_",$name).' + ")" );'.NL;
            switch($type) {
                case LOG:
                    echo 'console.debug(val'.preg_replace('~[^A-Z|0-9]~i',"_",$name).');'.NL;
                break;
                case INFO:
                    echo 'console.info(val'.preg_replace('~[^A-Z|0-9]~i',"_",$name).');'.NL;
                break;
                case WARN:
                    echo 'console.warn(val'.preg_replace('~[^A-Z|0-9]~i',"_",$name).');'.NL;
                break;
                case ERROR:
                    echo 'console.error(val'.preg_replace('~[^A-Z|0-9]~i',"_",$name).');'.NL;
                break;
            }
        } else {
            switch($type) {
                case LOG:
                    echo 'console.debug("'.str_replace('"','\\"',$var).'");'.NL;
                break;
                case INFO:
                    echo 'console.info("'.str_replace('"','\\"',$var).'");'.NL;
                break;
                case WARN:
                    echo 'console.warn("'.str_replace('"','\\"',$var).'");'.NL;
                break;
                case ERROR:
                    echo 'console.error("'.str_replace('"','\\"',$var).'");'.NL;
                break;
            }
        }
    }
    echo 'console.groupEnd();'.NL;
    echo '</script>'.NL;
}
}
$debug = new PHPDebug();
$logfile = new php_logfile();
//print_r($logfile);
class debug extends php_logfile {
  static $echo = false;
}