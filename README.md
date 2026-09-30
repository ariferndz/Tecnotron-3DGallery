# Tecnotron 3D Gallery

Catálogo de máquinas de Tecnotron para WordPress: ficha técnica, carrusel de imágenes, visor 3D con realidad aumentada, QR para verlas en el móvil, fichas PDF y solicitudes de presupuesto.

Este repositorio contiene el **plugin de WordPress** (código fuente y zip listo para instalar), los **modelos 3D** optimizados, las **herramientas** para regenerarlo todo, las **pruebas** y la **documentación**.

![Catálogo de máquinas](docs/img/catalogo.webp)

| | |
| --- | --- |
| 📦 Plugin listo para instalar | [`dist/tecnotron-maquinas.zip`](dist/tecnotron-maquinas.zip) |
| 📖 Guía de uso (añadir máquinas, categorías, GLB, PDF, CSV…) | [`docs/guia.md`](docs/guia.md) |
| 🧩 Código del plugin | [`tecnotron-maquinas/`](tecnotron-maquinas/) |

## Contenido

```
tecnotron-maquinas/     Código fuente del plugin (es exactamente lo que va dentro del zip)
dist/                   tecnotron-maquinas.zip, generado con «npm run build»
modelos/                GLB optimizados a tamaño real y sus 4 imágenes (Block Car, Peppa Bus, Bluey Family Car)
scripts/                build.mjs (zip del plugin), optimizar-glb.mjs, imagenes-glb.mjs, prototipo.mjs
tests/                  Pruebas en navegador (web pública y escritorio) y cargador de datos de demostración
dev/mu-plugins/         Ajustes sólo para el entorno de desarrollo
prototipo/              Maqueta HTML aprobada antes de hacer el plugin
docs/                   Guía de uso e imágenes
docker-compose.yml      WordPress + MariaDB para desarrollo y pruebas
.github/workflows/      Integración continua: zip, pruebas y publicación de versiones
```

## Instalar en WordPress

No hace falta programar ni tocar el servidor.

1. Descarga [`dist/tecnotron-maquinas.zip`](dist/tecnotron-maquinas.zip) (botón «Download raw file») o, si hay versiones publicadas, el zip de la última [versión](../../releases).
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin** → elige el zip → **Instalar** → **Activar**.
3. **Máquinas → Importar / exportar → Crear el catálogo inicial** (opcional): da de alta las 42 máquinas del configurador.
4. **Máquinas → Ajustes**: correo que recibe las solicitudes y datos comunes.
5. Abre `https://tu-web/maquinas/`. Si da error 404: **Ajustes → Enlaces permanentes → Guardar**.

Requisitos: WordPress 6.2+ y PHP 7.4+. Funciona con temas clásicos y de bloques. Todo lo demás (añadir y quitar máquinas, categorías, imágenes, modelos 3D, PDF, formulario, CSV, API) está en la [guía de uso](docs/guia.md).

**Actualizar** a una versión nueva: sube el zip nuevo por el mismo camino y elige **Reemplazar la actual con la subida**. Las máquinas, imágenes, modelos y solicitudes no se tocan: están en la base de datos y la biblioteca de medios, no en el plugin.

## Regenerar el plugin

