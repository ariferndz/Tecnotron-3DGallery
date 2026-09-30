<?php
/**
 * Datos y utilidades comunes: ajustes, campos de la ficha, iconos y lectura de una máquina.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

const TNM_CPT       = 'tn_maquina';
const TNM_TAX       = 'tn_categoria';
const TNM_SOL       = 'tn_solicitud';
const TNM_OPT       = 'tnm_ajustes';

/**
 * Ajustes del plugin con sus valores por defecto.
 *
 * @param string|null $key Clave concreta o null para todos.
 * @return mixed
 */
function tnm_opt( $key = null ) {
	$defaults = array(
		'slug'               => 'maquinas',
		'titulo'             => 'Nuestras máquinas',
		'intro'              => 'Kiddie rides, carruseles, grúas y juegos de habilidad. Consulta las características, descarga la ficha técnica y pide presupuesto sin compromiso.',
		'comunes'            => "Atención al cliente: 24/7/365\nServicio técnico: Propio en toda España",
		'vista_ficha'        => '3d',
		'email'              => get_option( 'admin_email' ),
		'guardar'            => 1,
		'form_shortcode'     => '',
		'privacidad_url'     => '',
		'contacto_titulo'    => 'Contáctanos',
		'contacto_texto'     => 'Cuéntanos qué espacio tienes y qué máquinas te interesan. Te enviamos la ficha técnica y un presupuesto a medida.',
		// Ficha técnica en PDF
		'pdf_color'          => '#7c3aed',
		'pdf_logo'           => 0,
		'pdf_logo_url'       => 'https://www.tecnotron.es/img/logo.png',
		'pdf_telefono'       => '',
		'pdf_email'          => '',
		'pdf_web'            => '',
		'pdf_direccion'      => '',
	);
	$saved = get_option( TNM_OPT, array() );
	$all   = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	return null === $key ? $all : ( $all[ $key ] ?? null );
}

/**
 * Campos de la ficha técnica, en el orden en que se muestran.
 * Una sola definición para el formulario del panel, la ficha pública, el CSV y la API.
 *
 * @return array<string,array>
 */
function tnm_campos() {
	return array(
		'codigo'       => array( 'label' => 'Código', 'icon' => 'file', 'type' => 'text', 'ph' => 'p. ej. KQ546' ),
		'consumo'      => array( 'label' => 'Consumo', 'icon' => 'zap', 'type' => 'text', 'ph' => 'p. ej. 0,3 kW' ),
		'alimentacion' => array( 'label' => 'Alimentación', 'icon' => 'plug', 'type' => 'text', 'ph' => 'p. ej. 220/240 V 50 Hz' ),
		'conformidad'  => array( 'label' => 'Conformidad', 'icon' => 'shield', 'type' => 'text', 'ph' => 'p. ej. CE' ),
		'peso'         => array( 'label' => 'Peso', 'icon' => 'weight', 'type' => 'number', 'unit' => 'kg', 'ph' => 'p. ej. 134' ),
		'ancho'        => array( 'label' => 'Ancho', 'type' => 'number', 'unit' => 'cm', 'ph' => 'p. ej. 141' ),
		'largo'        => array( 'label' => 'Largo', 'type' => 'number', 'unit' => 'cm', 'ph' => 'p. ej. 72,5' ),
		'alto'         => array( 'label' => 'Alto', 'type' => 'number', 'unit' => 'cm', 'ph' => 'p. ej. 156' ),
		'superficie'   => array( 'label' => 'Superficie ocupada', 'type' => 'number', 'unit' => 'm²', 'ph' => 'Automática' ),
	);
}

/**
 * Convierte «72,5» o «72.5» en número; vacío si no lo es.
 *
 * @param mixed $v Valor.
 * @return float|string
 */
function tnm_num( $v ) {
	$v = str_replace( array( ' ', ',' ), array( '', '.' ), (string) $v );
	return is_numeric( $v ) ? (float) $v : '';
}

