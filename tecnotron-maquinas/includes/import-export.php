<?php
/**
 * Importar y exportar el catálogo en CSV (compatible con Excel: «;» y UTF-8).
 * Permite cargar muchas máquinas de una vez y editar datos en bloque.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

/**
 * Columnas del CSV, en orden.
 *
 * @return string[]
 */
function tnm_csv_columnas() {
	return array( 'nombre', 'slug', 'categoria', 'codigo', 'ancho', 'largo', 'alto', 'peso', 'consumo', 'alimentacion', 'conformidad', 'orden', 'destacada', 'estado', 'glb_url', 'descripcion' );
}

/**
 * Importa un CSV.
 *
 * @param string $path          Ruta del archivo.
 * @param bool   $actualizar    Actualizar las máquinas que ya existen (por slug).
 * @param string $estado_nuevas 'publish' o 'draft' para las nuevas sin columna «estado».
 * @return array[] Una fila por máquina: [nombre, resultado, detalle].
 */
function tnm_importar_csv( $path, $actualizar = true, $estado_nuevas = 'publish' ) {
	$out = array();
	$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $raw || '' === trim( $raw ) ) {
		return array( array( '—', 'error', 'El archivo está vacío.' ) );
	}
	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' ); // CSV guardado por Excel en Windows
	}
	$first = strtok( $raw, "\n" );
	$delim = substr_count( $first, ';' ) >= substr_count( $first, ',' ) ? ';' : ',';

	$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore
	fwrite( $fh, $raw ); // phpcs:ignore
	rewind( $fh );
	$header = array_map(
		function ( $h ) {
			return str_replace( ' ', '_', remove_accents( strtolower( trim( $h ) ) ) );
		},
		(array) fgetcsv( $fh, 0, $delim, '"', '\\' )
	);
	if ( ! in_array( 'nombre', $header, true ) ) {
		return array( array( '—', 'error', 'Falta la columna «nombre». Descarga la plantilla y respeta la primera fila.' ) );
	}

	while ( ( $cells = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) !== false ) {
		if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
			continue;
		}
		$row    = array_combine( $header, array_pad( array_slice( $cells, 0, count( $header ) ), count( $header ), '' ) );
		$row    = array_map( 'trim', $row );
		$nombre = sanitize_text_field( $row['nombre'] );
		if ( '' === $nombre ) {
			continue;
		}
		$slug     = sanitize_title( ( $row['slug'] ?? '' ) ?: $nombre );
		$existing = get_page_by_path( $slug, OBJECT, TNM_CPT );
		if ( $existing && ! $actualizar ) {
			$out[] = array( $nombre, 'omitida', 'Ya existe y no se ha marcado «actualizar».' );
			continue;
		}
		$estado = strtolower( remove_accents( $row['estado'] ?? '' ) );
		$status = in_array( $estado, array( 'borrador', 'draft', 'oculta' ), true ) ? 'draft' : ( in_array( $estado, array( 'publicada', 'publish', 'visible' ), true ) ? 'publish' : ( $existing ? $existing->post_status : $estado_nuevas ) );

		$postarr = array(
			'post_type'   => TNM_CPT,
			'post_title'  => $nombre,
			'post_name'   => $slug,
			'post_status' => $status,
		);
		if ( isset( $row['orden'] ) && '' !== $row['orden'] ) {
			$postarr['menu_order'] = (int) $row['orden'];
		}
		if ( isset( $row['descripcion'] ) && '' !== $row['descripcion'] ) {
			$postarr['post_content'] = false === strpos( $row['descripcion'], '<' ) ? tnm_texto_a_html( $row['descripcion'] ) : wp_kses_post( $row['descripcion'] );
		}
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$id            = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $id ) ) {
			$out[] = array( $nombre, 'error', $id->get_error_message() );
			continue;
		}

		foreach ( array( 'codigo', 'consumo', 'alimentacion', 'conformidad' ) as $k ) {
			if ( isset( $row[ $k ] ) && '' !== $row[ $k ] ) {
				update_post_meta( $id, '_tnm_' . $k, sanitize_text_field( $row[ $k ] ) );
			}
		}
		foreach ( array( 'ancho', 'largo', 'alto', 'peso' ) as $k ) {
			if ( isset( $row[ $k ] ) && '' !== tnm_num( $row[ $k ] ) ) {
				update_post_meta( $id, '_tnm_' . $k, tnm_num( $row[ $k ] ) );
			}
		}
		if ( isset( $row['destacada'] ) && '' !== $row['destacada'] ) {
			update_post_meta( $id, '_tnm_destacada', in_array( strtolower( remove_accents( $row['destacada'] ) ), array( '1', 'si', 'x', 'yes', 'true' ), true ) ? 1 : 0 );
		}
		if ( ! empty( $row['glb_url'] ) ) {
			update_post_meta( $id, '_tnm_glb_url', esc_url_raw( $row['glb_url'] ) );
		}
		if ( ! empty( $row['categoria'] ) ) {
			$term = get_term_by( 'name', $row['categoria'], TNM_TAX ) ?: get_term_by( 'slug', sanitize_title( $row['categoria'] ), TNM_TAX );
			if ( ! $term ) {
				$r    = wp_insert_term( sanitize_text_field( $row['categoria'] ), TNM_TAX );
				$term = is_wp_error( $r ) ? null : get_term( $r['term_id'], TNM_TAX );
			}
			if ( $term ) {
				wp_set_object_terms( $id, array( (int) $term->term_id ), TNM_TAX );
			}
		}
		$out[] = array( $nombre, $existing ? 'actualizada' : 'creada', 'draft' === $status ? 'En borrador (no visible)' : 'Publicada' );
	}
	fclose( $fh ); // phpcs:ignore
	return $out;
}

