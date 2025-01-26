<?php
class Route {

	private $_route;
	private $_traefikURL;
	private $_url;
	private $_favIconURL;
	private $_tempUrl = array();
	private $_serviceIsUp;
	private $_favIcon;
	private $_clientIp;


	// build object
	public function __construct($route, $traefikURL) {
		$this->_route = $route;
		$this->_traefikURL = $traefikURL;
		$this->_clientIp = $this->_getIpAddress();
	}

	private function _getIpAddress() {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			return $_SERVER['REMOTE_ADDR'];
		}
	}

	private function _ipInRange($ip, $range) {
		if (strpos($range, '/') !== false) {
			list($subnet, $bits) = explode('/', $range);
			if ($bits === '32') {
				return $ip === $subnet;
			}
			return (ip2long($ip) & ~((1 << (32 - $bits)) - 1)) === ip2long($subnet);
		}
		return $ip === $range;
	}

	private function _parseRule($rule, $step = 0) {
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

	private function _checkCondition($condition) {
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
		} elseif (strpos($condition, 'Query') !== false) {
			// exemple : Query(`mobile`, `true`) => $this->_tempUrl['query'] = 'mobile=true'
			$this->_tempUrl['query'] = preg_replace('/Query\(`(.*)`, `(.*)`\)/', '$1=$2', $condition);
			return true;
		} 
		return false;
	}

	private function _checkRules($rule) {
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

	public function checkIfUserIsPermit($middlewareList, $ignoreMiddleware) {
		if (!isset($this->_route['middlewares'])) {
			return true;
		}
		$hasWhiteListButNotPresent = false;
		$hasMiddlewareBlock = false;
		foreach ($this->_route['middlewares'] as $middlewareName) {
			foreach ($middlewareList as $middle) {
				if ($middle['name'] == $middlewareName) {
					if (in_array($middlewareName, $ignoreMiddleware)) {
						continue;
					}
					if (isset($middle['ipWhiteList'])) { // if the middleware has an ipWhiteList
						$hasWhiteListButNotPresent = true;
						foreach ($middle['ipWhiteList']['sourceRange'] as $range) { // if the client ip is in one network set in the sourceRange
							if ($this->_ipInRange($this->_clientIp, $range)) {
								$hasWhiteListButNotPresent = false;
							}
						}
					} elseif (isset($middle['ipAllowList'])) {
						$hasWhiteListButNotPresent = true;
						foreach ($middle['ipAllowList']['sourceRange'] as $range) { // if the client ip is in one network set in the sourceRange
							if ($this->_ipInRange($this->_clientIp, $range)) {
								$hasWhiteListButNotPresent = false;
							}
						}
					} else {
						$hasMiddlewareBlock = true;
					}
					break;
				}
			}
		}
		if ($hasWhiteListButNotPresent) {
			return false;
		} elseif ($hasMiddlewareBlock) {
			return false;
		}
		return true;
	}

	public function checkIfServiceIsUp($typeRouter) {
		if ($this->_route["provider"] == "file") {
			$services = json_decode(file_get_contents($this->_traefikURL . $typeRouter . "/services/" . $this->_route["service"]), true);
			if (isset($services['serverStatus'])) {
				foreach ($services['serverStatus'] as $url => $status) {
					if ($status == "UP") {
						$this->_serviceIsUp = true;
						return true;
					}
				}
			} else {
				$this->_serviceIsUp = true;
				return true;
			}
			$this->_serviceIsUp = false;
			return false;
		} else {
			$this->_serviceIsUp = true;
			return true;
		}
	}

	public function buildURL($entrypointsList, $entrypointName) {
		$this->_url = isset($this->_route['tls']) ? 'https://' : 'http://';
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
				$this->_url .= $this->_tempUrl['host'];
			} else {
				// si pas de host, on prend le nom de domaine de la session
				$this->_url .= $_SERVER['HTTP_HOST'];
			}
			// si l'entrypoint n'est pas a l'entrypoints pour http ou https, on ajoute l'adresse de l'entrypoint
			if (!in_array($entrypointName['https'], $this->_route['entryPoints']) && !in_array($entrypointName['http'], $this->_route['entryPoints'])) {
				foreach ($this->_route['entryPoints'] as $entryPoint) {
					foreach ($entrypointsList as $entry) {
						if ($entry['name'] == $entryPoint) {
							$this->_url .= $entry['address'];
							break;
						}
					}
				}
			}
			if (isset($this->_tempUrl['path'])) {
				$this->_url .= $this->_tempUrl['path'];
			}
			if (strpos($this->_url, '/', strlen($this->_url) - 1) !== false) {
				$this->_url = substr($this->_url, 0, -1);
			}
			if (isset($this->_tempUrl['query'])) {
				$this->_url .= '?' . $this->_tempUrl['query'];
			}
		} else {
			return false;
		}
		return true;
	}

	private function _getFavIconURL($_url, $isRedirect = false) {
		$httpSession = curl_init();
		curl_setopt($httpSession, CURLOPT_URL, $_url);
		curl_setopt($httpSession, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($httpSession, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($httpSession, CURLOPT_MAXREDIRS, 10);
		curl_setopt($httpSession, CURLOPT_TIMEOUT, 10);
		$htmlContent = curl_exec($httpSession);
		$this->_favIconURL = curl_getinfo($httpSession, CURLINFO_EFFECTIVE_URL);
		if (strpos($this->_favIconURL, '/', strlen($this->_favIconURL) - 1) !== false) {
			$this->_favIconURL = substr($this->_favIconURL, 0, -1);
		} else {
			$this->_favIconURL = substr($this->_favIconURL, 0, strrpos($this->_favIconURL, '/'));
		}
		curl_close($httpSession);
		if (preg_match('/<meta http-equiv="refresh" content="0; url=(.*)" \/>/', $htmlContent, $matches)) {
			$_redirectUrl = '';
			if (strpos($matches[1], 'http') === 0) {
				$_redirectUrl = $matches[1];
			} else {
				if (substr($matches[1], 0, 1) == '/') {
					$_redirectUrl = $_url . $matches[1];
				} else {
					$_redirectUrl = $_url . '/' . $matches[1];
				}
			}
			if ($isRedirect) {
				return $htmlContent;
			}
			$htmlContent = $this->_getFavIconURL($_redirectUrl, true);
		}
		return $htmlContent;
	}

	private function _getFaviconOnService() {
		try {
			$htmlContent = $this->_getFavIconURL($this->_url);
		}
		catch (Exception $e) {
			return "";
		}
		$doc = new DOMDocument();
		@$doc->loadHTML($htmlContent);
		$links = $doc->getElementsByTagName('link');
		foreach ($links as $link) {
			if (!$link->hasAttribute('rel')) {
				continue;
			}
			// contient "icon" mais pas de "-icon" ou "icon-"
			if (strpos($link->getAttribute('rel'), 'icon') !== false && strpos($link->getAttribute('rel'), '-icon') === false && strpos($link->getAttribute('rel'), 'icon-') === false) {
				$iconUrl = $link->getAttribute('href');
				if (strpos($iconUrl, 'http') === 0) {
					return $iconUrl;
				} else {
					if (substr($iconUrl, 0, 1) == '/') {
						return $this->_favIconURL . $iconUrl;
					} else {
						return $this->_favIconURL . '/' . $iconUrl;
					}
				}
				break;
			}
		}
		$_headers = @get_headers($this->_url . "/favicon.ico");
		$_statusCode = $_headers[0] ?? '';
		if (stripos($_statusCode, '200 OK') == true) {
			return $this->_url . "/favicon.ico";
		}
		$_headers = @get_headers($this->_url . "/favicon.png");
		$_statusCode = $_headers[0] ?? '';
		if (stripos($_statusCode, '200 OK') == true) {
			return $this->_url . "/favicon.png";
		}
		return "";
	}

	public function updateFavicon() {
		$this->_favIconURL = $this->_getFaviconOnService();
		if ($this->_favIconURL == '') {
			$this->_favIcon = 'cache/default-favicon.ico';
			return;
		}
		$_iconData = @file_get_contents($this->_favIconURL);
		if ($_iconData === false) {
			$this->_favIcon = 'cache/default-favicon.ico';
			return;
		}
		$_isSvg = pathinfo($this->_favIconURL, PATHINFO_EXTENSION) === 'svg' || strpos($_iconData, '<svg') !== false;
		$_extension = $_isSvg ? 'svg' : 'ico';
		file_put_contents('cache/' . $this->_route['service'] . '-favicon.' . $_extension, $_iconData);
		$this->_favIcon = 'cache/' . $this->_route['service'] . '-favicon.' . $_extension;
	}

	public function checkFavicon() {
		if (file_exists('cache/' . $this->_route['service'] . '-favicon.ico') && filesize('cache/' . $this->_route['service'] . '-favicon.ico') > 0) {
			$this->_favIcon = 'cache/' . $this->_route['service'] . '-favicon.ico';
		} elseif (file_exists('cache/' . $this->_route['service'] . '-favicon.svg') && filesize('cache/' . $this->_route['service'] . '-favicon.svg') > 0) {
			$this->_favIcon = 'cache/' . $this->_route['service'] . '-favicon.svg';
		} else {
			if ($this->_serviceIsUp) {
				$this->updateFavicon();
			} else {
				$this->_favIcon = 'cache/default-favicon.ico';
			}
		}
	}

	public function getLinkInfo() {
		return array(
			'name' => preg_replace('/@.*/', '', $this->_route['service']),
			'service' => $this->_route['service'],
			'url' => $this->_url,
			'up' => $this->_serviceIsUp,
			'favicon' => $this->_favIcon
		);
	}

	public static function offlineFavicon($serviceName) {
		if (file_exists('cache/' . $serviceName . '-favicon.ico') && filesize('cache/' . $serviceName . '-favicon.ico') > 0) {
			return 'cache/' . $serviceName . '-favicon.ico';
		} elseif (file_exists('cache/' . $serviceName . '-favicon.svg') && filesize('cache/' . $serviceName . '-favicon.svg') > 0) {
			return 'cache/' . $serviceName . '-favicon.svg';
		} else {
			return 'cache/default-favicon.ico';
		}
	}
}
