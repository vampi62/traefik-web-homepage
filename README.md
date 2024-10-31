# Traefik Web Homepage

This project allows you to create a dynamic web page that lists the various services available on Traefik.

The site retrieves information from Traefik via its API and displays it in a table format:
- If a service is down, it might not be displayed on the page.
- If a service has an IPWhitelist middleware, the site will check if the client's IP address is authorized to access the service; if not, the link will not be displayed.
- If you have configured a health check for your services, the link box will appear red if the service is down, making it easy to identify non-functional services.

For services meeting the above conditions, a block with the service's name will be displayed, allowing users to access it. The site will load and display the icon for each service within its respective block, if available.

![menu](https://github.com/vampi62/traefik-web-homepage/blob/main/menu.PNG)

An arrow icon in the top-left corner allows you to refresh all service icons.

**Note:** This application only supports routes of type `Host`, `ClientIP`, `PathPrefix`, and `Query`, without additional complex conditions. Examples of valid routes include:
- `Host(`service1.yourDNS.net`)`
- `Host(`yourDNS.net`) && PathPrefix(`/service1/`)`
- `(Host(`example.com`) && QueryRegexp(`mobile`, `^(true|yes)$`) && ClientIP(`192.168.1.0/24`)) || (Host(`example.com`) && Path(`/products`))`
- `(Host(`test.example.com`) && PathPrefix(`/dashboard/`)) || (Host(`test.example.com`) && PathPrefix(`/api/`))`

## Installation

### 1. Traefik Configuration

To enable Traefik's API, add the following configuration to your Traefik settings:

```yaml
api:
  insecure: true
```

This exposes the API without authentication, which can be useful in development but may be a security risk in production. Consider using `api.dashboard` and configuring access restrictions in production environments.

### 2. Clone and Configure the Project

To set up the project:

```bash
git clone https://github.com/vampi62/traefik-web-homepage.git
cd traefik-web-homepage
```

In the `config.json` file, configure the following options:

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

- **exclude**: This section specifies providers and services to exclude from display. For example, excluding internal services like `webmenu` keeps them hidden on the homepage.
- **apiUrl**: This is the URL to access Traefik's API. If Traefik is running on a different host or port, update this URL accordingly.
- **middlewareNoBlock**: This list specifies middleware to ignore when checking access restrictions. This can be useful for middlewares like `crowdsec` that manage IP filtering without blocking service links.

Place the contents of the `html` folder in your server's web directory if you are not using Docker. For Docker deployment, continue to the next section.

## Docker Deployment

To run this project as a Docker container:

```bash
sudo docker run -d \
	--net traefikNetwork \  # Replace with your Traefik network
	--name webmenu \
	--restart unless-stopped \
	-l "traefik.enable=true" \
	-l "traefik.http.routers.webmenu.rule=Host(`yourDNS.net`)" \ # Replace with your DNS
	-l "traefik.http.routers.webmenu.entrypoints=websecure" \
	-l "traefik.http.routers.webmenu.tls.certresolver=myresolver" \
	-l "traefik.http.services.webmenu.loadbalancer.server.port=80" \
	-v /html:/var/www/html \
	php:7.4-apache
```

### Notes on Docker Configuration:
- `--net traefikNetwork`: Replace `traefikNetwork` with the name of your Traefik network. This is essential for Traefik to route requests correctly.
- Replace `yourDNS.net` with the domain you intend to use to access this application.
- This example uses `php:7.4-apache` as the base image. Ensure that the PHP version is compatible with your project requirements.

After deployment, navigate to `http://yourDNS.net` to access your Traefik web homepage and see the dynamically updated list of services available on your Traefik setup. 