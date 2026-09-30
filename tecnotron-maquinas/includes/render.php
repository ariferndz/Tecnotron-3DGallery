<?php
/**
 * HTML del catálogo, las tarjetas, la ficha (carrusel, características, dimensiones, descargas),
 * el formulario de presupuesto y el visor 3D.
 *
 * Los mismos fragmentos sirven para la página de cada máquina, la ventana del catálogo y la API.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

/**
 * «141 × 72,5 × 156 cm»
 *
 * @param array $m Máquina.
 * @return string
 */
function tnm_dims( $m ) {
	return $m['tiene_medidas'] ? tnm_fmt( $m['ancho'] ) . ' × ' . tnm_fmt( $m['largo'] ) . ' × ' . tnm_fmt( $m['alto'] ) . ' cm' : '';
}

/**
 * Icono de la categoría cuando no hay foto.
 *
 * @param array $m Máquina.
 * @return string
 */
function tnm_placeholder( $m ) {
	return '<span class="tnm-ph" aria-hidden="true">' . tnm_icon( $m['cat']['icono'] ) . '</span>';
}

/* =====================================================================
   Catálogo
   ===================================================================== */

/**
 * Catálogo completo: cabecera, filtros, tarjetas, formulario, bandeja de solicitud y ventana de ficha.
 *
 * @param array $args ['categoria' => slug para limitar, 'titulo' => texto].
 * @return string
 */
