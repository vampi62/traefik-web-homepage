# Traefik Web Homepage

This project allows you to create a dynamic web page that lists the various services available on Traefik.

The site retrieves information from Traefik via its API and displays it in a table format:
- If a service is down, it might not be displayed on the page.
- If a service has an IPWhitelist middleware, the site will check if the client's IP address is authorized to access the service; if not, the link will not be displayed.
- If you have configured a health check for your services, the link box will appear red if the service is down, making it easy to identify non-functional services.

For services meeting the above conditions, a block with the service's name will be displayed, allowing users to access it. The site will load and display the icon for each service within its respective block, if available.

if your browser is in dark mode, the site will automatically switch to a dark theme.

![menu](https://github.com/vampi62/traefik-web-homepage/blob/main/menu.PNG)

The site also supports categorizing services, allowing you to group services by type. For example, you can group services into categories like "Media," "Home Automation," or "Productivity." Each category will have its own color and icon, making it easy to identify services at a glance.

![menu](https://github.com/vampi62/traefik-web-homepage/blob/main/menuCat.PNG)

An arrow icon in the top-left corner allows you to refresh all service icons.
the first time you access the site, it will take a few seconds to load all the icons. After that, the site will cache the icons, making subsequent loads faster.

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

### 2. Clone and Configure the Project

To set up the project:

```bash
git clone https://github.com/vampi62/traefik-web-homepage.git
cd traefik-web-homepage
```

In the `config.json` file, configure the following options:

```json
{
    "apiUrl": "http://traefik:8080/api/",
    "entryPointName": {
        "http": "web",
        "https": "websecure"
    },
    "http": {
        "exclude": {
            "provider": [
                "internal"
            ],
            "service": [
                "webmenu@docker",
                "dsm-web@file",
                "file-web@file",
                "vpn-web@file"
            ]
        },
        "ignoreMiddleware": [
            "crowdsec@file",
            "crowdsec@docker"
        ]
    },
    "tcp": {
        "exclude": {
            "provider": [
            ],
            "service": [
            ]
        },
        "ignoreMiddleware": [
        ]
    },
    "enableCategories": true,
    "categories": {
        "unclassifiedName": "Unclassified",
        "unclassifiedColor": "#000000",
        "unclassifiedIcon": "fas fa-question",
        "unclassifiedShowIfNoService": true,
        "categories": {
            "Domotique": {
                "color": "#FF0000",
                "icon": "fas fa-home",
                "showIfNoService": true
            },
            "Download": {
                "color": "#FFA500",
                "icon": "fas fa-download",
                "showIfNoService": true
            },
            "ERP": {
                "color": "#FFFF00",
                "icon": "fas fa-building",
                "showIfNoService": true
            },
            "NAS": {
                "color": "#00FF00",
                "icon": "fas fa-hdd",
                "showIfNoService": true
            },
            "IA": {
                "color": "#00FFFF",
                "icon": "fas fa-brain",
                "showIfNoService": true
            }
        },
        "services": {
            "jeedom@docker": {
                "category": "Domotique"
            },
            "rhasspy@docker": {
                "category": "Domotique"
            },
            "transmission@file": {
                "category": "Download"
            },
            "jdownloader@file": {
                "category": "Download"
            },
            "grocy@docker": {
                "category": "ERP"
            },
            "mmp@docker": {
                "category": "ERP"
            },
            "ai@file": {
                "category": "IA"
            },
            "stablediffusion@file": {
                "category": "IA"
            }
        }
    }
}
```

- **apiUrl**: This is the URL to access Traefik's API. If Traefik is running on a different host or port, update this URL accordingly.
- **entryPointName**: This section specifies the entry points to use for HTTP and HTTPS services. These names should match the entry points configured in Traefik.
- **http**: This section specifies the configuration for HTTP services.
- **exclude**: This section specifies providers and services to exclude from display. For example, excluding internal services like `webmenu` keeps them hidden on the homepage.
- **ignoreMiddleware**: This list specifies middleware to ignore when checking access restrictions. This can be useful for middlewares like `crowdsec` that manage IP filtering without blocking service links.
- **tcp**: This section specifies the configuration for TCP services. The `exclude` and `ignoreMiddleware` options work similarly to their HTTP counterparts.
- **enableCategories**: This option enables or disables the display of categories on the homepage.
- **categories**: This section defines the categories and services to display on the homepage.
  - **unclassifiedName**: The name to use for services that do not belong to any category.
  - **unclassifiedColor**: The color to use for the unclassified category.
  - **unclassifiedIcon**: The icon to use for the unclassified category.
  - **unclassifiedShowIfNoService**: Whether to display the unclassified category if no services are available.
  - **categories**: This section defines the categories to display on the homepage.
    - **Domotique**: This is an example category. You can add or remove categories as needed.
      - **color**: The color to use for this category.
      - **icon**: The icon to use for this category.
      - **showIfNoService**: Whether to display this category if no services are available.
  - **services**: This section defines the services and their categories.
    - **jeedom@docker**: This is an example service. You can add or remove services as needed.
      - **category**: The category to which this service belongs.

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
	-l "traefik.http.routers.webmenu.service=webmenu@docker" \
	-l "traefik.http.services.webmenu.loadbalancer.server.port=80" \
	-v /html:/var/www/html \
	php:7.4-apache
```

### Notes on Docker Configuration:
- `--net traefikNetwork`: Replace `traefikNetwork` with the name of your Traefik network. This is essential for Traefik to route requests correctly.
- Replace `yourDNS.net` with the domain you intend to use to access this application.
- This example uses `php:7.4-apache` as the base image. Ensure that the PHP version is compatible with your project requirements.

After deployment, navigate to `http://yourDNS.net` to access your Traefik web homepage and see the dynamically updated list of services available on your Traefik setup. 