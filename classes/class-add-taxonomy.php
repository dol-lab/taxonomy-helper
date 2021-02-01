<?php
/**
 * Simplifies the creation of taxonomies.
 * Inspired by hlashbrooke/WordPress-Plugin-Template
 *
 * You can just use it like WordPress' register_taxonomy.
 *
 * Labels are automatically complemented if you just assign
 *
 * Just add 'singular_name' & 'plural_name' to args. Everything else is created automatically.
 */
class Add_Taxonomy {

	/**
	 * The slug of the taxonomy.
	 *
	 * @var string
	 */
	public $taxonomy_slug = '';

	/**
	 * could be post, user, blog, ...
	 *
	 * @var string
	 */
	public $object_type = '';

	public $args = array();

	/*
	 * Check wp-includes/taxonomy ->register_taxonomy() for more context.
	 *
	 * @param string       $taxonomy    Taxonomy key, must not exceed 32 characters.
	 * @param array|string $object_type Object type or array of object types with which the taxonomy should be associated.
	 * @param array|string $args
	 * @return WP_Taxonomy|WP_Error The registered taxonomy object on success, WP_Error object on failure.
	 */
	public function __construct( string $taxonomy_slug, string $object_type, $args = array() ) {

		$this->taxonomy_slug = $taxonomy_slug;
		$this->object_type = $object_type;
		$this->args = $this->get_default_args( $args );

		register_taxonomy( $taxonomy_slug, $object_type, $this->args );
		$this->add_filters_hooks();

	}

	public function add_filters_hooks() {}


	private function get_default_args( $args ) {

		$labels = wp_parse_args( $args['labels'], $this->get_default_labels( $args ) );
		// error_log( "labels" . print_r( $labels , true) );

		$capabilities = isset( $args['capabilities'] ) ? wp_parse_args( $args['capabilities'], $this->get_default_capabilities() ) : $this->get_default_capabilities();

		return array(
			'capabilities'          => $capabilities,
			'labels'                => apply_filters( $this->taxonomy_slug . '_labels', $labels ),
			'hierarchical'          => true,
			'public'                => true,
			'show_ui'               => true,
			'show_in_nav_menus'     => true,
			'show_tagcloud'         => true,
			'meta_box_cb'           => null,
			'show_admin_column'     => true,
			'show_in_quick_edit'    => true,
			'update_count_callback' => '',
			'show_in_rest'          => true,
			'rest_base'             => $this->taxonomy_slug,
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'query_var'             => $this->taxonomy_slug,
			'rewrite'               => true,
			'sort'                  => '',
		);
	}


	private function get_default_capabilities() {
		return array(
			'manage_terms' => 'manage_categories',
			'edit_terms' => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);
	}

	private function get_default_labels( $args ) {

		$labels = isset( $args['labels'] ) ? $args['labels'] : array();
		if ( ! isset( $labels['name'] ) ) {
			$labels['name'] = esc_html_x( 'Items', 'Plural Name', 'text_domain' );
		}
		$singular_name = isset( $labels['singular_name'] ) ?
			$labels['singular_name'] : esc_html_x( 'Item', 'Singular Name', 'text_domain' );
		$plural_name = isset( $labels['plural_name'] ) ?
			$labels['plural_name'] : $labels['name'];

		/**
		 * Register new taxonomy
		 *
		 * @todo https://wpml.org/forums/topic/translation-for-dynamic-value/
		 * @todo https://wpml.org/documentation/support/translation-for-texts-by-other-plugins-and-themes/
		 *
		 * @return array
		 */
		return array(
			'name'                       => $plural_name,
			'singular_name'              => $singular_name,
			'menu_name'                  => $plural_name,
			'all_items'                  => sprintf( esc_html__( 'All %s', 'text_domain' ), $plural_name ),
			'edit_item'                  => sprintf( esc_html__( 'Edit %s', 'text_domain' ), $singular_name ),
			'view_item'                  => sprintf( esc_html__( 'View %s', 'text_domain' ), $singular_name ),
			'update_item'                => sprintf( esc_html__( 'Update %s', 'text_domain' ), $singular_name ),
			'add_new_item'               => sprintf( esc_html__( 'Add New %s', 'text_domain' ), $singular_name ),
			'new_item_name'              => sprintf( esc_html__( 'New %s Name', 'text_domain' ), $singular_name ),
			'parent_item'                => sprintf( esc_html__( 'Parent %s', 'text_domain' ), $singular_name ),
			'parent_item_colon'          => sprintf( esc_html__( 'Parent %s:', 'text_domain' ), $singular_name ),
			'search_items'               => sprintf( esc_html__( 'Search %s', 'text_domain' ), $plural_name ),
			'popular_items'              => sprintf( esc_html__( 'Popular %s', 'text_domain' ), $plural_name ),
			'separate_items_with_commas' => sprintf( esc_html__( 'Separate %s with commas', 'text_domain' ), $plural_name ),
			'add_or_remove_items'        => sprintf( esc_html__( 'Add or remove %s', 'text_domain' ), $plural_name ),
			'choose_from_most_used'      => sprintf( esc_html__( 'Choose from the most used %s', 'text_domain' ), $plural_name ),
			'not_found'                  => sprintf( esc_html__( 'No %s found', 'text_domain' ), $plural_name ),
		);
	}
}
