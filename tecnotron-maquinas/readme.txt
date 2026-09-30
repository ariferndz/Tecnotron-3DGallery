=== Tecnotron Máquinas ===
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Catálogo de máquinas con ficha técnica, carrusel de imágenes, visor 3D con realidad aumentada (model-viewer), QR, fichas PDF y solicitudes de presupuesto.

== Instalación ==
1. Plugins → Añadir nuevo → Subir plugin → tecnotron-maquinas.zip → Instalar → Activar.
2. Ajustes → Enlaces permanentes → Guardar (sólo si /maquinas/ diera error 404).
3. Máquinas → Importar / exportar → «Crear el catálogo inicial» (opcional).
4. Máquinas → Ajustes: correo para las solicitudes y datos comunes.

== Direcciones ==
* /maquinas/ — catálogo
* /maquinas/categoria/<categoría>/ — una categoría
* /maquinas/<máquina>/ — ficha
* /maquinas/<máquina>/visor/ — visor 3D a pantalla completa (destino del QR)
* /wp-json/tecnotron/v1/maquinas — lista pública en JSON
* Shortcode: [tecnotron_catalogo] o [tecnotron_catalogo categoria="kiddie-rides"]

== Personalizar ==
* Plantillas: copia templates/catalogo.php, maquina.php o visor.php a <tema>/tecnotron-maquinas/.
* Colores: redefine las variables --tnm-* de .tnm en el CSS adicional del tema.

== Terceros ==
* model-viewer 4.3.1 (Google, Apache-2.0): assets/vendor/model-viewer.min.js
* qrcode-generator 1.5.2 (Kazuhiko Arase, MIT): assets/vendor/qrcode.js

== Datos ==
Desactivar o borrar el plugin no borra máquinas, categorías, imágenes ni solicitudes.
