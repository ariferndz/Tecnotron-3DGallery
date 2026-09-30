<?php
/**
 * Catálogo: /maquinas/ y /maquinas/categoria/<categoría>/.
 * Para cambiarla, copia este archivo a <tu-tema>/tecnotron-maquinas/catalogo.php.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

tnm_page_start();
$tnm_args = array();
if ( is_tax( TNM_TAX ) ) {
	$tnm_term = get_queried_object();
	$tnm_args = array( 'categoria' => $tnm_term->slug, 'titulo' => $tnm_term->name );
}
echo tnm_render_catalogo( $tnm_args ); // phpcs:ignore WordPress.Security.EscapeOutput
tnm_page_end();
