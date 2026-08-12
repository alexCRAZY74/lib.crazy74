<?php
abstract class DebugHandler {
	public static $forcedOutput = false;
  public static function __callStatic($name, $arguments) {
    /*echo "<pre>test: ".print_r(array(
			'get_called_class'=>get_called_class(),
			'self::class'=>self::class,
			'__CLASS__'=> __CLASS__,
		),true)."</pre>";*/
		if (!static::$forcedOutput) {
			if (defined('PHPDEBUG_MODE_OUTPUT') && !PHPDEBUG_MODE_OUTPUT) {
				return;
			}
		}
    $class = get_called_class();
    $static_postfix = '__static';
    $method = '_'.$name.$static_postfix;
    $mList = get_class_methods($class);
    if (in_array($method, $mList)) {
      return call_user_func_array(array($class, $method), $arguments);
    }
    echo "<pre>";
    echo "Вызов статического метода '$name' (`$class`::`$method`) "
         . implode(', ', $arguments). "\n";
		echo static::_lineFile__static() . "\n";
    print_r($mList);echo "\r\n";echo "\r\n";
    echo "</pre>";
	}
	protected static function _trace__static(){
		static::echo('tracecall',static::backtrace());
	}
	protected static function _logTrace__static(){
		static::trace();
	}
	public static $skipfiles = array();
	protected static function _backtrace__static(){
		//static::echoGroup(__METHOD__);
		$result = array();
		$db = debug_backtrace();
		//static::outecho('static::$skipfiles', static::$skipfiles);
		//echo "<pre>static::\$skipfiles: ".print_r(static::$skipfiles, true)."</pre>";
		//static::outecho('$db', $db);
		if (is_array($db) && !empty($db)) {
			foreach($db as $row) {
				$skip = false;
				if (isset($row['file']) && $row['file'] == __FILE__) {
					$skip = true;
				}
				if (isset($row['file']) && in_array($row['file'], static::$skipfiles)) {
					$skip = true;
				}
				if (!$skip) {
					if (isset($row['file'])) $row['file'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['file']);
					unset($row['object']);
					unset($row['args']);
					$result[] = $row;
				}
			}
		}
		//static::outecho('$result', $result);
		//static::echoGroupEnd();
		return $result;
	}
	protected static function _lineFile__static($short = false) {
		$result = '';
		$db = static::backtrace();
		//static::outecho('$db', $db);
		if (is_array($db) && !empty($db)) {
			$result = ( $short ? basename($db[0]['file']) : $db[0]['file'] ).':'.$db[0]['line'];
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
	protected static function _outecho__static(){
		return call_user_func_array(array(__CLASS__, '_echo__static'), func_get_args());
	}
	protected static function _memory__static(){
		$result = memory_get_usage(false);
		static::_echo__static('memory usage', \utils::sizebytes($result).' ('.\utils::sizebytes(memory_get_usage(true)).')');
		return $result;
	}
	protected static function _echo__static(){
		$type = 'struc';
		$title = '';
		$variable = null;
		$args = func_get_args();
		if (!empty($args) && is_string($args[0])
			&& in_array(strtolower($args[0]), array('struc','dump','textarea','table'))) {
			$type = strtolower(array_shift($args));
		}
		if (count($args) > 1 &&  is_string($args[0])) {
			$title = array_shift($args);
		}
		if (count($args) == 1) {
			$variable = $args[0];
		} else {
			$variable = $args;
		}
		if ($type == 'textarea' && !is_string($variable)) {
			$type = 'struc';
		}
		if ($type == 'table') {
			debug::table($title,$variable);
		} else {
			$cssBlock = array(
				'color' => 'black',
				'font-size' => '1rem',
				'margin'=>'0.3rem 0rem',
				'padding'=>'0.1rem 1rem',
				'border' => '1px dashed #d96500',
				'background-color' => '#f7f4e2',
				'display' => 'inline-block',
				'vertical-align' => 'top',
			);
			$cssTitle = array('color'=>'blue','white-space'=>'nowrap');
			echo "\r\n";
			static::_html_tag_open('pre',$cssBlock);
			static::_html_tag('p',static::lineFile(), array(
				'margin' => 0,
				'font-size' => '0.9rem',
				'margin-bottom' => '0.2rem',
			));
			if ($type == 'textarea') {
				if (!empty($title)) {
					static::_html_tag('span', $title, $cssTitle); echo ":\r\n";
				}
				static::_html_tag('textarea',$variable, array(
					'margin' => 0,
					'width' => '100%'
				));
			} else {
				if (!empty($title)) {
					static::_html_tag('span', $title, $cssTitle); echo " = ";
				}
				echo static::_dump_var($variable, $type);
			}
			static::_html_tag_close();
			echo "\r\n";
		}
	}
	protected static function _groupFunc__static(){
		$title = null;
		$db = debug_backtrace();
		//static::outecho('$db', $db);
		$trace = array();
		$caller = array();
		if (is_array($db) && !empty($db)) {
			foreach($db as $row) {
				$skip = false;
				if (isset($row['class']) && in_array($row['class'], array(__CLASS__,'console'))) {
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
	protected static function _dump_var($variable, $type = 'var') {
		$el = error_reporting();
		error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
		ob_start();
		if ($type == 'dump') {
			var_dump($variable);
		} else {
			var_export($variable);
		}
		error_reporting($el);
		$dump = ob_get_contents();
		ob_end_clean();
		$dump = str_replace("]=>\n", "] =>", $dump);
		$dump = str_replace("=>\n", "=>", $dump);
		$dump = str_replace("=> \n", "=>", $dump);
		$dump = str_replace(["=>  ","=>   "], "=> ", $dump);
		//$dump = str_replace(["\n)","\n  )"], ")", $dump);
		$dump = str_replace(["(\n)","(\n  )"], "()", $dump);
		//$dump = str_replace(["    ","   ","  "], " ", $dump);
		return htmlspecialchars($dump);
	}
	protected static function _table__static($label,$data = false){
		$cssTable = array(
			'color'=>'black',
			'background-color'=>'#f7f4e2',
			'border'=>'2px dotted #d96500',
			'border-collapse' => 'collapse',
			'font-size'=>'0.9rem',
		);
		$cssCell = array(
			'border'=>'1px dotted #d96500',
			'padding' => '0.1em 0.3em',
		);
		$cssCellKey = array_merge($cssCell,array(
			'white-space' => 'nowrap',
			'font-size'=>'0.9em',
		));
		$cssCellData = array_merge($cssCell,array(
			'padding' => '0.2em 1em',
		));
		$cssCellCaprion = array_merge($cssCell, array(
			'background-color' => '#f7f4e2',
			'text-align' => 'left',
			'border-bottom' => 'none',
		));
		if ($data === false) {
      $data = $label;
      $label = '';
    }
    if (!is_array($data) || empty($data)) {
      static::echo($label, $data);
      return;
    }
		$keys = array_keys($data);
		$row = $data[$keys[0]];
		if (is_object($row)) $row = (array)$row;
		/*if (!is_array($row)) {
      static::echo($label, $data);
      return;
		}*/
    $keys = is_array($row) ? array_keys($row) : array(null);
    if (is_array($row)) foreach ($data as $k=>$row) {
			if (is_object($row)) $row = (array)$row;
      $keys = array_merge($keys,array_keys($row));
    }
    $keys = array_unique($keys);
		$keysInRow = count($keys) > 1 || (count($keys) == 1 && is_string($keys[0]));
		static::_html_tag_open('table', $cssTable);
		static::_html_tag_open('caption', $cssCellCaprion);
		if (is_string($label) && !empty($label)) {
			static::_html_tag('span', $label, array('color'=>'blue','font-size'=>'1.15em',));
			echo "<br/>";
		}
		static::_html_tag('span', static::lineFile());
		static::_html_tag_close(); //caption
		static::_html_tag_open('thead');
		static::_html_tag_open('tr');
		static::_html_tag('th', 'key', $cssCellKey);
		foreach ($keys as $key) {
			static::_html_tag('th', '`'.$key.'`', $cssCellKey);
		}
		static::_html_tag_close(); //tr
		static::_html_tag_close(); //thead
		static::_html_tag_open('tbody');
		foreach ($data as $index => $row) {
			if (is_object($row)) $row = (array)$row;
			static::_html_tag_open('tr');
			static::_html_tag('td', $index, $cssCellKey);
			foreach ($keys as $key) {
				if ($keysInRow) {
					$value = isset($row[$key]) ? $row[$key] : null;
				} else {
					$value = $row;
				}
				if (isset($row[$key]) || !$keysInRow) {
					if (is_string($value)) {
						$td = "'{$value}'";
					} elseif (is_array($value) || is_object($value)) {
						$td = '<pre><small>'.static::_dump_var($value).'</small></pre>';
					} else {
						$td = json_encode($value);
					}
				} else {
					$td = '&nbsp;';
				}
				static::_html_tag('td', $td, $cssCellData);
			}
			static::_html_tag_close(); //tr
		}
		static::_html_tag_close(); //tbody
		static::_html_tag_close(); //table
	}
	protected static $group_keys = array();
	protected static $times = null;
	protected static $mem_start = array();
	public static $cssGroupLevels = array(
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
	public static $GroupLevel = -1;
	protected static function _group__static($title = null, $border = null, $backgroundcolor = null) {
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
		$key = 'group_'.hrtime(true).'_'.count(static::$group_keys);
		static::$group_keys[] = $key;
    if (class_exists('worktimes')) {
      if (!is_a(static::$times, 'worktimes')) {
        static::$times = new worktimes();
				//static::$times->debug = true;
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
	protected static function _groupEnd__static($label = false) {
		$key = array_pop(static::$group_keys);
		$postInfo = array();
		if (is_string($label) && !empty($label)) {
			$postInfo[] = "<b>{$label}</b>";
		}
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
	protected static function _echoGroup__static(){
		return call_user_func_array(array(__CLASS__, '_group__static'), func_get_args());
	}
	protected static function _echoGroupEnd__static(){
		return call_user_func_array(array(__CLASS__, '_groupEnd__static'), func_get_args());
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

	protected static function _test__static(){
		static::outecho('lineFile', static::lineFile());
		static::outecho('backtrace', static::backtrace());
	}
}
