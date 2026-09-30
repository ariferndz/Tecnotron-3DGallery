<?php
/**
 * Tipos de contenido: Máquinas, Categorías de máquina y Solicitudes; y la dirección /visor/ de cada máquina.
 *
 * @package TecnotronMaquinas
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tnm_register_types' );

function tnm_register_types() {
	$slug = sanitize_title( tnm_opt( 'slug' ) ) ?: 'maquinas';

	// La categoría se registra antes que la máquina: así /maquinas/categoria/<x>/ no se confunde con una máquina.
	register_taxonomy(
		TNM_TAX,
		TNM_CPT,
		array(
			'labels'            => array(
				'name'          => 'Categorías de máquina',
				'singular_name' => 'Categoría',
				'menu_name'     => 'Categorías',
				'add_new_item'  => 'Añadir categoría',
				'edit_item'     => 'Editar categoría',
				'search_items'  => 'Buscar categorías',
				'all_items'     => 'Todas las categorías',
				'not_found'     => 'No hay categorías',
			),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => $slug . '/categoria', 'with_front' => false ),
		)
	);

	register_post_type(
		TNM_CPT,
		array(
			'labels'        => array(
				'name'               => 'Máquinas',
				'singular_name'      => 'Máquina',
				'menu_name'          => 'Máquinas',
				'add_new'            => 'Añadir máquina',
				'add_new_item'       => 'Añadir máquina',
				'edit_item'          => 'Editar máquina',
				'new_item'           => 'Nueva máquina',
				'view_item'          => 'Ver ficha',
				'view_items'         => 'Ver catálogo',
				'search_items'       => 'Buscar máquinas',
				'not_found'          => 'No hay máquinas',
				'not_found_in_trash' => 'No hay máquinas en la papelera',
				'all_items'          => 'Todas las máquinas',
				'featured_image'     => 'Imagen principal',
				'set_featured_image' => 'Elegir imagen principal',
				'remove_featured_image' => 'Quitar imagen principal',
				'use_featured_image' => 'Usar como imagen principal',
				'archives'           => 'Catálogo de máquinas',
				'item_published'     => 'Máquina publicada.',
				'item_updated'       => 'Máquina actualizada.',
			),
			'public'        => true,
			'has_archive'   => $slug,
			'rewrite'       => array( 'slug' => $slug, 'with_front' => false ),
			'menu_icon'     => 'dashicons-screenoptions',
			'menu_position' => 21,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'revisions', 'excerpt' ),
			'show_in_rest'  => true,
		)
	);

	register_post_type(
		TNM_SOL,
		array(
			'labels'          => array(
				'name'          => 'Solicitudes de presupuesto',
				'singular_name' => 'Solicitud',
				'menu_name'     => 'Solicitudes',
				'all_items'     => 'Solicitudes',
				'edit_item'     => 'Solicitud',
				'not_found'     => 'Aún no hay solicitudes',
				'search_items'  => 'Buscar solicitudes',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=' . TNM_CPT,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);

	// /maquinas/diggy/visor/ → visor 3D a pantalla completa (destino del QR)
	add_rewrite_endpoint( 'visor', EP_PERMALINK );
}

// La máquina se edita con el editor clásico: descripción arriba y debajo las cajas de ficha, imágenes, 3D y PDF.
// Prioridad alta para ganar a plugins y temas que activan el editor de bloques en todo (p. ej. Classic Editor en modo «bloques»).
add_filter(
	'use_block_editor_for_post_type',
	function ( $use, $type ) {
		return TNM_CPT === $type ? false : $use;
	},
	999,
	2
);
add_filter(
	'use_block_editor_for_post',
	function ( $use, $post ) {
		return $post && TNM_CPT === get_post_type( $post ) ? false : $use;
	},
	999,
	2
);

// «Imagen principal» aparece aunque el tema no declare imágenes destacadas.
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'post-thumbnails', array( TNM_CPT ) );
	},
	99
);

// Si Elementor está activado para este tipo, su editor oculta las cajas de la máquina y la plantilla del plugin
// no usa su diseño: se quita «Editar con Elementor» de las máquinas.
add_action(
	'init',
	function () {
		remove_post_type_support( TNM_CPT, 'elementor' );
	},
	999
);

// Catálogo: destacadas primero, luego el campo «Orden» y el nombre; todas en una página (el filtro es instantáneo).
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_post_type_archive( TNM_CPT ) || $q->is_tax( TNM_TAX ) ) {
			$q->set( 'posts_per_page', 500 );
			$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
		}
	}
);

// Si cambia la dirección del catálogo en Ajustes, se regeneran los enlaces permanentes.
add_action(
	'update_option_' . TNM_OPT,
	function ( $old, $new ) {
		if ( ( $old['slug'] ?? '' ) !== ( $new['slug'] ?? '' ) ) {
			update_option( 'tnm_flush', 1 );
		}
	},
	10,
	2
);
add_action(
	'init',
	function () {
		if ( get_option( 'tnm_flush' ) ) {
			delete_option( 'tnm_flush' );
			flush_rewrite_rules();
		}
	},
	99
);
