<?php

//
//  Custom Child Theme Functions
//

// Unleash the power of Thematic's dynamic classes 
define('THEMATIC_COMPATIBLE_BODY_CLASS', true);
define('THEMATIC_COMPATIBLE_POST_CLASS', true);

// Unleash the power of Thematic's comment form
// define('THEMATIC_COMPATIBLE_COMMENT_FORM', true);

// Unleash the power of Thematic's feed link functions
// define('THEMATIC_COMPATIBLE_FEEDLINKS', true);


////////// HIDE UPDATE NAG & FIX LOGIN SIZE /////////
    function fix_dash() {
      echo('<style type="text/css"> .update-nag {display:none!important;} </style>');
    }
    add_action('admin_head', 'fix_dash');
	
    function fix_login() {
      echo('<style type="text/css"> .login h1 a {background-size:300px 80px!important;} </style>');
    }
    add_action('login_head', 'fix_login');




////////// Remove Unnecessary Widget Areas //////////
function remove_widgetized_area($content) {
	unset($content['1st Subsidiary Aside']);
	unset($content['2nd Subsidiary Aside']);
	unset($content['3rd Subsidiary Aside']);
	unset($content['Index Top']);
	unset($content['Index Insert']);
	unset($content['Index Bottom']);
	unset($content['Single Top']);
	unset($content['Single Insert']);
	unset($content['Single Bottom']);
	// unset($content['Page Top']);
	unset($content['Page Bottom']);
	return $content;
}
add_filter('thematic_widgetized_areas', 'remove_widgetized_area');


////////// SEO-FRIENDLY PAGE TITLES //////////

function childtheme_doctitle() {
 
 // You don't want to change this one.
 $site_name = get_bloginfo('name');

 // But you like to have a different separator
 $separator = ' &mdash; ';

 // We will keep the original code
 if ( is_single() ) {
 $content = single_post_title('', FALSE);
 }
 elseif ( is_home() || is_front_page() ) {
 $content = get_bloginfo('description');
 }
 elseif ( is_page() ) {
 $content = single_post_title('', FALSE);
 }
 elseif ( is_search() ) {
 $content = __('Search Results for:', 'thematic');
 $content .= ' ' . wp_specialchars(stripslashes(get_search_query()), true);
 }
 elseif ( is_category() ) {
 $content = __('Category Archives:', 'thematic');
 $content .= ' ' . single_cat_title("", false);;
 }
 elseif ( is_tag() ) {
 $content = __('Tag Archives:', 'thematic');
 $content .= ' ' . thematic_tag_query();
 }
 elseif ( is_404() ) {
 $content = __('Not Found', 'thematic');
 }
 else {
 $content = get_bloginfo('description');
 }

 if (get_query_var('paged')) {
 $content .= ' ' .$separator. ' ';
 $content .= 'Page';
 $content .= ' ';
 $content .= get_query_var('paged');
 }

 // until we reach this point. You want to have the site_name everywhere?
 // Ok .. here it is.
 $my_elements = array(
 'site_name' => $site_name,
 'separator' => $separator,
 'content' => $content
 );

 // and now we're reversing the array as long as we're not on home or front_page
 // if (!( is_home() || is_front_page() )) {
 // $my_elements = array_reverse($my_elements);
 // }

 // And don't forget to return your new creation
 return $my_elements;
}

// Add the filter to the original function
add_filter('thematic_doctitle', 'childtheme_doctitle');

/**
 * Get site url for links 
 *
 * @author WPSnacks.com
 * @link http://www.wpsnacks.com
 */
function url_shortcode() {
return get_bloginfo('url');
}
add_shortcode('url','url_shortcode');



?>