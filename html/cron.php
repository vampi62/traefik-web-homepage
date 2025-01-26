<?php

require_once("route.class.php");

// read config file
$config = json_decode(file_get_contents('config.json'), true);

// Récupérer la liste des entrypoints
$entrypointsList = json_decode(file_get_contents($config['apiUrl'] . "entrypoints"), true);

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
		$routeObjet = new Route($route,$config['apiUrl']);
		if (!$routeObjet->checkIfUserIsPermit($middlewareList,$config[$typeRouter]['ignoreMiddleware'])) {
			continue;
		}
		if (!$routeObjet->buildURL($entrypointsList,$config['entryPointName'])) {
			continue;
		}
		if ($routeObjet->checkIfServiceIsUp($typeRouter)) {
			$routeObjet->updateFavicon();
		}
	}
}
header("Refresh: 0; URL=/");
?>