<?php
class Route {

	private $_route = array();
	private $_traefikMiddlewarelist = array();
	private $_configIgnoreMiddlewarelist = array();
	private $_routerUrl = "";
	private $_debugModule = false;
	private $_tempUrl = array(); // temp url in the build function (_checkRules and _checkCondition) and compiled in buildLinkURL
	private $_serviceIsUp = false; // validate with function checkIfServiceIsUp, return if the service is up in traefik
	private $_middlewareAccessValid = false; // validate with function checkIfUserIsPermit, return if user can view the router
	private $_internalUrlPath = "";
	private $_localFavIcon = "";
	private $_clientIp;
	private $_logMessage = ["middleware" => array()];


	// build object
	public function __construct(array $route, string $traefikURL, array $traefikMiddlewarelist, array $configIgnoreMiddlewarelist, bool $debugModule) {
		$this->_route = $route;
		$this->_traefikMiddlewarelist = $traefikMiddlewarelist;
		$this->_configIgnoreMiddlewarelist = $configIgnoreMiddlewarelist;
		$this->_debugModule = $debugModule;
		$this->_clientIp = $this->_getIpAddress();
	}

	public function __destruct() {
		if ($this->_debugModule) {
			file_put_contents('php://stderr', print_r("log for router : " . $this->_route['name']));
			file_put_contents('php://stderr', print_r(json_encode($this->_logMessage)));
		}
	}

