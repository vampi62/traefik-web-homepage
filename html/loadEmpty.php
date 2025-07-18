<?php

require_once("route.class.php");

// read config file
$config = json_decode(file_get_contents('config.json'), true);

// Récupérer la liste des entrypoints
$entrypointsList = json_decode(file_get_contents($config['apiUrl'] . "entrypoints"), true);

$services = [];
foreach ([ 'http', 'tcp' ] as $typeRouter) {
	// Récupérer la liste des routes
	$routesList = json_decode(file_get_contents($config['apiUrl'] . $typeRouter . "/routers"), true);
	// Récupérer la liste des middlewares
	$middlewareList = json_decode(file_get_contents($config['apiUrl'] . $typeRouter . "/middlewares"), true);
	foreach ($routesList as $route) {
		foreach ($config[$typeRouter]['exclude'] as $key => $value) {
			if (in_array($route[$key], $value)) {
				continue 2;
			}
		}
		if ($config['debug']['enabled'] && ($route['service'] == $config['debug']['service'])) {file_put_contents('php://stderr', print_r($route, TRUE));}
		$routeObjet = new Route($route,$config['apiUrl'],$config['debug']);
		if (!$routeObjet->checkIfUserIsPermit($middlewareList,$config[$typeRouter]['ignoreMiddleware'])) {
			continue;
		}
		if (!$routeObjet->buildLinkURL($entrypointsList,$config['entryPointName'])) {
			continue;
		}
		if ($routeObjet->checkIfServiceIsUp($typeRouter)) {
			$routeObjet->GetCachedFavicon();
		}
		$info = $routeObjet->getLinkInfo();
		if ($config['debug']['enabled'] && ($route['service'] == $config['debug']['service'])) {file_put_contents('php://stderr', print_r($info, TRUE));}
		$services[$info['service']] = $info;
	}
}
header('Content-Type: application/json');
echo json_encode($services);
