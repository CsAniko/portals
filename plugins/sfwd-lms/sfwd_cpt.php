<?php

require_once('sfwd_module_class.php');

if ( !class_exists( 'SFWD_CPT' ) ) {
	abstract class SFWD_CPT extends Semper_Fi_Module {
		protected $post_name;
		protected $post_type;
		protected $post_options;
		protected $tax_options;
		protected $slug_name;
		protected $taxonomies = null;

		function __construct() {
			parent::__construct();
			$this->post_options = Array (	'label' 				=> $this->post_name,
											'labels'				=> Array(	'name' => $this->post_name,
																				'singular_name'		=> $this->post_name,
																				'add_new'			=> __( 'Add New', 'learndash' ),
																				'all_items'			=> $this->post_name,
																				'add_new_item'		=> sprintf( __( 'Add New %s', 'learndash' ), $this->post_name ),
																				'edit_item'			=> sprintf( __( 'Edit %s', 'learndash' ), $this->post_name ),
																				'new_item'			=> sprintf( __( 'New %s', 'learndash' ), $this->post_name ),
																				'view_item'			=> sprintf( __( 'View %s', 'learndash' ), $this->post_name ),
																				'search_items'		=> sprintf( __( 'Search %s', 'learndash' ), $this->post_name ),
																				'not_found'			=> sprintf( __( 'No %s found', 'learndash' ), $this->post_name ),
																				'not_found_in_trash'=> sprintf( __( 'No %s found in Trash', 'learndash' ), $this->post_name ),
																				'parent_item_colon'	=> sprintf( __( 'Parent %s', 'learndash' ), $this->post_name ),
																				'menu_name'			=> $this->post_name
																			),
											'public' 				=> true, 
											'rewrite'				=> Array( 'slug' => $this->slug_name, 'with_front' => false ),
											'show_ui' 				=> true,
											'has_archive'			=> true,
											'show_in_nav_menus'		=> true,
											'supports' 				=> Array(	'title',
																				'editor' )
							);
			$this->tax_options = Array( 'public' => true, 'hierarchical' => true );
		}

		function activate() {
			remove_action( 'init', Array( $this, 'add_post_type' ) );
			$this->add_post_type();
		}

		function deactivate() {
			remove_action( 'init', Array( $this, 'add_post_type' ) );
		}

		function admin_menu() {
			$this->add_menu("edit.php?post_type={$this->post_type}");
		}

		function add_post_type() {
			$this->post_options = apply_filters( 'sfwd_cpt_options', $this->post_options, $this->post_type );
			register_post_type( $this->post_type, $this->post_options );
			add_filter( 'sfwd_cpt_register_tax', Array( $this, 'register_tax' ), 10 );
		}
		
		function register_tax( $tax_data ) {
			if ( !is_array( $tax_data ) ) $tax_data = Array();
			if ( is_array( $this->taxonomies ) )
				foreach( $this->taxonomies as $k => $t ) {
					$this->tax_options['label'] = $t;
					$this->tax_options = apply_filters( 'sfwd_cpt_tax', $this->tax_options, $this->post_type, $k );
					if ( empty( $tax_data[$k] ) || !is_array( $tax_data[$k] ) ) $tax_data[$k] = Array();
					$tax_data[$k][] = Array( $this->post_type, $this->tax_options );
				}
			return $tax_data;
		}
		
		function loop_shortcode( $atts, $content = null ) {
				$args = array(
		        	"pagination"		=> '',
					"posts_per_page"	=> '',
		        	"query"				=> '',
		        	"category"			=> '',
		        	"post_type"			=> '',
		        	"order"				=> '',
		        	"orderby"			=> '',
		        	"meta_key"			=> '',
					"taxonomy"			=> 'courses',
					"tax_field"			=> 'slug',
					"tax_terms"			=> ''
		        );

				if ( !empty( $atts ) )
					foreach( $atts as $k => $v )
						if ( $v === '' ) unset( $atts[$k] );
				$filter = shortcode_atts( $args, $atts);
		        extract( shortcode_atts( $args, $atts) );		
		        global $paged, $post;

		        $posts = new WP_Query();
				
		        if( $pagination == 'true' ) $query .= '&paged=' . $paged;
		        if( !empty( $category   ) ) $query .= '&category_name=' . $category;

				foreach ( Array('post_type', 'order', 'orderby', 'meta_key', 'query') as $field)
					if ( !empty( $$field ) ) $query .= "&$field=" . $$field;
				
				$query = wp_parse_args( $query, $filter );
				if ( !empty( $taxonomy ) && !empty( $tax_field ) && !empty( $tax_terms ) ) {
					$query['tax_query'] = Array(
						Array( 'taxonomy' => $taxonomy, 'field' => $tax_field, 'terms' => $tax_terms )
					);

				}
		        $posts->query( $query );

		        $buf = '';
		        while ( $posts->have_posts() ) : $posts->the_post();	/*** run shortcodes in loop               ***/
		                        $id = $post->ID;              			/*** allow use of id variable in template ***/
								$class = '';
								if($post->post_type == 'sfwd-quiz')
								{
									$class = (learndash_is_quiz_notcomplete(null, array($post->ID => 1 )))? 'class="notcompleted"':'class="completed"';

								}
								else if($post->post_type == 'sfwd-lessons')
								{
									$class = (learndash_is_lesson_notcomplete(null, array($post->ID => 1 )))? 'class="notcompleted"':'class="completed"';
								}
								if(isset($_GET['test']))
								echo "<br>".$post_type.":".$post->post_type.":".$post->ID.":".$class;
							
																	
								$show_content = str_replace("{learndash_completed_class}", $class, $content );
								$show_content = apply_filters( 'sfwd_cpt_loop', $show_content );
								$show_content = str_replace( '$id', "$id", $show_content );
		                        $buf .= do_shortcode ( $show_content );
		        endwhile;
		        if ( $pagination == 'true' )
					$buf .= '<div class="navigation">
			          <div class="alignleft">' . get_previous_posts_link('« Previous') . '</div>
			          <div class="alignright">' . get_next_posts_link('More »') . '</div>
			        </div>';
		        wp_reset_query();
		        return $buf;
		}
		
		function shortcode( $atts, $content = null, $code ) {
			extract( shortcode_atts( array(
				'post_type' => $code,
				'posts_per_page' => -1,
				'taxonomy' => '',
				'tax_field' => '',
				'tax_terms' => '',
				'order' => 'DESC',
				'orderby' => 'date',
				'wrapper' => 'div',
				'title' => 'h4'
			), $atts ) );

			global $shortcode_tags;
			$save_tags = $shortcode_tags;

			add_shortcode( 'loop', Array( $this, 'loop_shortcode' ) );
			add_shortcode( 'the_title', 'get_the_title' );
			add_shortcode( 'the_permalink', 'get_permalink' );
			add_shortcode( 'the_excerpt', 'get_the_excerpt' );
			add_shortcode( 'the_content', 'get_the_content' );		
										
			$template = "[loop post_type='$post_type' posts_per_page='$posts_per_page' order='$order' orderby='$orderby' taxonomy='$taxonomy' tax_field='$tax_field' tax_terms='$tax_terms']"
								  . "<$wrapper id=post-\$id><$title><a {learndash_completed_class} href='[the_permalink]'>[the_title]</a></$title>"
								  . "</$wrapper>[/loop]";
			// <div class='entry-content'>[the_content]</div>
			$template = apply_filters( 'sfwd_cpt_template', $template );
			$buf = do_shortcode( $template );

			$shortcode_tags = $save_tags;
			return $buf;
		}
		
		function get_settings_values( $location = null ) {
			$settings = $this->setting_options( $location );
			$values = $this->get_current_options( Array(), $location );
			foreach ( $settings as $k => $v )
				$settings[$k]['value'] = $values[$k];
			return $settings;
		}
		
		function display_settings_values( $location = null ) {
			$meta = $this->get_settings_values( $location );
			if ( !empty( $meta ) ) {
			?>
			<ul class='post-meta'>
			<?php
			foreach ( $meta as $m )
				echo "<li><span class='post-meta-key'>{$m['name']}</span> {$m['value']}</li>\n";
			?>
			</ul>
			<?php
			}
		}
	}
}

