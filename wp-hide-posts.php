<?php
/*
Plugin Name: CF Hide Posts
Plugin URI: https://github.com/crowdfavorite/wp-hide-posts
Description: Allows one to exclude posts from the main loop as well as the main RSS feed. Filters are in place to add this capability to multiple post types. 
Version: 0.1
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

/**
 * Initialize the taxonomy
 **/
function cfhp_init() {
	load_plugin_textdomain('cfhp');

	$post_types = cfhp_post_types();
	$tax = cfhp_get_taxonomy_slug();
	$args = array(
		'public' => false,
		'query_var' => false,
		'rewrite' => false,
		'hierarchical' => true,
	);

	foreach ($post_types as $post_type) {
		register_taxonomy($tax, $post_type, $args);
	}
}
add_action('init', 'cfhp_init', 0);

/**
 * Get the term id, wrapper for term creation
 * @return int 0 if unable to find or create the term. Term id otherwise
 **/
function cfhp_get_term_id() {
	return cfhp_create_term();
}

/**
 * Create the term if it needs to be created
 * @return int 0 if unable to find or create the term. Term id otherwise
 **/
function cfhp_create_term() {
	$term_slug = cfhp_get_term_slug();
	$tax = cfhp_get_taxonomy_slug();

	$term = get_term_by('slug', $term_slug, $tax);
	if (!$term) {
		$term_data = wp_create_term($term_slug, $tax);
		if (is_wp_error($term_data)) {
			return 0;
		}
		else if (is_array($term_data)) {
			return $term_data['term_id'];
		}
		else {
			return $term_data;
		}
	}
	else {
		return $term->term_id;
	}
}
// Create the term on admin init
add_action('admin_init', 'cfhp_create_term', 0);

function cfhp_get_taxonomy_slug() {
	return 'cfhp';
}

function cfhp_get_term_slug() {
	return 'cfhp';
}

function cfhp_post_types() {
	return apply_filters('cfhp_post_types', array(
		'post',
	));
}

/**
 * Check if this is the home page feed
 **/
function cfhp_is_home_rss($query) {
	if (
		$query->is_feed &&  
		!( 
			$query->is_singular || 
			$query->is_archive || 
			$query->is_search || 
			$query->is_trackback || 
			$query->is_404 || 
			$query->is_admin || 
			$query->is_comments_popup || 
			$query->is_robots
		)
	) {
		return true;
	}

	return false;
}

/**
 * Modify the loop to exclude certain posts
 **/ 
function cfhp_pre_get_posts($query) {
	if ($query->is_home || cfhp_is_home_rss($query) || apply_filters('cfhp_hide_on', false, &$query)) {
		$query->query_vars['tax_query'][] = array(
			'taxonomy' => cfhp_get_taxonomy_slug(),
			'field' => 'slug',
			'terms' => cfhp_get_term_slug(),
			'operator' => 'NOT IN',
		);
	}
}
add_action('pre_get_posts', 'cfhp_pre_get_posts');

/**
 * Initialize meta box
 **/
function cfhp_add_meta_boxes() {
	$post_types = cfhp_post_types();
	foreach ($post_types as $post_type) {
		add_meta_box('cf-hide-posts', __('Hide Post', 'cfhp'), 'cfhp_meta_box', $post_type, 'side');
	}
}
add_action('add_meta_boxes', 'cfhp_add_meta_boxes');

/**
 * Meta box markup
 **/
function cfhp_meta_box($post) {
	$tax = cfhp_get_taxonomy_slug();
	$terms = wp_get_object_terms($post->ID, $tax, array('fields' => 'ids'));
	if (is_array($terms) && !empty($terms)) {
		$checked = true;
	}
	else {
		$checked = false;
	}

	// cf_meta_set class formats a little nicer if you the cf meta plugin installed.
?>
<div class="cf_meta_set">
	<?php _e('<p>Hide this post from the homepage loop and RSS feed. This post will show up in all other archives.</p>', 'cfhp'); ?>
	<input type="hidden" name="<?php echo esc_attr('tax_input['.$tax.'][]') ?>" value="0" />
	<input type="checkbox" id="cfhp-select" name="<?php echo esc_attr('tax_input['.$tax.'][]') ?>" value="<?php echo esc_attr(cfhp_get_term_id()); ?>"<?php checked($checked, true, true); ?> /> 
	<label for="cfhp-select" class="after">
		<?php _e('Hide Post?', 'cfhp'); ?>	
	</label>
</div>
<?php 
}
