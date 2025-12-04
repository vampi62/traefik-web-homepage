<?php

/* use parallel\Runtime;
use parallel\Future;

$runtime = new Runtime();
$threadFetchFavIcon = [];
*/

require_once "class/traefik.class.php";
require_once "class/route.class.php";
require_once "class/favIcon.class.php";

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
		$localServiceUrl = $routeObjet->checkIfServiceIsUp($typeRouter, [$traefik, 'getServiceStatus']);
		if (!boolval($localServiceUrl)) {
			continue;
		}
		$favIconUrl = null;
		if (!$routeObjet->getCachedFavicon()) { // no favIconFind
			/* // start worker findFavIcon
			$threadFetchFavIcon[] = $runtime->run(
			function (string $url, string $typeRouter, string $key, bool $debug): array {
					$favIcon = new FavIcon($url, $key, $debug);
					return [$typeRouter . "-" . $key, $favIcon->updateFavicon()];
				},
				[$localServiceUrl, $typeRouter, $key, $config["debug"]["enabled"] && $config["debug"]["router"] == $key]
			); */
			$favIcon = new FavIcon($localServiceUrl, $key, $config["debug"]["enabled"] && $config["debug"]["router"] == $key);
			$favIconUrl = $favIcon->updateFavicon();
		}
		$allRouterExisting[$typeRouter . "-" . $key] = $routeObjet->getLinkInfo();
		if (!is_null($favIconUrl)) {
			$allRouterExisting[$typeRouter . "-" . $key]["favicon"] = $favIconUrl;
		}
	}
}

/* // await all worker findFavIcon before return json result
foreach ($threadFetchFavIcon as $thread) {
    $results = $thread->value();
	if (isset($allRouterExisting[$results[0]])) {
		$allRouterExisting[$results[0]]["favicon"] = $results[1];
	}
} */

header("Content-Type: application/json");
echo json_encode($allRouterExisting);