/* Adds widget for displaying posts */
if ( !class_exists( 'SFWD_CPT_Widget' ) ) {
	class SFWD_CPT_Widget extends WP_Widget {
		protected $post_type;
		protected $post_name;
		protected $post_args;
		public function __construct( $post_type, $post_name, $args = Array() ) {
			$this->post_type = $post_type;
			$this->post_name = $post_name;
			if ( !is_array( $args ) ) $args = Array();
			if ( empty( $args['description'] ) )
				$args['description'] = sprintf( __( "Displays a list of %s", 'learndash' ), $post_name );
			
			if ( empty( $this->post_args ) )
				$this->post_args = Array( 'post_type' => $this->post_type, 'numberposts' => -1, 'order' => 'DESC', 'orderby' => 'date' );
				
			parent::__construct( "{$post_type}-widget", $post_name, $args );
		}

		public function widget( $args, $instance ) {

			extract( $args, EXTR_SKIP );

			/* Before Widget content */
			$buf = $before_widget;

			/* Get user defined widget title */
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
			
			if ( !empty( $title ) ) $buf .= $before_title . $title . $after_title;
			$buf .= '<ul>';

			/* Display Widget Data */
			$args = $this->post_args;

			$args['posts_per_page'] = $args['numberposts'];
			$args['wrapper'] = 'li';
			global $shortcode_tags;
			if ( !empty( $shortcode_tags[ $this->post_type ] ) )
				$buf .= call_user_func( $shortcode_tags[ $this->post_type ], $args, null, $this->post_type );
			
			/* After Widget content */
			$buf .= '</ul>' . $after_widget;
			echo $buf;
		}

		public function update( $new_instance, $old_instance ) {

			/* Updates widget title value */
			$instance = $old_instance;
			$instance['title'] = strip_tags( $new_instance['title'] );
			return $instance;

		}

		public function form( $instance ) {
			if ( $instance )
				$title = esc_attr( $instance[ 'title' ] );
			else
				$title = $this->post_name;
			?>
			<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'learndash' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" 
			name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
			</p>
			<?php 
		}
	}
}
