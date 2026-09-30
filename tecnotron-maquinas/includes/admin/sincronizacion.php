<?php
/**
 * Escritorio de la sincronización con Plataformas: conexión en Ajustes, estado, «Sincronizar ahora» y avisos en las
 * pantallas de las máquinas que se editan allí.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	function () {
		// Se guarda con el mismo formulario que el resto de Ajustes (grupo tnm_ajustes)
		register_setting(
			'tnm_ajustes',
			TNM_SYNC_OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $in ) {
					$in   = is_array( $in ) ? $in : array();
					$prev = get_option( TNM_SYNC_OPT, array() );
					$prev = is_array( $prev ) ? $prev : array();
					if ( ! empty( $in['desconectar'] ) ) {
						return array( 'url' => '', 'clave' => '', 'secreto' => '' );
					}
					// Las claves no se vuelven a mostrar: el campo vacío conserva la guardada
					$secreto = fn( $k ) => '' !== trim( (string) ( $in[ $k ] ?? '' ) ) ? sanitize_text_field( $in[ $k ] ) : ( $prev[ $k ] ?? '' );
					return array(
						'url'     => untrailingslashit( esc_url_raw( trim( (string) ( $in['url'] ?? '' ) ) ) ),
						'clave'   => $secreto( 'clave' ),
						'secreto' => $secreto( 'secreto' ),
					);
				},
			)
		);
	}
);

// Al desconectar se olvida también el estado: si se vuelve a conectar, la primera lectura vuelve a ser prudente
add_action(
	'update_option_' . TNM_SYNC_OPT,
	function ( $old, $new ) {
		if ( empty( $new['url'] ) || empty( $new['clave'] ) ) {
			delete_option( TNM_SYNC_ESTADO );
			wp_clear_scheduled_hook( 'tnm_sincronizar' );
		}
	},
	10,
	2
);

/**
 * Campos de la conexión, dentro del formulario de Ajustes.
 */
function tnm_sync_ajustes_campos() {
	$o     = get_option( TNM_SYNC_OPT, array() );
	$o     = is_array( $o ) ? $o : array();
	$c     = tnm_sync_conf();
	$n     = TNM_SYNC_OPT;
	$fijo  = fn( $k ) => defined( 'TNM_PLATAFORMAS_' . strtoupper( $k ) );
	$clave = fn( $k ) => $fijo( $k ) ? 'Definida en wp-config.php' : ( ! empty( $o[ $k ] ) ? '•••••••• guardada (escribe otra para cambiarla)' : '' );
	?>
	<h2 id="tnm-plataformas">Sincronización con Plataformas</h2>
	<p>Las fichas de las máquinas (descripción, características, fotos, PDF y modelo 3D) se escriben en <b>Plataformas</b> y esta web las copia sola: cada 15 minutos y en cuanto Plataformas avisa de un cambio. Mientras está conectada, esas máquinas no se editan aquí.</p>
	<p class="description"><b>Antes de conectarla por primera vez</b>, importa en Plataformas lo que ya está publicado aquí (Plataformas → Usuarios → «Importar lo que ya hay en la web»), para que no se pierda nada. Aun así, la primera sincronización no vacía ningún dato que esta web tenga y Plataformas no.</p>
	<table class="form-table" role="presentation">
		<tr><th><label for="tnm-sync-url">Dirección de Plataformas</label></th><td><input id="tnm-sync-url" class="regular-text code" type="url" name="<?php echo esc_attr( $n ); ?>[url]" value="<?php echo esc_attr( $fijo( 'url' ) ? $c['url'] : ( $o['url'] ?? '' ) ); ?>" placeholder="https://plataformas.app.tecnotron.es"<?php disabled( $fijo( 'url' ) ); ?>></td></tr>
		<tr><th><label for="tnm-sync-clave">Clave del catálogo</label></th><td><input id="tnm-sync-clave" class="regular-text code" type="password" autocomplete="new-password" name="<?php echo esc_attr( $n ); ?>[clave]" value="" placeholder="<?php echo esc_attr( $clave( 'clave' ) ); ?>"<?php disabled( $fijo( 'clave' ) ); ?>><p class="description">Una de las claves de <code>CATALOG_API_KEYS</code> en Plataformas.</p></td></tr>
		<tr><th><label for="tnm-sync-secreto">Secreto de los avisos</label></th><td><input id="tnm-sync-secreto" class="regular-text code" type="password" autocomplete="new-password" name="<?php echo esc_attr( $n ); ?>[secreto]" value="" placeholder="<?php echo esc_attr( $clave( 'secreto' ) ); ?>"<?php disabled( $fijo( 'secreto' ) ); ?>>
			<p class="description">El mismo que <code>WEB_WEBHOOK_SECRET</code> en Plataformas. Allí, <code>WEB_WEBHOOK_URL</code> tiene que ser <code><?php echo esc_html( rest_url( 'tecnotron/v1/sincronizar' ) ); ?></code>. Sin secreto la web se pone al día cada 15 minutos.</p></td></tr>
		<?php if ( tnm_sync_activa() && ! $fijo( 'url' ) ) : ?>
		<tr><th>Desconectar</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[desconectar]" value="1"> Olvidar la conexión: las máquinas vuelven a editarse aquí, con los últimos datos recibidos</label></td></tr>
		<?php endif; ?>
	</table>
	<?php
	tnm_sync_panel();
}