function tnm_render_catalogo( $args = array() ) {
	$q = array(
		'post_type'      => TNM_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 500,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
	);
	if ( ! empty( $args['categoria'] ) ) {
		$q['tax_query'] = array( array( 'taxonomy' => TNM_TAX, 'field' => 'slug', 'terms' => $args['categoria'] ) );
	}
	$posts    = get_posts( $q );
	$maquinas = array_map( 'tnm_maquina', $posts );
	// Destacadas delante, conservando el orden manual del resto
	usort(
		$maquinas,
		function ( $a, $b ) {
			return (int) $b['destacada'] - (int) $a['destacada'];
		}
	);
	$con3d  = count( array_filter( $maquinas, fn( $m ) => (bool) $m['glb'] ) );
	$counts = array();
	foreach ( $maquinas as $m ) {
		$counts[ $m['cat']['slug'] ] = ( $counts[ $m['cat']['slug'] ] ?? 0 ) + 1;
	}
	$titulo = $args['titulo'] ?? tnm_opt( 'titulo' );

	ob_start();
	?>
	<div class="tnm tnm-catalogo" data-tnm-catalogo>
		<header class="tnm-hero">
			<p class="tnm-eyebrow">Productos</p>
			<h1 class="tnm-hero-title"><?php echo esc_html( $titulo ); ?></h1>
			<?php if ( tnm_opt( 'intro' ) ) : ?><p class="tnm-lead"><?php echo esc_html( tnm_opt( 'intro' ) ); ?></p><?php endif; ?>
			<ul class="tnm-stats">
				<li><b><?php echo (int) count( $maquinas ); ?></b> máquinas</li>
				<?php if ( $con3d ) : ?><li><?php echo tnm_icon( 'ar' ); ?><b><?php echo (int) $con3d; ?></b> en 3D con realidad aumentada</li><?php endif; ?>
				<?php foreach ( tnm_datos_comunes() as list( $label, $value, $icon ) ) : ?>
					<li><?php echo tnm_icon( $icon ); ?><?php echo esc_html( $label . ' ' . $value ); ?></li>
				<?php endforeach; ?>
			</ul>
		</header>

		<div class="tnm-toolbar">
			<div class="tnm-tb-row">
				<label class="tnm-search"><?php echo tnm_icon( 'search' ); ?><span class="tnm-sr">Buscar máquina</span><input type="search" data-tnm-q placeholder="Buscar máquina o código…" autocomplete="off"></label>
				<?php if ( $con3d ) : ?><button type="button" class="tnm-chip-btn" data-tnm-only3d aria-pressed="false"><?php echo tnm_icon( 'cube' ); ?>Con 3D</button><?php endif; ?>
				<label class="tnm-sort"><span class="tnm-sr">Ordenar</span>
					<select data-tnm-sort><option value="orden">Destacadas</option><option value="az">A–Z</option><option value="small">Más compactas</option><option value="big">Más grandes</option></select>
				</label>
			</div>
			<?php if ( empty( $args['categoria'] ) ) : ?>
			<div class="tnm-cats" aria-label="Categorías">
				<button type="button" class="tnm-chip" data-tnm-cat="all" aria-pressed="true">Todas <i><?php echo (int) count( $maquinas ); ?></i></button>
				<?php
				foreach ( tnm_categorias() as $t ) :
					if ( empty( $counts[ $t->slug ] ) ) {
						continue;
					}
					$c = tnm_categoria_info( $t );
					?>
					<button type="button" class="tnm-chip" data-tnm-cat="<?php echo esc_attr( $t->slug ); ?>" aria-pressed="false" style="--c:<?php echo esc_attr( $c['color'] ); ?>"><span class="tnm-dot"></span><?php echo esc_html( $t->name ); ?> <i><?php echo (int) $counts[ $t->slug ]; ?></i></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<div class="tnm-results"><p class="tnm-count" data-tnm-count aria-live="polite"><b><?php echo (int) count( $maquinas ); ?></b> máquinas</p></div>
		<div class="tnm-grid" data-tnm-grid>
			<?php
			foreach ( $maquinas as $i => $m ) {
				echo tnm_render_card( $m, $i ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML escapado dentro.
			}
			?>
			<div class="tnm-empty" data-tnm-empty hidden><b>No hay máquinas con esos filtros</b>Prueba con otra búsqueda o categoría.</div>
		</div>

		<?php echo tnm_render_contacto(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<div class="tnm-tray" data-tnm-tray hidden>
			<span class="tnm-tray-n" data-tnm-tray-n>0</span>
			<span class="tnm-tray-txt" data-tnm-tray-txt></span>
			<a class="tnm-btn tnm-primary tnm-sm" href="#tnm-contacto">Pedir presupuesto</a>
		</div>

		<dialog class="tnm tnm-modal" data-tnm-modal aria-label="Ficha de la máquina">
			<div class="tnm-m-top">
				<button type="button" class="tnm-icon-btn" data-tnm-prev aria-label="Máquina anterior"><?php echo tnm_icon( 'left' ); ?></button>
				<button type="button" class="tnm-icon-btn" data-tnm-next aria-label="Máquina siguiente"><?php echo tnm_icon( 'right' ); ?></button>
				<span class="tnm-m-crumb" data-tnm-crumb></span>
				<button type="button" class="tnm-icon-btn" data-tnm-close aria-label="Cerrar ficha"><?php echo tnm_icon( 'x' ); ?></button>
			</div>
			<div class="tnm-m-body" data-tnm-modal-body></div>
		</dialog>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Tarjeta del catálogo. Los datos van en atributos para filtrar y ordenar sin recargar.
 *
 * @param array $m Máquina.
 * @param int   $i Posición (las primeras imágenes no se difieren).
 * @return string
 */
function tnm_render_card( $m, $i = 0 ) {
	$img   = $m['imagenes'] ? wp_get_attachment_image( $m['imagenes'][0], 'medium_large', false, array( 'alt' => $m['nombre'], 'loading' => $i > 7 ? 'lazy' : 'eager', 'decoding' => 'async', 'sizes' => '(max-width: 720px) 50vw, 300px' ) ) : tnm_placeholder( $m );
	$area  = $m['ancho'] && $m['largo'] ? $m['ancho'] * $m['largo'] : 0;
	$attrs = array(
		'data-id'    => $m['id'],
		'data-url'   => $m['url'],
		'data-cat'   => $m['cat']['slug'],
		'data-name'  => remove_accents( strtolower( $m['nombre'] . ' ' . $m['codigo'] . ' ' . $m['cat']['nombre'] ) ),
		'data-title' => $m['nombre'],
		'data-area'  => $area,
		'data-3d'    => $m['glb'] ? '1' : '0',
		'data-pos'   => $i,
		'style'      => '--c:' . $m['cat']['color'],
	);
	$a = '';
	foreach ( $attrs as $k => $v ) {
		$a .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	ob_start();
	?>
	<article class="tnm-card"<?php echo $a; // phpcs:ignore ?>>
		<a class="tnm-card-media" href="<?php echo esc_url( $m['url'] ); ?>" data-tnm-open aria-label="Ver ficha de <?php echo esc_attr( $m['nombre'] ); ?>">
			<?php echo $img; // phpcs:ignore ?>
			<?php if ( $m['glb'] ) : ?><span class="tnm-badges"><span class="tnm-badge"><?php echo tnm_icon( 'cube' ); ?>3D · AR</span></span><?php endif; ?>
		</a>
		<div class="tnm-card-body">
			<p class="tnm-card-cat"><?php echo esc_html( $m['cat']['singular'] ); ?></p>
			<h2 class="tnm-card-name"><?php echo esc_html( $m['nombre'] ); ?></h2>
			<?php if ( $m['tiene_medidas'] ) : ?><p class="tnm-card-dims"><?php echo tnm_icon( 'ruler' ) . esc_html( tnm_dims( $m ) ); ?></p><?php endif; ?>
			<div class="tnm-card-actions">
				<a class="tnm-pill" href="<?php echo esc_url( $m['url'] ); ?>" data-tnm-open>Ver ficha</a>
				<button type="button" class="tnm-add" data-tnm-add="<?php echo (int) $m['id']; ?>" data-title="<?php echo esc_attr( $m['nombre'] ); ?>" aria-pressed="false" aria-label="Añadir a mi solicitud: <?php echo esc_attr( $m['nombre'] ); ?>" title="Añadir a mi solicitud"><?php echo tnm_icon( 'plus' ); ?></button>
			</div>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

/* =====================================================================
   Ficha
   ===================================================================== */

/**
 * Ficha de una máquina.
 *
 * @param array  $m       Máquina.
 * @param string $context 'pagina' (su propia URL, con h1) o 'ventana' (dentro del catálogo).
 * @return string
 */
function tnm_render_ficha( $m, $context = 'pagina' ) {
	$post  = get_post( $m['id'] );
	$h     = 'pagina' === $context ? 'h1' : 'h2';
	$vista = ( '3d' === tnm_opt( 'vista_inicial' ) && $m['glb'] ) || ( ! $m['imagenes'] && $m['glb'] ) ? '3d' : 'imagenes';
	$desc  = tnm_render_descripcion( $post );
	$data  = array(
		'data-id'     => $m['id'],
		'data-nombre' => $m['nombre'],
		'data-url'    => $m['url'],
		'data-visor'  => $m['visor'],
		'data-glb'    => $m['glb'],
		'data-poster' => $m['imagenes'] ? wp_get_attachment_image_url( $m['imagenes'][0], 'large' ) : '',
		'data-dims'   => $m['tiene_medidas'] ? $m['ancho'] . ',' . $m['largo'] . ',' . $m['alto'] : '',
		'data-cat'    => $m['cat']['nombre'],
		'data-vista'  => $vista,
		'style'       => '--c:' . $m['cat']['color'],
	);
	$a = '';
	foreach ( $data as $k => $v ) {
		$a .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	ob_start();
	?>
	<article class="tnm tnm-ficha tnm-ficha--<?php echo esc_attr( $context ); ?>" data-tnm-ficha<?php echo $a; // phpcs:ignore ?>>
		<div class="tnm-f-media">
			<?php if ( $m['glb'] && $m['imagenes'] ) : ?>
			<div class="tnm-f-tabs" role="tablist" aria-label="Vista">
				<button type="button" role="tab" data-tnm-tab="imagenes" aria-selected="<?php echo 'imagenes' === $vista ? 'true' : 'false'; ?>"><?php echo tnm_icon( 'image' ); ?>Imágenes</button>
				<button type="button" role="tab" data-tnm-tab="3d" aria-selected="<?php echo '3d' === $vista ? 'true' : 'false'; ?>"><?php echo tnm_icon( 'cube' ); ?>3D · AR</button>
			</div>
			<?php endif; ?>
			<div class="tnm-f-stage">
				<div class="tnm-f-view" data-tnm-view="imagenes"<?php echo 'imagenes' === $vista ? '' : ' hidden'; ?>><?php echo tnm_render_carousel( $m ); // phpcs:ignore ?></div>
				<?php if ( $m['glb'] ) : ?><div class="tnm-f-view tnm-f-3d" data-tnm-view="3d"<?php echo '3d' === $vista ? '' : ' hidden'; ?>></div><?php endif; ?>
			</div>
		</div>
		<div class="tnm-f-info">
			<p class="tnm-eyebrow"><?php echo esc_html( $m['cat']['singular'] ); ?></p>
			<<?php echo $h; // phpcs:ignore ?> class="tnm-f-name" tabindex="-1"><?php echo esc_html( $m['nombre'] ); ?></<?php echo $h; // phpcs:ignore ?>>
			<?php if ( $m['codigo'] ) : ?><p class="tnm-f-code">Código de producto: <b><?php echo esc_html( $m['codigo'] ); ?></b></p><?php endif; ?>

			<?php if ( $desc ) : ?>
			<section class="tnm-f-sec"><h3 class="tnm-f-h">Descripción</h3><div class="tnm-desc"><?php echo $desc; // phpcs:ignore ?></div></section>
			<?php endif; ?>

			<section class="tnm-f-sec"><h3 class="tnm-f-h">Características</h3><?php echo tnm_render_specs( $m ); // phpcs:ignore ?></section>

			<?php if ( $m['tiene_medidas'] ) : ?>
			<section class="tnm-f-sec"><h3 class="tnm-f-h">Dimensiones</h3><figure class="tnm-dimfig"><?php echo tnm_render_dim_svg( $m ); // phpcs:ignore ?></figure></section>
			<?php endif; ?>

			<section class="tnm-f-sec"><h3 class="tnm-f-h">Descargas</h3>
				<ul class="tnm-downloads">
					<?php foreach ( $m['fichas'] as $f ) : ?>
						<li><a class="tnm-dl" href="<?php echo esc_url( $f['url'] ); ?>" target="_blank" rel="noopener"><span class="tnm-fi"><?php echo tnm_icon( 'file' ); ?></span><span><b><?php echo esc_html( $f['label'] ); ?></b><small>PDF</small></span><?php echo tnm_icon( 'download' ); ?></a></li>
					<?php endforeach; ?>
					<li><button type="button" class="tnm-dl" data-tnm-print><span class="tnm-fi"><?php echo tnm_icon( 'file' ); ?></span><span><b>Ficha técnica · <?php echo esc_html( $m['nombre'] ); ?></b><small>PDF con descripción, características y dimensiones</small></span><?php echo tnm_icon( 'download' ); ?></button></li>
				</ul>
			</section>
		</div>
		<div class="tnm-f-bar">
			<button type="button" class="tnm-btn tnm-soft" data-tnm-add="<?php echo (int) $m['id']; ?>" data-title="<?php echo esc_attr( $m['nombre'] ); ?>" aria-pressed="false"><?php echo tnm_icon( 'plus' ); ?><span class="tnm-lbl">Añadir a mi solicitud</span></button>
			<button type="button" class="tnm-icon-btn" data-tnm-share aria-label="Compartir ficha"><?php echo tnm_icon( 'share' ); ?></button>
			<span class="tnm-grow"></span>
			<a class="tnm-btn tnm-primary" href="#tnm-contacto" data-tnm-quote="<?php echo (int) $m['id']; ?>">Pedir presupuesto</a>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

/**
 * Descripción: el texto del editor (párrafos, negritas, listas). Si se pegó texto plano con «•», se convierte en lista.
 *
 * @param WP_Post $post Máquina.
 * @return string
 */
function tnm_render_descripcion( $post ) {
	$raw = trim( (string) $post->post_content );
	if ( '' === $raw ) {
		return '';
	}
	if ( false === strpos( $raw, '<' ) ) {
		$raw = tnm_texto_a_html( $raw );
	}
	return wpautop( wp_kses_post( $raw ) );
}

/**
 * Texto plano → HTML: párrafos separados por línea en blanco; líneas que empiezan por «•», «-» o «*» → lista.
 *
 * @param string $txt Texto.
 * @return string
 */
function tnm_texto_a_html( $txt ) {
	$html = '';
	foreach ( preg_split( '/\n\s*\n/', str_replace( "\r", '', trim( $txt ) ) ) as $block ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", $block ) ), 'strlen' );
		$head  = array();
		$items = array();
		foreach ( $lines as $l ) {
			if ( preg_match( '/^(?:•|-|\*)\s*(.+)$/u', $l, $mm ) ) {
				$items[] = $mm[1];
			} else {
				$head[] = $l;
			}
		}
		if ( $head ) {
			$html .= '<p>' . esc_html( implode( ' ', $head ) ) . '</p>';
		}
		if ( $items ) {
			$html .= '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>';
		}
	}
	return $html;
}

/**
 * Filas de características en el orden de la ficha. Sólo las que tienen dato, más los datos comunes.
 *
 * @param array $m Máquina.
 * @return array[] [icono, etiqueta, valor, nota]
 */
function tnm_spec_rows( $m ) {
	$rows = array();
	foreach ( array( 'consumo', 'alimentacion', 'conformidad' ) as $k ) {
		if ( '' !== (string) $m[ $k ] ) {
			$rows[] = array( tnm_campos()[ $k ]['icon'], tnm_campos()[ $k ]['label'], $m[ $k ], '' );
		}
	}
	if ( $m['peso'] ) {
		$rows[] = array( 'weight', 'Peso', tnm_fmt( $m['peso'] ) . ' kg', '' );
	}
	if ( $m['tiene_medidas'] ) {
		$rows[] = array( 'box', 'Dimensiones', tnm_dims( $m ), 'ancho × largo × alto' );
	}
	if ( $m['superficie'] ) {
		$rows[] = array( 'area', 'Superficie ocupada', tnm_fmt( $m['superficie'], 2 ) . ' m²', '' );
	}
	foreach ( tnm_datos_comunes() as list( $label, $value, $icon ) ) {
		$rows[] = array( $icon, $label, $value, '' );
	}
	return $rows;
}

/**
 * @param array $m Máquina.
 * @return string
 */
function tnm_render_specs( $m ) {
	$html = '<dl class="tnm-specs">';
	foreach ( tnm_spec_rows( $m ) as list( $icon, $label, $value, $note ) ) {
		$html .= '<div class="tnm-spec">' . tnm_icon( $icon ) . '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . ( $note ? '<small>' . esc_html( $note ) . '</small>' : '' ) . '</dd></div>';
	}
	return $html . '</dl>';
}

/**
 * Carrusel de imágenes (imagen principal + galería). Se desliza con el dedo; flechas y puntos en escritorio.
 *
 * @param array $m Máquina.
 * @return string
 */
function tnm_render_carousel( $m ) {
	$ids = $m['imagenes'];
	if ( ! $ids ) {
		return '<div class="tnm-noimg">' . tnm_placeholder( $m ) . '</div>';
	}
	$n    = count( $ids );
	$html = '<div class="tnm-carousel" data-tnm-carousel><div class="tnm-slides" tabindex="0" aria-roledescription="carrusel" aria-label="' . esc_attr( 'Imágenes de ' . $m['nombre'] ) . '">';
	foreach ( $ids as $i => $id ) {
		$html .= '<figure class="tnm-slide" aria-label="' . esc_attr( ( $i + 1 ) . ' de ' . $n ) . '">' . wp_get_attachment_image(
			$id,
			'large',
			false,
			array(
				'loading'  => $i ? 'lazy' : 'eager',
				'decoding' => 'async',
				'sizes'    => '(max-width: 720px) 100vw, 640px',
				'alt'      => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ?: $m['nombre'],
			)
		) . '</figure>';
	}
	$html .= '</div>';
	if ( $n > 1 ) {
		$html .= '<button type="button" class="tnm-car-btn tnm-car-prev" data-tnm-car="-1" aria-label="Imagen anterior">' . tnm_icon( 'left' ) . '</button>';
		$html .= '<button type="button" class="tnm-car-btn tnm-car-next" data-tnm-car="1" aria-label="Imagen siguiente">' . tnm_icon( 'right' ) . '</button>';
		$html .= '<div class="tnm-car-dots">';
		for ( $i = 0; $i < $n; $i++ ) {
			$html .= '<button type="button" data-tnm-dot="' . $i . '" aria-label="Imagen ' . ( $i + 1 ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '></button>';
		}
		$html .= '</div><span class="tnm-car-count" data-tnm-car-count>1 / ' . $n . '</span>';
	}
	return $html . '</div>';
}

/**
 * Dibujo de dimensiones: la máquina como volumen en perspectiva (ancho, largo y alto) junto a una persona de 1,75 m.
 * Los colores salen del CSS (clases tnm-d-*), así el mismo dibujo sirve en pantalla y en la ficha impresa.
 *
 * @param array $m Máquina.
 * @return string
 */
function tnm_render_dim_svg( $m ) {
	$W   = (float) $m['ancho'];
	$L   = (float) $m['largo'];
	$H   = (float) $m['alto'];
	$P   = 175;
	$PW  = 48;
	$ang = M_PI / 6;
	$kd  = 0.55; // perspectiva caballera: fondo a 30° y 0,55
	$dxL = $L * $kd * cos( $ang );
	$dyL = $L * $kd * sin( $ang );
	$VW  = 340;
	$VH  = 220;
	$padL = 46;
	$padR = 16;
	$padT = 22;
	$padB = 46;
	$gap  = 34;
	$k    = min( ( $VW - $padL - $padR ) / ( $W + $dxL + $gap + $PW ), ( $VH - $padT - $padB ) / max( $H + $dyL, $P ) );
	$w    = $W * $k;
	$h    = $H * $k;
	$dx   = $dxL * $k;
	$dy   = $dyL * $k;
	$x0   = $padL;
	$y0   = $VH - $padB;
	$f    = fn( $x, $y ) => round( $x, 1 ) . ' ' . round( $y, 1 );
	$r    = fn( $v ) => round( $v, 1 );

	$front  = "M{$f($x0,$y0)}H{$r($x0+$w)}V{$r($y0-$h)}H{$r($x0)}Z";
	$top    = "M{$f($x0,$y0-$h)}L{$f($x0+$dx,$y0-$h-$dy)}H{$r($x0+$w+$dx)}L{$f($x0+$w,$y0-$h)}Z";
	$side   = "M{$f($x0+$w,$y0)}L{$f($x0+$w+$dx,$y0-$dy)}V{$r($y0-$h-$dy)}L{$f($x0+$w,$y0-$h)}Z";
	$hidden = "M{$f($x0,$y0)}L{$f($x0+$dx,$y0-$dy)}H{$r($x0+$w+$dx)}M{$f($x0+$dx,$y0-$dy)}V{$r($y0-$h-$dy)}";

	$aY  = $y0 + 12;
	$hX  = $x0 - 12;
	$off = 10;
	$lx1 = $x0 + $w + $off * sin( $ang );
	$ly1 = $y0 + $off * cos( $ang );
	$lx2 = $lx1 + $dx;
	$ly2 = $ly1 - $dy;
	$lmx = ( $lx1 + $lx2 ) / 2 + 8;
	$lmy = ( $ly1 + $ly2 ) / 2 + 12;
	$px  = $x0 + $w + $dx + $gap * $k + $PW * $k / 2;
	$ph  = $P * $k;
	$pt  = $y0 - $ph;
	$hr  = 11 * $k;

	$t   = fn( $x, $y, $txt, $extra = '' ) => '<text class="tnm-d-val" x="' . $r( $x ) . '" y="' . $r( $y ) . '" ' . $extra . '>' . esc_html( $txt ) . '</text>';
	$cap = fn( $x, $y, $txt, $extra = '' ) => '<text class="tnm-d-cap" x="' . $r( $x ) . '" y="' . $r( $y ) . '" ' . $extra . '>' . esc_html( $txt ) . '</text>';
	$rot = fn( $deg, $x, $y ) => 'transform="rotate(' . $deg . ' ' . $r( $x ) . ' ' . $r( $y ) . ')"';

	$person = '<g class="tnm-d-person"><circle cx="' . $r( $px ) . '" cy="' . $r( $pt + $hr ) . '" r="' . $r( $hr ) . '"/>'
		. '<path d="M' . $f( $px - 17 * $k, $pt + 2.3 * $hr ) . 'h' . $r( 34 * $k ) . 'a' . $f( 6 * $k, 6 * $k ) . ' 0 0 1 ' . $f( 6 * $k, 6 * $k ) . 'V' . $r( $pt + $ph * .55 ) . 'h' . $r( -8 * $k ) . 'V' . $r( $y0 ) . 'h' . $r( -12 * $k ) . 'V' . $r( $pt + $ph * .6 ) . 'h' . $r( -4 * $k ) . 'V' . $r( $y0 ) . 'h' . $r( -12 * $k ) . 'V' . $r( $pt + $ph * .55 ) . 'h' . $r( -8 * $k ) . 'V' . $r( $pt + 2.3 * $hr + 6 * $k ) . 'a' . $f( 6 * $k, 6 * $k ) . ' 0 0 1 ' . $f( 6 * $k, -6 * $k ) . 'z"/></g>';

	$label = sprintf( 'Dimensiones: %s cm de ancho, %s de largo y %s de alto, junto a una persona de 1,75 m', tnm_fmt( $W ), tnm_fmt( $L ), tnm_fmt( $H ) );
	return '<svg class="tnm-dim" viewBox="0 0 ' . $VW . ' ' . $VH . '" role="img" aria-label="' . esc_attr( $label ) . '">'
		. '<path class="tnm-d-floor" d="M8 ' . $r( $y0 ) . 'H' . ( $VW - 8 ) . '"/>'
		. '<path class="tnm-d-hidden" d="' . $hidden . '"/>'
		. '<path class="tnm-d-side" d="' . $side . '"/>'
		. '<path class="tnm-d-top" d="' . $top . '"/>'
		. '<path class="tnm-d-front" d="' . $front . '"/>'
		. '<g class="tnm-d-dim"><path d="M' . $f( $x0, $aY ) . 'H' . $r( $x0 + $w ) . 'M' . $f( $x0, $aY - 4 ) . 'v8M' . $f( $x0 + $w, $aY - 4 ) . 'v8"/>'
		. '<path d="M' . $f( $hX, $y0 ) . 'V' . $r( $y0 - $h ) . 'M' . $f( $hX - 4, $y0 ) . 'h8M' . $f( $hX - 4, $y0 - $h ) . 'h8"/>'
		. '<path d="M' . $f( $lx1, $ly1 ) . 'L' . $f( $lx2, $ly2 ) . '"/></g>'
		. $t( $x0 + $w / 2, $aY + 14, tnm_fmt( $W ) . ' cm', 'text-anchor="middle"' ) . $cap( $x0 + $w / 2, $aY + 24, 'ANCHO', 'text-anchor="middle"' )
		. $t( $hX - 6, $y0 - $h / 2, tnm_fmt( $H ) . ' cm', 'text-anchor="middle" ' . $rot( -90, $hX - 6, $y0 - $h / 2 ) )
		. $cap( $hX - 18, $y0 - $h / 2, 'ALTO', 'text-anchor="middle" ' . $rot( -90, $hX - 18, $y0 - $h / 2 ) )
		. $t( $lmx, $lmy, tnm_fmt( $L ) . ' cm', 'text-anchor="middle" ' . $rot( -30, $lmx, $lmy ) )
		. $cap( $lmx + 4, $lmy + 10, 'LARGO', 'text-anchor="middle" ' . $rot( -30, $lmx + 4, $lmy + 10 ) )
		. $person . $cap( $px, $y0 + 14, '1,75 M', 'text-anchor="middle"' )
		. '</svg>';
}

/* =====================================================================
   Formulario de presupuesto
   ===================================================================== */

/**
 * Formulario de solicitud. Si en Ajustes hay un formulario externo (shortcode), se usa ese
 * y la lista de máquinas se escribe en su campo «maquinas».
 *
 * @param int[] $preselect Máquinas que ya van en la solicitud (p. ej. la de la ficha).
 * @return string
 */
function tnm_render_contacto( $preselect = array() ) {
	$priv = tnm_opt( 'privacidad_url' ) ?: get_privacy_policy_url();
	$sc   = trim( (string) tnm_opt( 'form_shortcode' ) );
	ob_start();
	?>
	<section class="tnm tnm-contacto" id="tnm-contacto" data-tnm-contacto data-preselect="<?php echo esc_attr( implode( ',', array_map( 'absint', $preselect ) ) ); ?>">
		<div class="tnm-contact-in">
			<div class="tnm-contact-copy">
				<p class="tnm-eyebrow">Contacto</p>
				<h2><?php echo esc_html( tnm_opt( 'contacto_titulo' ) ); ?></h2>
				<p><?php echo esc_html( tnm_opt( 'contacto_texto' ) ); ?></p>
				<ul class="tnm-checks">
					<li><?php echo tnm_icon( 'check' ); ?>Te ayudamos a elegir según tu espacio</li>
					<li><?php echo tnm_icon( 'check' ); ?>Ficha técnica de cada máquina</li>
					<li><?php echo tnm_icon( 'check' ); ?>Presupuesto sin compromiso</li>
				</ul>
			</div>
			<div>
				<?php if ( $sc ) : ?>
					<div class="tnm-form tnm-form--externo">
						<div class="tnm-sel-box"><p class="tnm-sel-title">Máquinas de interés</p><div class="tnm-sel-chips" data-tnm-sel></div></div>
						<?php echo do_shortcode( $sc ); // phpcs:ignore ?>
					</div>
				<?php else : ?>
				<form class="tnm-form" data-tnm-form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
					<input type="hidden" name="action" value="tnm_solicitud">
					<input type="hidden" name="maquinas" value="" data-tnm-maquinas>
					<input type="hidden" name="ts" value="<?php echo esc_attr( time() ); ?>">
					<input type="hidden" name="volver" value="<?php echo esc_url( home_url( add_query_arg( array() ) ) ); ?>">
					<div class="tnm-hp" aria-hidden="true"><label>Web <input type="text" name="web" tabindex="-1" autocomplete="off"></label></div>
					<div class="tnm-sel-box"><p class="tnm-sel-title">Máquinas de interés</p><div class="tnm-sel-chips" data-tnm-sel></div></div>
					<div class="tnm-two">
						<div class="tnm-field"><?php echo tnm_icon( 'user' ); ?><label class="tnm-sr" for="tnm-nombre">Nombre</label><input id="tnm-nombre" name="nombre" placeholder="Nombre *" autocomplete="name" required maxlength="120"><p class="tnm-err">Indica tu nombre.</p></div>
						<div class="tnm-field"><?php echo tnm_icon( 'mail' ); ?><label class="tnm-sr" for="tnm-email">Correo electrónico</label><input id="tnm-email" name="email" type="email" placeholder="Correo electrónico *" autocomplete="email" required maxlength="160"><p class="tnm-err">Revisa el correo electrónico.</p></div>
						<div class="tnm-field"><?php echo tnm_icon( 'phone' ); ?><label class="tnm-sr" for="tnm-tel">Teléfono</label><input id="tnm-tel" name="telefono" type="tel" placeholder="Teléfono *" autocomplete="tel" required maxlength="40"><p class="tnm-err">Indica un teléfono de contacto.</p></div>
						<div class="tnm-field"><?php echo tnm_icon( 'pin' ); ?><label class="tnm-sr" for="tnm-ciudad">Provincia / Ciudad</label><input id="tnm-ciudad" name="ciudad" placeholder="Provincia / Ciudad *" autocomplete="address-level2" required maxlength="120"><p class="tnm-err">Indica tu provincia o ciudad.</p></div>
					</div>
					<div class="tnm-field"><?php echo tnm_icon( 'msg' ); ?><label class="tnm-sr" for="tnm-msg">Mensaje</label><textarea id="tnm-msg" name="mensaje" placeholder="Mensaje * (tipo de local, espacio disponible, plazos…)" required maxlength="4000"></textarea><p class="tnm-err">Escribe un mensaje.</p></div>
					<label class="tnm-consent"><input type="checkbox" name="consentimiento" value="1" required><span>He leído y consiento el <?php echo $priv ? '<a href="' . esc_url( $priv ) . '" target="_blank" rel="noopener">Tratamiento de mis Datos Personales</a>' : 'Tratamiento de mis Datos Personales'; ?> conforme a la normativa vigente. *</span></label>
					<button class="tnm-btn tnm-primary tnm-lg" type="submit">Enviar solicitud</button>
					<p class="tnm-form-msg" data-tnm-form-msg role="status" aria-live="polite"></p>
				</form>
				<div class="tnm-form-ok" data-tnm-form-ok<?php echo isset( $_GET['solicitud'] ) && 'ok' === $_GET['solicitud'] ? '' : ' hidden'; // phpcs:ignore ?>>
					<?php echo tnm_icon( 'check' ); ?>
					<h3>¡Solicitud enviada!</h3>
					<p>Gracias. Nuestro equipo comercial revisará tu solicitud y te contactará.</p>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
