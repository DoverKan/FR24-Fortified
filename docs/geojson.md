# Capas GeoJSON — Guía de creación

Todo archivo `.geojson` colocado en `public/geojson/` se carga automáticamente al arrancar el mapa. No hay que tocar código: basta con crear el archivo.

---

## Cómo añadir una capa nueva

1. Crea un archivo `public/geojson/mi-capa.geojson` con estructura GeoJSON estándar.
2. Recarga el mapa en el navegador.
3. La capa aparecerá automáticamente en el panel de capas con un checkbox para activarla/desactivarla.

El nombre del archivo determina la etiqueta en el panel. Ejemplos:

| Nombre del archivo | Etiqueta en el panel |
|---|---|
| `hospitales.geojson` | Hospitales |
| `zona-restringida.geojson` | Restringida |
| `torres-comunicacion.geojson` | Comunicacion |

Para usar una etiqueta personalizada, añade el nombre del archivo a `LAYER_LABELS` en `mapa.php`:

```javascript
const LAYER_LABELS = {
    vor: 'VOR / DME',
    'mi-capa': 'Mi etiqueta personalizada',
    // ...
};
```

---

## Estructura base

Todo GeoJSON válido sigue esta estructura:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": { },
      "geometry": { }
    }
  ]
}
```

---

## Propiedades reconocidas

Estas propiedades en cada `Feature` controlan cómo se muestra en el mapa:

| Propiedad | Tipo | Descripción |
|---|---|---|
| `nombre` | string | Nombre del elemento. Aparece en el popup y como etiqueta en polígonos. **Recomendado.** |
| `tipo` | string | Categoría del elemento (informativa, aparece en el popup). |
| `fill` | string (hex) | Color del marcador, polígono o línea. Ej: `"#e74c3c"`. Si se omite, se usa el color por defecto del tipo. |
| `icon` | string | Icono del marcador para puntos. Ver tabla de iconos. |
| `descripcion` | string u objeto | Contenido del popup. Puede ser texto plano o un objeto con claves/valores que se renderiza como tabla. |

### `descripcion` como objeto (tabla en popup)

```json
"descripcion": {
  "ICAO": "LEMD",
  "Frecuencia": "118.75",
  "Notas": "Torre principal"
}
```

### `descripcion` como texto plano

```json
"descripcion": "Centro de salud comarcal"
```

---

## Tipos de geometría

### Point — Marcador

Muestra un marcador interactivo en la posición indicada. Al hacer clic abre el popup.

```json
{
  "type": "Feature",
  "properties": {
    "nombre": "Torre de comunicaciones",
    "tipo": "Infraestructura",
    "fill": "#e74c3c",
    "icon": "antenna",
    "descripcion": {
      "Altura": "120 m",
      "Operador": "Telefónica"
    }
  },
  "geometry": {
    "type": "Point",
    "coordinates": [-3.7038, 40.4168]
  }
}
```

> Las coordenadas van siempre en orden **[longitud, latitud]** (estándar GeoJSON).

---

### Polygon — Área o zona

Dibuja un polígono relleno con borde. Muestra etiqueta en su centroide y popup al hacer clic.

```json
{
  "type": "Feature",
  "properties": {
    "nombre": "Zona restringida LER-01",
    "tipo": "Espacio aéreo",
    "fill": "#e74c3c",
    "descripcion": {
      "Límite inferior": "SFC",
      "Límite superior": "FL095",
      "Notas": "Activa L-V 08:00-20:00"
    }
  },
  "geometry": {
    "type": "Polygon",
    "coordinates": [
      [
        [-3.80, 40.50],
        [-3.70, 40.50],
        [-3.70, 40.40],
        [-3.80, 40.40],
        [-3.80, 40.50]
      ]
    ]
  }
}
```

> El primer y último punto del anillo deben ser iguales para cerrar el polígono.

---

### LineString — Ruta o traza

Dibuja una línea. Útil para rutas, trazados de carreteras, cables, etc.

```json
{
  "type": "Feature",
  "properties": {
    "nombre": "Ruta de patrulla",
    "fill": "#27ae60"
  },
  "geometry": {
    "type": "LineString",
    "coordinates": [
      [-3.80, 40.50],
      [-3.75, 40.45],
      [-3.70, 40.40]
    ]
  }
}
```

---

### MultiPolygon — Varias zonas en un mismo Feature

Agrupa varias áreas discontinuas en un único Feature. Cada subarray es un polígono independiente.

```json
{
  "type": "Feature",
  "properties": {
    "nombre": "Zona A + Zona B",
    "fill": "#9b59b6"
  },
  "geometry": {
    "type": "MultiPolygon",
    "coordinates": [
      [
        [[-3.80, 40.50], [-3.75, 40.50], [-3.75, 40.45], [-3.80, 40.45], [-3.80, 40.50]]
      ],
      [
        [[-3.60, 40.30], [-3.55, 40.30], [-3.55, 40.25], [-3.60, 40.25], [-3.60, 40.30]]
      ]
    ]
  }
}
```

---

## Iconos disponibles para puntos

El valor de `icon` determina el marcador visual. Los iconos disponibles son:

| Valor `icon` | Visual | Uso típico |
|---|---|---|
| `airport` | Círculo con código ICAO | Aeropuertos, ULM |
| `vor` | Símbolo hexagonal VOR | VOR/DME |
| `helipad` | Círculo con H | Helipuertos |
| `meshtastic` | Punto con icono Meshtastic | Nodos Meshtastic |
| `antenna` | Cuadrado naranja | Torres, antenas |
| `hospital` | Cuadrado rojo | Hospitales, clínicas |
| `school` | Cuadrado amarillo | Colegios |
| `park` | Cuadrado verde | Parques, zonas verdes |
| `poi` | Cuadrado genérico | Punto de interés genérico |
| *(vacío o cualquier otro)* | Cuadrado con color `fill` | Marcador genérico |

> Si el `icon` no coincide con ninguno de los anteriores se usa un marcador genérico cuadrado con el color `fill`.

---

## GeoJSON mixto — mezclar tipos

Un mismo archivo puede contener puntos, polígonos y líneas. El mapa los procesa todos:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": { "nombre": "Punto A", "icon": "poi", "fill": "#3498db" },
      "geometry": { "type": "Point", "coordinates": [-3.70, 40.41] }
    },
    {
      "type": "Feature",
      "properties": { "nombre": "Zona A", "fill": "#3498db" },
      "geometry": {
        "type": "Polygon",
        "coordinates": [[[-3.80, 40.50], [-3.70, 40.50], [-3.70, 40.40], [-3.80, 40.40], [-3.80, 40.50]]]
      }
    },
    {
      "type": "Feature",
      "properties": { "nombre": "Ruta A", "fill": "#3498db" },
      "geometry": {
        "type": "LineString",
        "coordinates": [[-3.80, 40.50], [-3.75, 40.45], [-3.70, 40.40]]
      }
    }
  ]
}
```

