<?php

require_once 'route.class.php';

function getIpAddress() {
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		return $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		return $_SERVER['REMOTE_ADDR'];
	}
}

// set the traefik ip and port
$traefikAPIURL = "http://traefik:8080/api/";

// Récupérer la liste des routes
$routesList = json_decode(file_get_contents($traefikAPIURL . "http/routers"), true);

// Récupérer la liste des middlewares
$middlewareList = json_decode(file_get_contents($traefikAPIURL . "http/middlewares"), true);

// Récupérer la liste des entrypoints
$entrypointsList = json_decode(file_get_contents($traefikAPIURL . "entrypoints"), true);

// Récupérer l'IP du client
$clientIp = getIpAddress();

$routeLinks = [];
foreach ($routesList as $route) {
	if ($route["provider"] == "internal") {
		continue;
	}
	if ($route['service'] == "webmenu") {
		continue;
	}
	$routeObjet = new Route($route,$traefikAPIURL);
	if (!$routeObjet->checkIfUserIsPermit($middlewareList, $clientIp)) {
		continue;
	}
	$routeObjet->buildURL($entrypointsList);
	$routeObjet->checkIfServiceIsUp();
	$routeObjet->checkFavicon();
	$routeLinks[] = $routeObjet->getLinkInfo();
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>menu navigation</title>
		<style type="text/css">
			.tile-container {
				display: flex;
				flex-wrap: wrap;
				justify-content: center;
			}
			.tile {
				width: 200px;
				height: 150px;
				margin: 10px;
				background-color: #f2f2f2;
				text-decoration: none;
				color: #333;
				display: flex;
				justify-content: center;
				align-items: center;
				transition: background-color 0.3s ease;
			}
			.tile:hover {
				background-color: #e6e6e6;
			}
			.tile-content {
				text-align: center;
			}
			.tile-hs {
				background-color: #e77c7c;
			}
			.tile-hs:hover {
				background-color: #e25454;
			}
			.bt-refresh {
				position: fixed;
				top: 20px;
				left: 20px;
				cursor: pointer;
				width: 50px;
				height: 50px;
				background-color: #f2f2f2;
				text-decoration: none;
				color: #333;
				display: flex;
				justify-content: center;
				align-items: center;
				transition: background-color 0.3s ease;
			}
			.bt-refresh:hover {
				background-color: #e6e6e6;
			}
			.animRotate {
				animation: rotate 2s infinite linear;
			}
			@keyframes rotate {
				from {
					transform: rotate(0deg);
				}
				to {
					transform: rotate(360deg);
				}
			}
		</style>
	</head>
	<body>
		<a href="cron.php" class="bt-refresh"><img id="imgrefresh" src='cache/refresh.png' style='width: 32px; height: 32px;'></a>
		<h1 style="text-align: center;">menu navigation</h1>
		<div class="tile-container">
			<?php foreach ($routeLinks as $link): ?>
			<a href="<?= $link['url'] ?>" class="tile <?= $link['up'] ? '' : 'tile-hs' ?>">
				<div class="tile-content">
					<h3><?= $link['name'] ?></h3>
					<img src='<?= $link['favicon'] ?>' alt='favicon' style='width: 32px; height: 32px;'>
				</div>
			</a>
			<?php endforeach; ?>
		</div>
		<script>
			document.querySelectorAll('.tile-hs').forEach(function(tile) {
				tile.addEventListener('click', function(event) {
					event.preventDefault();
					alert('ce service n\'est pas disponible');
				});
			});
			document.querySelector('.bt-refresh').addEventListener('click', function(event) {
				document.getElementById('imgrefresh').classList.add('animRotate');
			});
		</script>
	</body>
</html>
