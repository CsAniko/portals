<?php
/**
 * widget_contactbuddy Class
 *
 * Adds widget capabilities.
 *
 * Author:	Skyler Moore
 * Date:	August 2010
 *
 */

 
class widget_contactbuddy extends WP_Widget {
	var $_widget_control_width = 300;
	var $_widget_control_height = 300;
	
	
	/**
	 * widget_contactbuddy::widget_contactbuddy()
	 * 
	 * Default constructor.
	 * 
	 * @return void
	 */
	function widget_contactbuddy() {
		$widget_ops = array('description' => __('Displays contact form as a widget..', 'contactbuddy'));
		$this->WP_Widget('contactbuddy', __('ContactBuddy'), $widget_ops);
	}
	
	
	/**
	 * widget_contactbuddy::widget()
	 *
	 * Display public widget.
	 *
	 * @param	array	$args		Widget arguments -- currently not in use.
	 * @param	array	$instance	Instance data including title, group id, etc.
	 * @return	void
	 */
	function widget($args, $instance) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $before_widget;
		
		if ( $title )
			echo $before_title . $title . $after_title;
		
		$group = intval( $instance['group'] );
		do_action( 'contactbuddy-widget', $group, true);
		
		echo $after_widget;
	}
	
	
	/**
	 * widget_contactbuddy::update()
	 *
	 * Save widget form settings.
	 *
	 * @param	array	$new_instance	NEW instance data including title, group id, etc.
	 * @param	array	$old_instance	PREVIOUS instance data including title, group id, etc.
	 * @return	void
	 */
	function update($new_instance, $old_instance) {
		if (!isset($new_instance['submit'])) {
			return false;
		}
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['group'] = intval($new_instance['group']);
		return $instance;
	}
	
	
	/**
	 * widget_contactbuddy::form()
	 *
	 * Display widget control panel.
	 *
	 * @param	array	$instance	Instance data including title, group id, etc.
	 * @return	void
	 */
	function form($instance) {
		$title = ( isset( $instance['title'] ) ) ? $instance['title'] : '';
		?>
		<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'contactbuddy'); ?>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</label>
		
		<input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
		<?php
	}
	
	
} // End widget_contactbuddy class.

// Register function to create widget.
add_action('widgets_init', 'widget_contactbuddy_init');

function widget_contactbuddy_init() {
	register_widget('widget_contactbuddy');
}
?>
