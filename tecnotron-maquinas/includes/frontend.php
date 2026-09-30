<?php
/**
 * Parte pública: plantillas (catálogo, ficha y visor), shortcode, recursos, API y datos para buscadores.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

/* ---------- plantillas ---------- */

add_filter( 'template_include', 'tnm_template_include', 99 );

/**
 * Elige la plantilla del plugin para el catálogo, las categorías, cada máquina y su visor.
 * Un tema puede sustituirlas copiándolas a su carpeta /tecnotron-maquinas/.
 *
 * @param string $template Plantilla del tema.
 * @return string
 */
function tnm_template_include( $template ) {
	if ( is_singular( TNM_CPT ) && false !== get_query_var( 'visor', false ) ) {
		return tnm_locate_template( 'visor.php' );
	}
	if ( is_singular( TNM_CPT ) ) {
		return tnm_locate_template( 'maquina.php' );
	}
	if ( is_post_type_archive( TNM_CPT ) || is_tax( TNM_TAX ) ) {
		return tnm_locate_template( 'catalogo.php' );
	}
	return $template;
}

/**
 * @param string $name Archivo.
 * @return string
 */
function tnm_locate_template( $name ) {
	$theme = locate_template( 'tecnotron-maquinas/' . $name );
	return $theme ?: TNM_DIR . 'templates/' . $name;
}

/**
 * Cabecera del sitio: tema clásico (header.php) o tema de bloques (parte «header»).
 */
function tnm_page_start() {
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		// Las partes de plantilla se generan antes de wp_head() para que sus estilos lleguen a <head>.
		$GLOBALS['tnm_block_header'] = do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' );
		$GLOBALS['tnm_block_footer'] = do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' );
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'tnm-page' ); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
		<?php
		echo $GLOBALS['tnm_block_header']; // phpcs:ignore
	} else {
		get_header();
	}
	echo '<main id="tnm-main" class="tnm-main">';
}

/**
 * Pie del sitio.
 */
function tnm_page_end() {
	echo '</main>';
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		echo $GLOBALS['tnm_block_footer']; // phpcs:ignore
		echo '</div>';
		wp_footer();
		echo '</body></html>';
	} else {
		get_footer();
	}
}

add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_singular( TNM_CPT ) || is_post_type_archive( TNM_CPT ) || is_tax( TNM_TAX ) ) {
			$classes[] = 'tnm-page';
		}
		return $classes;
	}
);

/* ---------- shortcode: [tecnotron_catalogo] o [tecnotron_catalogo categoria="kiddie-rides"] ---------- */

add_shortcode(
	'tecnotron_catalogo',
	function ( $atts ) {
		$atts = shortcode_atts( array( 'categoria' => '', 'titulo' => '' ), $atts, 'tecnotron_catalogo' );
		tnm_enqueue();
		$args = array( 'categoria' => sanitize_title( $atts['categoria'] ) );
		if ( $atts['titulo'] ) {
			$args['titulo'] = $atts['titulo'];
		}
		return tnm_render_catalogo( $args );
	}
);

/* ---------- recursos ---------- */

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_style( 'tnm', TNM_URL . 'assets/css/tecnotron-maquinas.css', array(), TNM_VERSION );
		wp_register_script( 'tnm', TNM_URL . 'assets/js/tecnotron-maquinas.js', array(), TNM_VERSION, true );
		$icons = array_intersect_key( tnm_icon_paths(), array_flip( array( 'x', 'plus', 'check', 'cube', 'ar', 'qr', 'ruler', 'file' ) ) );
		wp_localize_script(
			'tnm',
			'TNM',
			array(
				'rest'   => esc_url_raw( rest_url( 'tecnotron/v1/' ) ),
				'mv'     => TNM_URL . 'assets/vendor/model-viewer.min.js?ver=' . TNM_MV_VERSION,
				'qr'     => TNM_URL . 'assets/vendor/qrcode.js?ver=' . TNM_QR_VERSION,
				'pdf'    => TNM_URL . 'assets/vendor/ficha-pdf.js?ver=' . TNM_VERSION,
				'base'   => get_post_type_archive_link( TNM_CPT ),
				'site'   => get_bloginfo( 'name' ),
				'icons'  => $icons,
				'hoy'    => wp_date( 'j/n/Y' ),
			)
		);
		if ( is_singular( TNM_CPT ) || is_post_type_archive( TNM_CPT ) || is_tax( TNM_TAX ) ) {
			tnm_enqueue();
		}
	}
);

function tnm_enqueue() {
	wp_enqueue_style( 'tnm' );
	wp_enqueue_script( 'tnm' );
}

/* ---------- API: /wp-json/tecnotron/v1/ ---------- */

