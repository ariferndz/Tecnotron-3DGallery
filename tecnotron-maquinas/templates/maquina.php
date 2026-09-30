<?php
/**
 * Página de una máquina: /maquinas/<máquina>/ (enlace para compartir, Google y la vista sin JavaScript).
 * Para cambiarla, copia este archivo a <tu-tema>/tecnotron-maquinas/maquina.php.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

tnm_page_start();
while ( have_posts() ) {
	the_post();
	$tnm_m = tnm_maquina( get_post() );
	?>
	<div class="tnm tnm-single">
		<nav class="tnm-crumbs" aria-label="Ruta">
			<a href="<?php echo esc_url( get_post_type_archive_link( TNM_CPT ) ); ?>"><?php echo tnm_icon( 'left' ); // phpcs:ignore ?>Catálogo</a>
			<span aria-hidden="true">/</span>
			<?php if ( $tnm_m['cat']['id'] ) : ?><a href="<?php echo esc_url( get_term_link( $tnm_m['cat']['id'], TNM_TAX ) ); ?>"><?php echo esc_html( $tnm_m['cat']['nombre'] ); ?></a><?php endif; ?>
		</nav>
		<?php echo tnm_render_ficha( $tnm_m, 'pagina' ); // phpcs:ignore ?>
		<?php echo tnm_render_contacto( array( $tnm_m['id'] ) ); // phpcs:ignore ?>
	</div>
	<?php
}
tnm_page_end();
