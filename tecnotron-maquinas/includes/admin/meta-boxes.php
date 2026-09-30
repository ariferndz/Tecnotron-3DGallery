<?php
/**
 * Pantalla de edición de una máquina: ficha técnica, modelo 3D, galería y fichas PDF.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'enter_title_here',
	function ( $text, $post ) {
		return TNM_CPT === $post->post_type ? 'Nombre de la máquina' : $text;
	},
	10,
	2
);

add_action(
	'edit_form_after_title',
	function ( $post ) {
		if ( TNM_CPT === $post->post_type ) {
			echo '<h2 class="tnm-editor-title">Descripción</h2><p class="tnm-editor-help">Texto de presentación. Usa la lista con viñetas del editor para enumerar ventajas («dos juegos en uno…»).</p>';
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( TNM_CPT, 'edit-' . TNM_TAX, TNM_CPT . '_page_tnm-ajustes', TNM_CPT . '_page_tnm-importar' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'tnm-admin', TNM_URL . 'assets/css/admin.css', array(), TNM_VERSION );
		if ( TNM_CPT === $screen->id ) {
			wp_enqueue_media();
			wp_enqueue_script( 'tnm-admin', TNM_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), TNM_VERSION, true );
			wp_localize_script( 'tnm-admin', 'TNM_ADMIN', array( 'mv' => TNM_URL . 'assets/vendor/model-viewer.min.js?ver=' . TNM_MV_VERSION ) );
		}
		if ( 'edit-' . TNM_TAX === $screen->id ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".tnm-color").wpColorPicker();});' );
		}
	}
);

add_action(
	'add_meta_boxes_' . TNM_CPT,
	function () {
		add_meta_box( 'tnm_ficha', 'Ficha técnica', 'tnm_box_ficha', TNM_CPT, 'normal', 'high' );
		add_meta_box( 'tnm_galeria', 'Galería de imágenes (carrusel)', 'tnm_box_galeria', TNM_CPT, 'normal', 'high' );
		add_meta_box( 'tnm_modelo', 'Modelo 3D y realidad aumentada', 'tnm_box_modelo', TNM_CPT, 'normal', 'default' );
		add_meta_box( 'tnm_fichas', 'Fichas técnicas en PDF', 'tnm_box_fichas', TNM_CPT, 'normal', 'default' );
		add_meta_box( 'postexcerpt', 'Resumen para Google y redes sociales (opcional)', 'post_excerpt_meta_box', TNM_CPT, 'normal', 'low' );
	}
);

/**
 * @param WP_Post $post Máquina.
 */
function tnm_box_ficha( $post ) {
	wp_nonce_field( 'tnm_guardar', 'tnm_nonce' );
	$v = function ( $k ) use ( $post ) {
		$val = get_post_meta( $post->ID, '_tnm_' . $k, true );
		return esc_attr( is_numeric( $val ) ? str_replace( '.', ',', (string) $val ) : $val );
	};
	$campos = tnm_campos();
	?>
	<p class="tnm-help">Sólo se muestran en la web los campos rellenos. «Atención al cliente» y «Servicio técnico» son comunes a todas las máquinas y se cambian en <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TNM_CPT . '&page=tnm-ajustes' ) ); ?>">Ajustes</a>.</p>
	<div class="tnm-grid-fields">
		<?php foreach ( array( 'codigo', 'consumo', 'alimentacion', 'conformidad', 'peso' ) as $k ) : ?>
			<label><span><?php echo esc_html( $campos[ $k ]['label'] ); ?><?php echo isset( $campos[ $k ]['unit'] ) ? ' (' . esc_html( $campos[ $k ]['unit'] ) . ')' : ''; ?></span>
				<input type="text" name="tnm[<?php echo esc_attr( $k ); ?>]" value="<?php echo $v( $k ); // phpcs:ignore ?>" placeholder="<?php echo esc_attr( $campos[ $k ]['ph'] ); ?>"<?php echo 'number' === $campos[ $k ]['type'] ? ' inputmode="decimal"' : ''; ?>></label>
		<?php endforeach; ?>
	</div>
	<h4 class="tnm-sub">Dimensiones (cm) — ancho × largo × alto</h4>
	<div class="tnm-grid-fields tnm-dims-fields">
		<?php foreach ( array( 'ancho', 'largo', 'alto', 'superficie' ) as $k ) : ?>
			<label><span><?php echo esc_html( $campos[ $k ]['label'] . ' (' . $campos[ $k ]['unit'] . ')' ); ?></span>
				<input type="text" inputmode="decimal" name="tnm[<?php echo esc_attr( $k ); ?>]" value="<?php echo $v( $k ); // phpcs:ignore ?>" placeholder="<?php echo esc_attr( $campos[ $k ]['ph'] ); ?>" data-tnm-dim="<?php echo esc_attr( $k ); ?>"></label>
		<?php endforeach; ?>
	</div>
	<p class="tnm-help">La superficie ocupada se calcula sola (ancho × largo); rellénala sólo si quieres otro valor. Con las tres medidas se dibuja el esquema de dimensiones de la ficha.</p>
	<label class="tnm-check"><input type="checkbox" name="tnm[destacada]" value="1" <?php checked( get_post_meta( $post->ID, '_tnm_destacada', true ) ); ?>> Destacada: mostrar entre las primeras del catálogo</label>
	<?php
}

