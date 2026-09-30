<?php
/**
 * Visor 3D a pantalla completa: /maquinas/<máquina>/visor/ (destino del QR, cartelería y ferias).
 * Página propia, sin cabecera ni pie del tema, para que el modelo ocupe toda la pantalla del móvil.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

$tnm_m  = tnm_maquina( get_queried_object() );
$tnm_im = $tnm_m['imagenes'] ? wp_get_attachment_image_url( $tnm_m['imagenes'][0], 'large' ) : '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0d0d10">
<title><?php echo esc_html( $tnm_m['nombre'] . ' · 3D · ' . get_bloginfo( 'name' ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( TNM_URL . 'assets/css/tecnotron-maquinas.css?ver=' . TNM_VERSION ); ?>">
<script type="module" src="<?php echo esc_url( TNM_URL . 'assets/vendor/model-viewer.min.js?ver=' . TNM_MV_VERSION ); ?>"></script>
</head>
<body class="tnm tnm-visor-page" style="--c:<?php echo esc_attr( $tnm_m['cat']['color'] ); ?>">
<header class="tnm-visor-top">
	<a class="tnm-icon-btn" href="<?php echo esc_url( $tnm_m['url'] ); ?>" aria-label="Ver la ficha completa"><?php echo tnm_icon( 'left' ); // phpcs:ignore ?></a>
	<div class="tnm-visor-title"><b><?php echo esc_html( $tnm_m['nombre'] ); ?></b><?php if ( $tnm_m['tiene_medidas'] ) : ?><span><?php echo esc_html( tnm_dims( $tnm_m ) ); ?></span><?php endif; ?></div>
	<a class="tnm-btn tnm-primary tnm-sm" href="<?php echo esc_url( $tnm_m['url'] . '#tnm-contacto' ); ?>">Presupuesto</a>
</header>
<?php if ( $tnm_m['glb'] ) : ?>
<model-viewer class="tnm-visor" src="<?php echo esc_url( $tnm_m['glb'] ); ?>" poster="<?php echo esc_url( $tnm_im ); ?>" alt="<?php echo esc_attr( 'Modelo 3D de ' . $tnm_m['nombre'] ); ?>"
	camera-controls touch-action="pan-y" auto-rotate auto-rotate-delay="2500" rotation-per-second="14deg" shadow-intensity="1" shadow-softness=".8"
	environment-image="neutral" exposure="1.05" camera-orbit="-35deg 72deg auto" ar ar-modes="webxr scene-viewer quick-look" ar-scale="fixed">
	<button slot="ar-button" class="tnm-ar-btn" type="button"><?php echo tnm_icon( 'ar' ); // phpcs:ignore ?>Ver en tu local · tamaño real</button>
	<div slot="progress-bar" class="tnm-mv-progress"><i></i></div>
</model-viewer>
<p class="tnm-visor-help">Gira el modelo con el dedo. Pulsa «Ver en tu local» y apunta al suelo para colocar la máquina a tamaño real.</p>
<script>
document.querySelector('model-viewer').addEventListener('progress', e => { const b = document.querySelector('.tnm-mv-progress i'); if (b) b.style.width = Math.round(e.detail.totalProgress * 100) + '%'; if (e.detail.totalProgress >= 1) document.querySelector('.tnm-mv-progress')?.remove(); });
</script>
<?php else : ?>
<div class="tnm-visor-empty"><?php echo tnm_placeholder( $tnm_m ); // phpcs:ignore ?><p><b>Modelo 3D en preparación</b><br><a href="<?php echo esc_url( $tnm_m['url'] ); ?>">Ver la ficha de la máquina</a></p></div>
<?php endif; ?>
</body>
</html>
