<?php

class LearnDash_Course_Info_Widget extends WP_Widget {

	function LearnDash_Course_Info_Widget() {
		$widget_ops = array('classname' => 'widget_ldcourseinfo', 'description' => __('LearnDash - Course attempt and score information of users. Visible only to users logged in.', 'learndash'));
		$control_ops = array();//'width' => 400, 'height' => 350);
		$this->WP_Widget('ldcourseinfo', __('Course Information', 'learndash'), $widget_ops, $control_ops);
	}

	function widget( $args, $instance ) {

		extract($args);
		$title = apply_filters( 'widget_title', empty($instance['title']) ? '' : $instance['title'], $instance );

		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			if(empty($current_user->ID))
			return;
		
			$user_id = $current_user->ID;
		}	
		
		$courseinfo = learndash_course_info($user_id);
		
		if(empty($courseinfo))
		return;
		
		echo $before_widget;
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; } 
		
		echo $courseinfo;
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = strip_tags($instance['title']);
		//$text = format_to_edit($instance['text']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'learndash'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<?php
	}
}
function learndash_course_info($user_id){
	return SFWD_LMS::get_course_info($user_id);
}
add_action('widgets_init', create_function('', 'return register_widget("LearnDash_Course_Info_Widget");'));

function learndash_course_info_shortcode($atts){
	$current_user = wp_get_current_user();

	if(empty($current_user->ID))
	return;

	$user_id = $current_user->ID;
		
	return SFWD_LMS::get_course_info($user_id);
}
add_shortcode('ld_course_info', 'learndash_course_info_shortcode');