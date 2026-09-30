<?php
/**
 * Prepara un WordPress de pruebas con el catálogo completo.
 *
 *   php tests/datos-demo.php <carpeta de WordPress> [dirección del sitio]
 *   php tests/datos-demo.php /var/www/html http://localhost:8080
 *
 * · Si WordPress aún no está instalado, lo instala (usuario admin / admin123) con enlaces permanentes /%postname%/.
 * · Activa el plugin (debe estar en wp-content/plugins/tecnotron-maquinas).
 * · Importa las 42 máquinas de data/maquinas-configurador.csv.
 * · Sube a cada máquina con GLB en modelos/ (Block Car, Peppa Bus, Bluey Family Car) sus 4 imágenes y su modelo.
 * · Añade dos fichas PDF de ejemplo a la Grúa de tren.
 *
 * Se puede ejecutar varias veces: si el catálogo ya existe, no duplica nada.
 * NO usar en la web real: está pensado para el entorno de desarrollo y las pruebas.
 *
 * @package TecnotronMaquinas
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'Sólo desde la línea de órdenes.' );
}
// Este archivo se ejecuta en el ámbito global de WordPress: nombres con prefijo para no pisar sus variables ($wp, $post…).
$tnm_demo_wp  = rtrim( $argv[1] ?? '', '/' );
$tnm_demo_url = rtrim( $argv[2] ?? 'http://localhost:8080', '/' );
if ( ! $tnm_demo_wp || ! file_exists( $tnm_demo_wp . '/wp-load.php' ) ) {
	fwrite( STDERR, "Uso: php tests/datos-demo.php <carpeta de WordPress> [dirección del sitio]\n" );
	exit( 1 );
}

$tnm_demo_host          = parse_url( $tnm_demo_url );
$_SERVER['HTTP_HOST']   = $tnm_demo_host['host'] . ( isset( $tnm_demo_host['port'] ) ? ':' . $tnm_demo_host['port'] : '' );
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = $tnm_demo_host['host'];
$_SERVER['HTTPS']       = ( 'https' === ( $tnm_demo_host['scheme'] ?? 'http' ) ) ? 'on' : '';

if ( in_array( '--instalar', $argv, true ) ) {
	// Paso 1, en un proceso aparte: instalar WordPress si hace falta (en este modo WordPress no carga plugins).
	define( 'WP_INSTALLING', true );
	require $tnm_demo_wp . '/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	if ( ! is_blog_installed() ) {
		wp_install( 'Tecnotron (demo)', 'admin', 'admin@example.com', true, '', 'admin123' );
		update_option( 'siteurl', $tnm_demo_url );
		update_option( 'home', $tnm_demo_url );
		update_option( 'permalink_structure', '/%postname%/' );
		echo "WordPress instalado en $tnm_demo_url (admin / admin123)\n";
	}
	exit( 0 );
}
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $tnm_demo_wp ) . ' ' . escapeshellarg( $tnm_demo_url ) . ' --instalar', $tnm_demo_code );
if ( 0 !== $tnm_demo_code ) {
	exit( $tnm_demo_code );
}

// Paso 2: WordPress normal, con sus plugins.
require $tnm_demo_wp . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

tnm_datos_demo( dirname( __DIR__ ) . '/modelos' );

/**
 * Activa el plugin y carga el catálogo de demostración.
 *
 * @param string $modelos Carpeta con los GLB y sus imágenes.
 */
function tnm_datos_demo( $modelos ) {
	wp_set_current_user( 1 );

	$plugin = 'tecnotron-maquinas/tecnotron-maquinas.php';
	if ( ! is_plugin_active( $plugin ) ) {
		$r = activate_plugin( $plugin ); // Carga el plugin y registra sus tipos de contenido.
		if ( is_wp_error( $r ) ) {
			fwrite( STDERR, 'No se pudo activar el plugin: ' . $r->get_error_message() . "\n" );
			exit( 1 );
		}
		echo "Plugin activado\n";
	}

	if ( get_page_by_path( 'diggy', OBJECT, TNM_CPT ) ) {
		echo "El catálogo ya existe: no se importa de nuevo\n";
	} else {
		$res = tnm_importar_csv( TNM_DIR . 'data/maquinas-configurador.csv', false, 'publish' );
		echo 'Máquinas importadas: ', count( $res ), "\n";

		$subir = function ( $file, $post_id ) {
			$tmp = wp_tempnam( basename( $file ) );
			copy( $file, $tmp );
			$id = media_handle_sideload( array( 'name' => basename( $file ), 'tmp_name' => $tmp ), $post_id );
			if ( is_wp_error( $id ) ) {
				fwrite( STDERR, 'Error al subir ' . basename( $file ) . ': ' . $id->get_error_message() . "\n" );
				return 0;
			}
			return $id;
		};

		foreach ( glob( $modelos . '/*.glb' ) as $glb ) {
			$slug = basename( $glb, '.glb' );
			$p    = get_page_by_path( $slug, OBJECT, TNM_CPT );
			if ( ! $p ) {
				echo "· $slug.glb: no hay ninguna máquina con ese slug\n";
				continue;
			}
			$imgs = array();
			foreach ( array( 1, 2, 3, 4 ) as $i ) {
				if ( file_exists( "$modelos/$slug-$i.png" ) ) {
					$imgs[] = $subir( "$modelos/$slug-$i.png", $p->ID );
				}
			}
			$imgs = array_values( array_filter( $imgs ) );
			if ( $imgs ) {
				set_post_thumbnail( $p->ID, $imgs[0] );
				update_post_meta( $p->ID, '_tnm_galeria', implode( ',', array_slice( $imgs, 1 ) ) );
			}
			update_post_meta( $p->ID, '_tnm_glb_id', $subir( $glb, $p->ID ) );
			echo "✓ $slug: ", count( $imgs ), " imágenes y modelo 3D\n";
		}

		// Un PDF mínimo de ejemplo para la Grúa de tren
		$pdf = sys_get_temp_dir() . '/especificaciones-kq546.pdf';
		file_put_contents( $pdf, "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 144]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj 4 0 obj<</Length 44>>stream\nBT /F1 18 Tf 20 70 Td (Ficha KQ546) Tj ET\nendstream endobj 5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF" );
		$grua = get_page_by_path( 'grua-de-tren', OBJECT, TNM_CPT );
		if ( $grua ) {
			$pid = $subir( $pdf, $grua->ID );
			update_post_meta(
				$grua->ID,
				'_tnm_fichas',
				array(
					array( 'label' => 'Especificaciones técnicas (ITA)', 'id' => $pid, 'url' => '' ),
					array( 'label' => 'Specifications (ENG)', 'id' => $pid, 'url' => '' ),
				)
			);
			echo "✓ grua-de-tren: 2 fichas PDF\n";
		}
	}

	flush_rewrite_rules();
	echo 'Catálogo: ', get_post_type_archive_link( TNM_CPT ), "\n";
}