Necesitas [Node.js](https://nodejs.org) 20 o superior y Git. PHP es opcional (si está instalado, se comprueba la sintaxis de todos los archivos).

```bash
git clone https://github.com/ariferndz/Tecnotron-3DGallery.git
cd Tecnotron-3DGallery
npm ci            # la primera vez: instala las herramientas en node_modules/
npm run build     # → dist/tecnotron-maquinas.zip
```

`npm run build` ([scripts/build.mjs](scripts/build.mjs)):

1. Copia las librerías de terceros desde `node_modules` a `tecnotron-maquinas/assets/vendor/`: **model-viewer** (visor 3D y realidad aumentada) y **qrcode-generator** (QR).
2. Comprueba que sus versiones coinciden con las constantes `TNM_MV_VERSION` y `TNM_QR_VERSION` del plugin y con `readme.txt`.
3. Comprueba que la versión del plugin es la misma en la cabecera de `tecnotron-maquinas.php`, en `TNM_VERSION` y en el `Stable tag` de `readme.txt`.
4. Revisa la sintaxis de los PHP.
5. Empaqueta la carpeta en `dist/tecnotron-maquinas.zip`. El zip es reproducible: con el mismo código sale idéntico byte a byte en Windows, macOS o Linux, así que la integración continua puede comprobar que el zip subido está al día.

Si algo no cuadra, el script no genera el zip y explica qué corregir.

### Publicar una versión nueva

1. Haz los cambios en `tecnotron-maquinas/`.
2. Sube el número de versión (p. ej. `1.1.0`) en los tres sitios: línea `Version:` de `tecnotron-maquinas.php`, `define( 'TNM_VERSION', … )` y `Stable tag:` de `readme.txt`. Cambiarla hace que los navegadores descarguen el CSS y el JS nuevos.
3. `npm run build` y, si puedes, las pruebas (ver abajo).
4. Sube los cambios **incluido `dist/tecnotron-maquinas.zip`**.
5. Opcional: crea la etiqueta `v1.1.0` (`git tag v1.1.0 && git push --tags`). La integración continua publica una versión en GitHub con el zip adjunto.
6. Instálala en WordPress con **Reemplazar la actual con la subida**.

### Actualizar model-viewer o el generador de QR

```bash
npm install --save-exact @google/model-viewer@4.4.0    # la versión nueva
```

Cambia `TNM_MV_VERSION` en `tecnotron-maquinas.php` y la línea de «Terceros» en `readme.txt`, ejecuta `npm run build` y prueba la realidad aumentada en un Android y un iPhone antes de publicar. Para el QR es igual con `qrcode-generator` y `TNM_QR_VERSION`.

## Modelos 3D

La web usa **GLB** (glTF binario), no OBJ. Si una máquina está en OBJ, FBX u otro formato, ábrela en Blender y expórtala con **Archivo → Exportar → glTF 2.0**, formato *glTF Binary (.glb)*.

Para dejar un GLB listo para la web:

```bash
npm run optimizar -- "C:/modelos/Peppa_Bus.glb" --medida 180
# → modelos/peppa-bus.glb  3901 KB → 1116 KB · Medidas del modelo: 180 × 92 × 145 cm

npm run imagenes -- modelos/peppa-bus.glb
# → modelos/peppa-bus-1.png … peppa-bus-4.png (vista 3/4, frontal, lateral y trasera, fondo transparente)
```

- **`optimizar`** ([scripts/optimizar-glb.mjs](scripts/optimizar-glb.mjs)) comprime la geometría y pasa las texturas a WebP de 2.048 px como máximo, sin simplificar la malla. Con `--medida` (en cm, la mayor de ancho y largo de la ficha) escala el modelo para que en realidad aumentada aparezca **a tamaño real**. Opciones: `--salida ruta.glb`, `--textura 1024`.
- **`imagenes`** ([scripts/imagenes-glb.mjs](scripts/imagenes-glb.mjs)) renderiza 4 vistas en PNG transparente, recortadas y centradas en 4:3, para usarlas como imagen principal y galería. Necesita Chromium: `npx playwright install chromium` la primera vez.

Después se suben desde la ficha de la máquina en WordPress (caja **Modelo 3D y realidad aumentada**); ver [Modelos 3D en la guía](docs/guia.md#modelos-3d-glb).

| Modelo | Original | Optimizado | Medidas del modelo (cm) |
| --- | --- | --- | --- |
| `block-car.glb` | 4,6 MB | 1,3 MB | 198 × 93 × 91 |
| `peppa-bus.glb` | 4,0 MB | 1,1 MB | 180 × 92 × 145 |
| `bluey-family-car.glb` | 4,6 MB | 1,4 MB | 197 × 114 × 133 |

Los originales no se guardan en el repositorio (la carpeta `modelos/originales/` está ignorada por si quieres dejarlos ahí en tu equipo).

## Entorno de desarrollo y pruebas

Con [Docker](https://www.docker.com/products/docker-desktop/) se levanta un WordPress local con el plugin enlazado: cualquier cambio en `tecnotron-maquinas/` se ve al recargar la página.

```bash
npm run dev          # WordPress en http://localhost:8080
npm run dev:datos    # instala WordPress y carga el catálogo de demostración
```

- Catálogo: <http://localhost:8080/maquinas/>
- Escritorio: <http://localhost:8080/wp-admin/> · usuario `admin`, contraseña `admin123`
- `npm run dev:parar` lo detiene (los datos se conservan; `docker compose down -v` los borra).

El catálogo de demostración ([tests/datos-demo.php](tests/datos-demo.php)) importa las 42 máquinas del CSV, sube a Block Car, Peppa Bus y Bluey sus imágenes y su GLB de `modelos/`, y añade dos PDF de ejemplo a la Grúa de tren. Se puede ejecutar varias veces sin duplicar nada.

**Pruebas.** Recorren la web como un cliente y el escritorio como un administrador, con Chromium:

```bash
npx playwright install chromium    # la primera vez
npm test                           # o npm run test:publico / npm run test:admin
```

| Web pública ([tests/e2e-publico.mjs](tests/e2e-publico.mjs)) | Escritorio ([tests/e2e-admin.mjs](tests/e2e-admin.mjs)) |
| --- | --- |
| 42 tarjetas, buscador, filtros de categoría y «con 3D» | Listado de máquinas |
| Ficha en ventana: cambia la dirección, carrusel, siguiente/anterior | Vista previa del GLB y aviso de tamaño |
| Modelo 3D a escala real y QR del visor | Guardar consumo, peso y PDF; la API lo refleja |
| Solicitud: bandeja, validación de los 5 campos y envío | 5 categorías, ajustes |
| Página de máquina: 8 datos, JSON-LD, preselección, ficha impresa | Exportar CSV y reimportarlo (42 actualizadas, 0 errores) |
| PDF, página de categoría, API | Solicitudes y filtro «Modelos 3D» de Medios |
| Móvil: sin desplazamiento lateral, ficha y visor del QR | Sin errores de JavaScript ni HTTP |

Las capturas quedan en `tests/capturas/`. Contra otro WordPress (LocalWP, XAMPP, un servidor de pruebas):

```bash
php tests/datos-demo.php /ruta/a/wordpress http://mi-sitio.local
BASE=http://mi-sitio.local WP_USER=admin WP_PASS=admin123 npm test
```

> ⚠️ `datos-demo.php` y `dev/mu-plugins/` son sólo para desarrollo: no los uses en la web real.

**Integración continua.** En cada cambio en `main` y en cada pull request, GitHub Actions ([.github/workflows/ci.yml](.github/workflows/ci.yml)) regenera el zip, comprueba que el subido está al día, levanta WordPress con Docker y pasa todas las pruebas. Las capturas y el zip quedan como artefactos de cada ejecución.

## Prototipo HTML

La maqueta del catálogo que se aprobó antes de programar el plugin, en un único archivo que se abre con doble clic, sin servidor ni conexión:

```bash
npm run prototipo    # → prototipo/catalogo-maquinas.html (≈ 6,5 MB, incluye los 3 modelos)
```

## Cómo está hecho el plugin

- Sin dependencias de otros plugins (ni ACF ni constructores) y sin cargar nada de servidores externos: model-viewer y el generador de QR van dentro del plugin y sólo se descargan cuando se usan.
- Tipos de contenido propios: máquinas (`tn_maquina`), categorías (`tn_categoria`) y solicitudes (`tn_solicitud`, privadas).
- Direcciones: `/maquinas/`, `/maquinas/categoria/<categoría>/`, `/maquinas/<máquina>/` y `/maquinas/<máquina>/visor/` (destino del QR).
- Shortcode `[tecnotron_catalogo]` y `[tecnotron_catalogo categoria="kiddie-rides"]`.
- API pública de sólo lectura: `/wp-json/tecnotron/v1/maquinas`.
- Plantillas sustituibles desde el tema (`<tema>/tecnotron-maquinas/catalogo.php`, `maquina.php`, `visor.php`) y colores con variables CSS `--tnm-*`.
- Filtro `tnm_solicitudes_por_hora` para cambiar el límite antispam del formulario (5 por hora e IP).

El detalle de cada archivo, los datos que guarda cada máquina y la API están en [Estructura técnica](docs/guia.md#estructura-técnica).

## Licencias

- Plugin y herramientas: [GPL-2.0-or-later](LICENSE), como WordPress.
- [model-viewer](https://github.com/google/model-viewer) (Google, Apache-2.0) y [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) (Kazuhiko Arase, MIT): sus licencias van en `tecnotron-maquinas/assets/vendor/`.
- Los modelos 3D, las imágenes y los datos de las máquinas son material de Tecnotron. Los personajes (Peppa Pig, Bluey…) son marcas de sus titulares.
