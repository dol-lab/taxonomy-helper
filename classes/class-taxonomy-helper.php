<?php
/**
 * Simplifies the creation of taxonomies.
 *
 * You can just use it like WordPress' register_taxonomy.
 * - Just add 'singular_name' & 'plural_name' to args. Everything else is created automatically (if not specified).
 *   (See how far you get, when a translation is necessary, you probably need to specify everything)
 * - Adds some helper functions for default taxonomy queries.
 */
class Taxonomy_Helper {

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

	/**
	 * If blog_id is not specified, we don't to a blog check.
	 * Otherwise we switch to the proper blog (via __call )
	 *
	 * @var null|int
	 */
	private $blog_id = null;

	/**
	 * Weather the taxonomy is registered in WP.
	 *
	 * @var false
	 */
	private $registered = false;

	/**
	 * 
	 * @var \WP_Taxonomy
	 */
	public $taxonomy;

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
		$this->args = $this->th_get_default_args( $args );
	}

	/**
	 * Register the taxonomy with wordpress (once)
	 * 
	 * @return WP_Taxonomy 
	 * @throws Exception 
	 */
	public function register(){
		if ( ! $this->registered ){
			$tax = \register_taxonomy( $this->taxonomy_slug, $this->object_type, $this->args );
			if ( is_wp_error( $this->taxonomy ) ){
				throw new Exception( $tax->get_error_message() );
			}
			$this->taxonomy = $tax;
		}
		return $this->taxonomy;
	}

	/**
	 * This function wraps all other functions in a possible blog-switch.
	 *
	 * @param mixed $method
	 * @param mixed $arguments
	 * @return mixed
	 */
	public function __call( $method, $arguments ) {
		if ( ! method_exists( $this, $method ) ) {
			return;
		}
		if ( $this->th_need_blog_switch() ) {
			\switch_to_blog( $this->blog_id );
		}
		$value = call_user_func_array( array( $this, $method ), $arguments );
		if ( $this->th_need_blog_switch() ) {
			\restore_current_blog();
		}
		return $value;
	}

	public function th_need_blog_switch() {
		return null !== $this->blog_id && $this->blog_id != \get_current_blog_id();
	}

	/**
	 * Check if we are in the current blog.
	 * - if blog_id == null => every blog is current
	 * - if $this->blog_id is the current one.
	 *
	 * @return bool
	 */
	public function th_is_current_blog() {
		return null === $this->blog_id || $this->blog_id != \get_current_blog_id();
	}

	public function th_set_blog_id( int $blog_id ) {
		if ( null != $this->blog_id ) {
			throw new Exception( 'Your blog id is already set' );
		}
		$this->$blog_id = $blog_id;
	}

	/**
	 * A wrapper for WP_Term_Query with our taxonomy as default and simplified output.
	 *
	 * @param array $query check WP_Term_Query for options...
	 * @return WP_Term[] an array of WP_Term objects.
	 */
	public function th_get_terms( array $query = array() ) {
		$query = wp_parse_args(
			$query,
			array(
				'taxonomy'               => $this->taxonomy_slug,
				'hide_empty'             => false,
			)
		);

		unset( $query['count'] ); // don't allow count, simplify output ($tq->query can no longer return int).
		$tq = new WP_Term_Query();
		return $tq->query( $query );
	}

	protected function th_get_terms_by_object_ids( array $obj_ids ) {
		return $this->th_get_terms( array( 'object_ids' => $obj_ids ) );
	}

	public function th_update_term( $term_id, $args ) {
		return wp_update_term( $term_id, $this->taxonomy_slug, $args );
	}

	/**
	 *
	 * @param int              $object_id The object to relate to.
	 * @param string|int|array $terms     A single term slug, single term ID, or array of either term slugs or IDs.
	 *                                    Will replace all existing related terms in this taxonomy. Passing an
	 *                                    empty value will remove all related terms.
	 * @param bool             $append    Optional. If false will delete difference of terms. Default false.
	 * @return array|WP_Error Term taxonomy IDs of the affected terms or WP_Error on failure.
	 */
	protected function th_set_object_terms( $object_id, $terms, $append ) {
		return wp_set_object_terms( $object_id, $terms, $this->taxonomy, $append );
	}


	/**
	 *
	 * @param array $query You can use $query from WP_Term_Query (without count).
	 * @return int[] an array of term-ids. Can be empty.
	 * @throws Exception
	 */
	protected function th_get_objects_by_terms( array $query = array() ) {
		$terms_array = $this->th_get_terms( $query );
		$term_ids = wp_list_pluck( $terms_array, 'term_id' );

		$terms = get_objects_in_term( $term_ids, $this->taxonomy_slug ); // WP_Tax_Query.

		if ( is_wp_error( $terms ) ) {
			/**
			 * Don't handle multiple outputs.
			 * The risk of a non-exsting taxonomy is low, as we create it within this class.
			 */
			throw new \Exception( $terms->get_error_message() );
		}
		// ...users/blogs by terms
		return $terms;
	}


	private function th_get_default_args( $args ) {

		$labels = wp_parse_args( $args['labels'], $this->th_get_default_labels( $args ) );
		// error_log( "labels" . print_r( $labels , true) );

		$capabilities = isset( $args['capabilities'] ) ? wp_parse_args( $args['capabilities'], $this->th_get_default_capabilities() ) : $this->th_get_default_capabilities();

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


	private function th_get_default_capabilities() {
		return array(
			'manage_terms' => 'manage_categories',
			'edit_terms' => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);
	}

	private function th_get_default_labels( $args ) {

		$labels = isset( $args['labels'] ) ? $args['labels'] : array();
		if ( ! isset( $labels['name'] ) ) {
			$labels['name'] = \esc_html_x( 'Items', 'Plural Name' );
		}
		$singular_name = isset( $labels['singular_name'] ) ?
			$labels['singular_name'] : \esc_html_x( 'Item', 'Singular Name' );
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
			'all_items'                  => sprintf( esc_html__( 'All %s' ), $plural_name ),
			'edit_item'                  => sprintf( esc_html__( 'Edit %s' ), $singular_name ),
			'view_item'                  => sprintf( esc_html__( 'View %s' ), $singular_name ),
			'update_item'                => sprintf( esc_html__( 'Update %s' ), $singular_name ),
			'add_new_item'               => sprintf( esc_html__( 'Add New %s' ), $singular_name ),
			'new_item_name'              => sprintf( esc_html__( 'New %s Name' ), $singular_name ),
			'parent_item'                => sprintf( esc_html__( 'Parent %s' ), $singular_name ),
			'parent_item_colon'          => sprintf( esc_html__( 'Parent %s:' ), $singular_name ),
			'search_items'               => sprintf( esc_html__( 'Search %s' ), $plural_name ),
			'popular_items'              => sprintf( esc_html__( 'Popular %s' ), $plural_name ),
			'separate_items_with_commas' => sprintf( esc_html__( 'Separate %s with commas' ), $plural_name ),
			'add_or_remove_items'        => sprintf( esc_html__( 'Add or remove %s' ), $plural_name ),
			'choose_from_most_used'      => sprintf( esc_html__( 'Choose from the most used %s' ), $plural_name ),
			'not_found'                  => sprintf( esc_html__( 'No %s found' ), $plural_name ),
		);
	}

	/**
	 * Currently not in use. This might be easier than the walker...
	 *
	 * @param string $taxonomy
	 * @param int    $parent_id
	 * @return string
	 */
	public function hierarchical_term_tree( string $taxonomy, $parent_id = 0 ) {
		$terms = get_terms(
			$taxonomy,
			array(
				'hide_empty' => false,
				'parent' => $parent_id,
				'taxonomy' => $taxonomy,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $terms->get_error_message();
		}
		$children = '';
		foreach ( $terms as $trm ) {
			$grand_children = 0 !== $trm->term_id ? $this->hierarchical_term_tree( $taxonomy, $trm->term_id ) : null;
			$children .= "<li>$trm->name [$trm->term_taxonomy_id] ( $trm->count )$grand_children</li>";
		}
		return $children ? "<ul>$children</ul>" : '';
	}
}
