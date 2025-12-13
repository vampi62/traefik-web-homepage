<?php
class FavIcon {

	private $_url;
	private $_routeName;
	private $_debugModule = false;
	private $_favIconServiceURL;
	private $_favIconLink;
	private $_logMessage = ["middleware" => array()];


	// build object
	public function __construct(string $url, string $internalUrlPath, string $routeName, bool $debugModule) {
		if (strlen($internalUrlPath) > 0) {
			$this->_url = $this->_mergeUrls($url, $internalUrlPath);
		}
		else {
			$this->_url = $url;
		}
		$this->_routeName = $routeName;
		$this->_debugModule = $debugModule;
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
		if ($this->_debugModule) {
			file_put_contents('php://stderr', print_r($this->_routeName, true) . "\n");
			file_put_contents('php://stderr', '_getServiceContent:' . print_r($_url, TRUE) . "\n");
			file_put_contents('php://stderr', print_r($httpCode, TRUE) . "\n");
			if (curl_errno($httpSession)) {
				file_put_contents('php://stderr', print_r(curl_error($httpSession), TRUE) . "\n");
			}
		}
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
				$_redirectUrl = $this->_mergeUrls($this->_favIconServiceURL, $matches[1]);
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
		if ($this->_debugModule) {
			file_put_contents('php://stderr', print_r($this->_routeName, true) . "\n");
			file_put_contents('php://stderr', '_getPictureFavicon:' . print_r($this->_favIconServiceURL, TRUE) . "\n");
			file_put_contents('php://stderr', print_r($this->_favIconLink, TRUE) . "\n");
		}
		$rules = array(
			$this->_mergeUrls($this->_url, 'favicon.ico'),
			$this->_mergeUrls($this->_url, 'favicon.png'),
			$this->_mergeUrls($this->_url, 'favicon.svg'),
			$this->_mergeUrls($this->_url, 'favicon.jpg'),
			$this->_mergeUrls($this->_favIconServiceURL, 'favicon.ico'),
			$this->_mergeUrls($this->_favIconServiceURL, 'favicon.png'),
			$this->_mergeUrls($this->_favIconServiceURL, 'favicon.svg'),
			$this->_mergeUrls($this->_favIconServiceURL, 'favicon.jpg')
		);
		if ($this->_favIconLink !== null) {
			// si le lien du favicon est relatif, on le transforme en absolu
			if (strpos($this->_favIconLink, 'http') === 0) {
				$rules[] = $this->_favIconLink;
			}
			else {
				$rules[] = $this->_mergeUrls($this->_favIconServiceURL, $this->_favIconLink);
				$rules[] = $this->_mergeUrls($this->_url, $this->_favIconLink);
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
			if ($this->_debugModule) {
				file_put_contents('php://stderr', print_r($this->_routeName, true) . "\n");
				file_put_contents('php://stderr', print_r($rule, true) . "\n");
				file_put_contents('php://stderr', print_r($httpCode, true) . "\n");
				if (curl_errno($httpSession)) {
					file_put_contents('php://stderr', print_r(curl_error($httpSession), true) . "\n");
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
		}
		return array('iconFound' => $iconFound, 'extension' => $extension, 'data' => $data);
	}

	private function _mergeUrls($_base, $_relative) {
		$parsedBase = rtrim($_base, '/');
		$parsedRelative = ltrim($_relative, '/');
		return $parsedBase . '/' . $parsedRelative;
	}

	public function updateFavicon() {
		try {
			$htmlContent = $this->_getServiceContent($this->_url);
			file_put_contents('php://stderr', print_r("Raw content for " . $this->_routeName . ": " . $htmlContent . "\n", true));
		}
		catch (Exception $e) {
			file_put_contents('php://stderr', print_r("Error fetching HTML content for " . $this->_routeName . ": " . $e->getMessage() . "\n", true));
			return "cache/default-favicon.ico";
		}
		file_put_contents('php://stderr', print_r("HTML content length for " . $this->_routeName . ": " . strlen($htmlContent) . "\n", true));
		if ($htmlContent && strlen($htmlContent) > 0) {
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
		}
		$_icon = $this->_getPictureFavicon();
		if ($_icon['iconFound']) {
			file_put_contents("cache/" . $this->_routeName . '-favicon.' . $_icon['extension'], $_icon['data']);
			return "cache/" . $this->_routeName . '-favicon.' . $_icon['extension'];
		}
		return "cache/default-favicon.ico";
	}
}