---

## Cómo obtener coordenadas de lugares reales

### Overpass Turbo (recomendado para datos OSM)

1. Ve a [overpass-turbo.eu](https://overpass-turbo.eu)
2. Escribe una consulta Overpass QL, por ejemplo:
   ```
   [out:json];
   (
     node["amenity"="hospital"]({{bbox}});
     way["amenity"="hospital"]({{bbox}});
   );
   out geom;
   ```
3. Haz clic en **Ejecutar** y luego en **Exportar → GeoJSON**
4. Limpia las propiedades que no necesites y añade `nombre`, `tipo`, `fill` e `icon`

### geojson.io

1. Ve a [geojson.io](https://geojson.io)
2. Usa las herramientas de dibujo para crear puntos, líneas o polígonos directamente sobre el mapa
3. Copia el JSON generado en la columna derecha

### Google Maps / cualquier mapa

Las coordenadas se obtienen haciendo clic derecho sobre el punto deseado. Recuerda el orden: **longitud primero, latitud segundo**.

---

## Validación

Antes de guardar el archivo puedes validar el GeoJSON en [geojsonlint.com](https://geojsonlint.com) o en [geojson.io](https://geojson.io) (pega el contenido y verifica que se muestra correctamente en el mapa de previsualización).

Errores comunes:
- **Polígono no cerrado**: el último punto debe ser igual al primero.
- **Coordenadas invertidas**: recuerda `[longitud, latitud]`, no `[latitud, longitud]`.
- **Comas finales**: JSON no permite comas después del último elemento de un array u objeto.

---

## Ejemplo completo — capa de hospitales

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {
        "nombre": "Hospital Universitario",
        "tipo": "Hospital",
        "fill": "#e74c3c",
        "icon": "hospital",
        "descripcion": {
          "Urgencias": "924 218 100",
          "Helipuerto": "Sí"
        }
      },
      "geometry": {
        "type": "Point",
        "coordinates": [-6.9716, 38.8823]
      }
    },
    {
      "type": "Feature",
      "properties": {
        "nombre": "Centro de Salud Norte",
        "tipo": "Centro de salud",
        "fill": "#e67e22",
        "icon": "hospital",
        "descripcion": "Atención primaria"
      },
      "geometry": {
        "type": "Point",
        "coordinates": [-6.9601, 38.8941]
      }
    }
  ]
}
```

Guarda el archivo como `public/geojson/hospitales.geojson` y recarga el mapa.
