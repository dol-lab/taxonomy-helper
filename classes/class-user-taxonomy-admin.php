<?php

/**
 * Adds admin interfaces for user taxonomies.
 *
 * Currently only triggered, if in the right blog.
 *
 * @package
 */
class User_Taxonomy_Admin {

	/**
	 *
	 * @var User_Taxonomy_Helper
	 */
	public $user_taxonomy_helper;

	public $taxonomy_slug;

	public function __construct( User_Taxonomy_Helper $user_taxonomy_helper ) {
		$this->user_taxonomy_helper = $user_taxonomy_helper;
		$this->taxonomy_slug = $user_taxonomy_helper->taxonomy_slug;
		$this->add_filters_hooks();
	}
	public function add_filters_hooks() {
		add_filter( 'sanitize_user', array( $this, 'filter_disallow_username_same_as_taxonomy' ) );
		add_action( 'admin_menu', array( $this, 'user_taxonomy_helper_admin_page' ) );
		add_filter( "manage_edit-{$this->taxonomy_slug}_columns", array( $this, 'filter_manage_edit_user_tax_columns' ), 10 );
		add_filter( "manage_{$this->taxonomy_slug}_custom_column", array( $this, 'filter_manage_user_tax_custom_column' ), 10, 3 );
		add_filter( 'parent_file', array( $this, 'filter_user_tax_parent_file' ) );
		add_filter( 'rest_prepare_user', array( $this, 'filter_user_taxonomy_helper_to_rest' ), 10, 3 );

		if ( $this->user_taxonomy_helper->show_on_profile_page ) {
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
	public function filter_user_taxonomy_helper_to_rest( WP_REST_Response $response, WP_User $user, WP_REST_Request $request ) {
		$links = $this->prepare_rest_link_user_taxonomy( $user, $this->taxonomy_slug );
		if ( ! empty( $links ) ){
			$response->add_links( $links );
		}
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
	 * @todo: errorhandling! : https://wordpress.stackexchange.com/questions/261167/getting-admin-notices-to-appear-after-page-refresh
	 * @param int $user_id The ID of the user to save the terms for.
	 */
	public function cb_save_user_tax_terms( $user_id ) {
		$tax = get_taxonomy( $this->taxonomy_slug );
		// switch_to_blog( 1 );
		/* Make sure the current user can edit the user and assign terms before proceeding. */
		if ( ! current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) ) {
			return false;
		}

		// Variables are not set, if no checkbox is checked.
		if ( ! isset( $_POST['tax_input'] ) || ! isset( $_POST['tax_input'][ $this->taxonomy_slug ] ) ) {
			error_log( "No user terms found when saving user [$user_id] profile. They might have all been deselected." );
			$terms = array();
		} else {
			$terms = $_POST['tax_input'][ $this->taxonomy_slug ];
		}

		/*
		 Sets the terms (we're just using a single term) for the user. */
		// error_log( "set obj terms uid(obj):$user_id, term:" . print_r( $terms, true ) );

		wp_set_object_terms( $user_id, $terms, $this->taxonomy_slug, false );
		clean_object_term_cache( $user_id, $this->taxonomy_slug );
		// restore_current_blog();
	}

	/**
	 * todo move the inline styles somewhere close to the walker.
	 *
	 * @param \WP_User|string $user The user object currently being edited. This can be add-existing-user or add-new-user.
	 */
	function cb_edit_user_tax_section( $user ) {
		global $pagenow;
		// switch_to_blog( 1 );
		$tax = get_taxonomy( $this->taxonomy_slug );

		if ( ! $tax ){
			error_log( "Sorry, a taxonomy with the name '{$this->taxonomy_slug}' does not exist." );
			return;
		}
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
			$walker = new Nested_Select_Terms_Walker();

			if ( 'user-new.php' === $pagenow || is_string( $user ) ) {
				$selected_ids = array(); // don't check terms, when creating a new user.
			} else {
				$selected = wp_get_object_terms( $user->ID, $this->taxonomy_slug );
				$selected_ids = wp_list_pluck( $selected, 'term_id' );
				$walker->user_id = $user->ID;
			}

			$term_select = wp_terms_checklist(
				$user->ID,
				array(
					'checked_ontop' => false,
					'taxonomy' => $this->taxonomy_slug,
					'hierarchical' => true,
					'walker' => $walker,
					'echo' => false,
					'selected_cats' => $selected_ids,
				)
			);
		}

		echo "
			<h3>{$labels->name}</h3>

			<table class='form-table nested-term-select'>
				<tr>
				<th><label for='{$this->taxonomy_slug}'>{$labels->add_or_remove_items}</label></th>
				<td>
					$term_select
				</td>
				</tr>
			</table>
			<style>
				/* some basic styling for the Nested_Select_Terms_Walker */
				.nested-term-select input[disabled=disabled]+*:before,
				.nested-term-select input[type=checkbox][readonly]+*:before {
					content: '\\00a0🔒\\00a0';
					display: inline-block;
				}
				.nested-term-select input[type=checkbox][readonly] {
					pointer-events: none;
				}
				.nested-term-select ul,
				.nested-term-select li {
					padding: 1px 20px;
					position:relative;
					margin: 0;
					list-style: none;
				}
				.nested-term-select label {
					/* display:inline-block; */
				}
				.nested-term-select summary {
					padding: 2px 0;
				}
				.nested-term-select summary:hover {
					background-color: #eee;
				}
				.nested-term-select summary:focus{
					outline: 1px solid #aaa;
				}
				.nested-term-select details {
					position: relative;
					transition: all 1s;
				}
				.nested-term-select details:before {
					content: ' ';
					height: calc( 100% - 30px );
					top: 25px;
					margin-left: 4px;
					position: absolute;
					width: 1px;
					background-color: #ddd;
				}
				.nested-term-select details:hover:before {
					background-color: #aaa;
				}
			</style>
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
			return $term->count;
		}
		return $display;
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
	public function user_taxonomy_helper_admin_page() {
		if ( ! $tax = get_taxonomy( $this->taxonomy_slug ) ){
			return;
		}
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
	 * @return string The username, might be empty
	 */
	public function filter_disallow_username_same_as_taxonomy( $username ) {
		return ( $this->taxonomy_slug === $username ) ? '' : $username;
	}
}
