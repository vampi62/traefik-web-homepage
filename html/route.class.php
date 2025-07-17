<?php
class Route {

	private $_route;
	private $_traefikURL;
	private $_debug;
	private $_url;
	private $_favIconServiceURL;
	private $_favIconLink;
	private $_tempUrl = array();
	private $_serviceIsUp;
	private $_favIcon;
	private $_clientIp;
	private $_isPermited;
	private $_urlIsFormed;


	// build object
	public function __construct($route, $traefikURL, $debug) {
		$this->_route = $route;
		$this->_traefikURL = $traefikURL;
		$this->_debug = $debug;
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
			$this->_isPermited = true;
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
			$this->_isPermited = false;
			return false;
		} elseif ($hasMiddlewareBlock) {
			$this->_isPermited = false;
			return false;
		}
		$this->_isPermited = true;
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

	public function buildLinkURL($entrypointsList, $entrypointName) {
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
			$this->_urlIsFormed = true;
			return true;
		}
		$this->_urlIsFormed = false;
		return false;
	}

	private function _getServiceContent($_url, $retry_remaining = 3) {
		$httpSession = curl_init();
		curl_setopt($httpSession, CURLOPT_URL, $_url);
		curl_setopt($httpSession, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($httpSession, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($httpSession, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($httpSession, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($httpSession, CURLOPT_MAXREDIRS, 10);
		curl_setopt($httpSession, CURLOPT_TIMEOUT, 10);
		$htmlContent = curl_exec($httpSession);
		$this->_favIconServiceURL = curl_getinfo($httpSession, CURLINFO_EFFECTIVE_URL);
		$httpCode = curl_getinfo($httpSession, CURLINFO_HTTP_CODE);
		if ($this->_debug['enabled']) {
			if ($this->_route['service'] == $this->_debug['service'] || $this->_debug['service'] == '') {
				file_put_contents('php://stderr', '_getServiceContent:' . print_r($_url, TRUE) . "\n");
				file_put_contents('php://stderr', print_r($httpCode, TRUE) . "\n");
				if (curl_errno($httpSession)) {
					file_put_contents('php://stderr', print_r(curl_error($httpSession), TRUE) . "\n");
				}
			}
		}
		curl_close($httpSession);
		if (strpos($this->_favIconServiceURL, '/', strlen($this->_favIconServiceURL) - 1) !== false) {
			$this->_favIconServiceURL = substr($this->_favIconServiceURL, 0, -1);
		} else {
			$this->_favIconServiceURL = substr($this->_favIconServiceURL, 0, strrpos($this->_favIconServiceURL, '/'));
		}
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
			if ($retry_remaining == 0) {
				return $htmlContent;
			}
			$htmlContent = $this->_getServiceContent($_redirectUrl, $retry_remaining - 1);
		}
		elseif ($httpCode != 200 && $retry_remaining > 0) {
			$htmlContent = $this->_getServiceContent($_url, $retry_remaining - 1);
		}
		return $htmlContent;
	}

	private function _getPictureFavicon() {
		//$this->_url // url construite du service
		//$this->_favIconServiceURL // url réel du service (différent de $this->_url si redirection)
		//$this->_favIconLink // url (relative ou absolue) du favicon
		if ($this->_debug['enabled'] && ($this->_route['service'] == $this->_debug['service'] || $this->_debug['service'] == '')) {
			file_put_contents('php://stderr', '_getPictureFavicon:' . print_r($this->_favIconServiceURL, TRUE) . "\n");
			file_put_contents('php://stderr', print_r($this->_favIconLink, TRUE) . "\n");
		}
		$rules = array(
			$this->_url . "/favicon.ico",
			$this->_url . "/favicon.png",
			$this->_url . "/favicon.svg",
			$this->_url . "/favicon.jpg",
			$this->_favIconServiceURL . "/favicon.ico",
			$this->_favIconServiceURL . "/favicon.png",
			$this->_favIconServiceURL . "/favicon.svg",
			$this->_favIconServiceURL . "/favicon.jpg"
		);
		if ($this->_favIconLink !== null) {
			// si le lien du favicon est relatif, on le transforme en absolu
			if (strpos($this->_favIconLink, 'http') === 0) {
				$rules[] = $this->_favIconLink;
			}
			else {
				if (strpos($this->_favIconLink, '/') === 0) {
					$rules[] = $this->_favIconServiceURL . $this->_favIconLink;
					$rules[] = $this->_url . $this->_favIconLink;
				} else {
					$rules[] = $this->_favIconServiceURL . '/' . $this->_favIconLink;
					$rules[] = $this->_url . '/' . $this->_favIconLink;
				}
			}
		}
		$iconFound = false;
		$extension = '';
		$data = '';
		foreach ($rules as $rule) {
			$httpSession = curl_init();
			curl_setopt($httpSession, CURLOPT_URL, $rule);
			curl_setopt($httpSession, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($httpSession, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($httpSession, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($httpSession, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($httpSession, CURLOPT_MAXREDIRS, 10);
			curl_setopt($httpSession, CURLOPT_TIMEOUT, 10);
			$data = curl_exec($httpSession);
			$httpCode = curl_getinfo($httpSession, CURLINFO_HTTP_CODE);
			if ($this->_debug['enabled']) {
				if ($this->_route['service'] == $this->_debug['service'] || $this->_debug['service'] == '') {
					file_put_contents('php://stderr', print_r($rule, TRUE) . "\n");
					file_put_contents('php://stderr', print_r($httpCode, TRUE) . "\n");
					if (curl_errno($httpSession)) {
						file_put_contents('php://stderr', print_r(curl_error($httpSession), TRUE) . "\n");
					}
				}
			}
			$extension = curl_getinfo($httpSession, CURLINFO_CONTENT_TYPE);
			if (strpos($extension, 'image/') === 0) {
				// if extension is svg, we keep it as svg otherwise we keep it as ico
				if ($extension == 'image/svg+xml') {
					$extension = 'svg';
				} else {
					$extension = 'ico';
				}
				$iconFound = true;
				break;
			}
			curl_close($httpSession);
		}
		return array('iconFound' => $iconFound, 'extension' => $extension, 'data' => $data);
	}

	public function updateFavicon() {
		try {
			$htmlContent = $this->_getServiceContent($this->_url);
		}
		catch (Exception $e) {
			return 'cache/default-favicon.ico';
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
				$this->_favIconLink = $link->getAttribute('href');
				break;
			}
		}
		$_icon = $this->_getPictureFavicon();
		if ($_icon['iconFound']) {
			file_put_contents('cache/' . $this->_route['service'] . '-favicon.' . $_icon['extension'], $_icon['data']);
			return $this->_favIcon = 'cache/' . $this->_route['service'] . '-favicon.' . $_icon['extension'];
		}
	}

	public function GetCachedFavicon() {
		if (file_exists('cache/' . $this->_route['service'] . '-favicon.ico') && filesize('cache/' . $this->_route['service'] . '-favicon.ico') > 0) {
			$this->_favIcon = 'cache/' . $this->_route['service'] . '-favicon.ico';
		} elseif (file_exists('cache/' . $this->_route['service'] . '-favicon.svg') && filesize('cache/' . $this->_route['service'] . '-favicon.svg') > 0) {
			$this->_favIcon = 'cache/' . $this->_route['service'] . '-favicon.svg';
		} else {
			if ($this->_serviceIsUp && $this->_urlIsFormed && $this->_isPermited) {
				$this->_favIcon = $this->updateFavicon();
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
			'favicon' => $this->_favIcon,
			'isPermited' => $this->_isPermited,
			'urlIsFormed' => $this->_urlIsFormed
		);
	}

	public static function GetOfflineCachedFavicon($serviceName) {
		if (file_exists('cache/' . $serviceName . '-favicon.ico') && filesize('cache/' . $serviceName . '-favicon.ico') > 0) {
			return 'cache/' . $serviceName . '-favicon.ico';
		} elseif (file_exists('cache/' . $serviceName . '-favicon.svg') && filesize('cache/' . $serviceName . '-favicon.svg') > 0) {
			return 'cache/' . $serviceName . '-favicon.svg';
		} else {
			return 'cache/default-favicon.ico';
		}
	}
}
