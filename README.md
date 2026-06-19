# FlightRadar24-Fortified

Panel de monitoreo web para receptores ADS-B de FlightRadar24 (Feeder y Box). Muestra en tiempo real el estado del receptor, estadísticas de vuelos y un mapa interactivo de posiciones de aeronaves.

**Repositorio:** https://github.com/DoverKan/FR24-Fortified

---

## Características

- **Dashboard en tiempo real** — estado del receptor, estadísticas y tabla de vuelos activos con auto-refresco
- **Mapa interactivo** — visualización de posiciones de aeronaves sobre Mapbox GL con capas GeoJSON (espacio aéreo, aeropuertos, etc.)
- **Consola SBS** — stream en tiempo real del protocolo SBS/AVR (puerto 30003) vía Server-Sent Events
- **Soporte dual** — compatible con receptores tipo `feeder` y tipo `box`
- **Versión standalone** — archivo único `fr24-standalone.php` para despliegue sin configuración adicional

---

## Requisitos

- PHP 7.4 o superior con extensión `curl` habilitada
- Servidor web Apache (Linux) o XAMPP (Windows)
- Acceso de red al receptor FlightRadar24 (local o LAN)
- Token de Mapbox (solo para la página de mapa)

---

## Instalación en Linux (Apache)

### 1. Instalar Apache y PHP

```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-curl git
```

Verificar que los servicios estén activos:

```bash
sudo systemctl enable apache2
sudo systemctl start apache2
```

### 2. Clonar el repositorio

```bash
cd /var/www/html
sudo git clone https://github.com/DoverKan/FR24-Fortified.git fr24
sudo chown -R www-data:www-data /var/www/html/fr24
```

### 3. Configurar el VirtualHost de Apache

Crear el archivo de sitio:

```bash
sudo nano /etc/apache2/sites-available/fr24.conf
```

Contenido:

```apache
<VirtualHost *:80>
    ServerName fr24.local
    DocumentRoot /var/www/html/fr24/public

    <Directory /var/www/html/fr24/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Activar el sitio:

```bash
sudo a2ensite fr24.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### 4. Configurar la aplicación

```bash
sudo cp /var/www/html/fr24/config/config.example.php /var/www/html/fr24/config/config.php
sudo nano /var/www/html/fr24/config/config.php
```

### 5. Acceder al dashboard

Abre el navegador en `http://localhost` o en la IP del servidor.

---

## Instalación en Windows (XAMPP)

### 1. Instalar XAMPP

1. Descarga XAMPP desde https://www.apachefriends.org
2. Ejecuta el instalador y selecciona al menos **Apache** y **PHP**
3. Abre el **Panel de Control de XAMPP** e inicia el módulo **Apache**

### 2. Clonar el repositorio

Abre **Git Bash** o **PowerShell** y ejecuta:

```bash
cd C:\xampp\htdocs
git clone https://github.com/DoverKan/FR24-Fortified.git fr24
```

### 3. Configurar la aplicación

```bash
copy C:\xampp\htdocs\fr24\config\config.example.php C:\xampp\htdocs\fr24\config\config.php
```

Edita `config.php` con un editor de texto (Notepad++, VSCode, etc.).

### 4. Verificar que PHP cURL está habilitado

Abre `C:\xampp\php\php.ini`, busca la línea siguiente y asegúrate de que **no** tiene `;` al inicio:

```ini
extension=curl
```

Reinicia Apache desde el Panel de Control de XAMPP.

### 5. Acceder al dashboard

Abre el navegador en `http://localhost/fr24`

---

## Configuración

Editar `config/config.php` con los valores de tu receptor:

```php
define('FR24_IP',      '192.168.1.11');  // IP del receptor en la red local
define('FR24_PORT',    8754);             // Puerto del receptor
define('FR24_TYPE',    'box');            // 'feeder' o 'box'
define('ICAO',         'LEBZ');           // Código ICAO del aeropuerto (opcional)
define('LAT',          38.891944);        // Latitud del centro del mapa (opcional)
define('LON',          -6.822397);        // Longitud del centro del mapa (opcional)
define('MAPBOX_TOKEN', 'pk.eyJ...');     // Token de Mapbox (necesario para el mapa)
```

---

## Estructura del proyecto

```
FR24/
├── config/
│   ├── config.php              # Configuración activa (credenciales, en .gitignore)
│   └── config.example.php      # Plantilla de configuración
│
├── src/
│   ├── Config.php              # Cargador y validador de configuración
│   └── Source/
│       ├── SourceInterface.php # Interfaz común para fuentes de datos
│       ├── FeederSource.php    # Conector para receptor tipo Feeder
│       ├── BoxSource.php       # Conector para receptor tipo Box
│       └── views/
│           ├── feeder.php      # Vista del dashboard para Feeder
│           └── box.php         # Vista del dashboard para Box
│
├── public/
│   ├── index.php               # Dashboard principal
│   ├── mapa.php                # Mapa interactivo de aeronaves
│   ├── console.php             # Consola del stream SBS
│   ├── data.php                # Endpoint JSON para auto-refresco
│   ├── sse.php                 # Endpoint Server-Sent Events
│   ├── fr24-standalone.php     # Versión autónoma en un solo archivo
│   ├── partials/               # Componentes reutilizables (sidebar, topbar)
│   ├── css/                    # Hojas de estilo
│   └── geojson/                # Capas GeoJSON (aeropuertos, sectores, etc.)
│
└── docs/                       # Documentación adicional
```

---

## Páginas y endpoints

| Ruta | Descripción |
|---|---|
| `/` o `/index.php` | Dashboard principal con estado del receptor y tabla de vuelos |
| `/mapa.php` | Mapa interactivo con posiciones de aeronaves |
| `/console.php` | Consola del stream SBS en tiempo real |
| `/data.php` | API JSON para refresco de datos del dashboard |
| `/sse.php` | Stream de Server-Sent Events (usado por la consola) |
| `/fr24-standalone.php` | Versión standalone (admite `?action=stream\|ping\|stats\|weather`) |

---

## Tipos de receptor

### Feeder

Consulta `/monitor.json` (estado) y `/flights.json` (aeronaves) del receptor.

```php
define('FR24_TYPE', 'feeder');
define('FR24_PORT', 8754);
```

### Box

Realiza peticiones paralelas a `/index.php`, `/flights.js`, `/gps.json` y `/stats.json`, y parsea la respuesta JSONP de vuelos.

```php
define('FR24_TYPE', 'box');
```

---

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 7.4+ |
| Frontend | HTML5, CSS3, JavaScript (vanilla) |
| UI | Bootstrap 5.3.3 |
| Mapas | Mapbox GL JS v3.3.0 |
| HTTP | PHP cURL (peticiones paralelas) |
| Streaming | Server-Sent Events (SSE) |

---

## Seguridad

El archivo `config/config.php` está excluido de git mediante `.gitignore` para evitar exponer credenciales. Usa siempre `config/config.example.php` como base y nunca confirmes el archivo de configuración real al repositorio.

---

## Licencia

Proyecto privado. Todos los derechos reservados.