/**
 * Número en formato español: 72,5 · 1.250 · 1,02.
 *
 * @param float $n        Número.
 * @param int   $decimals Decimales máximos.
 * @return string
 */
function tnm_fmt( $n, $decimals = 1 ) {
	$s = number_format( (float) $n, $decimals, ',', '.' );
	return $decimals ? rtrim( rtrim( $s, '0' ), ',' ) : $s;
}

/**
 * Todos los datos de una máquina listos para mostrar.
 *
 * @param WP_Post|int $post Máquina.
 * @return array
 */
function tnm_maquina( $post ) {
	$post = get_post( $post );
	$m    = array(
		'id'     => $post->ID,
		'nombre' => get_the_title( $post ),
		'slug'   => $post->post_name,
		'url'    => get_permalink( $post ),
		'visor'  => trailingslashit( get_permalink( $post ) ) . 'visor/',
	);
	foreach ( array_keys( tnm_campos() ) as $k ) {
		$m[ $k ] = get_post_meta( $post->ID, '_tnm_' . $k, true );
	}
	foreach ( array( 'ancho', 'largo', 'alto', 'peso', 'superficie' ) as $k ) {
		$m[ $k ] = tnm_num( $m[ $k ] );
	}
	$m['tiene_medidas'] = $m['ancho'] && $m['largo'] && $m['alto'];
	if ( ! $m['superficie'] && $m['ancho'] && $m['largo'] ) {
		$m['superficie'] = round( $m['ancho'] * $m['largo'] / 10000, 2 );
	}
	$m['destacada'] = (bool) get_post_meta( $post->ID, '_tnm_destacada', true );
	$m['glb']       = tnm_glb_url( $post->ID );
	$m['cat']       = tnm_categoria_de( $post->ID );
	$m['imagenes']  = tnm_imagenes( $post->ID );
	$m['fichas']    = tnm_fichas( $post->ID );
	return $m;
}

/**
 * URL del modelo GLB: archivo de la biblioteca de medios o dirección externa.
 *
 * @param int $id Máquina.
 * @return string
 */
function tnm_glb_url( $id ) {
	$att = (int) get_post_meta( $id, '_tnm_glb_id', true );
	if ( $att && ( $url = wp_get_attachment_url( $att ) ) ) {
		return $url;
	}
	return esc_url_raw( (string) get_post_meta( $id, '_tnm_glb_url', true ) );
}

/**
 * Imágenes del carrusel: la imagen destacada primero y después la galería, sin repetir.
 *
 * @param int $id Máquina.
 * @return int[] Identificadores de adjunto.
 */
function tnm_imagenes( $id ) {
	$ids   = array();
	$thumb = (int) get_post_thumbnail_id( $id );
	if ( $thumb ) {
		$ids[] = $thumb;
	}
	$gal = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, '_tnm_galeria', true ) ) ) );
	foreach ( $gal as $g ) {
		if ( ! in_array( $g, $ids, true ) && wp_attachment_is_image( $g ) ) {
			$ids[] = $g;
		}
	}
	return $ids;
}

/**
 * Fichas PDF: [{label, url}].
 *
 * @param int $id Máquina.
 * @return array
 */
function tnm_fichas( $id ) {
	$rows = get_post_meta( $id, '_tnm_fichas', true );
	$out  = array();
	foreach ( is_array( $rows ) ? $rows : array() as $r ) {
		$url = ! empty( $r['id'] ) ? wp_get_attachment_url( (int) $r['id'] ) : ( $r['url'] ?? '' );
		if ( $url ) {
			$out[] = array( 'label' => $r['label'] ?: 'Ficha técnica', 'url' => $url );
		}
	}
	return $out;
}

/**
 * Categoría principal de una máquina con su color, icono y nombre en singular.
 *
 * @param int $id Máquina.
 * @return array
 */
function tnm_categoria_de( $id ) {
	$terms = get_the_terms( $id, TNM_TAX );
	$t     = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
	return tnm_categoria_info( $t );
}

