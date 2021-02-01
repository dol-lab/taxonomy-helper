# Taxonomy Helper

WordPress allows you to add custom taxonomies (which are like categories). The design in quite flexible, and can not only be related to posts, but also to users.

This adds some classes which help adding taxonomies.

```php
// works like register_taxonomy. It guesses all the other labels (in english).
$taxonomy = new AddTaxonomy(
	'post-affiliation',
	'post',
	array(
		'labels' => array(
			'singular_name' => esc_html( 'Affiliation', 'text_domain' ),
			'plural_name' => esc_html( 'Post Affiliations', 'text_domain' ),
		),
	),
);

// add a taxonomy to the user-object. It will automatically add interfaces to the backend to manage usercategories (wip).
$taxonomy = new AddUserTaxonomy(
	'user-affiliation',
	'user',
	array(
		'labels' => array(
			'singular_name' => esc_html( 'Affiliation', 'text_domain' ),
			'plural_name' => esc_html( 'User Affiliations', 'text_domain' ),
		),
		'capabilities' => array(
			'manage_terms' => 'edit_users', // Using 'edit_users' cap to keep this simple.
			'edit_terms'   => 'edit_users',
			'delete_terms' => 'edit_users',
			'assign_terms' => 'read',
		),
	)
);

```
