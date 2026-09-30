<?php
/**
 * Sincronización con Plataformas (plataformas.app.tecnotron.es).
 *
 * La ficha de cada máquina (textos, características, fotos, PDF y modelo 3D) se escribe en Plataformas y esta web
 * la refleja: lee GET /api/catalog cada 15 minutos y en cuanto Plataformas avisa de un cambio (webhook firmado).
 * Mientras está conectada, lo que se cambie aquí en una máquina sincronizada se sustituye en la siguiente lectura.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

const TNM_SYNC_OPT    = 'tnm_plataformas';
const TNM_SYNC_ESTADO = 'tnm_sync_estado';

/**
 * Conexión con Plataformas. Las constantes de wp-config.php tienen prioridad sobre Máquinas → Ajustes.
 *
 * @return array{url:string,clave:string,secreto:string}
 */
function tnm_sync_conf() {
	$o = get_option( TNM_SYNC_OPT, array() );
	$o = is_array( $o ) ? $o : array();
	$c = array(
		'url'     => defined( 'TNM_PLATAFORMAS_URL' ) ? TNM_PLATAFORMAS_URL : ( $o['url'] ?? '' ),
		'clave'   => defined( 'TNM_PLATAFORMAS_CLAVE' ) ? TNM_PLATAFORMAS_CLAVE : ( $o['clave'] ?? '' ),
		'secreto' => defined( 'TNM_PLATAFORMAS_SECRETO' ) ? TNM_PLATAFORMAS_SECRETO : ( $o['secreto'] ?? '' ),
	);
	return array(
		'url'     => untrailingslashit( esc_url_raw( trim( (string) $c['url'] ) ) ),
		'clave'   => trim( (string) $c['clave'] ),
		'secreto' => trim( (string) $c['secreto'] ),
	);
}

/** ¿Está conectada? Con la dirección y la clave basta para leer; el secreto sólo hace falta para los avisos. */
function tnm_sync_activa() {
	$c = tnm_sync_conf();
	return '' !== $c['url'] && '' !== $c['clave'];
}

/**
 * ¿La edita Plataformas? Sólo mientras la web está conectada: al desconectarla, todo vuelve a editarse aquí.
 *
 * @param int $post_id Máquina.
 * @return bool
 */
function tnm_sincronizada( $post_id ) {
	return tnm_sync_activa() && '' !== (string) get_post_meta( $post_id, '_tnm_plataformas_id', true );
}

/** Resultado de la última sincronización. */
function tnm_sync_estado() {
	$e = get_option( TNM_SYNC_ESTADO, array() );
	return wp_parse_args( is_array( $e ) ? $e : array(), array( 'ok' => '', 'intento' => '', 'error' => '', 'resumen' => array(), 'primera' => false ) );
}

/* ---------- cuándo se sincroniza: cada 15 minutos y al recibir un aviso de Plataformas ---------- */

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['tnm_15_min'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Cada 15 minutos (Tecnotron Máquinas)' );
		return $s;
	}
);
add_action( 'tnm_sincronizar', 'tnm_sincronizar_cron' );
add_action( 'tnm_sincronizar_ya', 'tnm_sincronizar_cron' );
/** Tarea programada: sin salida. */
function tnm_sincronizar_cron() {
	tnm_sincronizar();
}
add_action(
	'init',
	function () {
		$programada = wp_next_scheduled( 'tnm_sincronizar' );
		if ( tnm_sync_activa() && ! $programada ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'tnm_15_min', 'tnm_sincronizar' );
		} elseif ( ! tnm_sync_activa() && $programada ) {
			wp_clear_scheduled_hook( 'tnm_sincronizar' );
		}
	}
);

// Aviso de Plataformas: POST /wp-json/tecnotron/v1/sincronizar, firmado con el secreto compartido.
// Sólo dice que algo cambió; la sincronización se hace aparte para contestar enseguida.
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'tecnotron/v1',
			'/sincronizar',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $req ) {
					$c = tnm_sync_conf();
					if ( ! tnm_sync_activa() || '' === $c['secreto'] ) {
						return new WP_Error( 'tnm_sync_off', 'La sincronización con Plataformas no está configurada en esta web.', array( 'status' => 503 ) );
					}
					$esperada = 'sha256=' . hash_hmac( 'sha256', $req->get_body(), $c['secreto'] );
					if ( ! hash_equals( $esperada, (string) $req->get_header( 'x_plataformas_signature' ) ) ) {
						return new WP_Error( 'tnm_firma', 'Firma no válida.', array( 'status' => 401 ) );
					}
					if ( ! wp_next_scheduled( 'tnm_sincronizar_ya' ) ) {
						wp_schedule_single_event( time(), 'tnm_sincronizar_ya' );
					}
					spawn_cron();
					return new WP_REST_Response( array( 'ok' => true ), 202 );
				},
			)
		);
	}
);