add_action(
	'rest_api_init',
	function () {
		// Lista pública de máquinas (para un visor externo, el configurador u otras webs).
		register_rest_route(
			'tecnotron/v1',
			'/maquinas',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$posts = get_posts( array( 'post_type' => TNM_CPT, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
					return array_map( 'tnm_maquina_publica', $posts );
				},
			)
		);
		// HTML de la ficha para abrirla en la ventana del catálogo sin recargar la página.
		register_rest_route(
			'tecnotron/v1',
			'/maquinas/(?P<id>\d+)/ficha',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'validate_callback' => fn( $v ) => is_numeric( $v ) ) ),
				'callback'            => function ( WP_REST_Request $req ) {
					$post = get_post( (int) $req['id'] );
					if ( ! $post || TNM_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
						return new WP_Error( 'tnm_no_existe', 'Máquina no encontrada', array( 'status' => 404 ) );
					}
					$m = tnm_maquina( $post );
					return array( 'html' => tnm_render_ficha( $m, 'ventana' ), 'titulo' => $m['nombre'], 'url' => $m['url'] );
				},
			)
		);
	}
);

/**
 * Datos públicos de una máquina para la API.
 *
 * @param WP_Post $post Máquina.
 * @return array
 */
function tnm_maquina_publica( $post ) {
	$m = tnm_maquina( $post );
	return array(
		'id'           => $m['id'],
		'slug'         => $m['slug'],
		'nombre'       => $m['nombre'],
		'categoria'    => $m['cat']['nombre'],
		'codigo'       => $m['codigo'],
		'ancho_cm'     => $m['ancho'],
		'largo_cm'     => $m['largo'],
		'alto_cm'      => $m['alto'],
		'peso_kg'      => $m['peso'],
		'superficie_m2' => $m['superficie'],
		'consumo'      => $m['consumo'],
		'alimentacion' => $m['alimentacion'],
		'conformidad'  => $m['conformidad'],
		'glb'          => $m['glb'],
		'imagenes'     => array_map( fn( $id ) => wp_get_attachment_image_url( $id, 'large' ), $m['imagenes'] ),
		'fichas'       => $m['fichas'],
		'descripcion'  => tnm_html_a_texto( $post->post_content ),
		'destacada'    => $m['destacada'],
		'vista'        => $m['vista'],
		'orden'        => (int) $post->menu_order,
		'url'          => $m['url'],
		'visor'        => $m['visor'],
	);
}

/* ---------- buscadores y redes: descripción, imagen y datos de producto ---------- */

add_action(
	'wp_head',
	function () {
		if ( ! is_singular( TNM_CPT ) ) {
			return;
		}
		$m    = tnm_maquina( get_queried_object() );
		$desc = wp_trim_words( wp_strip_all_tags( tnm_render_descripcion( get_queried_object() ) ), 30, '…' );
		if ( '' === $desc ) {
			$desc = trim( $m['cat']['singular'] . '. ' . tnm_dims( $m ) );
		}
		$img = $m['imagenes'] ? wp_get_attachment_image_url( $m['imagenes'][0], 'large' ) : '';
		if ( false !== get_query_var( 'visor', false ) ) {
			echo '<meta name="robots" content="noindex">' . "\n";
		}
		// Si hay un plugin de SEO, él se ocupa de la descripción y Open Graph.
		if ( ! defined( 'WPSEO_VERSION' ) && ! class_exists( 'RankMath' ) && ! defined( 'AIOSEO_VERSION' ) ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
			printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $m['nombre'] ) );
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
			printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $m['url'] ) );
			echo '<meta property="og:type" content="product">' . "\n";
			if ( $img ) {
				printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $img ) );
			}
		}
		$ld = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $m['nombre'],
			'description' => $desc,
			'brand'       => array( '@type' => 'Brand', 'name' => get_bloginfo( 'name' ) ),
			'category'    => $m['cat']['nombre'],
			'url'         => $m['url'],
		);
		if ( $m['codigo'] ) {
			$ld['sku'] = $m['codigo'];
		}
		if ( $img ) {
			$ld['image'] = $img;
		}
		if ( $m['tiene_medidas'] ) {
			$ld['width']  = array( '@type' => 'QuantitativeValue', 'value' => $m['ancho'], 'unitCode' => 'CMT' );
			$ld['depth']  = array( '@type' => 'QuantitativeValue', 'value' => $m['largo'], 'unitCode' => 'CMT' );
			$ld['height'] = array( '@type' => 'QuantitativeValue', 'value' => $m['alto'], 'unitCode' => 'CMT' );
		}
		if ( $m['peso'] ) {
			$ld['weight'] = array( '@type' => 'QuantitativeValue', 'value' => $m['peso'], 'unitCode' => 'KGM' );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
	},
	5
);
