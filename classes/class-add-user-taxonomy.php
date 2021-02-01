<?php

/**
 * @todo: when you add a user, the form is showing, but is not applied...
 */
class Add_User_Taxonomy extends Add_Taxonomy {

	/**
	 * Decides if a selectbox for taxonomies shows in the backend.
	 * (You might want to handle things with a plugin like ACF)
	 *
	 * You can add it as an option to $args.
	 * True by default.
	 *
	 * @var bool
	 */
	public $show_on_profile_page = true;

	public function __construct( $taxonomy_slug, $object_type, $args ) {

		$this->show_on_profile_page = isset( $args['show_on_profile_page'] ) ? $args['show_on_profile_page'] : true;
		unset( $args['show_on_profile_page'] );

		if ( 'user' !== $object_type ) {
			wp_die( 'You can only init AddUserTaxonomy with $object_type user. You initialized with ' . $object_type . '.' );
		}
		parent::__construct( $taxonomy_slug, $object_type, $args );
	}

	public function add_filters_hooks() {
		parent::add_filters_hooks();

		add_filter( 'sanitize_user', array( $this, 'filter_disallow_username_same_as_taxonomy' ) );
		add_action( 'admin_menu', array( $this, 'add_user_taxonomy_admin_page' ) );
		add_filter( "manage_edit-{$this->taxonomy_slug}_columns", array( $this, 'filter_manage_edit_user_tax_columns' ), 10 );
		add_filter( "manage_{$this->taxonomy_slug}_custom_column", array( $this, 'filter_manage_user_tax_custom_column' ), 10, 3 );
		add_filter( 'parent_file', array( $this, 'filter_user_tax_parent_file' ) );

		add_filter( 'rest_prepare_user', array( $this, 'filter_add_user_taxonomy_to_rest' ), 10, 3 );

		if ( $this->show_on_profile_page ) {

			add_action( 'show_user_profile', array( $this, 'cb_edit_user_tax_section' ), 10 );
			add_action( 'edit_user_profile', array( $this, 'cb_edit_user_tax_section' ), 10 );
			add_action( 'user_new_form', array( $this, 'cb_edit_user_tax_section' ), 10 );

			add_action( 'personal_options_update', array( $this, 'cb_save_user_tax_terms' ), 10 );
			add_action( 'edit_user_profile_update', array( $this, 'cb_save_user_tax_terms' ), 10 );
			add_action( 'user_register', array( $this, 'cb_save_user_tax_terms' ), 10 );
		}
	}

	/**
	 * Filters user data returned from the REST API.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_User          $user     User object used to create response.
	 * @param WP_REST_Request  $request  Request object.
	 */
	function filter_add_user_taxonomy_to_rest( WP_REST_Response $response, WP_User $user, WP_REST_Request $request ) {
		$response->add_links( $this->prepare_rest_link_user_taxonomy( $user, $this->taxonomy_slug ) );
		return $response;
	}

	private function prepare_rest_link_user_taxonomy( WP_User $user, string $taxonomy_slug ) {
		$taxonomy_obj = get_taxonomy( $taxonomy_slug );
		if ( empty( $taxonomy_obj->show_in_rest ) ) {
			return; // Skip taxonomies that are not public.
		}
		$links['https://api.w.org/term'] = array();
		$tax_base = ! empty( $taxonomy_obj->rest_base ) ? $taxonomy_obj->rest_base : $taxonomy_slug;
		/**
		 * The query_arg needs to be 'post' instead of user. Otherwise terms are not filtered.
		 */
		$terms_url = add_query_arg( 'post', $user->ID, rest_url( 'wp/v2/' . $tax_base ) );
		$links['https://api.w.org/term'][] = array(
			'href'       => $terms_url,
			'taxonomy'   => $taxonomy_slug,
			'embeddable' => true,
		);
		return $links;
	}


	/**
	 * @todo: add nonce.
	 * @param int $user_id The ID of the user to save the terms for.
	 */
	public function cb_save_user_tax_terms( $user_id ) {
		$tax = get_taxonomy( $this->taxonomy_slug );
		// switch_to_blog( 1 );
		/* Make sure the current user can edit the user and assign terms before proceeding. */
		if ( ! current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) ) {
			return false;
		}
		$term = $_POST[ $this->taxonomy_slug ];
		/* Sets the terms (we're just using a single term) for the user. */
		error_log( "set obj terms uid(obj):$user_id, term:" . print_r( $term, true ) );

