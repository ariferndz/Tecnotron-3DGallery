<?php
/**
 * Listado de máquinas en el panel: foto, medidas, contenido de la ficha, destacada y orden.
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
				$new['tnm_contenido'] = 'Contenido de la ficha';
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
			case 'tnm_contenido':
				$edit = get_edit_post_link( $id );
				foreach ( tnm_estado_ficha( $id ) as $k => $e ) {
					$corto = array( 'foto' => 'Foto', 'galeria' => 'Galería', 'datos' => 'Datos', 'modelo' => '3D', 'pdf' => 'PDF' )[ $k ];
					printf( '<a class="tnm-b %s" href="%s#%s" title="%s">%s</a> ', $e['ok'] ? 'tnm-b-ok' : 'tnm-b-falta', esc_url( $edit ), esc_attr( $e['caja'] ), esc_attr( $e['texto'] ), esc_html( $corto ) );
				}
				break;
			case 'tnm_orden':
				echo (int) get_post_field( 'menu_order', $id );
				break;
		}
	},
	10,
	2
);

// Enlace directo a las cajas de imágenes, ficha y 3D (la «Edición rápida» no las tiene).
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( TNM_CPT === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
			$actions = array( 'tnm_editar' => sprintf( '<a href="%s#tnm-contenido">Imágenes, ficha y 3D</a>', esc_url( get_edit_post_link( $post->ID ) ) ) ) + $actions;
		}
		return $actions;
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
