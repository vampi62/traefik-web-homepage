<?php

require_once "class/traefik.class.php";
require_once "class/route.class.php";

$config = json_decode(file_get_contents("config.json"), true);

$traefik = new Traefik($config);

$listEntry = $traefik->getEntrypointList();

$allRouterExisting = array();

foreach (["http", "tcp"] as $typeRouter) {
	$listRouter = $traefik->getRouterList($typeRouter);
	$listMiddleware = $traefik->getMiddlewareList($typeRouter);
	foreach ($listRouter as $key => $route) {
		$routeObjet = new Route($route, $config["apiUrl"], $listMiddleware, $config[$typeRouter]["ignoreMiddleware"], $config["debug"]["enabled"] && $config["debug"]["router"] == $key);
		if (!$routeObjet->checkIfUserIsPermit()) {
			continue;
		}
		if (!$routeObjet->buildLinkURL($listEntry,$config["entryPointName"])) {
			continue;
		}
		$routeObjet->checkIfServiceIsUp($typeRouter, [$traefik, 'getServiceStatus']);
		$routeObjet->getCachedFavicon();
		$allRouterExisting[$typeRouter . "-" . $key] = $routeObjet->getLinkInfo();
	}
}

# extract config for the client
$pubConfig = array(
	"enableCategories" => $config["enableCategories"],
	"categories" => $config["categories"],
	"debug" => $config["debug"],
);

# send js table with all routers and config
echo "<script>";
echo "var routersList = " . json_encode($allRouterExisting, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ";";
echo "var config = " . json_encode($pubConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ";";
echo "</script>";

# load html page
include "template/index.html";