/**
 * @param WP_Term|null $t Término.
 * @return array
 */
function tnm_categoria_info( $t ) {
	if ( ! $t ) {
		return array( 'id' => 0, 'slug' => 'sin-categoria', 'nombre' => 'Máquinas', 'singular' => 'Máquina', 'color' => '#a78bfa', 'icono' => 'cube' );
	}
	$color = get_term_meta( $t->term_id, 'tnm_color', true );
	return array(
		'id'       => $t->term_id,
		'slug'     => $t->slug,
		'nombre'   => $t->name,
		'singular' => get_term_meta( $t->term_id, 'tnm_singular', true ) ?: $t->name,
		'color'    => sanitize_hex_color( $color ) ?: '#a78bfa',
		'icono'    => get_term_meta( $t->term_id, 'tnm_icono', true ) ?: 'cube',
	);
}

/**
 * Categorías de partida (se crean al activar si no existen).
 */
function tnm_crear_categorias_iniciales() {
	$cats = array(
		array( 'Kiddie rides', 'kiddie-rides', 'Kiddie ride', '#f59e0b', 'car' ),
		array( 'Carruseles', 'carruseles', 'Carrusel', '#ec4899', 'carousel' ),
		array( 'Grúas y premios', 'gruas-y-premios', 'Grúa / premios', '#22d3ee', 'claw' ),
		array( 'Habilidad y deporte', 'habilidad-y-deporte', 'Habilidad y deporte', '#84cc16', 'target' ),
		array( 'Simuladores y arcade', 'simuladores-y-arcade', 'Simulador / arcade', '#a78bfa', 'wheel' ),
	);
	foreach ( $cats as $i => list( $name, $slug, $singular, $color, $icon ) ) {
		if ( term_exists( $slug, TNM_TAX ) ) {
			continue;
		}
		$r = wp_insert_term( $name, TNM_TAX, array( 'slug' => $slug ) );
		if ( ! is_wp_error( $r ) ) {
			update_term_meta( $r['term_id'], 'tnm_singular', $singular );
			update_term_meta( $r['term_id'], 'tnm_color', $color );
			update_term_meta( $r['term_id'], 'tnm_icono', $icon );
			update_term_meta( $r['term_id'], 'tnm_orden', $i + 1 );
		}
	}
}

/**
 * Categorías ordenadas por su campo «Orden».
 *
 * @return WP_Term[]
 */
function tnm_categorias() {
	$terms = get_terms( array( 'taxonomy' => TNM_TAX, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	usort(
		$terms,
		function ( $a, $b ) {
			return ( (int) get_term_meta( $a->term_id, 'tnm_orden', true ) <=> (int) get_term_meta( $b->term_id, 'tnm_orden', true ) ) ?: strcasecmp( $a->name, $b->name );
		}
	);
	return $terms;
}

/**
 * Datos comunes a todas las fichas («Etiqueta: valor» por línea en Ajustes).
 *
 * @return array[] [label, value, icon]
 */
function tnm_datos_comunes() {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) tnm_opt( 'comunes' ) ) as $line ) {
		if ( false === strpos( $line, ':' ) ) {
			continue;
		}
		list( $label, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
		if ( '' === $label || '' === $value ) {
			continue;
		}
		$l    = remove_accents( strtolower( $label ) );
		$icon = false !== strpos( $l, 'atencion' ) ? 'headset' : ( false !== strpos( $l, 'servicio' ) || false !== strpos( $l, 'tecnico' ) ? 'wrench' : ( false !== strpos( $l, 'garant' ) ? 'shield' : 'check' ) );
		$out[] = array( $label, $value, $icon );
	}
	return $out;
}

/**
 * Iconos (trazo, 24 × 24).
 *
 * @return array<string,string>
 */
