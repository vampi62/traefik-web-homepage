<?php

require_once("route.class.php");

// set the traefik ip and port
$traefikAPIURL = "http://traefik:8080/api/";

// Récupérer la liste des routes
$routesList = json_decode(file_get_contents($traefikAPIURL . "http/routers"), true);

// Récupérer la liste des entrypoints
$entrypointsList = json_decode(file_get_contents($traefikAPIURL . "entrypoints"), true);

$routeLinks = [];
foreach ($routesList as $route) {
	if ($route["provider"] == "internal") {
		continue;
	}
	if ($route['service'] == "webmenu") {
		continue;
	}
	$routeObjet = new Route($route,$traefikAPIURL);
	$routeObjet->buildURL($entrypointsList);
	if ($routeObjet->checkIfServiceIsUp()) {
		$routeObjet->updateFavicon();
	}
}
header("Refresh: 0; URL=/");
?>