function createCat(name, categorie) {
	let catFrame = document.createElement("div");
	let catTitle = document.createElement("div");
	catTitle.classList.add("cat-title");
	catTitle.style.color = categorie["color"];
	let catTitleText = document.createElement("div");
	catTitleText.style.display = "flex";
	catTitleText.innerHTML = name;

	catTitle.appendChild(catTitleText);
	if (categorie["description"]) {
		let catDescription = document.createElement("div");
		catDescription.innerHTML = categorie["description"];

		catTitle.appendChild(catDescription);
	}
	let catContainer = document.createElement("div");
	catContainer.classList.add("tile-container");
	for (let i = 0; i < categorie["routers"].length; i++) {
		if (categorie["routers"][i] in routersList) {
			routersList[categorie["routers"][i]]["classed"] = true;
			catContainer.appendChild(createRouter(categorie["routers"][i], routersList[categorie["routers"][i]]));
		}
	};
	catFrame.appendChild(catTitle);
	catFrame.appendChild(catContainer);
	return catFrame;
}

function createRouter(routerNameId, router) {
	let link = document.createElement("a");
	link.classList.add("tile");
	if (router["up"] && router["url"]) {
		link.href = router["url"];
	}
	if (!router["up"] || !router["url"]) {
		link.classList.add("tile-hs");
	}
	link.id = routerNameId;
	let div = document.createElement("div");
	div.classList.add("tile-content");
	let title = document.createElement("h3");
	title.innerHTML = router["name"];
	let img = document.createElement("img");
	img.src = router["favicon"];
	img.alt = "favicon";
	img.classList.add("favicon");
	div.appendChild(title);
	div.appendChild(img);
	link.appendChild(div);
	return link;
}

function buildPage() {
	let frame = document.createElement("div");
	frame.classList.add("tile-container");
	if (config["enableCategories"]) {
		let categories = config["categories"]["categories"];
		Object.keys(categories).forEach((name) => {
			frame.appendChild(createCat(name, categories[name]));
		});
		let unclassifiedRouter = [];
		Object.keys(routersList).forEach((routerNameId) => {
			if (!routersList[routerNameId]["classed"]) {
				unclassifiedRouter.push(routerNameId);
			}
		});
		config["categories"]["unclassified"]["routers"] = unclassifiedRouter;
		frame.appendChild(createCat(config["categories"]["unclassified"]["name"], config["categories"]["unclassified"]));
	} else {
		Object.keys(routersList).forEach((routerNameId) => {
			frame.appendChild(createRouter(routerNameId, routersList[routerNameId]));
		});
	}
	document.getElementById("mainFrame").appendChild(frame);
}
buildPage();



function updatefavicon(router, favicon) {
	if (favicon === null) {
		return;
	}
	document.getElementById(router).querySelector('img').src = favicon;
}

document.querySelectorAll('.tile-hs').forEach(function(tile) {
	tile.addEventListener('click', function(event) {
		event.preventDefault();
		alert('aucun service disponible pour cette route');
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
		if (config['debug']) {
			console.log('reloadAll.php response:', json);
		}
		for (var router in json) {
			updatefavicon(router, json[router].favicon);
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
	if (config['debug']) {
		console.log('loadEmpty.php response:', json);
	}
	for (var router in json) {
		updatefavicon(router, json[router].favicon);
	}
}).catch(function(error) {
	document.getElementById('imgrefresh').classList.remove('animRotate');
	console.log('fetch failed :', error);
});
