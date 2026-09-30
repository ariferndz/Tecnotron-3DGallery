<?php
/**
 * Solicitudes de presupuesto: recepción del formulario, correo al equipo comercial y copia en el panel.
 *
 * Contra el correo basura: campo trampa oculto, tiempo mínimo de relleno y límite de envíos por IP.
 * No usa nonce a propósito: con caché de página caducaría y el formulario fallaría a los visitantes.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_nopriv_tnm_solicitud', 'tnm_recibir_solicitud' );
add_action( 'admin_post_tnm_solicitud', 'tnm_recibir_solicitud' );

function tnm_recibir_solicitud() {
	$json   = isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ), 'application/json' );
	$volver = isset( $_POST['volver'] ) ? esc_url_raw( wp_unslash( $_POST['volver'] ) ) : home_url( '/' );
	$fail   = function ( $msg, $code = 400 ) use ( $json, $volver ) {
		if ( $json ) {
			wp_send_json( array( 'ok' => false, 'error' => $msg ), $code );
		}
		wp_safe_redirect( add_query_arg( 'solicitud', 'error', $volver ) . '#tnm-contacto' );
		exit;
	};

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- ver cabecera del archivo.
	if ( ! empty( $_POST['web'] ) ) {
		$fail( 'No se pudo enviar.' ); // campo trampa relleno: un robot
	}
	$ts = isset( $_POST['ts'] ) ? (int) $_POST['ts'] : 0;
	if ( ! $ts || time() - $ts < 3 ) {
		$fail( 'Espera unos segundos y vuelve a enviarlo.' );
	}
	$ip_key = 'tnm_sol_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$count  = (int) get_transient( $ip_key );
	// Máximo de solicitudes por hora desde una misma IP. Se puede cambiar con add_filter( 'tnm_solicitudes_por_hora', … ).
	if ( $count >= (int) apply_filters( 'tnm_solicitudes_por_hora', 5 ) ) {
		$fail( 'Has enviado varias solicitudes seguidas. Inténtalo más tarde o llámanos.', 429 );
	}

	$d = array(
		'nombre'   => sanitize_text_field( wp_unslash( $_POST['nombre'] ?? '' ) ),
		'email'    => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		'telefono' => sanitize_text_field( wp_unslash( $_POST['telefono'] ?? '' ) ),
		'ciudad'   => sanitize_text_field( wp_unslash( $_POST['ciudad'] ?? '' ) ),
		'mensaje'  => sanitize_textarea_field( wp_unslash( $_POST['mensaje'] ?? '' ) ),
	);
	$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['maquinas'] ?? '' ) ) ) ) );
	$consent = ! empty( $_POST['consentimiento'] );
	// phpcs:enable

	if ( strlen( $d['nombre'] ) < 2 || ! is_email( $d['email'] ) || strlen( preg_replace( '/\D/', '', $d['telefono'] ) ) < 9 || strlen( $d['ciudad'] ) < 2 || strlen( $d['mensaje'] ) < 3 ) {
		$fail( 'Revisa los campos obligatorios.' );
	}
	if ( ! $consent ) {
		$fail( 'Debes aceptar el tratamiento de datos.' );
	}
	$maquinas = array();
	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( $p && TNM_CPT === $p->post_type && 'publish' === $p->post_status ) {
			$maquinas[ $id ] = get_the_title( $p );
		}
	}

	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

	$lista = $maquinas ? implode( ', ', $maquinas ) : '—';
	$sol_id = 0;
	if ( tnm_opt( 'guardar' ) ) {
		$sol_id = wp_insert_post(
			array(
				'post_type'    => TNM_SOL,
				'post_status'  => 'private',
				'post_title'   => $d['nombre'] . ' · ' . $d['ciudad'],
				'post_content' => $d['mensaje'],
			)
		);
		if ( $sol_id && ! is_wp_error( $sol_id ) ) {
			foreach ( array( 'email', 'telefono', 'ciudad' ) as $k ) {
				update_post_meta( $sol_id, '_tnm_' . $k, $d[ $k ] );
			}
			update_post_meta( $sol_id, '_tnm_maquinas', array_keys( $maquinas ) );
			update_post_meta( $sol_id, '_tnm_origen', $volver );
		}
	}

	$to      = array_filter( array_map( 'trim', explode( ',', (string) tnm_opt( 'email' ) ) ), 'is_email' );
	$subject = sprintf( 'Solicitud de presupuesto: %s (%s)', $d['nombre'], $lista );
	$lines   = array(
		'Nueva solicitud de presupuesto desde el catálogo web.',
		'',
		'Máquinas: ' . $lista,
		'Nombre: ' . $d['nombre'],
		'Correo: ' . $d['email'],
		'Teléfono: ' . $d['telefono'],
		'Provincia / ciudad: ' . $d['ciudad'],
		'',
		'Mensaje:',
		$d['mensaje'],
		'',
		'Página de origen: ' . $volver,
	);
	foreach ( $maquinas as $id => $name ) {
		$lines[] = $name . ': ' . get_permalink( $id );
	}
	if ( $sol_id && ! is_wp_error( $sol_id ) ) {
		$lines[] = '';
		$lines[] = 'En el panel: ' . admin_url( 'post.php?post=' . $sol_id . '&action=edit' );
	}
	$sent = $to ? wp_mail( $to, $subject, implode( "\n", $lines ), array( 'Reply-To: ' . $d['nombre'] . ' <' . $d['email'] . '>' ) ) : false;
	if ( ! $sent && ! $sol_id ) {
		$fail( 'No se pudo enviar. Llámanos o escríbenos directamente.', 500 );
	}

	if ( $json ) {
		wp_send_json( array( 'ok' => true ) );
	}
	wp_safe_redirect( add_query_arg( 'solicitud', 'ok', $volver ) . '#tnm-contacto' );
	exit;
}

/* ---------- panel: listado y detalle de solicitudes ---------- */

