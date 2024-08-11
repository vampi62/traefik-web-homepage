<?php

require_once("route.class.php");

// read config file
$config = json_decode(file_get_contents('config.json'), true);

// Récupérer la liste des routes
$routesList = json_decode(file_get_contents($config['apiUrl'] . "http/routers"), true);

// Récupérer la liste des middlewares
$middlewareList = json_decode(file_get_contents($config['apiUrl'] . "http/middlewares"), true);

// Récupérer la liste des entrypoints
$entrypointsList = json_decode(file_get_contents($config['apiUrl'] . "entrypoints"), true);

$routeLinks = [];
foreach ($routesList as $route) {
	foreach ($config['exclude'] as $key => $value) {
		if (in_array($route[$key], $value)) {
			continue 2;
		}
	}
	$routeObjet = new Route($route,$config['apiUrl']);
	if (!$routeObjet->checkIfUserIsPermit($middlewareList,$config['middlewareNoBlock'])) {
		continue;
	}
	if (!$routeObjet->buildURL($entrypointsList)) {
		continue;
	}
	if ($routeObjet->checkIfServiceIsUp()) {
		$routeObjet->updateFavicon();
	}
}
header("Refresh: 0; URL=/");
?>