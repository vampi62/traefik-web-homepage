<?php
class Traefik {

	private $_config = array();

	public function __construct(array $config) {
		$this->_config = $config;
	}

	public function getRouterList(string $typeRouter): array {
		$routesList = json_decode(file_get_contents($this->_config['apiUrl'] . $typeRouter . "/routers"), true);
		$routesListArrange = array();
		foreach ($routesList as $route) {
			foreach ($this->_config[$typeRouter]['exclude'] as $key => $value) {
				if (in_array($route["name"], $value)) {
					continue 2;
				}
			}
			$routesListArrange[$route["name"]] = $route;
		}
		return $routesListArrange;
	}

	public function getMiddlewareList(string $typeRouter): array {
		$middlewareList = json_decode(file_get_contents($this->_config['apiUrl'] . $typeRouter . "/middlewares"), true);
		$middlewareListArrange = array();
		foreach ($middlewareList as $middleware) {
			$middlewareListArrange[$middleware["name"]] = $middleware;
		}
		return $middlewareListArrange;
	}

	public function getEntrypointList(): array {
		$entrypointList = json_decode(file_get_contents($this->_config['apiUrl'] . "entrypoints"), true);
		$entrypointListArrange = array();
		foreach ($entrypointList as $entrypoint) {
			$entrypointListArrange[$entrypoint["name"]] = $entrypoint;
		}
		return $entrypointListArrange;

	}

	public function getServiceStatus(string $typeRouter, string $serviceName): string {
		$services = json_decode(file_get_contents($this->_config['apiUrl'] . $typeRouter . "/services/" . $serviceName), true);
		if (isset($services['serverStatus'])) {
			foreach ($services['serverStatus'] as $url => $status) {
				if ($status == "UP") {
					return $url;
				}
			}
		} else if (isset($services['loadBalancer']) && isset($services['loadBalancer']["servers"])) {
			return $services['loadBalancer']["servers"][0]["url"] || "";
		}
		return "";
	}
	
}