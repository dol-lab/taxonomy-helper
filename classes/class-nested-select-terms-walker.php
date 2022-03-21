<?php
require_once ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php';

/**
 * WordPress walker, which wraps nested categories in html5-native <details><summary>... container.
 *
 * @package
 */
class Nested_Select_Terms_Walker extends Walker_Category_Checklist {

	/**
	 * Start the element output.
	 *
	 * @todo: performance could probably increased...
	 *
	 * @see Walker::start_el()
	 *
	 * @since 2.5.1
	 *
	 * @param string  $output   Used to append additional content (passed by reference).
	 * @param WP_Term $term The current term object.
	 * @param int     $depth    Depth of the term in reference to parents. Default 0.
	 * @param array   $args     An array of arguments. @see wp_terms_checklist()
	 * @param int     $id       ID of the current term.
	 */
	public function start_el( &$output, $term, $depth = 0, $args = array(), $id = 0 ) {
		$taxonomy = $args['taxonomy'];
		$args = apply_filters( 'nested_term_select_walker_args', $args, $term, $depth, $this );
		$name = 'tax_input[' . $taxonomy . ']';
		$args['selected_cats'] = ! empty( $args['selected_cats'] ) ? array_map( 'intval', $args['selected_cats'] ) : array();

		$is_selected = in_array( $term->term_id, $args['selected_cats'], true );
		$checked = checked( $is_selected, true, false );
		$disabled = disabled( ! empty( $args['disabled'] ), true, false );
		$readonly = wp_readonly( ! empty( $args['readonly'] ), true, false );

		$open = $this->is_open( $term->term_id, $taxonomy, $args['selected_cats'] ) ? 'open' : '';
		$has_children = $this->has_children( $term->term_id, $args['taxonomy'] );

		$trim_len = 40;
		$description_short = strlen( $term->description ) > $trim_len ? substr( $term->description, 0, $trim_len ) . '…' : $term->description;
		$description_string = $term->description ? "- <span title='$term->description'>$description_short</span>" : '';

		$label = "
			<label
				class='selectit'
				id='{$taxonomy}-{$term->term_id}'
				for='{$term->slug}'
			>
				<input " .
					"value='{$term->slug}' " .
					"name='{$name}[]' " .
					"type='checkbox'  " .
					"id='in-$taxonomy-$term->term_id' " .
					"$checked " .
					"$disabled " .
					"$readonly " .
				'/>' .
				"<b>$term->name</b> $description_string" .
			'</label>
		';
		$output .= $has_children ? "<details $open><summary>$label</summary>" : "<li>$label</li>";
	}



	/**
	 * Ends the element output, if needed.
	 *
	 * @see Walker::end_el()
	 *
	 * @since 2.5.1
	 *
	 * @param string  $output   Used to append additional content (passed by reference).
	 * @param WP_Term $term The current term object.
	 * @param int     $depth    Depth of the term in reference to parents. Default 0.
	 * @param array   $args     An array of arguments. @see wp_terms_checklist()
	 */
	public function end_el( &$output, $term, $depth = 0, $args = array() ) {
		$has_children = $this->has_children( $term->term_id, $args['taxonomy'] );
		$output .= $has_children ? '</details></li>' : '';
	}

	private function has_children( int $term_id, string $taxonomy_slug ) {
		return ! empty( get_term_children( $term_id, $taxonomy_slug ) );
	}

	 /**
	  * We fold hierarchy. This checks if (any depth) child is checked, so parents are unfolded.
	  *
	  * @param int    $term_id
	  * @param string $taxonomy
	  * @param array  $all_selected_ids
	  * @return bool
	  * @throws Exception
	  */
	private function is_open( int $term_id, string $taxonomy, array $all_selected_ids ) {
		$children_term_ids = get_term_children( $term_id, $taxonomy );
		if ( is_wp_error( $children_term_ids ) ) {
			throw new Exception( $children_term_ids->get_error_message() );
		}
		return ! empty( array_intersect( $children_term_ids, $all_selected_ids ) );
	}
}