/**
 * Estado de la última sincronización con el botón «Sincronizar ahora».
 */
function tnm_sync_panel() {
	if ( ! tnm_sync_activa() ) {
		echo '<p class="tnm-sync-estado">Sin conectar: las máquinas se editan en esta web.</p>';
		return;
	}
	$e     = tnm_sync_estado();
	$r     = is_array( $e['resumen'] ) ? $e['resumen'] : array();
	$hace  = fn( $iso ) => $iso ? sprintf( 'hace %s', human_time_diff( strtotime( $iso ) ) ) : 'nunca';
	$boton = wp_nonce_url( admin_url( 'admin-post.php?action=tnm_sincronizar' ), 'tnm_sincronizar' );
	echo '<div class="tnm-sync-estado' . ( $e['error'] ? ' tnm-sync-error' : '' ) . '">';
	printf( '<p><b>Última sincronización correcta:</b> %s', esc_html( $hace( $e['ok'] ) ) );
	if ( $r ) {
		printf( ' — %d creadas, %d actualizadas, %d sin cambios, %d retiradas', (int) ( $r['creadas'] ?? 0 ), (int) ( $r['actualizadas'] ?? 0 ), (int) ( $r['sin_cambios'] ?? 0 ), (int) ( $r['retiradas'] ?? 0 ) );
	}
	echo '.</p>';
	if ( $e['error'] ) {
		printf( '<p><b>Último intento (%s):</b> %s</p>', esc_html( $hace( $e['intento'] ) ), esc_html( $e['error'] ) );
	}
	if ( ! empty( $r['avisos'] ) ) {
		echo '<details><summary>' . esc_html( count( $r['avisos'] ) . ' aviso(s)' ) . '</summary><ul>';
		foreach ( array_slice( $r['avisos'], 0, 50 ) as $a ) {
			echo '<li>' . esc_html( $a ) . '</li>';
		}
		echo '</ul></details>';
	}
	printf( '<p><a class="button" href="%s">Sincronizar ahora</a> <a class="button-link" href="%s" target="_blank" rel="noopener">Abrir Plataformas</a></p>', esc_url( $boton ), esc_url( tnm_sync_conf()['url'] ) );
	echo '</div>';
}

// «Sincronizar ahora»: se hace en esta misma petición y se vuelve a Ajustes con el resultado.
add_action(
	'admin_post_tnm_sincronizar',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'tnm_sincronizar' );
		$r = tnm_sincronizar();
		set_transient( 'tnm_sync_mensaje_' . get_current_user_id(), is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'success', sprintf( 'Sincronizado con Plataformas: %d creadas, %d actualizadas, %d sin cambios, %d retiradas.', $r['creadas'], $r['actualizadas'], $r['sin_cambios'], $r['retiradas'] ) ), 60 );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php?post_type=' . TNM_CPT . '&page=tnm-ajustes' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-' . TNM_CPT, TNM_CPT . '_page_tnm-ajustes' ), true ) ) {
			return;
		}
		$msg = get_transient( 'tnm_sync_mensaje_' . get_current_user_id() );
		if ( $msg ) {
			delete_transient( 'tnm_sync_mensaje_' . get_current_user_id() );
			printf( '<div class="notice notice-%s is-dismissible tnm-sync-resultado"><p>%s</p></div>', esc_attr( $msg[0] ), esc_html( $msg[1] ) );
		}
		if ( 'edit-' . TNM_CPT === $screen->id && tnm_sync_activa() ) {
			$e = tnm_sync_estado();
			printf(
				'<div class="notice notice-info tnm-sync-lista"><p><b>El catálogo se edita en Plataformas</b> y esta web se actualiza sola (última vez: %s).%s <a href="%s" target="_blank" rel="noopener">Abrir Plataformas</a> · <a href="%s">Sincronizar ahora</a></p></div>',
				esc_html( $e['ok'] ? 'hace ' . human_time_diff( strtotime( $e['ok'] ) ) : 'nunca' ),
				$e['error'] ? ' <b>Último error:</b> ' . esc_html( $e['error'] ) : '',
				esc_url( tnm_sync_conf()['url'] ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tnm_sincronizar' ), 'tnm_sincronizar' ) )
			);
		}
	}
);

// Pantalla de una máquina sincronizada: sus cajas se ven, pero no se tocan (la sincronización lo sustituiría).
add_filter(
	'admin_body_class',
	function ( $classes ) {
		$screen = get_current_screen();
		$post   = get_post();
		if ( $screen && TNM_CPT === $screen->id && $post && tnm_sincronizada( $post->ID ) ) {
			$classes .= ' tnm-sincronizada';
		}
		return $classes;
	}
);

add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( TNM_CPT === $post->post_type && tnm_sincronizada( $post->ID ) ) {
			$editar = (string) get_post_meta( $post->ID, '_tnm_plataformas_editar', true );
			if ( $editar ) {
				$actions['tnm_plataformas'] = sprintf( '<a href="%s" target="_blank" rel="noopener">Editar en Plataformas</a>', esc_url( $editar ) );
			}
		}
		return $actions;
	},
	20,
	2
);