/* ---------- exportar: Máquinas → Importar / exportar → «Descargar CSV» ---------- */

add_action(
	'admin_post_tnm_exportar',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'tnm_exportar' );
		$posts = get_posts( array( 'post_type' => TNM_CPT, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=maquinas-' . gmdate( 'Y-m-d' ) . '.csv' );
		$fh = fopen( 'php://output', 'w' ); // phpcs:ignore
		fwrite( $fh, "\xEF\xBB\xBF" ); // phpcs:ignore -- BOM: Excel abre bien los acentos
		fputcsv( $fh, tnm_csv_columnas(), ';', '"', '\\' );
		foreach ( $posts as $p ) {
			$cat  = tnm_categoria_de( $p->ID );
			$desc = tnm_html_a_texto( $p->post_content );
			fputcsv(
				$fh,
				array(
					$p->post_title,
					$p->post_name,
					$cat['id'] ? $cat['nombre'] : '',
					get_post_meta( $p->ID, '_tnm_codigo', true ),
					str_replace( '.', ',', (string) get_post_meta( $p->ID, '_tnm_ancho', true ) ),
					str_replace( '.', ',', (string) get_post_meta( $p->ID, '_tnm_largo', true ) ),
					str_replace( '.', ',', (string) get_post_meta( $p->ID, '_tnm_alto', true ) ),
					str_replace( '.', ',', (string) get_post_meta( $p->ID, '_tnm_peso', true ) ),
					get_post_meta( $p->ID, '_tnm_consumo', true ),
					get_post_meta( $p->ID, '_tnm_alimentacion', true ),
					get_post_meta( $p->ID, '_tnm_conformidad', true ),
					$p->menu_order,
					get_post_meta( $p->ID, '_tnm_destacada', true ) ? 'sí' : '',
					'publish' === $p->post_status ? 'publicada' : 'borrador',
					get_post_meta( $p->ID, '_tnm_glb_url', true ),
					$desc,
				),
				';',
				'"',
				'\\'
			);
		}
		fclose( $fh ); // phpcs:ignore
		exit;
	}
);
