<?php
/**
 * Máquinas → Importar / exportar.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=' . TNM_CPT, 'Importar / exportar máquinas', 'Importar / exportar', 'edit_posts', 'tnm-importar', 'tnm_import_page' );
	}
);

function tnm_import_page() {
	$result = null;
	if ( isset( $_POST['tnm_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tnm_import_nonce'] ) ), 'tnm_importar' ) && current_user_can( 'edit_posts' ) ) {
		if ( ! empty( $_FILES['tnm_csv']['tmp_name'] ) && is_uploaded_file( $_FILES['tnm_csv']['tmp_name'] ) ) { // phpcs:ignore
			$result = tnm_importar_csv( $_FILES['tnm_csv']['tmp_name'], ! empty( $_POST['actualizar'] ), empty( $_POST['borrador'] ) ? 'publish' : 'draft' ); // phpcs:ignore
		} elseif ( ! empty( $_POST['tnm_inicial'] ) ) {
			$result = tnm_importar_csv( TNM_DIR . 'data/maquinas-configurador.csv', false, 'publish' );
		}
	}
	?>
	<div class="wrap tnm-settings">
		<h1>Importar / exportar máquinas</h1>
		<?php if ( null !== $result ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( sprintf( '%d filas procesadas.', count( $result ) ) ); ?></p></div>
			<table class="widefat striped tnm-import-result"><thead><tr><th>Máquina</th><th>Resultado</th><th>Detalle</th></tr></thead><tbody>
			<?php foreach ( $result as list( $name, $status, $detail ) ) : ?>
				<tr><td><?php echo esc_html( $name ); ?></td><td><span class="tnm-st tnm-st-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span></td><td><?php echo esc_html( $detail ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2>Exportar</h2>
		<p>Descarga todas las máquinas en un CSV que se abre con Excel. Puedes editarlo (medidas, códigos, descripciones…) y volver a importarlo aquí para actualizar en bloque.</p>
		<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tnm_exportar' ), 'tnm_exportar' ) ); ?>">Descargar CSV</a>
		<a class="button" href="<?php echo esc_url( TNM_URL . 'data/maquinas-configurador.csv' ); ?>" download>Descargar plantilla de ejemplo</a></p>

		<h2>Importar</h2>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'tnm_importar', 'tnm_import_nonce' ); ?>
			<p><input type="file" name="tnm_csv" accept=".csv,text/csv" required></p>
			<p><label><input type="checkbox" name="actualizar" value="1" checked> Actualizar las máquinas que ya existen (se reconocen por la columna <code>slug</code> o por el nombre)</label></p>
			<p><label><input type="checkbox" name="borrador" value="1"> Crear las nuevas como borrador (no se ven en la web hasta publicarlas)</label></p>
			<?php submit_button( 'Importar CSV', 'primary', 'submit', false ); ?>
		</form>
		<p class="description">Columnas: <code><?php echo esc_html( implode( ';', tnm_csv_columnas() ) ); ?></code>. Separador «;» o «,». Las celdas vacías no borran datos. En <code>descripcion</code>, una línea en blanco separa párrafos y las líneas que empiezan por «•» forman una lista. Las fotos, el GLB de la biblioteca y los PDF se añaden después desde la ficha de cada máquina.</p>

		<h2>Catálogo inicial</h2>
		<form method="post">
			<?php wp_nonce_field( 'tnm_importar', 'tnm_import_nonce' ); ?>
			<input type="hidden" name="tnm_inicial" value="1">
			<p>Crea las 42 máquinas del configurador (nombre, categoría y medidas; Diggy y Grúa de tren con su ficha completa). No toca las que ya existan.</p>
			<?php submit_button( 'Crear el catálogo inicial', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