/**
 * @param WP_Post $post Máquina.
 */
function tnm_box_galeria( $post ) {
	$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post->ID, '_tnm_galeria', true ) ) ) );
	?>
	<p class="tnm-help">El carrusel de la ficha empieza por la <b>imagen principal</b> (caja lateral «Imagen principal», también se usa en la tarjeta del catálogo) y sigue con estas imágenes. Arrástralas para cambiar el orden.</p>
	<input type="hidden" name="tnm[galeria]" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" data-tnm-galeria-input>
	<ul class="tnm-gallery" data-tnm-galeria>
		<?php foreach ( $ids as $id ) : ?>
			<li data-id="<?php echo (int) $id; ?>"><?php echo wp_get_attachment_image( $id, 'thumbnail' ); ?><button type="button" class="tnm-rm" aria-label="Quitar imagen">×</button></li>
		<?php endforeach; ?>
	</ul>
	<p><button type="button" class="button" data-tnm-galeria-add>Añadir imágenes</button></p>
	<?php
}

/**
 * @param WP_Post $post Máquina.
 */
function tnm_box_modelo( $post ) {
	$att = (int) get_post_meta( $post->ID, '_tnm_glb_id', true );
	$url = (string) get_post_meta( $post->ID, '_tnm_glb_url', true );
	$src = tnm_glb_url( $post->ID );
	$m   = tnm_maquina( $post );
	?>
	<p class="tnm-help">Sube el archivo <b>.glb</b> de la máquina (optimizado para web, idealmente menos de 5 MB). Se muestra en la pestaña «3D · AR» de la ficha y en el visor del QR. Para que la realidad aumentada muestre la máquina <b>a tamaño real</b>, el GLB debe estar en metros con las medidas reales.</p>
	<input type="hidden" name="tnm[glb_id]" value="<?php echo $att ? (int) $att : ''; ?>" data-tnm-glb-id>
	<p class="tnm-glb-row">
		<button type="button" class="button button-primary" data-tnm-glb-pick>Elegir o subir GLB</button>
		<button type="button" class="button" data-tnm-glb-clear<?php echo $src ? '' : ' hidden'; ?>>Quitar modelo</button>
		<span class="tnm-glb-name" data-tnm-glb-name><?php echo $att ? esc_html( basename( (string) get_attached_file( $att ) ) ) : ''; ?></span>
	</p>
	<p><label>O dirección externa del GLB (https://…)<br><input type="url" class="widefat" name="tnm[glb_url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://" data-tnm-glb-url></label></p>
	<div class="tnm-glb-preview" data-tnm-glb-preview data-src="<?php echo esc_attr( $src ); ?>" data-dims="<?php echo esc_attr( $m['tiene_medidas'] ? $m['ancho'] . ',' . $m['largo'] . ',' . $m['alto'] : '' ); ?>">
		<div class="tnm-glb-stage" data-tnm-glb-stage><?php echo $src ? '' : '<span>Sin modelo 3D</span>'; ?></div>
		<p class="tnm-glb-check" data-tnm-glb-check></p>
	</div>
	<?php
}

/**
 * @param WP_Post $post Máquina.
 */
function tnm_box_fichas( $post ) {
	$rows = get_post_meta( $post->ID, '_tnm_fichas', true );
	$rows = is_array( $rows ) ? $rows : array();
	?>
	<p class="tnm-help">PDF del fabricante o fichas propias (p. ej. «Especificaciones técnicas (ITA)»). Además, cada ficha ofrece siempre «Ficha técnica» generada con los datos de esta pantalla.</p>
	<table class="tnm-pdfs widefat striped" data-tnm-pdfs>
		<thead><tr><th>Texto del enlace</th><th>Archivo</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $i => $r ) : ?>
			<?php tnm_pdf_row( $i, $r ); ?>
		<?php endforeach; ?>
		</tbody>
	</table>
	<script type="text/html" id="tmpl-tnm-pdf-row"><?php tnm_pdf_row( '__i__', array() ); ?></script>
	<p><button type="button" class="button" data-tnm-pdf-add>Añadir PDF</button></p>
	<?php
}

