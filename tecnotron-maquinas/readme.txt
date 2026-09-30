=== Tecnotron Máquinas ===
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.3.0
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
* three.js 0.183.2 (MIT): dentro de assets/vendor/obj-a-glb.js, el conversor de OBJ a GLB del escritorio
* pdf-lib 1.17.1 (MIT): dentro de assets/vendor/ficha-pdf.js, la ficha técnica en PDF

== Cambios ==
= 1.3.0 =
* Temas con cabecera fija (Impreza en tecnotron.es y cualquier otro): la ficha y el catálogo empiezan debajo de la cabecera, y la barra de filtros y los anclajes (#contacto) se quedan justo debajo al bajar.
* Ficha técnica en PDF: logo de Tecnotron por defecto (Ajustes → dirección del logo), sobre placa blanca de esquinas redondeadas; los logos blancos se detectan y van sin placa.

= 1.2.0 =
* Ficha técnica en PDF de verdad: dos páginas A4 con diseño propio (fondo, franjas, portada con foto y detalle, características con iconos y dibujo de dimensiones) que se descarga al momento. Color, logo y datos de contacto en Ajustes.
* La ficha abre en «3D · AR» cuando la máquina tiene modelo, con «Imágenes» al lado; sin modelo sólo aparece «Imágenes».
* Descargas: el icono ya no se estira y el título tiene todo el ancho.

= 1.1.0 =
* El catálogo se ve igual con cualquier tema: botones, buscador, campos, listas e imágenes ya no heredan estilos del tema.
* Las píldoras de datos del catálogo tienen todas la misma altura.
* Pantalla de la máquina: panel «Contenido de la ficha» con lo que falta (imagen principal, galería, datos, modelo 3D, PDF) y enlaces a cada caja.
* Las cajas de la máquina se muestran siempre: editor clásico aunque otro plugin active el de bloques, sin «Editar con Elementor» y con «Imagen principal» aunque el tema no la declare.
* Modelo 3D: se puede subir un OBJ (con su .mtl y texturas); se convierte a GLB en el navegador y se escala a las medidas de la ficha.
* Listado de máquinas: columna «Contenido» con lo que tiene cada una.
* Filtro tnm_solicitudes_por_hora para cambiar el límite antispam del formulario.

= 1.0.0 =
* Primera versión.

== Datos ==
Desactivar o borrar el plugin no borra máquinas, categorías, imágenes ni solicitudes.
