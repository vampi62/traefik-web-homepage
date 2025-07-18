<?php

require_once 'route.class.php';

// read config file
$config = json_decode(file_get_contents('config.json'), true);

// Récupérer la liste des entrypoints
$entrypointsList = json_decode(file_get_contents($config['apiUrl'] . "entrypoints"), true);

$services = $config["categories"]["services"];
foreach (['http', 'tcp'] as $typeRouter) {
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
		$routeObjet = new Route($route, $config['apiUrl'], $config['debug']);
		$routeObjet->checkIfUserIsPermit($middlewareList, $config[$typeRouter]['ignoreMiddleware']);
		$routeObjet->buildLinkURL($entrypointsList,$config['entryPointName']);
		$routeObjet->checkIfServiceIsUp($typeRouter);
		$info = $routeObjet->getLinkInfo();
		if (!isset($services[$info['service']])) {
			$services[$info['service']] = array();
		}
		$services[$info['service']] = array_merge($services[$info['service']], $info);
	}
}
$categories = $config["categories"]["categories"];
$unCategorised = array();
foreach ($services as $key => $service) {
	if (!isset($service['favicon'])) {
		$service['favicon'] = Route::GetOfflineCachedFavicon($key);
	}
	if (!isset($service['category'])) { // Si la catégorie n'est pas définie pour le service
		$unCategorised[$key] = $service;
		continue;
	}
	if (!isset($categories[$service['category']])) { // Si la catégorie n'existe pas
		$unCategorised[$key] = $service;
		continue;
	}
	if (!isset($categories[$service['category']]["services"])) { // Si la catégorie n'a pas encore de services
		$categories[$service['category']]["services"] = array();
	}
	$categories[$service['category']]["services"][$key] = $service;
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

		@media (prefers-color-scheme: dark) {
			body {
				background-color: #333333;
				color: #ffffff;
			}

			.tile {
				background-color: #3c3c3c;
				color: #f2f2f2;
			}

			.tile:hover {
				background-color: #4a4a4a;
			}

			.tile-hs {
				background-color: #8b3a3a;
			}

			.tile-hs:hover {
				background-color: #a04545;
			}

			.bt-refresh {
				background-color: #6c6c6c;
				color: #f2f2f2;
			}

			.bt-refresh:hover {
				background-color: #9d9d9d;
			}
		}
	</style>
</head>

<body>
	<a class="bt-refresh"><img id="imgrefresh" src='cache/refresh.png' style='width: 32px; height: 32px;'></a>
	<h1 style="text-align: center;">menu navigation</h1>
	<?php if ($config['enableCategories']): ?>
	<div class="tile-container">
		<?php foreach ($categories as $keyCat => $category):
			if (!isset($category['services'])) {
				continue;
			}
			# check if a service is up and if it is permited
			$hasServiceUp = false;
			foreach ($category['services'] as $keyServ => $service) {
				if (isset($service["isPermited"]) && $service["isPermited"]) {
					$hasServiceUp = true;
					break;
				}
			}
			if (!$hasServiceUp) {
				continue;
			}
			?>
			<div>
				<div style="display: flex; flex-direction: column; color: <?= $category['color'] ?>;">
					<div style="display: flex;">
						<div>
							<?= $keyCat ?>
						</div>
					</div>
					<?php if (isset($category['description'])): ?>
						<div>
							<?= $category['description'] ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="tile-container">
					<?php foreach ($category['services'] as $keyServ => $service):
						if (isset($service["isPermited"]) && $service["isPermited"]):
						if (isset($service['up'])): ?>
							<a href="<?= $service['url'] ?>" class="tile <?= $service['up'] ? '' : 'tile-hs' ?>">
						<?php else: ?>
							<a class="tile tile-hs">
						<?php endif; ?>
								<div class="tile-content" id="<?= $keyServ ?>">
									<h3><?= preg_replace('/@.*/', '', $keyServ) ?></h3>
									<img src='<?= $service['favicon'] ?>' alt='favicon' style='width: 32px; height: 32px;'>
								</div>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
		<?php if (count($unCategorised) > 0):
			# check if a service is up and if it is permited
			$hasServiceUp = false;
			foreach ($unCategorised as $keyServ => $service) {
				if (isset($service["isPermited"]) && $service["isPermited"]) {
					$hasServiceUp = true;
					break;
				}
			}
			if ($hasServiceUp): ?>
			<div>
				<div style="display: flex; flex-direction: column; color: <?= $config['categories']['unclassifiedColor'] ?>;">
					<div style="display: flex;">
						<div>
							<?= $config['categories']['unclassifiedName'] ?>
						</div>
					</div>
					<?php if (isset($config['categories']['unclassifiedDescription'])):?>
						<div>
							<?= $config['categories']['unclassifiedDescription'] ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="tile-container">
					<?php foreach ($unCategorised as $keyServ => $service):
						if (isset($service["isPermited"]) && $service["isPermited"]):
						if (isset($service['up']) && $service['urlIsFormed']): ?>
							<a href="<?= $service['url'] ?>" class="tile <?= $service['up'] ? '' : 'tile-hs' ?>">
						<?php else: ?>
							<a class="tile tile-hs">
						<?php endif; ?>
								<div class="tile-content" id="<?= $keyServ ?>">
									<h3><?= preg_replace('/@.*/', '', $keyServ) ?></h3>
									<img src='<?= $service['favicon'] ?>' alt='favicon' style='width: 32px; height: 32px;'>
								</div>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php else: ?>
	<div class="tile-container">
		<?php foreach ($services as $keyServ => $service):
			if (isset($service['up']) && $service['urlIsFormed']): ?>
				<a href="<?= $service['url'] ?>" class="tile <?= $service['up'] ? '' : 'tile-hs' ?>">
			<?php else: ?>
				<a class="tile tile-hs">
			<?php endif; ?>
					<div class="tile-content" id="<?= $keyServ ?>">
						<h3><?= preg_replace('/@.*/', '', $keyServ) ?></h3>
						<img src='<?= $service['favicon'] ?>' alt='favicon' style='width: 32px; height: 32px;'>
					</div>
				</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<script>
		function update_favicon(service, favicon) {
			if (favicon === null) {
				return;
			}
			document.getElementById(service).querySelector('img').src = favicon;
		}


		document.querySelectorAll('.tile-hs').forEach(function(tile) {
			tile.addEventListener('click', function(event) {
				event.preventDefault();
				alert('ce service n\'est pas disponible');
			});
		});
		document.querySelector('.bt-refresh').addEventListener('click', function(event) {
			document.getElementById('imgrefresh').classList.add('animRotate');
			//json_decode
			fetch('reloadAll.php').then(function(response) {
				document.getElementById('imgrefresh').classList.remove('animRotate');
				if (!response.ok) {
					throw new Error('HTTP error, status = ' + response.status);
				}
				return response.json();
			}).then(function(json) {
				<?php 
					if ($config['debug']['enabled']) {
						echo "console.log('reloadAll.php response:', json);";
					}
				?>
				for (var service in json) {
					update_favicon(service, json[service].favicon);
				}
			}).catch(function(error) {
				document.getElementById('imgrefresh').classList.remove('animRotate');
				console.error('fetch failed', error);
			});
		});

		// fetch loadEmpty.php
		document.getElementById('imgrefresh').classList.add('animRotate');
		//json_decode
		fetch('loadEmpty.php').then(function(response) {
			document.getElementById('imgrefresh').classList.remove('animRotate');
			if (!response.ok) {
				throw new Error('HTTP error, status = ' + response.status);
			}
			return response.json();
		}).then(function(json) {
			<?php 
				if ($config['debug']['enabled']) {
					echo "console.log('loadEmpty.php response:', json);";
				}
			?>
			for (var service in json) {
				update_favicon(service, json[service].favicon);
			}
		}).catch(function(error) {
			document.getElementById('imgrefresh').classList.remove('animRotate');
			console.error('fetch failed', error);
		});
	</script>
</body>
</html>
