<?php
class Route {

	private $_route;
	private $_traefikURL;
	private $_url;
	private $_serviceIsUp;
	private $_favicon;


	// build object
	public function __construct($route, $traefikURL) {
		$this->_route = $route;
		$this->_traefikURL = $traefikURL;
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

	public function checkIfUserIsPermit($middlewareList, $clientIp) {
		if (!isset($this->_route['middlewares'])) {
			return true;
		}
		foreach ($this->_route['middlewares'] as $middlewareName) {
			foreach ($middlewareList as $middle) {
				if ($middle['name'] == $middlewareName) {
					if (isset($middle['ipWhiteList'])) {
						foreach ($middle['ipWhiteList']['sourceRange'] as $range) { // if the client ip is in one network set in the sourceRange
							if ($this->_ipInRange($clientIp, $range)) {
								return true;
							}
						}
					}
					break;
				}
			}
		}
		return false;
	}

	public function checkIfServiceIsUp() {
		if ($this->_route["provider"] == "file") {
			$services = json_decode(file_get_contents($this->_traefikURL . "http/services/" . $this->_route["service"]), true);
			if (isset($services['serverStatus'])) {
				foreach ($services['serverStatus'] as $url => $status) {
					if ($status == "UP") {
						$this->_serviceIsUp = true;
						return true;
					}
				}
			}
			$this->_serviceIsUp = false;
			return false;
		} else {
			$this->_serviceIsUp = true;
			return true;
		}
	}

	public function buildURL($entrypointsList) {
		$this->_url = isset($this->_route['tls']) ? 'https://' : 'http://';
		$this->_url .= preg_replace('/Host\(`(.*)`\)/', '$1', $this->_route['rule']);
		if (!in_array('websecure', $this->_route['entryPoints']) && !in_array('web', $this->_route['entryPoints'])) {
			foreach ($this->_route['entryPoints'] as $entryPoint) {
				foreach ($entrypointsList as $entry) {
					if ($entry['name'] == $entryPoint) {
						$this->_url .= $entry['address'];
						break;
					}
				}
			}
		}
		if (substr($this->_url, -1) == '/') {
			$this->_url = substr($this->_url, 0, -1);
		}
	}

	private function _getFaviconOnService() {
		$favicon = '';
		$html = file_get_contents($this->_url);
		$doc = new DOMDocument();
		@$doc->loadHTML($html);
		$links = $doc->getElementsByTagName('link');
		foreach ($links as $link) {
			if ($link->getAttribute('rel') == 'icon') {
				$iconUrl = $link->getAttribute('href');
				if (strpos($iconUrl, 'http') === false) {
					if (substr($iconUrl, 0, 1) == '/') {
						return $this->_url . $iconUrl;
					} else {
						return $this->_url . '/' . $iconUrl;
					}
				} else {
					return $iconUrl;
				}
				break;
			}
		}
		return $this->_url . "/favicon.ico";
	}

	public function updateFavicon() {
		$this->_favicon = $this->_getFaviconOnService();
		if (@get_headers($this->_favicon)[0] != 'HTTP/1.0 200 OK') {
			$this->_favicon = 'cache/default-favicon.ico';
		} else {
			$iconData = file_get_contents($this->_favicon);
			if (pathinfo($this->_favicon, PATHINFO_EXTENSION) == 'svg' || strpos($iconData, '<svg') !== false) {
				file_put_contents('cache/' . $this->_route['service'] . '-favicon.svg', $iconData);
				$this->_favicon = 'cache/' . $this->_route['service'] . '-favicon.svg';
			} else {
				file_put_contents('cache/' . $this->_route['service'] . '-favicon.ico', $iconData);
				$this->_favicon = 'cache/' . $this->_route['service'] . '-favicon.ico';
			}
		}
	}

	public function checkFavicon() {
		if (file_exists('cache/' . $this->_route['service'] . '-favicon.ico') && filesize('cache/' . $this->_route['service'] . '-favicon.ico') > 0) {
			$this->_favicon = 'cache/' . $this->_route['service'] . '-favicon.ico';
		} elseif (file_exists('cache/' . $this->_route['service'] . '-favicon.svg') && filesize('cache/' . $this->_route['service'] . '-favicon.svg') > 0) {
			$this->_favicon = 'cache/' . $this->_route['service'] . '-favicon.svg';
		} else {
			if ($this->_serviceIsUp) {
				$this->updateFavicon();
			} else {
				$this->_favicon = 'cache/default-favicon.ico';
			}
		}
	}

	public function getLinkInfo() {
		return array(
			'name' => preg_replace('/@.*/', '', $this->_route['service']),
			'url' => $this->_url,
			'up' => $this->_serviceIsUp,
			'favicon' => $this->_favicon
		);
	}
}