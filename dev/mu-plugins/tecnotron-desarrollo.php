<?php
/**
 * Sólo para el entorno de desarrollo (docker compose): lo carga WordPress automáticamente desde mu-plugins.
 * No copiar a la web real.
 *
 * @package TecnotronMaquinas
 */

// Las pruebas envían solicitudes seguidas desde la misma IP: se sube el límite antispam.
add_filter( 'tnm_solicitudes_por_hora', fn() => 1000 );

// Pruebas: ?cabecera=fija o ?cabecera=absoluta simulan un tema cuya cabecera se superpone a la página
// (como la cabecera fija de Impreza en tecnotron.es).
add_action(
	'wp_head',
	function () {
		$modo = sanitize_key( wp_unslash( $_GET['cabecera'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( in_array( $modo, array( 'fija', 'absoluta' ), true ) ) {
			$pos = 'fija' === $modo ? 'fixed' : 'absolute';
			echo "<style>header.wp-block-template-part{position:$pos;top:0;left:0;right:0;z-index:50;min-height:96px;background:rgba(20,20,24,.96)}.admin-bar header.wp-block-template-part{top:32px}</style>\n";
		}
	}
);

// Dentro del contenedor, WordPress no se alcanza a sí mismo en localhost:8080 (ése es el puerto de fuera): las tareas
// programadas que lanza una visita (por ejemplo, la sincronización tras un aviso de Plataformas) van al puerto 80.
add_filter(
	'cron_request',
	function ( $r ) {
		$r['url'] = preg_replace( '#^https?://[^/]+#', 'http://127.0.0.1', $r['url'] );
		return $r;
	}
);