	private function _getIpAddress(): string {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			return $_SERVER['REMOTE_ADDR'];
		}
	}

	private function _ipInRange($ip, $range): bool {
		if (strpos($range, '/') !== false) {
			list($subnet, $bits) = explode('/', $range);
			if ($bits === '32') {
				return $ip === $subnet;
			}
			return (ip2long($ip) & ~((1 << (32 - $bits)) - 1)) === ip2long($subnet);
		}
		return $ip === $range;
	}

	private function _parseRule($rule, $step = 0): array {
		$rule = trim($rule);
		// Gérer les parenthèses externes
		if ($step != 0 && $rule[0] === '(' && $rule[strlen($rule) - 1] === ')' && $rule[strlen($rule) - 2] !== '`') {
			$rule = substr($rule, 1, -1);
		}
		$tokens = [];
		$level = 0;
		$current = '';
		// Parcourir la chaîne caractère par caractère
		for ($i = 0; $i < strlen($rule); $i++) {
			$char = $rule[$i];
			if ($char === '(') {
				$level++;
			} elseif ($char === ')') {
				$level--;
			}
			// Détecter les "and" et "or" au niveau le plus externe
			if ($level === 0 && ($i < strlen($rule) - 1)) {
				if ($rule[$i] === '&' && $rule[$i + 1] === '&') {
					$tokens[] = trim($current);
					$tokens[] = 'and';
					$current = '';
					$i++;
					continue;
				} elseif ($rule[$i] === '|' && $rule[$i + 1] === '|') {
					$tokens[] = trim($current);
					$tokens[] = 'or';
					$current = '';
					$i++;
					continue;
				}
			}
			$current .= $char;
		}
		// Ajouter la dernière condition après la boucle
		if (!empty($current)) {
			$tokens[] = trim($current);
		}
		if (count($tokens) === 1) {
			return $tokens;
		}
		// Parcourir les tokens et appeler récursivement parseRule
		$result = [];
		for ($i = 0; $i < count($tokens); $i++) {
			if ($tokens[$i] === 'and' || $tokens[$i] === 'or') {
				$result[] = $tokens[$i];
			} else {
				$result[] = $this->_parseRule($tokens[$i], $step + 1);
			}
		}
		// Simplifier le tableau si nécessaire
		return $result;
	}

	private function _checkCondition(string $condition): bool {
		if (strpos($condition, 'HostSNI')  !== false) {
			$this->_tempUrl['host'] = preg_replace('/HostSNI\(`(.*)`\)/', '$1', $condition);
			return true;
		} elseif (strpos($condition, 'Host') !== false) {
			$this->_tempUrl['host'] = preg_replace('/Host\(`(.*)`\)/', '$1', $condition);
			return true;
		} elseif (strpos($condition, 'ClientIP') !== false) {
			return $this->_ipInRange($this->_clientIp, preg_replace('/ClientIP\(`(.*)`\)/', '$1', $condition));
		} elseif (strpos($condition, 'PathPrefix') !== false) {
			$this->_tempUrl['path'] = preg_replace('/PathPrefix\(`(.*)`\)/', '$1', $condition);
			return true;
		} elseif (strpos($condition, 'Path') !== false) {
			$this->_tempUrl['path'] = preg_replace('/Path\(`(.*)`\)/', '$1', $condition);
			return true;
		} elseif (strpos($condition, 'Query') !== false) {
			// exemple : Query(`mobile`, `true`) => $this->_tempUrl['query'] = 'mobile=true'
			$this->_tempUrl['query'] = preg_replace('/Query\(`(.*)`, `(.*)`\)/', '$1=$2', $condition);
			return true;
		} 
		return false;
	}

	private function _checkRules($rule): bool {
		$result = false;
		foreach ($rule as $condition) {
			if (is_array($condition)) {
				$result = $this->_checkRules($condition);
			} else {
				if ($condition == 'and') {
					if (!$result) {
						$this->_tempUrl = [];
						continue;
					}
				} elseif ($condition == 'or') {
					if ($result) {
						break;
					}
				} else {
					$result = $this->_checkCondition($condition);
				}
			}
		}
		return $result;
	}

	private function _searchMiddleware(string $searchMiddle): bool {
		if (!isset($this->_traefikMiddlewarelist[$searchMiddle])) {
			$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' not found"];
			return false;
		}
		if (in_array($searchMiddle, $this->_configIgnoreMiddlewarelist)) {
			$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' in ignore list"];
			return false;
		}
		$middle = $this->_traefikMiddlewarelist[$searchMiddle];
		switch ($middle["type"]) {
			case "chain":
				if (!isset($middle['chain']) && !isset($middle['chain']['middlewares'])) {
					$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' in error"];
					return false;
				}
				foreach ($middle['chain']['middlewares'] as $middleware) {
					$result = $this->_searchMiddleware($middleware);
					if (!$result) {
						$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' stoped"];
						return false;
					}
				}
				break;
			case "ipallowlist":
				$hasWhiteListButNotPresent = true;
				if (!isset($middle['ipAllowList']) && !isset($middle['ipAllowList']['sourceRange'])) {
					$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' in error"];
					return false;
				}
				foreach ($middle['ipAllowList']['sourceRange'] as $range) {
					if ($this->_ipInRange($this->_clientIp, $range)) {
						$hasWhiteListButNotPresent = false;
					}
				}
				if ($hasWhiteListButNotPresent) {
					$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' ip not match"];
					return false;
				}
				break;
			case "ipwhitelist":
				$hasWhiteListButNotPresent = true;
				if (!isset($middle['ipWhiteList']) && !isset($middle['ipWhiteList']['sourceRange'])) {
					$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' in error"];
					return false;
				}
				foreach ($middle['ipWhiteList']['sourceRange'] as $range) {
					if ($this->_ipInRange($this->_clientIp, $range)) {
						$hasWhiteListButNotPresent = false;
					}
				}
				if ($hasWhiteListButNotPresent) {
					$this->_logMessage["middleware"][$searchMiddle] = ["result" => false, "message" => "middleware '" . $searchMiddle . "' ip not match"];
					return false;
				}
				break;
			default:
				break;
		}
		$this->_logMessage["middleware"][$searchMiddle] = ["result" => true, "message" => "middleware '" . $searchMiddle . "' finish"];
		return true;
	}

	public function checkIfUserIsPermit(): bool {
		if (!isset($this->_route['middlewares'])) {
			$this->_middlewareAccessValid = true;
			return true;
		}
		foreach ($this->_route['middlewares'] as $middlewareName) {
			if (!$this->_searchMiddleware($middlewareName)) {
				$this->_middlewareAccessValid = false;
				return false;
			}
		}
		$this->_middlewareAccessValid = true;
		return true;
	}

	public function checkIfServiceIsUp(string $typeRouter, callable $traefikCheckFunction): string {
		// if no '@' in $this->_route["service"] we use $this->_route["name"]
		$serviceName = strstr($this->_route["service"], "@") ? $this->_route["service"] : $this->_route["name"];
		$traefikStatus = $traefikCheckFunction($typeRouter, $serviceName);
		$this->_serviceIsUp = boolval($traefikStatus);
		return $traefikStatus;
	}

	public function buildLinkURL(array $entrypointsList, array $entrypointName) {
		$this->_routerUrl = isset($this->_route['tls']) ? 'https://' : 'http://';
		// exemple
		// separe les conditon en prenant en compte les parenthèses et genere un tableau
		// exemple 1 : "(Host(`example.com`) && QueryRegexp(`mobile`, `^(true|yes)$`) && ClientIP(`192.168.1.0/24`)) || (Host(`example.com`) && Path(`/products`))"
		// resultat 1 : rule = [["Host(`example.com`)","and","QueryRegexp(`mobile`, `^(true|yes)$`)","and","ClientIP(`192.168.1.0/24`)"],"or",["Host(`example.com`)","and","Path(`/products`)"]]
		// exemple 2 : "(Host(`example.com`) && (QueryRegexp(`mobile`, `^(true|yes)$`) || QueryRegexp(`desktop`, `^(true|yes)$`)) && ClientIP(`192.168.1.0/24`)) || (Host(`example.com`) && Path(`/products`))"
		// resultat 2 : rule = [["Host(`example.com`)","and",["QueryRegexp(`mobile`, `^(true|yes)$`)","or","QueryRegexp(`desktop`, `^(true|yes)$`)"],"and","ClientIP(`192.168.1.0/24`)"],"or",["Host(`example.com`)","and","Path(`/products`)"]]
		$parsedRule = $this->_parseRule($this->_route['rule']);
		// parcourir le tableau pour construire l'url
		if ($this->_checkRules($parsedRule)) {
			if (isset($this->_tempUrl['host'])) {
				$this->_routerUrl .= $this->_tempUrl['host'];
			} else {
				// si pas de host, on prend le nom de domaine de la session
				$this->_routerUrl .= $_SERVER['HTTP_HOST'];
			}
			// si l'entrypoint n'est pas a l'entrypoints pour http ou https, on ajoute l'adresse de l'entrypoint
			if (!in_array($entrypointName['https'], $this->_route['entryPoints']) && !in_array($entrypointName['http'], $this->_route['entryPoints'])) {
				foreach ($this->_route['entryPoints'] as $entryPoint) {
					if (isset($entrypointsList[$entryPoint])) {
						$this->_routerUrl .= $entrypointsList[$entryPoint]['address'];
						break;
					}
				}
			}
			if (isset($this->_tempUrl['path'])) {
				$this->_routerUrl .= $this->_tempUrl['path'];
				$this->_internalUrlPath = $this->_tempUrl['path'];
			}
			if (isset($this->_tempUrl['query'])) {
				$this->_routerUrl .= '?' . $this->_tempUrl['query'];
			}
			return true;
		}
		return false;
	}

	public function getCachedFavicon(): bool {
		if (file_exists("cache/" . $this->_route['name'] . "-favicon.ico") && filesize("cache/" . $this->_route['name'] . "-favicon.ico") > 0) {
			$this->_localFavIcon = "cache/" . $this->_route['name'] . "-favicon.ico";
			return true;
		} elseif (file_exists("cache/" . $this->_route['name'] . "-favicon.svg") && filesize("cache/" . $this->_route['name'] . "-favicon.svg") > 0) {
			$this->_localFavIcon = "cache/" . $this->_route['name'] . "-favicon.svg";
			return true;
		} else {
			$this->_localFavIcon = "cache/default-favicon.ico";
			return false;
		}
	}

	public function getLinkInfo() {
		return array(
			'name' => preg_replace('/@.*/', '', $this->_route['name']),
			'url' => $this->_routerUrl,
			'internalUrlPath' => $this->_internalUrlPath,
			'up' => $this->_serviceIsUp,
			'favicon' => $this->_localFavIcon,
			'isPermited' => $this->_middlewareAccessValid,
			'log' => $this->_debugModule ? $this->_logMessage : array()
		);
	}
}
