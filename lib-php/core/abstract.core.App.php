<?php
declare(strict_types=1);

namespace core;

ob_start('ob_gzhandler');

abstract class App {

	public static bool $debugMode = false;
	public static string $outMode = 'json';
	public static string $coreVersion = '0.2 beta';
	public static string $debugTitle = 'App::flush($result)';

	public static function get(): array {
		return [
			'coreVersion' => static::$coreVersion,
			'lang' => \lang::current(),
		];
	}

	public static function set_timezone(string $value = ''): array {
		$debug = false && class_exists('console') && \console::$echo;
		if ($debug) {
			\console::echoGroup(__METHOD__ . '( ' . json_encode(func_get_args()) . ' )');
			\console::outecho('request', $_REQUEST);
		}

		$reqTimezone = (string) \request::get('_timezone', '');
		if ($reqTimezone !== '') {
			$value = $reqTimezone;
		}

		$result = ['success' => true];
		$result['timezone'] = \session::timezone($value);

		if ($debug) {
			\console::outecho('$_SESSION', $_SESSION);
			\console::outecho('return', $result);
			\console::echoGroupEnd();
		}

		return $result;
	}

	public static function startup(): void {
		$outecho = \request::get_bool('outecho') || \request::get_bool('debug');
		static::$debugMode = $outecho;

		if (class_exists('console')) {
			\console::$echo = $outecho;
			\console::$noOut = !$outecho;
			if (!defined('PHPDEBUG_MODE_OUTPUT')) {
				define('PHPDEBUG_MODE_OUTPUT', $outecho);
			}
		}

		if (!isset($_SESSION)) {
			$sid = (string) \request::get('$SID', '');
			if ($sid !== '') {
				session_id($sid);
			}
			session_start();
		}

		if ($outecho) {
			header("Content-Type: text/html; charset=utf-8");
			error_reporting(E_ALL | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED);
			if (class_exists('console')) {
				\console::outecho($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_SOFTWARE'] ?? '', 'php ' . phpversion(), \session::timezone());
			}
		} else {
			error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
		}

		$debug = false && class_exists('console') && \console::$echo;
		if ($debug) {
			\console::echoGroup(__METHOD__ . '( ' . json_encode(func_get_args()) . ' )');
		}

		$json = (string) \request::get('json', '');
		if ($json !== '') {
			try {
				$decoded = json_decode(stripslashes($json), true);
				if (is_array($decoded)) {
					$_REQUEST = array_merge($_REQUEST, $decoded);
				}
			} catch (\Exception $ex) {
				
			}
			unset($_REQUEST['json']);
		}

		$jsondata = (string) \request::get('jsondata', '');
		if ($jsondata !== '') {
			try {
				$decoded = json_decode($jsondata, true);
				if (is_array($decoded)) {
					$_REQUEST = array_merge($_REQUEST, $decoded);
				}
			} catch (\Exception $ex) {
				
			}
			unset($_REQUEST['jsondata']);
		}

		$jsongzdata = (string) \request::get('jsongzdata', '');
		if ($jsongzdata !== '') {
			try {
				$decoded = json_decode(gzdecode(base64_decode($jsongzdata)), true);
				if (is_array($decoded)) {
					$_REQUEST = array_merge($_REQUEST, $decoded);
				}
			} catch (\Exception $ex) {
				
			}
			unset($_REQUEST['jsongzdata']);
		}

		$tz = \session::timezone();
		if (!empty($tz)) {
			date_default_timezone_set($tz);
		} else {
			date_default_timezone_set('Asia/Almaty');
		}

		if ($debug) {
			\console::outecho('timezone', date_default_timezone_get());
		}

		ini_set('serialize_precision', '12');
		ini_set('magic_quotes_runtime', '0');
		ini_set('magic_quotes_gpc', '0');

		if (class_exists('\SessionCache') && method_exists('\SessionCache', 'checkTime')) {
			\SessionCache::checkTime();
		}

		if (\request::get_bool('jsongz')) {
			self::$outMode = 'jsongz';
		}

		if ($debug) {
			\console::echoGroupEnd();
		}
	}

	public static function ajax(bool $skipExist = false): bool {
		$resultAjax = false;
		[$className, $method, $isStatic] = static::check_class_method_request($skipExist);

		if (is_string($className) && $className !== '') {
			if (class_exists('console')) {
				\console::outecho('call', $className . ($method ? ($isStatic ? '::' : '->') . $method . '()' : '') . ' -- php ' . phpversion());
				if (\console::$echo && !\console::$noOut) {
					echo sprintf('<%s>%s</%1$s>', 'title', $className . ($method ? ($isStatic ? '::' : '->') . $method . '()' : ''));
				}
			}

			$resultAjax = true;
			$allowToUse = true;

			if ($allowToUse) {
				$result = [];
				if (is_string($method) && $method !== '') {
					if ($isStatic) {
						App::$debugTitle = "{$className}::{$method}()";
						$result = $className::$method();
					} else {
						App::$debugTitle = "{$className}->{$method}()";
						$class = new $className();
						$result = $class->$method();
					}
				} else {
					App::$debugTitle = "class $className";
					$result = new $className();
				}
			} else {
				$result = ['deny' => true];
			}

			$r = array_merge($_GET, $_POST);
			unset($r['PHPSESSID'], $r['jNomad_SID'], $r['random'], $r['_ym_uid'], $r['jsongz']);

			$r['debug'] = 'yes';
			$r['checksize'] = 'yes';

			$rURL = static::urlProtocol() . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['PHP_SELF'] ?? '') . '?' . http_build_query($r);

			if (is_object($result)) {
				$result->{'$SID'} = session_id();
				if (!App::$debugMode) {
					$result->{'debugURL'} = $rURL;
				}
			} elseif (is_array($result)) {
				if (empty($result) || !array_is_list($result)) {
					$result['$SID'] = session_id();
					if (!App::$debugMode) {
						$result['debugURL'] = $rURL;
					}
				}
			}

			if (class_exists('\\errors') && \errors::Exists()) {
				\errors::Result($result);
			}
			if (class_exists('\\changes') && \changes::Exists()) {
				\changes::Result($result);
			}
			self::flush($result);
		}
		return $resultAjax;
	}

