<?php
/**
 * Sólo para el entorno de desarrollo (docker compose): lo carga WordPress automáticamente desde mu-plugins.
 * No copiar a la web real.
 *
 * @package TecnotronMaquinas
 */

// Las pruebas envían solicitudes seguidas desde la misma IP: se sube el límite antispam.
add_filter( 'tnm_solicitudes_por_hora', fn() => 1000 );
