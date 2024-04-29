# traefik-web-homepage

ce projet permet de creer un site web qui repertorie dynamiquement les differents services disponible sur traefik

le site recupere les info de traefik via son api et les affiche sous forme de tableau :
- si le service est down il est possible qu'il ne soit pas afficher
- si il y a un middleware avec un IPWhitelist, le site recherchera si l'ip du client est autoriser a acceder au service, si non le lien ne sera pas afficher
- si vous avez configurer un healthCheck pour vos services, la case lien sera rouge si le service est down

- pour les services qui respectent les conditions ci-dessus, un bloc avec le nom du service sera afficher pour y acceder et le site chargera l'icon du service et l'affichera dans le bloc

![menu](https://github.com/vampi62/traefik-web-homepage/main/menu.png)


une fleche en haut a gauche permet de recharger toutes les icons des services

ne fonctionne que pour les routes de type Host uniquement, sans aucune autre condition
exemple de route:
 - Host(`service1.yourDNS.net`)
 - Host(`service2.yourDNS.net`)

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
dans les fichiers index.php et cron.php remplacer la variable "$traefikUrl" par l'url de votre api traefik



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