/**
 * @param int|string $i Índice.
 * @param array      $r Fila.
 */
function tnm_pdf_row( $i, $r ) {
	$id   = (int) ( $r['id'] ?? 0 );
	$file = $id ? basename( (string) get_attached_file( $id ) ) : ( $r['url'] ?? '' );
	?>
	<tr>
		<td><input type="text" class="widefat" name="tnm[fichas][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $r['label'] ?? '' ); ?>" placeholder="Especificaciones técnicas (ENG)"></td>
		<td><input type="hidden" name="tnm[fichas][<?php echo esc_attr( $i ); ?>][id]" value="<?php echo $id ? (int) $id : ''; ?>" data-tnm-pdf-id>
			<input type="url" class="widefat" name="tnm[fichas][<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $id ? '' : ( $r['url'] ?? '' ) ); ?>" placeholder="Elegir PDF o pegar https://…" data-tnm-pdf-url>
			<small data-tnm-pdf-name><?php echo $id ? esc_html( $file ) : ''; ?></small></td>
		<td class="tnm-pdf-actions"><button type="button" class="button" data-tnm-pdf-pick>Elegir PDF</button> <button type="button" class="button-link-delete" data-tnm-pdf-rm>Quitar</button></td>
	</tr>
	<?php
}

add_action(
	'save_post_' . TNM_CPT,
	function ( $post_id ) {
		if ( ! isset( $_POST['tnm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tnm_nonce'] ) ), 'tnm_guardar' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['tnm'] ) && is_array( $_POST['tnm'] ) ? wp_unslash( $_POST['tnm'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- se sanea campo a campo.

		foreach ( array( 'codigo', 'consumo', 'alimentacion', 'conformidad' ) as $k ) {
			update_post_meta( $post_id, '_tnm_' . $k, sanitize_text_field( $in[ $k ] ?? '' ) );
		}
		foreach ( array( 'peso', 'ancho', 'largo', 'alto', 'superficie' ) as $k ) {
			update_post_meta( $post_id, '_tnm_' . $k, tnm_num( $in[ $k ] ?? '' ) );
		}
		update_post_meta( $post_id, '_tnm_destacada', empty( $in['destacada'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_tnm_galeria', implode( ',', array_filter( array_map( 'absint', explode( ',', (string) ( $in['galeria'] ?? '' ) ) ) ) ) );
		update_post_meta( $post_id, '_tnm_glb_id', absint( $in['glb_id'] ?? 0 ) ?: '' );
		update_post_meta( $post_id, '_tnm_glb_url', esc_url_raw( $in['glb_url'] ?? '' ) );

		$fichas = array();
		foreach ( (array) ( $in['fichas'] ?? array() ) as $key => $r ) {
			if ( '__i__' === (string) $key || ! is_array( $r ) ) {
				continue;
			}
			$row = array( 'label' => sanitize_text_field( $r['label'] ?? '' ), 'id' => absint( $r['id'] ?? 0 ), 'url' => esc_url_raw( $r['url'] ?? '' ) );
			if ( $row['id'] || $row['url'] ) {
				$fichas[] = $row;
			}
		}
		update_post_meta( $post_id, '_tnm_fichas', $fichas );
	}
);
