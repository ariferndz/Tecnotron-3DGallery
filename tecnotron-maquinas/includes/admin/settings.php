<?php
/**
 * Máquinas → Ajustes: textos del catálogo, datos comunes, correo de solicitudes y dirección del catálogo.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=' . TNM_CPT, 'Ajustes del catálogo', 'Ajustes', 'manage_options', 'tnm-ajustes', 'tnm_settings_page' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'tnm_ajustes',
			TNM_OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $in ) {
					$in  = is_array( $in ) ? $in : array();
					$out = array(
						'slug'            => sanitize_title( $in['slug'] ?? '' ) ?: 'maquinas',
						'titulo'          => sanitize_text_field( $in['titulo'] ?? '' ),
						'intro'           => sanitize_textarea_field( $in['intro'] ?? '' ),
						'comunes'         => sanitize_textarea_field( $in['comunes'] ?? '' ),
						'vista_ficha'     => in_array( $in['vista_ficha'] ?? '', array( 'imagenes', '3d' ), true ) ? $in['vista_ficha'] : '3d',
						'email'           => implode( ', ', array_filter( array_map( 'sanitize_email', explode( ',', (string) ( $in['email'] ?? '' ) ) ), 'is_email' ) ),
						'guardar'         => empty( $in['guardar'] ) ? 0 : 1,
						'form_shortcode'  => wp_kses_post( trim( (string) ( $in['form_shortcode'] ?? '' ) ) ),
						'privacidad_url'  => esc_url_raw( $in['privacidad_url'] ?? '' ),
						'contacto_titulo' => sanitize_text_field( $in['contacto_titulo'] ?? '' ),
						'contacto_texto'  => sanitize_textarea_field( $in['contacto_texto'] ?? '' ),
						'pdf_color'       => sanitize_hex_color( $in['pdf_color'] ?? '' ) ?: '#7c3aed',
						'pdf_logo'        => absint( $in['pdf_logo'] ?? 0 ),
						'pdf_telefono'    => sanitize_text_field( $in['pdf_telefono'] ?? '' ),
						'pdf_email'       => sanitize_email( $in['pdf_email'] ?? '' ),
						'pdf_web'         => sanitize_text_field( $in['pdf_web'] ?? '' ),
						'pdf_direccion'   => sanitize_textarea_field( $in['pdf_direccion'] ?? '' ),
					);
					return $out;
				},
			)
		);
	}
);

function tnm_settings_page() {
	$o   = tnm_opt();
	$n   = TNM_OPT;
	$url = get_post_type_archive_link( TNM_CPT );
	?>
	<div class="wrap tnm-settings">
		<h1>Ajustes del catálogo</h1>
		<?php settings_errors(); // «Ajustes guardados»: WordPress sólo lo muestra solo en las páginas del menú Ajustes. ?>
		<p>El catálogo está en <a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a>. También puedes insertarlo en cualquier página con el shortcode <code>[tecnotron_catalogo]</code> o sólo una categoría con <code>[tecnotron_catalogo categoria="kiddie-rides"]</code>.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'tnm_ajustes' ); ?>
			<h2>Catálogo</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="tnm-titulo">Título</label></th><td><input id="tnm-titulo" class="regular-text" name="<?php echo esc_attr( $n ); ?>[titulo]" value="<?php echo esc_attr( $o['titulo'] ); ?>"></td></tr>
				<tr><th><label for="tnm-intro">Texto de introducción</label></th><td><textarea id="tnm-intro" class="large-text" rows="3" name="<?php echo esc_attr( $n ); ?>[intro]"><?php echo esc_textarea( $o['intro'] ); ?></textarea></td></tr>
				<tr><th><label for="tnm-slug">Dirección</label></th><td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="tnm-slug" name="<?php echo esc_attr( $n ); ?>[slug]" value="<?php echo esc_attr( $o['slug'] ); ?>" style="width:140px"><code>/</code><p class="description">Cambiarla rompe los QR ya impresos: elige la definitiva antes de imprimir.</p></td></tr>
				<tr><th>Vista inicial de la ficha</th><td><select name="<?php echo esc_attr( $n ); ?>[vista_ficha]"><option value="3d" <?php selected( $o['vista_ficha'], '3d' ); ?>>3D · AR (si la máquina tiene modelo)</option><option value="imagenes" <?php selected( $o['vista_ficha'], 'imagenes' ); ?>>Imágenes</option></select><p class="description">Sin modelo 3D, la ficha muestra sólo «Imágenes».</p></td></tr>
			</table>
			<h2>Datos comunes a todas las fichas</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="tnm-comunes">Una línea por dato</label></th><td><textarea id="tnm-comunes" class="large-text code" rows="4" name="<?php echo esc_attr( $n ); ?>[comunes]"><?php echo esc_textarea( $o['comunes'] ); ?></textarea><p class="description">Formato <code>Etiqueta: valor</code>. Aparecen al final de «Características» en todas las máquinas y en la cabecera del catálogo.</p></td></tr>
			</table>
			<h2>Solicitudes de presupuesto</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="tnm-email">Enviar a</label></th><td><input id="tnm-email" class="regular-text" name="<?php echo esc_attr( $n ); ?>[email]" value="<?php echo esc_attr( $o['email'] ); ?>"><p class="description">Uno o varios correos separados por comas.</p></td></tr>
				<tr><th>Copia en el panel</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[guardar]" value="1" <?php checked( $o['guardar'] ); ?>> Guardar cada solicitud en Máquinas → Solicitudes</label><p class="description">Son datos personales: bórralas cuando dejen de ser necesarias.</p></td></tr>
				<tr><th><label for="tnm-priv">Política de privacidad</label></th><td><input id="tnm-priv" class="regular-text" type="url" name="<?php echo esc_attr( $n ); ?>[privacidad_url]" value="<?php echo esc_attr( $o['privacidad_url'] ); ?>" placeholder="<?php echo esc_attr( get_privacy_policy_url() ); ?>"><p class="description">Vacío = la página de privacidad de WordPress.</p></td></tr>
				<tr><th><label for="tnm-ct">Título del bloque</label></th><td><input id="tnm-ct" class="regular-text" name="<?php echo esc_attr( $n ); ?>[contacto_titulo]" value="<?php echo esc_attr( $o['contacto_titulo'] ); ?>"></td></tr>
				<tr><th><label for="tnm-cx">Texto del bloque</label></th><td><textarea id="tnm-cx" class="large-text" rows="2" name="<?php echo esc_attr( $n ); ?>[contacto_texto]"><?php echo esc_textarea( $o['contacto_texto'] ); ?></textarea></td></tr>
				<tr><th><label for="tnm-sc">Usar otro formulario</label></th><td><input id="tnm-sc" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[form_shortcode]" value="<?php echo esc_attr( $o['form_shortcode'] ); ?>" placeholder='[contact-form-7 id="123"]'><p class="description">Opcional: el shortcode de vuestro formulario (Contact Form 7, WPForms…). Añádele un campo oculto llamado <code>maquinas</code> y recibirá la lista de máquinas elegidas.</p></td></tr>
			</table>
			<h2>Ficha técnica en PDF</h2>
			<p>El botón «Ficha técnica» de cada máquina genera un PDF de dos páginas con este diseño: portada con la foto y el nombre, y una segunda página con la descripción, las características y el dibujo de dimensiones.</p>
			<table class="form-table" role="presentation">
				<tr><th><label for="tnm-pdf-color">Color</label></th><td><input id="tnm-pdf-color" type="color" name="<?php echo esc_attr( $n ); ?>[pdf_color]" value="<?php echo esc_attr( $o['pdf_color'] ); ?>"><p class="description">Franjas, título y los círculos de las características.</p></td></tr>
				<tr><th>Logo</th><td>
					<?php $logo = (int) $o['pdf_logo'] ?: (int) get_theme_mod( 'custom_logo' ); ?>
					<input type="hidden" name="<?php echo esc_attr( $n ); ?>[pdf_logo]" value="<?php echo (int) $o['pdf_logo'] ?: ''; ?>" data-tnm-logo-id>
					<span class="tnm-logo-prev" data-tnm-logo-prev><?php echo $logo ? wp_get_attachment_image( $logo, 'medium' ) : ''; ?></span>
					<button type="button" class="button" data-tnm-logo-pick>Elegir logo</button>
					<button type="button" class="button-link-delete" data-tnm-logo-clear<?php echo $o['pdf_logo'] ? '' : ' hidden'; ?>>Quitar</button>
					<p class="description">PNG o SVG, mejor con fondo transparente. Sin logo se usa el del tema (Apariencia → Personalizar) o, si no hay, el nombre del sitio.</p></td></tr>
				<tr><th><label for="tnm-pdf-tel">Teléfono</label></th><td><input id="tnm-pdf-tel" class="regular-text" name="<?php echo esc_attr( $n ); ?>[pdf_telefono]" value="<?php echo esc_attr( $o['pdf_telefono'] ); ?>" placeholder="p. ej. +34 900 000 000"></td></tr>
				<tr><th><label for="tnm-pdf-email">Correo</label></th><td><input id="tnm-pdf-email" class="regular-text" type="email" name="<?php echo esc_attr( $n ); ?>[pdf_email]" value="<?php echo esc_attr( $o['pdf_email'] ); ?>" placeholder="p. ej. info@tecnotron.es"></td></tr>
				<tr><th><label for="tnm-pdf-web">Web</label></th><td><input id="tnm-pdf-web" class="regular-text" name="<?php echo esc_attr( $n ); ?>[pdf_web]" value="<?php echo esc_attr( $o['pdf_web'] ); ?>" placeholder="<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>"></td></tr>
				<tr><th><label for="tnm-pdf-dir">Dirección</label></th><td><textarea id="tnm-pdf-dir" class="large-text" rows="2" name="<?php echo esc_attr( $n ); ?>[pdf_direccion]" placeholder="Calle, número&#10;Código postal, ciudad"><?php echo esc_textarea( $o['pdf_direccion'] ); ?></textarea><p class="description">Los datos vacíos no salen en el pie del PDF.</p></td></tr>
			</table>
			<?php submit_button( 'Guardar ajustes' ); ?>
		</form>
	</div>
	<?php
}
