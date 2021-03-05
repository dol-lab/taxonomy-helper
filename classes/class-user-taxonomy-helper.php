<?php

/**
 * @todo: when you add a user, the form is showing, but is not applied...
 *
 * Simplifies the creation of user-taxonomies.
 * - Automatically adds admin-interfaces (if specified)
 * - Add some functions for default taxonomy queries (get users by terms, get terms by users, ... )
 * - Uses built in WordPress functionality so caching is (partially) already there.
 */
class User_Taxonomy_Helper extends Taxonomy_Helper {

	/**
	 * Decides if a checkboxes for taxonomies show in the backend.
	 * (You might want to handle things with a plugin like ACF instead)
	 *
	 * You can add it as an option to $args.
	 * True by default.
	 *
	 * @var bool
	 */
	public $show_on_profile_page = true;

	public function __construct( $taxonomy_slug, $object_type, $args, $blog_id = null ) {

		$this->show_on_profile_page = isset( $args['show_on_profile_page'] ) ? $args['show_on_profile_page'] : true;
		unset( $args['show_on_profile_page'] );

		if ( 'user' !== $object_type ) {
			wp_die( 'You can only init AddUserTaxonomy with $object_type user. You initialized with ' . $object_type . '.' );
		}
		parent::__construct( $taxonomy_slug, $object_type, $args );

		/**
		 * Don't show admin interfaces if not in the proper blog.
		 *
		 * @todo we might want to give easy access to reading functions (like REST from other blogs...)
		 */
		if ( $this->th_is_current_blog() ) {
			$admin = new User_Taxonomy_Admin( $this );
		}
	}


	/**
	 *
	 * @param mixed $query use query lik in WP_Term_Query
	 * @return array
	 * @throws Exception
	 */
	public function th_get_users_by_terms( $query ) {
		$user_ids = $this->th_get_objects_by_terms( $query );
		$user_query = new \WP_User_Query(
			array(
				'order'   => 'DESC',
				'orderby' => 'user_registered',
				'include' => $user_ids,
			)
		);
		return $user_query->get_results();
	}

	public function th_get_terms_by_user_ids( array $user_ids ) {
		return $this->th_get_terms_by_object_ids( $user_ids );
	}

}
