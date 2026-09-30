<?php
/**
 * Plugin Name:       Tecnotron Máquinas
 * Description:       Catálogo de máquinas con ficha técnica, galería, visor 3D con realidad aumentada, fichas PDF y solicitudes de presupuesto.
 * Version:           1.6.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            FastCore para Tecnotron
 * License:           GPL-2.0-or-later
 * Text Domain:       tecnotron-maquinas
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

define( 'TNM_VERSION', '1.6.0' );
define( 'TNM_FILE', __FILE__ );
define( 'TNM_DIR', plugin_dir_path( __FILE__ ) );
define( 'TNM_URL', plugin_dir_url( __FILE__ ) );
// Versiones de las librerías incluidas en assets/vendor/ (las comprueba scripts/build.mjs).
define( 'TNM_MV_VERSION', '4.3.1' );
define( 'TNM_QR_VERSION', '1.5.2' );
define( 'TNM_THREE_VERSION', '0.183.2' ); // Dentro de assets/vendor/obj-a-glb.js (conversor OBJ → GLB).
define( 'TNM_PDFLIB_VERSION', '1.17.1' ); // Dentro de assets/vendor/ficha-pdf.js (ficha técnica en PDF).

require_once TNM_DIR . 'includes/helpers.php';
require_once TNM_DIR . 'includes/post-types.php';
require_once TNM_DIR . 'includes/media.php';
require_once TNM_DIR . 'includes/render.php';
require_once TNM_DIR . 'includes/frontend.php';
require_once TNM_DIR . 'includes/solicitudes.php';
require_once TNM_DIR . 'includes/import-export.php';
require_once TNM_DIR . 'includes/sincronizacion.php';

if ( is_admin() ) {
	require_once TNM_DIR . 'includes/admin/meta-boxes.php';
	require_once TNM_DIR . 'includes/admin/taxonomy-fields.php';
	require_once TNM_DIR . 'includes/admin/settings.php';
	require_once TNM_DIR . 'includes/admin/columns.php';
	require_once TNM_DIR . 'includes/admin/import-page.php';
	require_once TNM_DIR . 'includes/admin/sincronizacion.php';
}

/**
 * Activación: registra tipos y URL, crea las categorías de partida y refresca los enlaces permanentes.
 */
function tnm_activate() {
	tnm_register_types();
	tnm_crear_categorias_iniciales();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tnm_activate' );

/**
 * Desactivación: deja de sincronizar con Plataformas y refresca los enlaces permanentes.
 */
function tnm_deactivate() {
	wp_clear_scheduled_hook( 'tnm_sincronizar' );
	wp_clear_scheduled_hook( 'tnm_sincronizar_ya' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tnm_deactivate' );