add_filter(
	'manage_' . TNM_SOL . '_posts_columns',
	function () {
		return array( 'cb' => '<input type="checkbox">', 'title' => 'Nombre · ciudad', 'tnm_contacto' => 'Contacto', 'tnm_maquinas' => 'Máquinas', 'date' => 'Fecha' );
	}
);
add_action(
	'manage_' . TNM_SOL . '_posts_custom_column',
	function ( $col, $id ) {
		if ( 'tnm_contacto' === $col ) {
			$e = get_post_meta( $id, '_tnm_email', true );
			printf( '<a href="mailto:%1$s">%1$s</a><br>%2$s', esc_attr( $e ), esc_html( get_post_meta( $id, '_tnm_telefono', true ) ) );
		}
		if ( 'tnm_maquinas' === $col ) {
			$names = array_map( 'get_the_title', (array) get_post_meta( $id, '_tnm_maquinas', true ) );
			echo esc_html( implode( ', ', array_filter( $names ) ) ?: '—' );
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_' . TNM_SOL,
	function () {
		add_meta_box(
			'tnm_sol',
			'Datos de la solicitud',
			function ( $post ) {
				$maq  = array_filter( (array) get_post_meta( $post->ID, '_tnm_maquinas', true ) );
				$rows = array(
					'Correo'             => '<a href="mailto:' . esc_attr( get_post_meta( $post->ID, '_tnm_email', true ) ) . '">' . esc_html( get_post_meta( $post->ID, '_tnm_email', true ) ) . '</a>',
					'Teléfono'           => esc_html( get_post_meta( $post->ID, '_tnm_telefono', true ) ),
					'Provincia / ciudad' => esc_html( get_post_meta( $post->ID, '_tnm_ciudad', true ) ),
					'Máquinas'           => implode( ', ', array_map( fn( $id ) => '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>', $maq ) ) ?: '—',
					'Mensaje'            => nl2br( esc_html( $post->post_content ) ),
					'Enviada desde'      => '<a href="' . esc_url( get_post_meta( $post->ID, '_tnm_origen', true ) ) . '" target="_blank">' . esc_html( get_post_meta( $post->ID, '_tnm_origen', true ) ) . '</a>',
					'Fecha'              => esc_html( get_the_date( 'j/n/Y H:i', $post ) ),
				);
				echo '<table class="form-table">';
				foreach ( $rows as $k => $v ) {
					echo '<tr><th>' . esc_html( $k ) . '</th><td>' . $v . '</td></tr>'; // phpcs:ignore
				}
				echo '</table>';
			},
			TNM_SOL,
			'normal',
			'high'
		);
		remove_meta_box( 'submitdiv', TNM_SOL, 'side' );
	}
);