/* ---------- descargas desde Plataformas ---------- */

/**
 * WordPress sólo descarga de direcciones públicas y de los puertos 80, 443 y 8080. Plataformas es una dirección
 * que ha configurado un administrador: se admite aunque esté en la red interna o use otro puerto.
 *
 * @param string $host Servidor de la petición.
 * @return bool
 */
function tnm_sync_es_plataformas( $host ) {
	$h = wp_parse_url( tnm_sync_conf()['url'], PHP_URL_HOST );
	return $h && tnm_sync_activa() && strtolower( (string) $host ) === strtolower( $h );
}
add_filter( 'http_request_host_is_external', fn( $externo, $host ) => $externo || tnm_sync_es_plataformas( $host ), 10, 2 );
add_filter(
	'http_allowed_safe_ports',
	function ( $puertos, $host ) {
		$puerto = wp_parse_url( tnm_sync_conf()['url'], PHP_URL_PORT );
		if ( $puerto && tnm_sync_es_plataformas( $host ) ) {
			$puertos[] = (int) $puerto;
		}
		return $puertos;
	},
	10,
	2
);

/**
 * Adjunto de la biblioteca de medios para un fichero de Plataformas. Allí cada fichero tiene un nombre único que no
 * se reescribe nunca, así que la misma dirección es el mismo fichero: se descarga una sola vez.
 *
 * @param string $url     Dirección del fichero.
 * @param int    $post_id Máquina a la que se adjunta.
 * @param string $nombre  Nombre del fichero en la biblioteca, sin extensión (p. ej. «block-car-2»).
 * @param string $titulo  Título del adjunto.
 * @return int|WP_Error
 */
function tnm_sync_adjunto( $url, $post_id, $nombre, $titulo ) {
	$url = esc_url_raw( (string) $url );
	if ( ! $url ) {
		return new WP_Error( 'tnm_url', 'dirección no válida' );
	}
	$previo = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_tnm_origen', 'meta_value' => $url ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $previo ) {
		return (int) $previo[0];
	}
	$tmp = download_url( $url, 120 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$id  = media_handle_sideload( array( 'name' => sanitize_file_name( $nombre . ( $ext ? '.' . $ext : '' ) ), 'tmp_name' => $tmp ), $post_id, $titulo );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return $id;
	}
	update_post_meta( $id, '_tnm_origen', $url );
	return (int) $id;
}

/* ---------- la sincronización ---------- */

/**
 * Lee el catálogo de Plataformas y actualiza las máquinas de la web.
 *
 * @param bool $forzar Rehacer también las que no han cambiado.
 * @return array|WP_Error Resumen: creadas, actualizadas, sin_cambios, retiradas y avisos.
 */
function tnm_sincronizar( $forzar = false ) {
	if ( ! tnm_sync_activa() ) {
		return new WP_Error( 'tnm_sync_off', 'Falta configurar la dirección y la clave de Plataformas.' );
	}
	if ( get_transient( 'tnm_sync_bloqueo' ) ) {
		// Llega un aviso mientras se sincroniza: se repite al terminar para no perder ese cambio
		update_option( 'tnm_sync_pendiente', 1, false );
		return new WP_Error( 'tnm_sync_ocupada', 'Ya hay una sincronización en marcha; se repetirá al terminar.' );
	}
	set_transient( 'tnm_sync_bloqueo', 1, 15 * MINUTE_IN_SECONDS );
	delete_option( 'tnm_sync_pendiente' );
	$GLOBALS['tnm_sincronizando'] = true;
	try {
		$r = tnm_sync_ejecutar( $forzar );
	} finally {
		$GLOBALS['tnm_sincronizando'] = false;
		delete_transient( 'tnm_sync_bloqueo' );
	}
	if ( get_option( 'tnm_sync_pendiente' ) && ! wp_next_scheduled( 'tnm_sincronizar_ya' ) ) {
		wp_schedule_single_event( time(), 'tnm_sincronizar_ya' );
	}
	return $r;
}

