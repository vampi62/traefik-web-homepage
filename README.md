# Traefik Web Homepage

This project allows you to create a dynamic web page that lists the various routers available on Traefik.

The site retrieves information from Traefik via its API and displays it in a table format:
- If a service is down, it might not be displayed on the page.
- If a service has an IPWhitelist middleware, the site will check if the client's IP address is authorized to access the service; if not, the link will not be displayed.
- If you have configured a health check for your services, the link box will appear red if the service is down, making it easy to identify non-functional services.

For routers meeting the above conditions, a block with the service's name will be displayed, allowing users to access it. The site will load and display the icon for each service within its respective block, if available.

if your browser is in dark mode, the site will automatically switch to a dark theme.

![menu](https://github.com/vampi62/traefik-web-homepage/blob/main/menu.PNG)

The site also supports categorizing routers, allowing you to group routers by type. For example, you can group routers into categories like "Media," "Home Automation," or "Productivity." Each category will have its own color and icon, making it easy to identify routers at a glance.

![menu](https://github.com/vampi62/traefik-web-homepage/blob/main/menuCat.PNG)

An arrow icon in the top-left corner allows you to refresh all service icons.
the first time you access the site, it will take a few seconds to load all the icons. After that, the site will cache the icons, making subsequent loads faster.

**Note:** This application only supports routes of type `Host`, `ClientIP`, `PathPrefix`, and `Query`, without additional complex conditions. Examples of valid routes include:
- ``Host(`service1.yourDNS.net`)``
- ``Host(`yourDNS.net`) && PathPrefix(`/service1/`)``
- ``(Host(`example.com`) && QueryRegexp(`mobile`, `^(true|yes)$`) && ClientIP(`192.168.1.0/24`)) || (Host(`example.com`) && Path(`/products`))``
- ``(Host(`test.example.com`) && PathPrefix(`/dashboard/`)) || (Host(`test.example.com`) && PathPrefix(`/api/`))``

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
            "router": [
                "webmenu@docker",
                "electrostoreAPI@docker",
                "dsm-web@file",
                "file-web@file",
                "vpn-web@file",
                "mta-sts@docker",
                "api@internal",
                "acme-http-bypass@internal",
                "ak-outpost-traefik-router@docker",
                "dashboard@internal"
            ]
        },
        "ignoreMiddleware": [
            "copilotBearerAPIKEY@file"
        ]
    },
    "tcp": {
        "exclude": {
            "provider": [
            ],
            "router": [
                "mqtt@docker"
            ]
        },
        "ignoreMiddleware": [
        ]
    },
    "enableCategories": true,
    "categories": {
        "unclassified": {
            "name": "Unclassified",
            "color": "#000000",
            "icon": "fas fa-question",
            "showIfNoRouter": false
        },
        "categories": {
            "Domotique": {
                "color": "#FF0000",
                "icon": "fas fa-home",
                "showIfNoRouter": true,
                "routers": [
                    "http-jeedom@docker",
                    "http-rhasspy@docker"
                ]
            },
            "Download": {
                "color": "#FFA500",
                "icon": "fas fa-download",
                "showIfNoRouter": true,
                "routers": [
                    "http-transmission@file",
                    "http-jdownloader@file"
                ]
            },
            "ERP": {
                "color": "#FFFF00",
                "icon": "fas fa-building",
                "showIfNoRouter": true,
                "routers": [
                    "http-grocy@docker",
                    "http-mmp@docker",
                    "http-electrostoreFRONT@docker"
                ]
            },
            "NAS": {
                "color": "#00FF00",
                "icon": "fas fa-hdd",
                "showIfNoRouter": true,
                "routers": [
                    "tcp-dsm-websecure@file",
                    "tcp-file-websecure@file",
                    "tcp-vpn-websecure@file"
                ]
            },
            "IA": {
                "color": "#00FFFF",
                "icon": "fas fa-brain",
                "showIfNoRouter": true,
                "routers": [
                    "http-ai@file",
                    "http-sd-invoke@file",
                    "http-sd-forge@file",
                    "http-sd-a1111@file"
                ]
            }
        }
    },
    "debug": {
        "enabled": false,
        "router": "copilot@file"
    }
}
```

- **apiUrl**: This is the URL to access Traefik's API. If Traefik is running on a different host or port, update this URL accordingly.
- **entryPointName**: This section specifies the entry points to use for HTTP and HTTPS routers. These names should match the entry points configured in Traefik.
- **http**: This section specifies the configuration for HTTP routers.
- **exclude**: This section specifies providers and routers to exclude from display. For example, excluding internal routers like `webmenu` keeps them hidden on the homepage.
- **ignoreMiddleware**: This section lists middlewares that, if used by a router, will cause that router to be excluded from the web page.
- **tcp**: This section specifies the configuration for TCP routers. The `exclude` and `ignoreMiddleware` options work similarly to their HTTP counterparts.
- **enableCategories**: This option enables or disables the display of categories on the homepage.
- **categories**: This section defines the categories and routers to display on the homepage.
  - **unclassified**: This section defines the settings for the unclassified category.
    - **name**: The name to use for routers that do not belong to any category.
    - **color**: The color to use for the unclassified category.
    - **icon**: The icon to use for the unclassified category.
    - **showIfNoRouter**: Whether to display the unclassified category if no routers are available.
  - **categories**: This section defines the categories to display on the homepage.
    - **Domotique**: This is an example category. You can add or remove categories as needed.
      - **color**: The color to use for this category.
      - **icon**: The icon to use for this category.
      - **showIfNoRouter**: Whether to display this category if no routers are available.
      - **routers**: A list of routers that belong to this category.
- **debug**: This option enables or disables debug mode. When enabled, additional information will be displayed in the console.
  - **enabled**: Set this to `true` to enable debug mode.
  - **service**: This option specifies a service to debug. If set, only information about this service will be displayed.

## Docker Deployment

To run this project as a Docker container:

```bash
sudo docker run -d \
	--net traefikNetwork \  # Replace with your Traefik network
	--name webmenu \
	--restart unless-stopped \
	--read-only=true \
	--security-opt no-new-privileges \
	--cap-drop ALL \
	--tmpfs /var/run/apache2 \
	-l "traefik.enable=true" \
	-l "traefik.http.routers.webmenu.rule=Host(\`yourDNS.net\`)" \ # Replace with your DNS
	-l "traefik.http.routers.webmenu.entrypoints=websecure" \
	-l "traefik.http.routers.webmenu.tls.certresolver=myresolver" \
	-l "traefik.http.routers.webmenu.service=webmenu@docker" \
	-l "traefik.http.services.webmenu.loadbalancer.server.port=80" \
	-v ${PWD}/html:/var/www/html \
	php:8.5-apache
```

### Notes on Docker Configuration:
- `--net traefikNetwork`: Replace `traefikNetwork` with the name of your Traefik network. This is essential for Traefik to route requests correctly.
- Replace `yourDNS.net` with the domain you intend to use to access this application.

After deployment, navigate to `http://yourDNS.net` to access your Traefik web homepage and see the dynamically updated list of routers available on your Traefik setup. 