	public static function flush(mixed $data = null): void {
		if (self::$debugMode) {
			$checksize = \request::get_bool('checksize');
			if (class_exists('console')) {
				if ($checksize) {
					$n = self::encode($data);
					$p = gzencode($n, 9);
					$pLen = $p !== false ? strlen($p) : 0;
					$nLen = strlen($n);
					\console::outecho(self::$debugTitle . ' size', [
						'naked' => $nLen,
						'packed' => $pLen,
						'ratio' => $pLen > 0 ? $nLen / $pLen : 0,
					]);
				}
				\console::outecho(self::$debugTitle, $data);
			}
		} else {
			switch (static::$outMode) {
				case 'direct':
					break;
				case 'jsongz':
					header("Content-Type: application/jsongz; charset=utf-8");
					$encoded = gzencode(self::encode($data), 9);
					if ($encoded !== false) {
						echo $encoded;
					}
					break;
				default:
					header("Content-Type: application/json; charset=utf-8");
					echo self::encode($data);
					break;
			}
		}
	}

	public static function encode(mixed $data): string {
		$json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			$json = '';
		}
		if (class_exists('\\utils') && method_exists('\\utils', 'json_indent')) {
			return \utils::json_indent($json);
		}
		return $json;
	}

	public static function urlServer(bool $fullProtocol = false): string {
		if ($fullProtocol) {
			return static::urlProtocol() . ($_SERVER['HTTP_HOST'] ?? '');
		}
		return '//' . ($_SERVER['HTTP_HOST'] ?? '');
	}

	public static function urlProtocol(): string {
		$https = $_SERVER['HTTPS'] ?? '';
		$port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
		$scheme = $_SERVER['REQUEST_SCHEME'] ?? '';
		$forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

		if (
			(!empty($https) && $https !== 'off') ||
			$port === 443 ||
			$scheme === 'https' ||
			$forwarded === 'https'
		) {
			return "https://";
		}
		return "http://";
	}

	public static function check_class_method_request(bool $skipExist = false): array {
		$className = (string) \request::get('class', '');

		if ($skipExist) {
			$GLOBALS['HIDE_CLASS_INCLUDES'] = ($GLOBALS['HIDE_CLASS_INCLUDES'] ?? false) || true;
		}
		if ($skipExist && $className !== '' && !class_exists($className)) {
			$className = 'stdClass';
		}
		if ($className === '' || !class_exists($className)) {
			return [false, false, false];
		}

		$method = (string) \request::get('method', 'get');
		$mlist = get_class_methods($className);
		if (!is_array($mlist)) {
			$mlist = [];
		}

		if (!in_array($method, $mlist, true) && !in_array('__callStatic', $mlist, true)) {
			return [$className, false, false];
		}

		$reflection = new \ReflectionClass($className);
		$isStatic = false;
		$slist = $reflection->getMethods(\ReflectionMethod::IS_STATIC);

		foreach ($slist as $row) {
			if ($row->name === $method) {
				$isStatic = true;
				break;
			}
		}

		if (in_array('__callStatic', $mlist, true)) {
			$isStatic = true;
		}

		return [$className, $method, $isStatic];
	}
}
