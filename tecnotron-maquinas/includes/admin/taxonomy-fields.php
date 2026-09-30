<?php
/**
 * Campos de cada categoría: nombre en singular, color, icono y orden.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param WP_Term|null $term Categoría (null al crear).
 * @param bool         $edit Formulario de edición (tabla) o de alta (divs).
 */
function tnm_term_fields( $term = null, $edit = false ) {
	$get   = fn( $k, $d = '' ) => $term ? ( get_term_meta( $term->term_id, $k, true ) ?: $d ) : $d;
	$icon  = $get( 'tnm_icono', 'cube' );
	$field = function ( $id, $label, $html, $help ) use ( $edit ) {
		if ( $edit ) {
			echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>' . $html . '<p class="description">' . esc_html( $help ) . '</p></td></tr>'; // phpcs:ignore
		} else {
			echo '<div class="form-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>' . $html . '<p>' . esc_html( $help ) . '</p></div>'; // phpcs:ignore
		}
	};
	wp_nonce_field( 'tnm_term', 'tnm_term_nonce' );
	$field( 'tnm_singular', 'Nombre en singular', '<input type="text" id="tnm_singular" name="tnm_singular" value="' . esc_attr( $get( 'tnm_singular' ) ) . '" placeholder="Kiddie ride">', 'Se muestra sobre el nombre de cada máquina («KIDDIE RIDE»).' );
	$field( 'tnm_color', 'Color', '<input type="text" id="tnm_color" name="tnm_color" class="tnm-color" value="' . esc_attr( $get( 'tnm_color', '#a78bfa' ) ) . '">', 'Color del punto de la categoría, del rótulo y del dibujo de dimensiones.' );
	$opts = '';
	foreach ( tnm_iconos_categoria() as $k => $label ) {
		$opts .= '<label class="tnm-icon-opt"><input type="radio" name="tnm_icono" value="' . esc_attr( $k ) . '" ' . checked( $icon, $k, false ) . '>' . tnm_icon( $k ) . '<span>' . esc_html( $label ) . '</span></label>';
	}
	$field( 'tnm_icono', 'Icono', '<div class="tnm-icon-opts">' . $opts . '</div>', 'Se usa cuando una máquina aún no tiene foto.' );
	$field( 'tnm_orden', 'Orden', '<input type="number" id="tnm_orden" name="tnm_orden" value="' . esc_attr( $get( 'tnm_orden' ) ) . '" min="0" step="1" style="width:90px">', 'Posición en la fila de categorías del catálogo (1 = primera).' );
}

add_action( TNM_TAX . '_add_form_fields', fn() => tnm_term_fields( null, false ) );
add_action( TNM_TAX . '_edit_form_fields', fn( $term ) => tnm_term_fields( $term, true ) );

$tnm_save_term = function ( $term_id ) {
	if ( ! isset( $_POST['tnm_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tnm_term_nonce'] ) ), 'tnm_term' ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	update_term_meta( $term_id, 'tnm_singular', sanitize_text_field( wp_unslash( $_POST['tnm_singular'] ?? '' ) ) );
	update_term_meta( $term_id, 'tnm_color', sanitize_hex_color( wp_unslash( $_POST['tnm_color'] ?? '' ) ) ?: '#a78bfa' );
	$icon = sanitize_key( wp_unslash( $_POST['tnm_icono'] ?? 'cube' ) );
	update_term_meta( $term_id, 'tnm_icono', array_key_exists( $icon, tnm_iconos_categoria() ) ? $icon : 'cube' );
	update_term_meta( $term_id, 'tnm_orden', absint( $_POST['tnm_orden'] ?? 0 ) );
};
add_action( 'created_' . TNM_TAX, $tnm_save_term );
add_action( 'edited_' . TNM_TAX, $tnm_save_term );

add_filter(
	'manage_edit-' . TNM_TAX . '_columns',
	function ( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'name' === $k ) {
				$new['tnm_look'] = '';
			}
			$new[ $k ] = $v;
		}
		unset( $new['description'] );
		$new['tnm_orden'] = 'Orden';
		return $new;
	}
);
add_filter(
	'manage_' . TNM_TAX . '_custom_column',
	function ( $out, $col, $term_id ) {
		if ( 'tnm_look' === $col ) {
			$c = tnm_categoria_info( get_term( $term_id ) );
			return '<span class="tnm-term-look" style="--c:' . esc_attr( $c['color'] ) . '">' . tnm_icon( $c['icono'] ) . '</span>';
		}
		if ( 'tnm_orden' === $col ) {
			return esc_html( get_term_meta( $term_id, 'tnm_orden', true ) );
		}
		return $out;
	},
	10,
	3
);