		wp_set_object_terms( $user_id, $term, $this->taxonomy_slug, false );
		clean_object_term_cache( $user_id, $this->taxonomy_slug );
		// restore_current_blog();
	}

	/**
	 * @param object $user The user object currently being edited.
	 */
	function cb_edit_user_tax_section( $user ) {
		global $pagenow;
		// switch_to_blog( 1 );
		$tax = get_taxonomy( $this->taxonomy_slug );
		$labels = $tax->labels;
		/* Make sure the user can assign terms of the current taxonomy before proceeding. */
		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			return;
		}

		/* Get the terms of the $this->taxonomy_slug taxonomy. */
		$terms = get_terms( $this->taxonomy_slug, array( 'hide_empty' => false ) );
		$term_select = '';
		/* If there are any terms, loop through them and display checkboxes. */
		if ( empty( $terms ) ) {
			$term_select = $labels->not_found;
		} else {
			foreach ( $terms as $term ) {
				$checked = ( 'user-new.php' !== $pagenow ) ?
					checked( true, is_object_in_term( $user->ID, $this->taxonomy_slug, $term->slug ), false )
					: '';
				$term_attr = esc_attr( $term->slug );
				$term_select .= "
					<label for='$term_attr'>
						<input
							type='checkbox'
							name='{$this->taxonomy_slug}[]'
							id='$term_attr'
							value='{$term->slug}'
							$checked
						>
						{$term->name}
					</label><br/>
				";
			}
		}

		echo "
			<h3>{$labels->name}</h3>
			<table class='form-table'>
				<tr>
				<th><label for='{$this->taxonomy_slug}'>{$labels->add_or_remove_items}</label></th>
				<td>
					$term_select
				</td>
				</tr>
			</table>
		";

	}

	/**
	 * Update parent file name to fix the selected menu issue. Otherwise you would see 'Posts' expanded in backend.
	 */
	public function filter_user_tax_parent_file( $parent_file ) {
		global $submenu_file;
		if (
			isset( $_GET['taxonomy'] ) &&
			$_GET['taxonomy'] == $this->taxonomy_slug &&
			( $submenu_file == 'edit-tags.php?taxonomy=' . $this->taxonomy_slug )
		) {
			$parent_file = 'users.php';
		}
		return $parent_file;
	}

	/**
	 * @todo: this could link to a user-page which is filtered...
	 *   - network-admin for super-admin?
	 *
	 * @param string $display WP just passes an empty string here.
	 * @param string $column The name of the custom column.
	 * @param int    $term_id The ID of the term being displayed in the table.
	 */
	public function filter_manage_user_tax_custom_column( $display, $column, $term_id ) {
		if ( 'users' === $column ) {
			$term = get_term( $term_id, $this->taxonomy_slug );
			echo $term->count;
		}
	}


	/**
	 * Unsets the 'posts' column and adds a 'users' column on the manage {$this->taxonomy_slug} admin page.
	 */
	public function filter_manage_edit_user_tax_columns( $columns ) {
		unset( $columns['posts'] );
		$columns['users'] = __( 'Users' );
		return $columns;
	}


	/**
	 * Admin page for the taxonomy
	 */
	public function add_user_taxonomy_admin_page() {
		$tax = get_taxonomy( $this->taxonomy_slug );
		add_users_page(
			esc_attr( $tax->labels->menu_name ),
			esc_attr( $tax->labels->menu_name ),
			$tax->cap->manage_terms,
			'edit-tags.php?taxonomy=' . $tax->name
		);
	}

	/**
	 * Don't allow usernames, which are the same as the taxonomy name.
	 *
	 * @todo: is this the best filter to use? Some username blacklist?
	 *
	 * @param string $username
	 * @return string The username, might be emptyl
	 */
	public function filter_disallow_username_same_as_taxonomy( $username ) {
		return ( $this->taxonomy_slug === $username ) ? '' : $username;
	}



}
