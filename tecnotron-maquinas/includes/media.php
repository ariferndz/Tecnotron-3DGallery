<?php
/**
 * Permite subir modelos 3D (.glb) a la biblioteca de medios.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'upload_mimes',
	function ( $mimes ) {
		// La sincronización con Plataformas corre sin usuario (tarea programada o aviso): también puede traer modelos
		if ( current_user_can( 'upload_files' ) || tnm_sincronizando() ) {
			$mimes['glb'] = 'model/gltf-binary';
		}
		return $mimes;
	}
);

// WordPress comprueba el contenido real del archivo: un GLB empieza por «glTF».
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename ) {
		if ( 'glb' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) && is_readable( $file ) ) {
			$fh    = fopen( $file, 'rb' );
			$magic = $fh ? fread( $fh, 4 ) : '';
			if ( $fh ) {
				fclose( $fh );
			}
			if ( 'glTF' === $magic ) {
				$data['ext']             = 'glb';
				$data['type']            = 'model/gltf-binary';
				$data['proper_filename'] = false;
			}
		}
		return $data;
	},
	10,
	3
);

// Filtro «Modelos 3D» en la biblioteca de medios.
add_filter(
	'post_mime_types',
	function ( $types ) {
		$types['model/gltf-binary'] = array( 'Modelos 3D', 'Gestionar modelos 3D', _n_noop( 'Modelo 3D <span class="count">(%s)</span>', 'Modelos 3D <span class="count">(%s)</span>' ) );
		return $types;
	}
);
