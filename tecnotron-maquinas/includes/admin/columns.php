<?php
/**
 * Listado de máquinas en el panel: foto, medidas, 3D, destacada y orden.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'manage_' . TNM_CPT . '_posts_columns',
	function ( $cols ) {
		$new = array( 'cb' => $cols['cb'], 'tnm_img' => '' );
		foreach ( $cols as $k => $v ) {
			if ( 'cb' !== $k ) {
				$new[ $k ] = $v;
			}
			if ( 'title' === $k ) {
				$new['tnm_dims']  = 'Medidas (cm)';
				$new['tnm_3d']    = '3D';
				$new['tnm_orden'] = 'Orden';
			}
		}
		return $new;
	}
);

add_action(
	'manage_' . TNM_CPT . '_posts_custom_column',
	function ( $col, $id ) {
		switch ( $col ) {
			case 'tnm_img':
				echo has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, array( 56, 56 ) ) : '<span class="tnm-noimg-admin">—</span>';
				break;
			case 'tnm_dims':
				$m = tnm_maquina( $id );
				echo esc_html( tnm_dims( $m ) ?: '—' );
				echo $m['destacada'] ? ' <span class="tnm-flag">Destacada</span>' : '';
				break;
			case 'tnm_3d':
				echo tnm_glb_url( $id ) ? '<span class="tnm-yes" title="Tiene modelo GLB">✔</span>' : '—';
				break;
			case 'tnm_orden':
				echo (int) get_post_field( 'menu_order', $id );
				break;
		}
	},
	10,
	2
);

add_filter(
	'manage_edit-' . TNM_CPT . '_sortable_columns',
	function ( $cols ) {
		$cols['tnm_orden'] = 'menu_order';
		return $cols;
	}
);
