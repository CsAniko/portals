<?php


function ld_course_list($attr) {
	
	 $shortcode_atts = shortcode_atts ( array(
			'num' => '-1',
			'post_type' => 'sfwd-courses',
			'order' => 'DESC',
			'orderby' => 'ID',
			'mycourses' => false,
			'tag' => '',
			'tag_id' => 0,
			'tag__and' => '',
			'tag__in' => '',
			'tag__not_in' => '',
			'tag_slug__and' => '',
			'tag_slug__in' => '',			
			), $attr);
	extract($shortcode_atts);
	ob_start();
	$filter = array( 'post_type' => $post_type, 'posts_per_page' => $num, 'order' => $order , 'orderby' => $orderby );
	
	if(!empty($tag))
	$filter['tag'] = $tag;
	
	if(!empty($tag_id))
	$filter['tag_id'] = $tag;
	
	if(!empty($tag__and))
	$filter['tag__and'] = explode(",", $tag__and);
	
	if(!empty($tag__in))
	$filter['tag__in'] = explode(",", $tag__in);
	
	if(!empty($tag__not_in))
	$filter['tag__not_in'] = explode(",", $tag__not_in);
	
	if(!empty($tag_slug__and))
	$filter['tag_slug__and'] = explode(",", $tag_slug__and);
	
	if(!empty($tag_slug__in))
	$filter['tag_slug__in'] = explode(",", $tag_slug__in);
	
	
	
	$loop = new WP_Query( $filter );
		//print_r($loop);
	$course_list_template = get_template_directory()."/course_list_template.php";
	
	if(!file_exists($course_list_template))
	$course_list_template = dirname(__FILE__)."/course_list_template.php";
	
	
	while ( $loop->have_posts() ) : $loop->the_post();
		if(!$mycourses  || ld_course_check_user_access(get_the_ID()))
		include($course_list_template);
	endwhile; 
	$output = ob_get_clean();
	wp_reset_query(); 
	return $output;
}

function ld_lesson_list($attr) {
	$attr['post_type'] = 'sfwd-lessons';
	$attr['mycourses'] = false;
	return ld_course_list($attr);
}

function ld_quiz_list($attr) {
	$attr['post_type'] = 'sfwd-quiz';
	$attr['mycourses'] = false;
	return ld_course_list($attr);
}
add_shortcode("ld_course_list", "ld_course_list");
add_shortcode("ld_lesson_list", "ld_lesson_list");
add_shortcode("ld_quiz_list", "ld_quiz_list");

function ld_course_check_user_access($course_id, $user_id = null) {

	$course_options = get_post_meta($course_id, "_sfwd-courses", true);
	if(empty($course_options['sfwd-courses_course_price']) && empty($course_options['sfwd-courses_course_join']))
	return true;
	
	if(empty($user_id))
	{
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
	}
	
	$accesslist = explode(",", $course_options['sfwd-courses_course_access_list']);
	
	if(empty($user_id) || empty($accesslist) || !is_array($accesslist))
	return false;
	
	
	if(in_array($user_id, $accesslist))
	return true;
	else
	return false;
}

add_action( 'wp_head', 'ld_course_list_css' );

function ld_course_list_css()
{	
	?>
	<style>
		.ld-entry-content .attachment-post-thumbnail {
			float:left;
			margin: 15px;
		}
	</style>
	<?php
}

?>