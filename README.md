# traefik-web-homepage

ce projet permet de creer un site web qui repertorie dynamiquement les differents services disponible sur traefik

le site recupere les info de traefik via son api et les affiche sous forme de tableau :
- si le service est down il est possible qu'il ne soit pas afficher
- si il y a un middleware avec un IPWhitelist, le site recherchera si l'ip du client est autoriser a acceder au service, si non le lien ne sera pas afficher
- si vous avez configurer un healthCheck pour vos services, la case lien sera rouge si le service est down

- pour les services qui respectent les conditions ci-dessus, un bloc avec le nom du service sera afficher pour y acceder et le site chargera l'icon du service et l'affichera dans le bloc

![menu](https://github.com/vampi62/traefik-web-homepage/blob/main/menu.PNG)


une fleche en haut a gauche permet de recharger toutes les icons des services

ne fonctionne que pour les routes de type (Host,ClientIP,PathPrefix,Query) uniquement, sans aucune autre condition
exemple de route valide:
 - Host(`service1.yourDNS.net`)
 - Host(`yourDNS.net`) && PathPrefix(`/service1/`)
 - (Host(`example.com`) && QueryRegexp(`mobile`, `^(true|yes)$`) && ClientIP(`192.168.1.0/24`)) || (Host(`example.com`) && Path(`/products`))
 - (Host(`test.example.com`) && PathPrefix(`/dashboard/`)) || (Host(`test.example.com`) && PathPrefix(`/api/`))


## Installation

1. config traefik

activer l'api de traefik
```bash
api:
  insecure: true
```

2. clone et configuration du projet

```bash
git clone https://github.com/vampi62/traefik-web-homepage.git
cd traefik-web-homepage
```
dans le fichier config.json
```json
{
    "exclude": {
        "provider": [
            "internal"
        ],
        "service": [
            "webmenu"
        ]
    },
    "apiUrl": "http://traefik:8080/api/",
    "middlewareNoBlock": [
        "crowdsec@file",
        "crowdsec@docker"
    ]
}
```

placer le contenue du dossier html dans votre repertoire de serveur si vous n'utiliser pas docker, si vous utiliser docker aller a la section suivante pour deployer le conteneur


## Docker

```bash
sudo docker run -d \
	--net traefikNetwork \  # remplacer par le nom du reseau traefik
	--name webmenu \
	--restart unless-stopped \
	-l "traefik.enable=true" \
	-l "traefik.http.routers.webmenu.rule=Host(\`yourDNS.net\`)" \ # remplacer par votre nom de domaine principal
	-l "traefik.http.routers.webmenu.entrypoints=websecure" \
	-l "traefik.http.routers.webmenu.tls.certresolver=myresolver" \
	-l "traefik.http.services.webmenu.loadbalancer.server.port=80" \
	-v /html:/var/www/html \
	php:7.4-apache
```