/** ¿Está en marcha una sincronización? (los GLB sólo se aceptan en la biblioteca con permiso de subida o desde aquí) */
function tnm_sincronizando() {
	return ! empty( $GLOBALS['tnm_sincronizando'] );
}

/**
 * @param bool $forzar Rehacer también las que no han cambiado.
 * @return array|WP_Error
 */
function tnm_sync_ejecutar( $forzar ) {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- puede estar deshabilitada
	}
	$c      = tnm_sync_conf();
	$estado = tnm_sync_estado();
	$estado['intento'] = gmdate( 'c' );
	$fallo  = function ( $msg ) use ( $estado ) {
		$estado['error'] = $msg;
		update_option( TNM_SYNC_ESTADO, $estado, false );
		return new WP_Error( 'tnm_sync', $msg );
	};

	$res = wp_remote_get( $c['url'] . '/api/catalog', array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $c['clave'], 'Accept' => 'application/json' ) ) );
	if ( is_wp_error( $res ) ) {
		return $fallo( 'No se pudo conectar con Plataformas: ' . $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 401 === $code || 403 === $code ) {
		return $fallo( 'Plataformas no acepta la clave del catálogo: revisa que sea una de las de CATALOG_API_KEYS.' );
	}
	if ( 200 !== $code ) {
		return $fallo( "Plataformas respondió $code al pedir el catálogo." );
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $json ) || ! isset( $json['machines'] ) || ! is_array( $json['machines'] ) ) {
		return $fallo( 'Plataformas no devolvió un catálogo válido.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// La primera vez no se vacía nada que la web tenga y Plataformas todavía no (si aún no se ha importado allí)
	$conservar = empty( $estado['primera'] );
	$resumen   = array( 'creadas' => 0, 'actualizadas' => 0, 'sin_cambios' => 0, 'retiradas' => 0, 'avisos' => array() );
	$vistas    = array();
	$maquinas  = array_values( array_filter( $json['machines'], fn( $m ) => is_array( $m ) && ! empty( $m['id'] ) && ! empty( $m['slug'] ) && ! empty( $m['name'] ) ) );
	$ids       = array_map( fn( $m ) => (string) $m['id'], $maquinas );
	foreach ( $maquinas as $m ) {
		$r = tnm_sync_maquina( $m, $forzar, $conservar, $ids );
		if ( $r['post_id'] ) {
			$vistas[] = $r['post_id'];
		}
		if ( isset( $resumen[ $r['estado'] ] ) ) {
			$resumen[ $r['estado'] ]++;
		}
		foreach ( $r['avisos'] as $a ) {
			$resumen['avisos'][] = sanitize_text_field( $m['name'] ) . ': ' . $a;
		}
	}

	// Las que venían de Plataformas y allí ya no están: se retiran de la web (borrador), nunca se borran
	$antes = get_posts( array( 'post_type' => TNM_CPT, 'post_status' => array( 'publish', 'future', 'pending', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_tnm_plataformas_id' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( array_diff( $antes, $vistas ) as $pid ) {
		wp_update_post( array( 'ID' => $pid, 'post_status' => 'draft' ) );
		$resumen['retiradas']++;
	}

	$estado = array_merge( $estado, array( 'ok' => gmdate( 'c' ), 'error' => '', 'resumen' => $resumen, 'primera' => true ) );
	update_option( TNM_SYNC_ESTADO, $estado, false );
	return $resumen;
}

/**
 * Máquina de la web que corresponde a una de Plataformas: la ya enlazada o, si no, la de la misma dirección.
 * Por dirección sólo se toma una que no esté enlazada con otra máquina que Plataformas siga teniendo: así, si allí se
 * recrea la base de datos (ids nuevos) o la web estuvo conectada a otro Plataformas, no se duplican las fichas.
 *
 * @param array    $m   Máquina de Plataformas.
 * @param string[] $ids Ids de todas las máquinas del catálogo recibido.
 * @return WP_Post|null
 */
function tnm_sync_buscar( $m, $ids = array() ) {
	$q = get_posts( array( 'post_type' => TNM_CPT, 'post_status' => 'any', 'posts_per_page' => 1, 'meta_key' => '_tnm_plataformas_id', 'meta_value' => (string) $m['id'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $q ) {
		return $q[0];
	}
	$p = get_page_by_path( sanitize_title( $m['slug'] ), OBJECT, TNM_CPT );
	if ( ! $p || 'trash' === $p->post_status ) {
		return null;
	}
	$enlazada = (string) get_post_meta( $p->ID, '_tnm_plataformas_id', true );
	return '' === $enlazada || ! in_array( $enlazada, $ids, true ) ? $p : null;
}

/**
 * Guarda un dato; vacío en Plataformas sólo borra lo de la web si no hay que conservarlo.
 *
 * @param int    $id        Máquina.
 * @param string $key       Metadato.
 * @param mixed  $val       Valor.
 * @param bool   $conservar Primera sincronización.
 */
function tnm_sync_meta( $id, $key, $val, $conservar ) {
	if ( '' === $val && $conservar && '' !== (string) get_post_meta( $id, $key, true ) ) {
		return;
	}
	update_post_meta( $id, $key, $val );
}

/**
 * Crea o actualiza una máquina con los datos de Plataformas.
 *
 * @param array $m         Máquina de Plataformas (GET /api/catalog).
 * @param bool  $forzar    Rehacerla aunque no haya cambiado.
 * @param bool  $conservar Primera sincronización: no vaciar lo que la web tenga.
 * @param array $ids       Ids de todas las máquinas del catálogo recibido.
 * @return array{post_id:int,estado:string,avisos:string[]}
 */
function tnm_sync_maquina( $m, $forzar, $conservar, $ids = array() ) {
	$firma = md5( (string) wp_json_encode( $m ) );
	$post  = tnm_sync_buscar( $m, $ids );
	// Sin cambios en Plataformas ni retoques aquí desde la última vez: nada que hacer
	if ( $post && ! $forzar && get_post_meta( $post->ID, '_tnm_plataformas_firma', true ) === $firma
		&& get_post_meta( $post->ID, '_tnm_plataformas_modificada', true ) === $post->post_modified_gmt ) {
		return array( 'post_id' => $post->ID, 'estado' => 'sin_cambios', 'avisos' => array() );
	}

	$avisos  = array();
	$slug    = sanitize_title( $m['slug'] );
	$nombre  = sanitize_text_field( $m['name'] );
	$desc    = trim( (string) ( $m['description'] ?? '' ) );
	$web     = is_array( $m['web'] ?? null ) ? $m['web'] : array();
	$postarr = array(
		'post_type'   => TNM_CPT,
		'post_title'  => $nombre,
		'post_name'   => $slug,
		'post_status' => empty( $web['publish'] ) ? 'draft' : 'publish',
		'menu_order'  => (int) ( $web['order'] ?? 0 ),
	);
	if ( '' !== $desc || ! $conservar || ! $post ) {
		$postarr['post_content'] = '' === $desc ? '' : tnm_texto_a_html( $desc );
	}
	if ( $post ) {
		$postarr['ID'] = $post->ID;
		$id            = wp_update_post( wp_slash( $postarr ), true );
	} else {
		$id = wp_insert_post( wp_slash( $postarr ), true );
	}
	if ( is_wp_error( $id ) ) {
		return array( 'post_id' => $post ? $post->ID : 0, 'estado' => 'actualizadas', 'avisos' => array( $id->get_error_message() ) );
	}
	if ( get_post_field( 'post_name', $id ) !== $slug && ! empty( $web['publish'] ) ) {
		$avisos[] = "la dirección «{$slug}» ya la usa otra página de la web; ha quedado en «" . get_post_field( 'post_name', $id ) . '»';
	}

	foreach ( array( 'codigo' => 'code', 'consumo' => 'power', 'alimentacion' => 'supply', 'conformidad' => 'conformity' ) as $k => $src ) {
		tnm_sync_meta( $id, '_tnm_' . $k, sanitize_text_field( (string) ( $m[ $src ] ?? '' ) ), $conservar );
	}
	foreach ( array( 'ancho' => 'w_cm', 'largo' => 'l_cm', 'alto' => 'h_cm', 'peso' => 'weight_kg' ) as $k => $src ) {
		tnm_sync_meta( $id, '_tnm_' . $k, is_numeric( $m[ $src ] ?? null ) ? (float) $m[ $src ] : '', $conservar );
	}
	update_post_meta( $id, '_tnm_destacada', empty( $web['featured'] ) ? 0 : 1 );

	$cat = sanitize_text_field( (string) ( $m['category'] ?? '' ) );
	if ( '' !== $cat ) {
		$term = get_term_by( 'name', $cat, TNM_TAX ) ?: get_term_by( 'slug', sanitize_title( $cat ), TNM_TAX );
		if ( ! $term ) {
			$r    = wp_insert_term( $cat, TNM_TAX );
			$term = is_wp_error( $r ) ? null : get_term( $r['term_id'], TNM_TAX );
		}
		if ( $term ) {
			wp_set_object_terms( $id, array( (int) $term->term_id ), TNM_TAX );
		}
	} elseif ( ! $conservar ) {
		wp_set_object_terms( $id, array(), TNM_TAX );
	}

	// Fotos: la primera es la imagen principal; el resto, la galería
	$fotos = array();
	$lista = array_values( array_filter( (array) ( $m['photos'] ?? array() ), 'is_string' ) );
	foreach ( $lista as $i => $u ) {
		$a = tnm_sync_adjunto( $u, $id, $slug . '-' . ( $i + 1 ), $nombre );
		if ( is_wp_error( $a ) ) {
			$avisos[] = 'foto ' . ( $i + 1 ) . ': ' . $a->get_error_message();
		} else {
			$fotos[] = $a;
		}
	}
	if ( $fotos ) {
		set_post_thumbnail( $id, $fotos[0] );
		update_post_meta( $id, '_tnm_galeria', implode( ',', array_slice( $fotos, 1 ) ) );
	} elseif ( ! $lista && ! $conservar ) {
		delete_post_thumbnail( $id );
		update_post_meta( $id, '_tnm_galeria', '' );
	}

	// Fichas PDF: un PDF que no se pueda traer se enlaza donde está
	$fichas = array();
	foreach ( (array) ( $m['docs'] ?? array() ) as $i => $d ) {
		if ( empty( $d['url'] ) ) {
			continue;
		}
		$label = sanitize_text_field( (string) ( $d['label'] ?? '' ) ) ?: 'Ficha técnica';
		$a     = tnm_sync_adjunto( $d['url'], $id, $slug . '-' . sanitize_title( $label ), $label );
		if ( is_wp_error( $a ) ) {
			$avisos[] = "PDF «{$label}»: " . $a->get_error_message();
			$fichas[] = array( 'label' => $label, 'id' => 0, 'url' => esc_url_raw( $d['url'] ) );
		} else {
			$fichas[] = array( 'label' => $label, 'id' => $a, 'url' => '' );
		}
	}
	if ( $fichas || ! $conservar ) {
		update_post_meta( $id, '_tnm_fichas', $fichas );
	}

	// Modelo 3D: el que Plataformas prepara para la web (en metros, girado y con las gráficas)
	$glb = is_array( $m['model'] ?? null ) ? (string) ( $m['model']['url'] ?? '' ) : '';
	if ( $glb ) {
		$a = tnm_sync_adjunto( $glb, $id, $slug, $nombre . ' (3D)' );
		if ( is_wp_error( $a ) ) {
			$avisos[] = 'modelo 3D: ' . $a->get_error_message();
		} else {
			update_post_meta( $id, '_tnm_glb_id', $a );
			update_post_meta( $id, '_tnm_glb_url', '' );
		}
	} elseif ( ! $conservar ) {
		update_post_meta( $id, '_tnm_glb_id', '' );
		update_post_meta( $id, '_tnm_glb_url', '' );
	}

	update_post_meta( $id, '_tnm_plataformas_id', sanitize_text_field( (string) $m['id'] ) );
	update_post_meta( $id, '_tnm_plataformas_editar', esc_url_raw( (string) ( $m['edit_url'] ?? '' ) ) );
	// Con algún fallo no se apunta la firma: la próxima vez se reintenta aunque Plataformas no haya cambiado
	update_post_meta( $id, '_tnm_plataformas_firma', $avisos ? '' : $firma );
	clean_post_cache( $id );
	update_post_meta( $id, '_tnm_plataformas_modificada', get_post_field( 'post_modified_gmt', $id ) );
	return array( 'post_id' => (int) $id, 'estado' => $post ? 'actualizadas' : 'creadas', 'avisos' => $avisos );
}