function tnm_icon_paths() {
	return array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'check'    => '<path d="m5 12.5 4.5 4.5L19 7"/>',
		'cube'     => '<path d="M12 2.8 20 7.3v9.4l-8 4.5-8-4.5V7.3z"/><path d="m4 7.3 8 4.5 8-4.5M12 11.8v9.4"/>',
		'ar'       => '<path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16"/><path d="m12 7 4 2.3v4.4L12 16l-4-2.3V9.3z"/>',
		'ruler'    => '<path d="M3 16.5 16.5 3l4.5 4.5L7.5 21z"/><path d="m7 12.5 2 2M10 9.5l2 2M13 6.5l2 2"/>',
		'download' => '<path d="M12 4v11M7 10.5l5 5 5-5M5 20h14"/>',
		'share'    => '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="m8.2 10.8 7.6-4M8.2 13.2l7.6 4"/>',
		'left'     => '<path d="m15 18-6-6 6-6"/>',
		'right'    => '<path d="m9 18 6-6-6-6"/>',
		'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
		'qr'       => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM18 14h2M14 18h2"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
		'phone'    => '<path d="M5 3h3.5l1.5 4.5-2 1.5a12 12 0 0 0 6 6l1.5-2L20 14.5V18a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
		'pin'      => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'msg'      => '<path d="M4 5h16v11H9l-5 4z"/>',
		'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
		'zap'      => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
		'plug'     => '<path d="M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0zM12 17v5"/>',
		'shield'   => '<path d="M12 3 5 6v5c0 4.5 3 8.5 7 10 4-1.5 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-4"/>',
		'weight'   => '<path d="M6.5 8h11l2.5 12H4z"/><circle cx="12" cy="5.5" r="2.5"/>',
		'box'      => '<path d="M12 3 20 7.5v9L12 21l-8-4.5v-9z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
		'area'     => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 12h16M12 4v16" stroke-dasharray="2 2.5"/>',
		'headset'  => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/><path d="M19 20a3 3 0 0 1-3 2h-2"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'wrench'   => '<path d="M14.7 6.3a4 4 0 0 1 5-1l-3 3 .5 2 2 .5 3-3a4 4 0 0 1-5.3 5.3L9.5 20.5a2.1 2.1 0 0 1-3-3l7.4-7.4a4 4 0 0 1 .8-3.8z"/>',
		'car'      => '<path d="M3 16v-3l2.2-5A2 2 0 0 1 7 7h10a2 2 0 0 1 1.8 1l2.2 5v3z"/><path d="M3 13h18"/><circle cx="7.5" cy="17" r="2"/><circle cx="16.5" cy="17" r="2"/>',
		'carousel' => '<path d="M3 9 12 3l9 6z"/><path d="M5 9v10M12 9v10M19 9v10M3 20h18"/><circle cx="8.5" cy="14" r="1.6"/><circle cx="15.5" cy="12.5" r="1.6"/>',
		'claw'     => '<path d="M4 3h16M12 3v7M8 10h8"/><path d="M9 10l-2.5 5 2.5 2.5M15 10l2.5 5-2.5 2.5"/><path d="M4 21h16"/>',
		'target'   => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1" fill="currentColor"/>',
		'wheel'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="m3.6 10.4 6 1.1M20.4 10.4l-6 1.1M12 14.5V21"/>',
	);
}

/**
 * Iconos que se pueden elegir para una categoría.
 *
 * @return array<string,string>
 */
function tnm_iconos_categoria() {
	return array( 'car' => 'Vehículo', 'carousel' => 'Carrusel', 'claw' => 'Grúa', 'target' => 'Diana', 'wheel' => 'Volante', 'cube' => 'Cubo' );
}

/**
 * SVG de un icono.
 *
 * @param string $name Nombre.
 * @param string $cls  Clases extra.
 * @return string
 */
function tnm_icon( $name, $cls = '' ) {
	$p = tnm_icon_paths();
	return '<svg class="tnm-ic ' . esc_attr( $cls ) . '" viewBox="0 0 24 24" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
}
