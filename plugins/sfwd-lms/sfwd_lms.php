<?php
/*
Plugin Name: LearnDash LMS
Plugin URI: http://www.learndash.com
Description: LearnDash LMS Plugin - Turn your WordPress site into a learning management system.
Version: 1.3.5
Author: LearnDash
Author URI: http://www.learndash.com
*/

require_once(dirname(__FILE__).'/sfwd_cpt.php');
require_once(dirname(__FILE__).'/course_progress.php');
require_once(dirname(__FILE__).'/course_list_shortcode.php');
require_once(dirname(__FILE__).'/course_info_widget.php');
require_once(dirname(__FILE__).'/quiz_pro.php');

function sfwd_lms_has_access( $post_id ) {
	if ( current_user_can( 'level_8' ) ) return true;
	$courses = wp_get_object_terms( $post_id, 'courses' );
	if ( empty( $courses ) ) return true;
	$course = $courses[0];
	if ( !empty( $course->slug ) ) {
		$post_type = 'sfwd-courses';
		$p = get_posts( Array( 'name' => $course->slug, 'post_type' => $post_type, 'post_status' => 'publish', 'numberposts' => 1 ) );
		if ( empty( $p ) ) return true;
		$p = $p[0];
		$post_options = get_post_meta( $p->ID, '_' . $post_type, true );
		if ( empty( $post_options ) ) return $p->ID;
		$courses_prefix = $post_type . '_';
		$prefix_len = strlen( $courses_prefix );
		foreach( $post_options as $k => $v ) {
		    if ( strpos( $k, $courses_prefix ) === 0 ) {
		            $post_options[ substr( $k, $prefix_len ) ] = $v;
		            unset( $post_options[$k] );
		    }
		}
		extract($post_options);
		if ( empty( $course_price ) || ( $course_price == 0 ) ) return true;
		if ( !is_user_logged_in() ) return false;
		if ( !empty( $course_access_list ) ) $course_access_list = explode( ',', $course_access_list );
		else $course_access_list = Array();
		$user_id = get_current_user_id();
		if ( !in_array( $user_id, $course_access_list ) ) return $p->ID;
	}
	if ( !is_user_logged_in() ) return false;
	return true;
}

function sfwd_lms_access_redirect( $post_id ) {
	$access = sfwd_lms_has_access( $post_id );
	if ( $access === false ) auth_redirect();
	if ( $access === true ) return $access;
	$link = get_permalink( $access );
	wp_redirect( $link );
	exit();
}

if ( !class_exists( 'SFWD_LMS' ) ) {
	class SFWD_LMS extends Semper_Fi_Module  {
		public $post_types = Array();
		public $cache_key = '';
		public $quiz_json = '';
		public $count = null;
		
		function __construct() {
			self::$instance =& $this;
			$this->file = __FILE__;
			$this->name = "LMS";
			$this->plugin_name = "SFWD LMS";
			$this->name = 'LMS Options';
			$this->prefix = 'sfwd_lms_';
			$this->parent_option = 'sfwd_lms_options';
			parent::__construct();
			register_activation_hook(   $this->plugin_path['basename'], Array ( $this, 'activate' ) );
			add_action( 'init', Array( $this, 'add_post_types' ), 1 );
			add_filter( 'query_vars', Array( $this, 'add_query_vars' ) );
			add_action( 'parse_request', Array( $this, 'parse_ipn_request' ) );
			add_action( 'generate_rewrite_rules', Array( $this, 'paypal_rewrite_rules' ) );
			add_filter( 'sfwd_cpt_loop', Array( $this, 'cpt_loop_filter' ) );
			add_filter( 'edit_term_count', Array( $this, 'tax_term_count' ), 10, 3 );
			add_action( 'init', Array( $this, 'add_tag_init' ) ); //Initialise the tagging capability here
			add_action( 'plugins_loaded', Array($this, 'i18nize') );	//Add internationalization support
			
			add_shortcode( 'usermeta', Array( $this, 'usermeta_shortcode' ) );
			if ( is_admin() && get_transient( 'sfwd_lms_rewrite_flush' ) ) {
				add_action( 'admin_init', 'flush_rewrite_rules' );
				set_transient( 'sfwd_cpt_rewrite_flush', false );
			}
		}
 		function add_tag_init()
 		{
				$tag_args = array(
 					'taxonomies' => array('post_tag')
 					);
				register_post_type('sfwd-courses',$tag_args); //Tag arguments for $post_type='sfwd-courses'
				register_post_type('sfwd-lessons',$tag_args); //Tag arguments for $post_type='sfwd-courses'
				register_post_type('sfwd-quiz',$tag_args); //Tag arguments for $post_type='sfwd-courses'

 		}
 		function i18nize(){
			load_plugin_textdomain( 'learndash', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 			
 		}
		
		function tax_term_count( $columns, $id, $tax ) {
			if ( empty( $tax ) || ( $tax != 'courses' ) ) return $columns;
			if ( !empty( $_GET ) && !empty( $_GET['post_type'] ) ) {
				$post_type = $_GET['post_type'];
				$wpq = array (		'tax_query' => Array( Array( 'taxonomy' => $tax, 'field' => 'id', 'terms' => $id ) ),
									'post_type' => $post_type,
									'post_status' => 'publish',
									'posts_per_page' => -1
								);
				$q = new WP_Query ($wpq);
				$this->count = $q->found_posts;
				add_filter( 'number_format_i18n', Array( $this, 'column_term_number' ) );
			}
			return $columns;
		}
		
		function column_term_number( $number ) {
			remove_filter( 'number_format_i18n', Array( $this, 'column_term_number' ) );
			if ( $this->count !== null ) {
				$number = $this->count;
				$this->count = null;
			}
			return $number;
		}
		
		function usermeta_shortcode( $attr, $content = null ) {
			extract(shortcode_atts( array( "field" => null ), $attr ) );	
		    global $user_info, $user_ID;
		    get_currentuserinfo();
		    $user_info = get_userdata( $user_ID );
			if ( is_user_logged_in() )
				return $user_info->$field;
			return "";
		}
		
		function cpt_loop_filter( $content ) {
			global $post;
			if ( $post->post_type == 'sfwd-quiz' ) {
				$meta = get_post_meta( $post->ID, '_sfwd-quiz' );
				if ( is_array( $meta ) && !empty( $meta ) ) {
					$meta = $meta[0];
					if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_lesson'] ) ) )
						$content = '';
				}
			}
			return $content;
		}
		
		function activate() {
			set_transient( 'sfwd_lms_rewrite_flush', true );
		}
		
		function add_query_vars($vars) {
			return array_merge( array( 'sfwd-lms' ), $vars );
		}
		
		function parse_ipn_request( $wp ) {
		    if (array_key_exists('sfwd-lms', $wp->query_vars) 
		            && $wp->query_vars['sfwd-lms'] == 'paypal') {
				require_once( 'ipn.php' );
		    }
		}
		
		function paypal_rewrite_rules( $wp_rewrite ) {
			$wp_rewrite->rules = array_merge( array( 'sfwd-lms/paypal' => 'index.php?sfwd-lms=paypal' ), $wp_rewrite->rules );
		}

		function add_post_types() {
			$post = 0;
			if ( is_admin() && !empty( $_GET ) && ( isset( $_GET['post'] ) ) )
				$post_id = $_GET['post'];
			if ( !empty( $post_id ) ) {
				$this->quiz_json = get_post_meta( $post_id, '_quizdata', true );
				if ( !empty( $this->quiz_json ) )
					$this->quiz_json = $this->quiz_json['workingJson'];
			}
			$options = get_option('sfwd_cpt_options');
			
			$level1 = $level2 = $level3 = $level4 = $level5 = ''; 
			if ( !empty( $options['modules'] ) ) {
				$options = $options['modules'];
				if ( !empty( $options['sfwd-quiz_options'] ) ) {
					$options = $options['sfwd-quiz_options'];
					foreach( Array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level ) {
						$$level = '';
						if ( !empty( $options["sfwd-quiz_{$level}"] ) )
							$$level = $options["sfwd-quiz_{$level}"];
					}
				}
			}
			if ( empty( $this->quiz_json ) ) $this->quiz_json = '{"info":{"name":"","main":"","results":"","level1":"' . $level1 . '","level2":"' . $level2 . '","level3":"' . $level3 . '","level4":"' . $level4 . '","level5":"' . $level5 . '"}}';
			$posts_per_page = get_option( 'posts_per_page' );
			if ( empty( $posts_per_page ) ) $posts_per_page = 5;
			$post_args = Array(
					Array(
					  'plugin_name' => __('Courses', 'learndash'),
					  'slug_name' => 'courses',
					  'post_type' => 'sfwd-courses',
					  'template_redirect' => true,
					  'taxonomies' => Array( 'courses' => __('Manage Course Associations', 'learndash') ),
					  'cpt_options' => Array( 'hierarchical' => 'false', 'supports' => array ( 'title', 'editor', 'thumbnail' , 'author', 'comments', 'revisions') ),
					  'fields' => 
					  Array( 'course_materials' => 
					    Array(
					      'name' => __('Course Materials', 'learndash'),
					      'type' => 'textarea',
					      'help_text' => __('Options for course materials', 'learndash'),
					    ),
					    'course_price' => 
					    Array(
					      'name' => __('Course Price', 'learndash'),
					      'type' => 'text',
					      'help_text' => __('Options for course price', 'learndash'),
					    ),
						'course_access_list' => Array(
						  'name' => __('Course Access List', 'learndash'),
						  'type' => 'textarea',
						  'help_text' => __('Access list for courses.', 'learndash')
						),
						'course_lesson_orderby' => Array( 
							'name' => __('Sort Lesson By', 'learndash'),
							'type' => 'select',
							'initial_options' => 
								Array(	''		=> __('Use Default', 'learndash'),
										'title'	=> __('Title', 'learndash'),
										'date'	=> __('Date', 'learndash'),
										'menu_order' => __('Menu Order', 'learndash')
								),
							'default' => '',
							'help_text' => __('Choose the sort order of lessons in this course.', 'learndash')
							),
						'course_lesson_order' => Array( 
							'name' => __('Sort Lesson Direction', 'learndash'),
							'type' => 'select',
							'initial_options' => 
								Array(	''		=> __('Use Default', 'learndash'),
										'ASC'	=> __('Ascending', 'learndash'),
										'DESC'	=> __('Descending', 'learndash')
								),
							'default' => '',
							'help_text' => __('Choose the sort order of lessons in this course.', 'learndash')
							),
						'course_disable_lesson_progression' => Array( 
							'name' => __('Disable Lesson Progression', 'learndash'),
							'type' => 'checkbox',
							'default' => 0,
							'help_text' => __('Disable the feature that allows attempting lessons only in allowed order.', 'learndash')
							),
					  ),
					  'default_options' => Array(
						'paypal_email'		=> Array( 'name' => __('PayPal Email', 'learndash'), 'help_text' => __('Enter your PayPal email here.', 'learndash'), 'type' => 'text' ),
						'paypal_currency'	=> Array( 'name' => __('PayPal Currency', 'learndash'), 'help_text' => __('Enter the currency code for transactions.', 'learndash'), 'type' => 'text', 'default' => 'USD' ),
						'paypal_country'	=> Array( 'name' => __('PayPal Country', 'learndash'), 'help_text' => __('Enter your country code here.', 'learndash'), 'type' => 'text', 'default' => 'US' ),
						'paypal_cancelurl'	=> Array( 'name' => __('PayPal Cancel URL', 'learndash'), 'help_text' => __('Enter the URL used for purchase cancellations.', 'learndash'), 'type' => 'text', 'default' => get_home_url() ),
						'paypal_returnurl'	=> Array( 'name' => __('PayPal Return URL', 'learndash'), 'help_text' => __('Enter the URL used for completed purchases (typically a thank you page).', 'learndash'), 'type' => 'text', 'default' => get_home_url() ),
						'paypal_notifyurl'	=> Array( 'name' => __('PayPal Notify URL', 'learndash'), 'help_text' => __('Enter the URL used for IPN notifications.', 'learndash'), 'type' => 'text', 'default' => get_home_url() . "/sfwd-lms/paypal" ),
						'paypal_sandbox'	=> Array( 'name' => __('Use PayPal Sandbox', 'learndash'), 'help_text' => __('Check to enable the PayPal sandbox.', 'learndash') ),
						'shortcode_options' => Array(
								'name' => __('Shortcode Options', 'learndash'),
								'type' => 'html',
								'default' => '<b>' . __('Shortcode Options', 'learndash') . '</b>

											<p>' . __('You may use shortcodes to add information to any page/course/lesson/quiz. Here are built-in shortcodes for displaying relavent user information.', 'learndash') . '</p>
											<p><b>[ld_course_info]</b></p><p>' . __('This shortcode displays the users course progress and list of quiz scores.', 'learndash') . '</p>

											<br>
											<p><b>[ld_course_list]</b></p><p>' . __('This shortcode shows list of courses. You can use this short code on any page if you dont want to use the default /courses page. This short code can take following parameters:', 'learndash') . '</p>
											<ul>
											<li><b>num</b>: ' . __('limits the number of courses displayed. Example: <b>[ld_course_list num="10"]</b> shows 10 courses.', 'learndash') . '</li>
											<li><b>order</b>: ' . __('sets order of courses. Possible values: <b>DESC</b>, <b>ASC</b>. Example: <b>[ld_course_list order="ASC"]</b> shows courses in assending order.', 'learndash') . '</li>
											<li><b>orderby</b>: ' . __('sets what the list of ordered by. Example: <b>[ld_course_list order="ASC" orderby="title"]</b> shows courses in assending order by title.', 'learndash') . '</li>
											<li><b>tag</b>: ' . __('shows courses with mentioned tag. Example: <b>[ld_course_list tag="math"]</b> shows courses having tag math.', 'learndash') . '</li>
											<li><b>tag_id</b>: ' . __('shows courses with mentioned tag_id. Example: <b>[ld_course_list tag_id="30"]</b> shows courses having tag with tag_id 30.', 'learndash') . '</li>
											<li><b>mycourses</b>: ' . __('show current user\'s courses. Example: <b>[ld_course_list mycourses="true"]</b> shows courses the current user has access to.', 'learndash') . '</li>
											</ul>
											<p>' . __('See <a target="_blank" href="https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters">the full list of available orderby options here.</a>', 'learndash') . '</p>
											<br>
											<p><b>[ld_lesson_list]</b></p><p>' . __('This shortcode shows list of lessons. You can use this short code on any page. This short code can take following parameters: num, order, orderby, tag, tag_id. See [ld_course_list] above details on using the shortcode parameters.', 'learndash') . '</p>
											<br>
											<p><b>[ld_quiz_list]</b></p><p>' . __('This shortcode shows list of quizzes. You can use this short code on any page. This short code can take following parameters: num, order, orderby, tag, tag_id. See [ld_course_list] above details on using the shortcode parameters.', 'learndash') . '</p>
											<br>
											<p><b>[learndash_course_progress]</b></p><p>' . __('This shortcode displays users progress bar for the course in any course/lesson/quiz pages.', 'learndash') . '</p>',
								'save' => false,
								'label' => 'none'
							),	
					  ),
					),
					Array(
					  'plugin_name' => __('Lessons', 'learndash'),
					  'slug_name' => 'lessons',
					  'post_type' => 'sfwd-lessons',
					  'template_redirect' => true,
					  'taxonomies' => Array( 'courses' => __('Manage Course Associations', 'learndash') ),
					  'cpt_options' => Array( 'supports' => array ( 'title', 'thumbnail', 'editor', 'page-attributes' , 'author', 'comments', 'revisions') ),
					  'fields' => Array(
							'forced_lesson_time' => Array( 'name' => __('Forced Lesson Timer', 'learndash'), 'type' => 'text', 'help_text' => __('Minimum time a user has to spend on Lesson page before it can be marked complete. Examples: 40 (for 40 seconds), 20s, 45sec, 2m 30s, 2min 30sec, 1h 5m 10s, 1hr 5min 10sec', 'learndash'), 'default' => '' ),		
							),
					  'default_options' => Array(
												'orderby' => Array( 
													'name' => __('Sort By', 'learndash'),
													'type' => 'select',
													'initial_options' => 
														Array(	''		=> __('Select a choice...', 'learndash'),
																'title'	=> __('Title', 'learndash'),
																'date'	=> __('Date', 'learndash'),
																'menu_order' => __('Menu Order', 'learndash')
														),
													'default' => 'date',
													'help_text' => __('Choose the sort order.', 'learndash')
													),
												'order' => Array( 
													'name' => __('Sort Direction', 'learndash'),
													'type' => 'select',
													'initial_options' => 
														Array(	''		=> __('Select a choice...', 'learndash'),
																'ASC'	=> __('Ascending', 'learndash'),
																'DESC'	=> __('Descending', 'learndash')
														),
													'default' => 'DESC',
													'help_text' => __('Choose the sort order.', 'learndash')
													),
												'posts_per_page' => Array(
														'name' => __('Posts Per Page', 'learndash'),
														'type' => 'text',
														'help_text' => __('Enter the number of posts to display per page.', 'learndash'),
														'default' => $posts_per_page
													),
												)
					),
					Array(
					  'plugin_name' => __('Quizzes', 'learndash'),
					  'slug_name' => 'quizzes',
					  'post_type' => 'sfwd-quiz',
					  'template_redirect' => true,
					  'taxonomies' => Array( 'courses' => __('Manage Course Associations', 'learndash') ),
					  'cpt_options' => Array( 'hierarchical' => 'true', 'supports' => array ( 'title', 'thumbnail', 'editor', 'custom-fields' , 'author', 'comments', 'revisions' ) ),
					  'fields' => 
					  Array( 
						'repeats' => Array( 'name' => __('Repeats', 'learndash'), 'type' => 'text', 'help_text' => __('Number of repeats allowed for quiz', 'learndash'), 'default' => '' ),
						'threshold' => Array( 'name' => __('Certificate Threshold', 'learndash'), 'type' => 'text', 'help_text' => __('Minimum score required to award a certificate, between 0 and 1 where 1 = 100%.', 'learndash'), 'default' => '0.8' ),
						'passingpercentage' => Array( 'name' => __('Passing Percentage', 'learndash'), 'type' => 'text', 'help_text' => __('Passing percentage required to pass the quiz (number only). e.g. 80 for 80%.', 'learndash'), 'default' => '80' ),
						'lesson' => Array( 'name' => __('Associated Lesson', 'learndash'), 'type' => 'select', 'help_text' => __('Optionally associate a quiz with a lesson.', 'learndash'), 'default' => '' ),
						'certificate' => Array( 'name' => __('Associated Certificate', 'learndash'), 'type' => 'select', 'help_text' => __('Optionally associate a quiz with a certificate.', 'learndash'), 'default' => '' ),
						'quiz_pro' => Array( 'name' => __('Advanced Quiz', 'learndash'), 'type' => 'select', 'help_text' => __('Select an Advanced. Quiz, or build a Simple Quiz below. You can create an Advanced Quiz <a href="', 'learndash').admin_url( 'admin.php?page=ldAdvQuiz').'">here</a>.', 'initial_options' => (array(0 => '- Select A Quiz -') + LD_QuizPro::get_quiz_list()) , 'default' => ''),
						'quiz' => Array(
					      'name' => __('Quiz', 'learndash'),
					      'type' => 'html',
					      'help_text' => __('Options for quiz', 'learndash'),
						  'label' => 'none',
						  'save' => false,
						  'default' => '<p>' . __('You may select a quiz above or edit your quiz below once this post is created. <b style="color:#f00;">All fields are required and MUST be filled in, or data will be lost</b>, quizzes are in multiple choice format.', 'learndash') . '</p><div class="quizFormWrapper"></div><script type="text/javascript">var quizJSON = ' . $this->quiz_json . ';</script>'
					    ),
					  ),
					  'default_options' => Array(
						'start_button_text' => Array( 'name' => __('Start Button Text', 'learndash'), 'help_text' => __('Display this text on the start button for the quiz', 'learndash'), 'default' => __('Get Started!', 'learndash'), 'type' => 'text' ),
						'check_answer_text' => Array( 'name' => __('Check Answer Text', 'learndash'), 'help_text' => __('Text displayed for check answer button.', 'learndash'), 'default' => __('Check My Answer!', 'learndash'), 'type' => 'text' ),
						'next_question_text' => Array( 'name' => __('Next Question Text', 'learndash'), 'help_text' => __('Text displayed for next question button.', 'learndash'), 'default' => __('Next >>', 'learndash'), 'type' => 'text' ),
						'back_button_text' => Array( 'name' => __('Back Button Text', 'learndash'), 'help_text' => __('Text displayed for back button.', 'learndash'), 'default' => '', 'type' => 'text' ),
						'your_score_text' => Array( 'name' => __('Your Score Text', 'learndash'), 'help_text' => __('Text displayed for your score prompt.', 'learndash'), 'default' => __('Your Score:', 'learndash'), 'type' => 'text' ),
						'your_ranking_text' => Array( 'name' => __('Your Ranking Text', 'learndash'), 'help_text' => __('Text displayed for your ranking prompt.', 'learndash'), 'default' => __('Your Ranking:', 'learndash'), 'type' => 'text' ),
						'disabled_quiz_message' => Array( 'name' => __('Disabled Quiz Message', 'learndash'), 'help_text' => __('Message displayed for disabled quizzes.', 'learndash'), 'default' => __('Sorry. The requested quiz has been disabled.', 'learndash'), 'type' => 'text' ),
						'missing_quiz_message' => Array( 'name' => __('Missing Quiz Message', 'learndash'), 'help_text' => __('Message displayed for missing quizzes.', 'learndash'), 'default' => __('Sorry The requested quiz could not be found.', 'learndash'), 'type' => 'text' ),
					  	'level1' => Array( 'name' => __('Knowledge Level 1', 'learndash'), 'help_text' => __('Name for knowledge level 1 (from best to worst, 1 - 5)', 'learndash'), 'default' => 'Genius', 'type' => 'text' ),
					  	'level2' => Array( 'name' => __('Knowledge Level 2', 'learndash'), 'help_text' => __('Name for knowledge level 2 (from best to worst, 1 - 5)', 'learndash'), 'default' => __('Expert', 'learndash'), 'type' => 'text' ),
					  	'level3' => Array( 'name' => __('Knowledge Level 3', 'learndash'), 'help_text' => __('Name for knowledge level 3 (from best to worst, 1 - 5)', 'learndash'), 'default' => __('Intermediate', 'learndash'), 'type' => 'text' ),
					  	'level4' => Array( 'name' => __('Knowledge Level 4', 'learndash'), 'help_text' => __('Name for knowledge level 4 (from best to worst, 1 - 5)', 'learndash'), 'default' => __('Initiate', 'learndash'), 'type' => 'text' ),
					  	'level5' => Array( 'name' => __('Knowledge Level 5', 'learndash'), 'help_text' => __('Name for knowledge level 5 (from best to worst, 1 - 5)', 'learndash'), 'default' => __('Beginner', 'learndash'), 'type' => 'text' ),
						'certificate_post'  => Array( 'name' => __('Select Certificate', 'learndash'), 'help_text' => __('Select a Post to use as a Certificate For Quizzes.', 'learndash'), 'type' => 'select', 'initial_options' => Array() ),
						'correct' => Array( 'name' => __('Correct Response Message', 'learndash'), 'help_text' => __('Message text for a correct quiz response.', 'learndash'), 'default' => 'Right!', 'type' => 'text' ),
						'incorrect' => Array( 'name' => __('Incorrect Response Message', 'learndash'), 'help_text' => __('Message text for an incorrect quiz response.', 'learndash'), 'default' => 'Wrong.', 'type' => 'text' ),
						'random_sort_questions' => Array( 'name' => __('Random Sort Questions', 'learndash'), 'help_text' => __('Random sort questions option.', 'learndash') ),
						'random_sort_answers' => Array( 'name' => __('Random Sort Answers', 'learndash'), 'help_text' => __('Random sort answers option.', 'learndash') ),
						'random_sort' => Array( 'name' => __('Random Sort', 'learndash'), 'help_text' =>__('Random sort option.', 'learndash') ),
						'disable_next' => Array( 'name' => __('Disable Next', 'learndash'), 'help_text' => __('Disable the next option.', 'learndash') ),
						'disable_responses' => Array( 'name' => __('Disable Responses', 'learndash'), 'help_text' => __('Disable responses.', 'learndash') ),
						'completion_responses' => Array( 'name' => __('Completion Responses', 'learndash'), 'help_text' => __('Enable completion responses.', 'learndash') ),
					 )
					)
				);
			$cert_defaults = 	Array(
										'orderby' => Array( 
											'name' => __('Sort By', 'learndash'),
											'type' => 'select',
											'initial_options' => 
												Array(	''		=> __('Select a choice...', 'learndash'),
														'title'	=> __('Title', 'learndash'),
														'date'	=> __('Date', 'learndash'),
														'menu_order' => __('Menu Order', 'learndash')
												),
											'default' => 'date',
											'help_text' => __('Choose the sort order.', 'learndash')
											),
										'order' => Array( 
											'name' => __('Sort Direction', 'learndash'),
											'type' => 'select',
											'initial_options' => 
												Array(	''		=> __('Select a choice...', 'learndash'),
														'ASC'	=> __('Ascending', 'learndash'),
														'DESC'	=> __('Descending', 'learndash')
												),
											'default' => 'DESC',
											'help_text' => __('Choose the sort order.', 'learndash')
											),
										'posts_per_page' => Array(
												'name' => __('Posts Per Page', 'learndash'),
												'type' => 'text',
												'help_text' => __('Enter the number of posts to display per page.', 'learndash'),
												'default' => $posts_per_page
											),
										'shortcode_options' => Array(
												'name' => 'Shortcode Options',
												'type' => 'html',
												'default' => __('<b>Shortcode Options</b><p>You may use shortcodes to customize the display of your certificates. Provided is a built-in shortcode for displaying user information.</p><p><b>[usermeta]</b><p>This shortcode takes a parameter named field, which is the name of the user meta data field to be displayed.</p><p>Example: <b>[usermeta field="display_name"]</b> would display the user\'s Display Name.</p><p>See <a href="http://codex.wordpress.org/Function_Reference/get_userdata#Notes">the full list of available fields here</a>.</p>', 'learndash'),
												'save' => false,
												'label' => 'none'
											),
										);
			$post_args[] = 
					Array(
					  'plugin_name' => __('Certificates', 'learndash'),
					  'slug_name' => 'certificates',
					  'post_type' => 'sfwd-certificates',
					  'template_redirect' => false,
					  'fields' => Array(),
					  'default_options' => $cert_defaults,
					  'cpt_options' => Array( 'exclude_from_search' => true, 'has_archive' => false )
					);
					
			if ( current_user_can( 'level_8' ) ) {
				$post_args[] = 
						Array(
						  'plugin_name' => __('Transactions', 'learndash'),
						  'slug_name' => 'transactions',
						  'post_type' => 'sfwd-transactions',
						  'template_redirect' => false,
						  'cpt_options' => Array( 'supports' => array ( 'title', 'custom-fields' ), 'exclude_from_search' => true, 'publicly_queryable' => false, 'show_in_nav_menus' => false , 'show_in_menu'	=> 'edit.php?post_type=sfwd-courses'),
						  'fields' => Array(),
						  'default_options' => Array( null => Array( 'type' => 'html', 'save' => false, 'default' => __('Click the Export button below to export the transaction list.', 'learndash') ) )
						);
				add_action( 'admin_init', Array( $this, 'trans_export_init' ) );
				add_action( 'admin_init', Array( $this, 'quiz_export_init' ) );
				add_action( 'admin_init', Array( $this, 'course_export_init' ) );
			}
			add_action( 'show_user_profile', Array( $this, 'show_course_info' ) );
			add_action( 'edit_user_profile', Array( $this, 'show_course_info' ) );
			
			foreach( $post_args as $p )
				$this->post_types[$p['post_type']] = new SFWD_CPT_Instance( $p );
			add_action( 'publish_sfwd-courses', Array( $this, 'add_course_tax_entry' ), 10, 2 );
			add_action( 'init', Array( $this, 'tax_registration' ), 11 );
			$sfwd_quiz = $this->post_types['sfwd-quiz'];
			$quiz_prefix = $sfwd_quiz->get_prefix();
			add_filter( "{$quiz_prefix}display_settings", Array( $this, "quiz_display_settings" ), 10, 3 );
		}
		
		function show_course_info( $user ) {
			$user_id = $user->ID;
			echo "<h3>" . __('Course Info', 'learndash') . "</h3>";
			echo $this->get_course_info($user_id);
		}
		function get_course_info($user_id) {
			$return = '';
			
			$usermeta = get_user_meta( $user_id, '_sfwd-courses', true );
			if ( !empty( $usermeta ) ) {
				$return .=  "<h4>" . __('You are registered for the following courses', 'learndash') . "</h4>";
				$usermeta = explode( ",", $usermeta );
				foreach( $usermeta as $c ) {
					$course = get_post( $c );
					$return .=   $course->post_title . "<br />";
				}
				$return .=   "<br />";
			}
			$course_progress = get_user_meta($user_id, '_sfwd-course_progress', true); 
			if(!empty($course_progress))
			{
				$return .=   "<b>" . __('Course progress details:', 'learndash') . "</b><br><br>";
				foreach($course_progress as $course_id => $coursep)
				{
					$course = get_post($course_id);
					$return .= "<b>".$course->post_title."</b>: " . __('Completed', 'learndash') . " <b>".$coursep['completed']."</b> " . __('out of', 'learndash') . " <b>".$coursep['total']."</b> " . __('steps', 'learndash') . "<br>";
				}
				$return .=   "<br>";
			}
			
			$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
			if ( !empty( $usermeta ) ) {
				$return .=   __('<h4>You have taken the following quizzes</h4>', 'learndash');
				foreach( $usermeta as $k => $v ) {
					$quiz = get_post( $v['quiz'] );
					$passstatus = isset($v['pass'])? (($v['pass'] == 1)? "green":"red"):"";
					$c = learndash_certificate_details($v['quiz'], $user_id);
					$certificateLink = $c['certificateLink'];
					$certificate_threshold = $c['certificate_threshold'];
					
					$return .=  "<p><span  style='color:$passstatus'><b>" . __('Quiz', 'learndash') . "</b>: ";
					if(isset($quiz->post_title))
					$return .=  $quiz->post_title;
					$return .=  "</span>";
					if(isset($v['percentage']))
					$return .=  " - ".$v['percentage']."%";

					if(!empty($certificateLink) && ((isset($v['percentage']) && $v['percentage'] >= $certificate_threshold * 100) || (isset($v['count']) && $v['score']/$v['count'] > $certificate_threshold)))
					$return .=  " - <a href='". $certificateLink ."' target='_blank'>".__('Print Certificate', 'learndash')."</a> " ;
					
					$return .= "<br />";
					
					if(isset($v['rank']) && is_numeric($v['rank']))
					$return .=  __('Rank: ', 'learndash') . $v['rank'] . ", ";
					
					$return .=  __('Score ', 'learndash') . $v['score'] . __(' out of ', 'learndash') . $v['count'] . __(' question(s)', 'learndash');
					if(isset($v['points']) && isset($v['total_points']))
					$return .=  __(' . Points: ', 'learndash').$v['points']."/".$v['total_points'];
					$return .=  __(' on ', 'learndash') . date( DATE_RSS, $v['time'] ) ;
					

					$return .= "</p>";
				}
			}
			return $return;
		}
		function course_export_init() {
			error_reporting(0);
			$sfwd_courses = $this->post_types['sfwd-courses'];
			$courses_prefix = $sfwd_courses->get_prefix();
			add_filter($courses_prefix . 'submit_options', Array( $this, 'courses_filter_submit' ) );
			if ( !empty( $_REQUEST['courses_export_submit'] ) && !empty( $_REQUEST['nonce-sfwd'] ) ) {
				$nonce = $_REQUEST['nonce-sfwd'];
				if (!wp_verify_nonce($nonce, 'sfwd-nonce')) die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				require_once( 'parsecsv.lib.php' );
				$content = array();
				set_time_limit( 0 );
				$users = get_users( Array( 'meta_key' => '_sfwd-course_progress' ) );
				$quiz_titles = Array();
				$lessons = array();
				if ( !empty( $users ) )
					foreach( $users as $u ) {
						$user_id = $u->ID;
						$usermeta = get_user_meta( $user_id, '_sfwd-course_progress', true );
						if(is_string($usermeta))
						$usermeta = unserialize($usermeta);
						if ( !empty( $usermeta ) ) {
							//$usermeta = explode( ",", $usermeta );
							foreach( $usermeta as $c => $cv ) {
								$course = get_post( $c );
								$row = Array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'course_id' => $c, 'course_title' => $course->post_title, 'total_steps' => $cv['total'], 'completed_steps' => $cv['completed'], 'course_completed' => ($cv['completed'] >= $cv['total'])? "YES":"NO");
								$i = 1;
								if(!empty($cv['lessons']))
								foreach($cv['lessons'] as $lesson_id => $completed) {
									if(!empty($completed)) {
										if(empty($lessons[$lesson_id]))
										$lesson = $lessons[$lesson_id] = get_post($lesson_id);
										else
										$lesson = $lessons[$lesson_id];
										
										$row['lesson_completed_'.$i] = $lesson->post_title;
										$i++;
									}
								}
								$content[] = $row;
							}
						}
					}
				if ( ! empty( $content ) ) {
					$csv = new lmsParseCSV();
					$csv->output( true, 'courses.csv', $content, array_keys( reset( $content ) ) );
					die();
				} else {
					echo "<script type='text/javascript'>window.onload = function(){alert('" . __('No user course data found!', 'learndash') . "');};</script>";
				}
			}
		}
		function courses_filter_submit( $submit ) {
			$submit['courses_export_submit'] = Array( 'type' => 'submit', 
													'class' => 'button-primary',
													'value' => __('Export User Course Data &raquo;', 'learndash') );
			return $submit;
		}

		
		function quiz_export_init() {
			$sfwd_quiz = $this->post_types['sfwd-quiz'];
			$quiz_prefix = $sfwd_quiz->get_prefix();
			add_filter($quiz_prefix . 'submit_options', Array( $this, 'quiz_filter_submit' ) );
			if ( !empty( $_REQUEST['quiz_export_submit'] ) && !empty( $_REQUEST['nonce-sfwd'] ) ) {
				$nonce = $_REQUEST['nonce-sfwd'];
				if (!wp_verify_nonce($nonce, 'sfwd-nonce')) die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				require_once( 'parsecsv.lib.php' );
				$content = array();
				set_time_limit( 0 );
				$users = get_users( Array( 'meta_key' => '_sfwd-quizzes' ) );
				$quiz_titles = Array();
				if ( !empty( $users ) )
					foreach( $users as $u ) {
						$user_id = $u->ID;
						$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
						if ( !empty( $usermeta ) ) {
							foreach( $usermeta as $k => $v ) {
								if ( !empty($v['quiz']) && empty( $quiz_titles[$v['quiz']] ) ) {
									$quiz = get_post( $v['quiz'] );
									$quiz_titles[$v['quiz']] = $quiz->post_title;
								}
								else if(!empty($v['pro_quizid'])) {
									$quiz = get_post( $v['pro_quizid'] );
									$quiz_titles[$v['quiz']] = $quiz->post_title;
								}
								else
									$quiz_titles[$v['quiz']] = "";
								$content[] = Array( 'user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email, 'quiz_id' => $v['quiz'], 'quiz_title' => $quiz_titles[$v['quiz']], 'rank' => $v['rank'], 'score' => $v['score'], 'total' => $v['count'], 'date' => date( DATE_RSS, $v['time'] ) );
							}
						}
					}
				if ( ! empty( $content ) ) {
					$csv = new lmsParseCSV();
					$csv->output( true, 'quizzes.csv', $content, array_keys( reset( $content ) ) );
				}
				die();
			}			
		}
		
		function quiz_filter_submit( $submit ) {
			$submit['quiz_export_submit'] = Array( 'type' => 'submit', 
													'class' => 'button-primary',
													'value' => __('Export Quiz Data &raquo;', 'learndash') );
			return $submit;
		}
		
		function trans_export_init() {
			$sfwd_trans = $this->post_types['sfwd-transactions'];
			$trans_prefix = $sfwd_trans->get_prefix();
			add_filter($trans_prefix . 'submit_options', Array( $this, 'trans_filter_submit' ) );
			if ( !empty( $_REQUEST['export_submit'] ) && !empty( $_REQUEST['nonce-sfwd'] ) ) {
				$nonce = $_REQUEST['nonce-sfwd'];
				if (!wp_verify_nonce($nonce, 'sfwd-nonce')) die ( __( 'Security Check - If you receive this in error, log out and back in to WordPress', 'learndash' ) );
				require_once( 'parsecsv.lib.php' );
				$content = array();
				set_time_limit( 0 );
				$locations = query_posts( array( 'post_status' => 'publish', 'post_type' => 'sfwd-transactions', 'posts_per_page' => -1 ) );
				foreach ( $locations as $key => $location ) {
					$location_data = get_post_custom( $location->ID );
					foreach( $location_data as $k => $v ) {
						if ( $k[0] == '_' )
							unset( $location_data[$k] );
						else
							$location_data[$k] = $v[0];
					}
					$content[] = $location_data;
				}
				if ( ! empty( $content ) ) {
					$csv = new lmsParseCSV();
					$csv->output( true, 'transactions.csv', $content, array_keys( reset( $content ) ) );
				}
				die();
			}
		}
		
		function trans_filter_submit( $submit ) {
			unset( $submit['Submit'] );
			unset( $submit['Submit_Default'] );
			$submit['export_submit'] = Array( 'type' => 'submit', 
													'class' => 'button-primary',
													'value' => __('Export &raquo;', 'learndash') );
			return $submit;
		}
		
		function quiz_display_settings( $settings, $location, $current_options ) {
			global $sfwd_lms;
			$sfwd_quiz = $sfwd_lms->post_types['sfwd-quiz'];
		    $quiz_prefix = $sfwd_quiz->get_prefix();
		    $prefix_len = strlen( $quiz_prefix );
		    $quiz_options = $sfwd_quiz->get_current_options();
			if ( $location == null ) {
			    foreach( $quiz_options as $k => $v ) {
				    if ( strpos( $k, $quiz_prefix ) === 0 ) {
					    $quiz_options[ substr( $k, $prefix_len ) ] = $v;
					    unset( $quiz_options[$k] );
				    }
			    }
			    foreach( Array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level )
					$quiz['info'][$level] = $quiz_options[$level];
				$quiz['info']['name'] = $quiz['info']['main'] = $quiz['info']['results'] = '';
				$quiz_json = json_encode( $quiz );
				$settings['sfwd-quiz_quiz']['default'] = '<div class="quizFormWrapper"></div><script type="text/javascript">var quizJSON = ' . $quiz_json . ';</script>';
				if ( $location == null ) unset( $settings["{$quiz_prefix}quiz"] );
				if ( !empty( $settings["{$quiz_prefix}certificate_post"] ) ) {
					$posts = get_posts( Array( 'post_type' => 'sfwd-certificates' , 'numberposts' => -1 ) );
					$post_array = Array( '' => __('-- Select A Certificate --', 'learndash') );
					if ( !empty( $posts ) )
						foreach( $posts as $p )
							$post_array[$p->ID] = $p->post_title;
					$settings["{$quiz_prefix}certificate_post"]['initial_options'] = $post_array;
				}
			} else {
				if ( !empty( $settings["{$quiz_prefix}lesson"] ) ) {
					$posts = get_posts( Array( 'post_type' => 'sfwd-lessons' , 'numberposts' => -1 ) );
					$post_array = Array( '' => __('-- Select A Lesson --', 'learndash') );
					if ( !empty( $posts ) )
						foreach( $posts as $p )
							$post_array[$p->ID] = $p->post_title;
					$settings["{$quiz_prefix}lesson"]['initial_options'] = $post_array;
				}
				if ( !empty( $settings["{$quiz_prefix}certificate"] ) ) {
					$posts = get_posts( Array( 'post_type' => 'sfwd-certificates'  , 'numberposts' => -1) );
					$post_array = Array( '' => __('-- Select A Certificate --', 'learndash') );
					if ( !empty( $posts ) )
						foreach( $posts as $p )
							$post_array[$p->ID] = $p->post_title;
					$settings["{$quiz_prefix}certificate"]['initial_options'] = $post_array;
				}
			}
			return $settings;
		}

		function add_course_tax_entry( $post_id, $post ) {
		
			$term = get_term_by('slug', $post->post_name, 'courses');
			$term_id = isset($term->term_id)? $term->term_id:0;

			if ( !$term_id ) {
				$term = wp_insert_term( $post->post_title, 'courses', Array( 'slug' => $post->post_name ) );
				$term_id = $term['term_id'];
			}
			
			wp_set_object_terms( (int)$post_id, (int)$term_id, 'courses', true );
		}

		function tax_registration() {
			$taxes = apply_filters( 'sfwd_cpt_register_tax', Array() );
			if ( !empty( $taxes ) ) {
				$post_types = Array();
				$tax_options = null;
				foreach( $taxes as $k => $v ) {
					if ( !empty( $v ) )
						foreach( $v as $tax ) {
							if ( !is_Array( $tax[0] ) ) $tax[0] = Array( $tax[0] );
							$post_types = array_merge( $post_types, $tax[0] );
							if ( empty( $tax_options ) )
								$tax_options = $tax[1];
							else
								foreach( $tax[1] as $l => $w )
									$tax_options[$l] = $w;
						}
				}
				register_taxonomy( $k, $post_types, $tax_options );
			}
		}
	}
}

if ( !class_exists( 'SFWD_CPT_Instance' ) ) {
	class SFWD_CPT_Instance extends SFWD_CPT {
		public static $instances = Array();
		function __construct( $args ) {
			extract( $args );
			if ( empty( $plugin_name ) )	$plugin_name = "SFWD CPT Instance";
			if ( empty( $post_name ) )		$post_name = $plugin_name;
			if ( empty( $slug_name ) )		$slug_name = sanitize_file_name( strtolower( strtr( $post_name, ' ', '_' ) ) );
			if ( empty( $post_type ) )		$post_type = sanitize_file_name( strtolower( strtr( $slug_name, ' ', '_' ) ) );
			SFWD_CPT_Instance::$instances[ $post_type ] =& $this;
			if ( empty( $name ) )			$name = __("Options for ", 'learndash') . $post_name;
			if ( empty( $prefix ) )			$prefix = sanitize_file_name( $post_type ) . '_';
			if ( !empty( $taxonomies ) )	$this->taxonomies = $taxonomies;
			$this->file = __FILE__ . "?post_type={$post_type}";
			$this->plugin_name	= $plugin_name;
			$this->post_name	= $post_name;
			$this->slug_name	= $slug_name;
			$this->post_type	= $post_type;
			$this->name			= $name;
			$this->prefix		= $prefix;
			$posts_per_page = get_option( 'posts_per_page' );
			if ( empty( $posts_per_page ) ) $posts_per_page = 5;
			if ( empty( $default_options ) )
				$this->default_options = Array(
										'orderby' => Array( 
											'name' => __('Sort By', 'learndash'),
											'type' => __('select', 'learndash'),
											'initial_options' => 
												Array(	''		=> __('Select a choice...', 'learndash'),
														'title'	=> __('Title', 'learndash'),
														'date'	=> __('Date', 'learndash'),
														'menu_order' => __('Menu Order', 'learndash')
												),
											'default' => 'date',
											'help_text' => __('Choose the sort order.', 'learndash')
											),
										'order' => Array( 
											'name' => __('Sort Direction', 'learndash'),
											'type' => 'select',
											'initial_options' => 
												Array(	''		=> __('Select a choice...', 'learndash'),
														'ASC'	=> __('Ascending', 'learndash'),
														'DESC'	=> __('Descending', 'learndash')
												),
											'default' => 'DESC',
											'help_text' => __('Choose the sort order.', 'learndash')
											),
										'posts_per_page' => Array(
												'name' => __('Posts Per Page', 'learndash'),
												'type' => 'text',
												'help_text' => __('Enter the number of posts to display per page.', 'learndash'),
												'default' => $posts_per_page
											),
										);
			else
				$this->default_options = $default_options;
			if ( !empty( $fields ) ) {
				$this->locations = Array (
						'default' => Array( 'name' => $this->name, 'prefix' => $this->prefix, 'type' => 'settings', 'options' => null ),
						$this->post_type => Array( 'name' => $this->plugin_name, 'type' => 'metabox', 'prefix' => '',
												'options' => array_keys( $fields ),
												'default_options' => $fields,
												'display' => Array( $this->post_type ) )
						);
			}
			parent::__construct();
			if ( !empty( $description ) ) $this->post_options['description'] = wp_kses_post( $description );
			if ( !empty( $menu_icon ) ) $this->post_options['menu_icon'] = esc_url ( $menu_icon );
			if ( !empty( $cpt_options ) ) $this->post_options = wp_parse_args( $cpt_options, $this->post_options );
			add_action( 'admin_menu', Array( &$this, 'admin_menu') );
			add_shortcode( $this->post_type, Array( $this, 'shortcode' ) );
			add_action( 'init', Array( $this, 'add_post_type' ) );
			$classname = sanitize_file_name( strtr( $post_name, ' ', '_' ) ) . '_Widget';
			if ( !class_exists( $classname ) ) {
				eval("class $classname extends SFWD_CPT_Widget {
					public function __construct( \$post_type = '$post_type', \$post_name = '$post_name', \$args = Array() ) {
						parent::__construct( \$post_type, \$post_name, \$args );
					}
				}");
			}
			add_action( 'widgets_init', create_function( '', 'return register_widget( "' . $classname . '" );' ) );
			$this->update_options();
			if ( !is_admin() ) {
				add_action( 'pre_get_posts', Array( $this, 'pre_posts' ) );
				if ( isset( $template_redirect ) && ( $template_redirect === true ) ) {
					/*if ( !empty( $this->options[ $this->prefix . 'template_redirect'] ) ) {
						add_action("template_redirect", Array( $this, 'template_redirect' ) );
					} else*/ {
						add_action("template_redirect", Array( $this, 'template_redirect_access' ) );
						add_filter( "the_content", Array( $this, "template_content" ) );
					}
				}
			}
		}
		
		function get_archive_content( $content ) {
			global $post;
			if ( sfwd_lms_has_access( $post->ID ) ) {
				return $content;
			} else {
				return get_the_excerpt();
			}
		}
		
		function template_content( $content ) {
			global $wp;
		    //global $post;
			$post = get_post(get_the_id());
			$current_user = wp_get_current_user();
			$post_type = '';
			if ( get_query_var('post_type') )
		        $post_type = get_query_var( 'post_type' );

			if ( ( !in_the_loop() ) || ( $post_type != $this->post_type ) || ( $post_type != $post->post_type ) )
				return $content;
			
			if ( !empty( $wp->query_vars["name"]) ) {
				//$content = "[learndash_course_progress]<br>".$content; /* To add course progress in course related pages */
				// single
				if ( $this->post_type == 'sfwd-courses' ) {
				
					include_once('enhanced-paypal-shortcodes.php');
					$meta = $this->get_settings_values( 'sfwd-courses' );
					$courses_prefix = $this->get_prefix();
					$prefix_len = strlen( $courses_prefix );

					$courses_options = $this->options;
										
					foreach( $courses_options as $k => $v ) {
					    if ( strpos( $k, $courses_prefix ) === 0 ) {
						    $courses_options[ substr( $k, $prefix_len ) ] = $v;
						    unset( $courses_options[$k] );
					    }
					}
					extract($courses_options);

					//if(empty($meta['sfwd-courses_course_disable_lesson_progression']['value']) && !empty($current_user->ID))
					if(!empty($current_user->ID))
					$content = "<span id='learndash_course_status'><b>" . __('Course Status:', 'learndash') . "</b> ".learndash_course_status($post->ID, null).'<br></span><br>'.$content;
					
					if ( !empty( $paypal_sandbox ) ) $paypal_sandbox = 1;
					$post_options = get_post_meta( $post->ID, '_sfwd-courses', true ); 
					foreach( $post_options as $k => $v ) {
					    if ( strpos( $k, $courses_prefix ) === 0 ) {
						    $post_options[ substr( $k, $prefix_len ) ] = $v;
						    unset( $post_options[$k] );
					    }
					}
					extract($post_options);
					if ( !empty( $course_access_list ) ) $course_access_list = explode( ',', $course_access_list );
					else $course_access_list = Array();
					$user_id = get_current_user_id();
					if ( !current_user_can('level_8') && !in_array( $user_id, $course_access_list ) && !empty( $course_price ) ) {
					
						$paypal_button = '';
						if ( !empty( $paypal_email ) ) {
					//		if ( empty( $paypal_notifyurl ) ) $paypal_notifyurl = $this->plugin_path['url'] . "/ipn.php";
							$paypal_button = wptexturize(do_shortcode("<div class='learndash_checkout_button learndash_paypal_button'>[paypal type='paynow' amount='{$course_price}' sandbox='{$paypal_sandbox}' email='{$paypal_email}' itemno='{$post->ID}' name='{$post->post_title}' noshipping='1' nonote='1' qty='1' currencycode='{$paypal_currency}' rm='2' notifyurl='{$paypal_notifyurl}' returnurl='{$paypal_returnurl}' scriptcode='scriptcode' imagewidth='100px' pagestyle='paypal' lc='{$paypal_country}' cbt='" . __('Complete Your Purchase', 'learndash') . "']</div>"));
						} 
						 $payment_params = array('price' => $course_price,
												'post' => $post);


						 
						 $payment_buttons = apply_filters('learndash_payment_button', $paypal_button, $payment_params);

						if(empty($payment_buttons))
						 $payment_buttons = __('The buyer PayPal email is empty; please configure this in the plugin or use alternative payment gateway.', 'learndash');
						 
						 $content .= '<div class="learndash_checkout_buttons">'.$payment_buttons.'</div>';
						 
						 
					} else {
						if ( !empty( $meta ) && !empty( $meta[ $this->prefix . 'course_materials' ] ) && !empty( $meta[ $this->prefix . 'course_materials' ]['value'] ) ) {
							$materials = $meta[ $this->prefix . 'course_materials' ]['value'];
							$materials = wp_kses_post( wp_specialchars_decode( $materials, ENT_QUOTES ) );
							$content .= "<h2>{$meta[ $this->prefix . 'course_materials' ]['name']}</h2>\n<p>{$materials}</p>\n";
						}
						$terms = wp_get_post_terms( $post->ID, 'courses' );

						if ( !empty( $terms ) ) {

							$slug = $terms[0]->slug;
							global $sfwd_lms;
							$lessons = sfwd_lms_get_post_options( 'sfwd-lessons' );
							
							$orderby = (empty($course_lesson_orderby))? $lessons['orderby']:$course_lesson_orderby;
							$order = (empty($course_lesson_order))? $lessons['order']:$course_lesson_order;

							$lessons = wptexturize(do_shortcode("[sfwd-lessons tax_terms='{$slug}' order='{$order}' orderby='{$orderby}' posts_per_page='{$lessons['posts_per_page']}']"));
							
							if(!empty($lessons))
							$content .= "<p id='learndash_lessons'><b>" . __('Lessons', 'learndash') . "</b></p><p>$lessons</p><br/>";
							$quizzes = wptexturize(do_shortcode("[sfwd-quiz tax_terms='{$slug}']"));
							
							if(!empty($quizzes))
							$content .= "<p id='learndash_quizzes'><b>" . __('Quizzes', 'learndash') . "</b></p><p>$quizzes</p>";
						}
					}
				} elseif ( $this->post_type == 'sfwd-quiz' ) {
					$meta = @$this->get_settings_values( 'sfwd-quiz' );
					$count = 0;
					$repeats = null;
					if ( !empty( $meta ) && isset( $meta[ $this->prefix . 'repeats' ] ) && isset( $meta[ $this->prefix . 'repeats' ]['value'] ) && $meta[ $this->prefix . 'repeats' ]['value'] !== '' ) {
						$repeats = $meta[ $this->prefix . 'repeats' ]['value'];
						$user_id = get_current_user_id();
						if ( $user_id ) {
							$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
							$usermeta = maybe_unserialize( $usermeta );
							if ( !is_array( $usermeta ) ) $usermeta = Array();
							if ( !empty( $usermeta ) )	{
								foreach( $usermeta as $k => $v ) {
									if ( $v['quiz'] == $post->ID ) $count++;
								}
							}
						}
					}

					if(learndash_lesson_progression_enabled() && !is_quiz_accessable(null, $post))
					$content = __('Please go back and complete the previous lesson.<br>', 'learndash');
					else
					if ( ( $repeats === null ) || ( $repeats >= $count ) ) {
						if(!empty($meta['sfwd-quiz_quiz_pro']['value']))
						$content .= wptexturize(do_shortcode("[LDAdvQuiz ".$meta['sfwd-quiz_quiz_pro']['value']."]"));
						else
						$content .= wptexturize(do_shortcode("[slickquiz id={$post->ID}]"));
					} else {
						$content .= "<p id='learndash_already_taken'>" . __('You have already taken this quiz $count times and may not take it again.', 'learndash') ."</p>";
					}
				} elseif ( $this->post_type == 'sfwd-lessons' ) {
					$posts = get_posts( Array( 'post_type' => 'sfwd-quiz' , 'numberposts' => -1) );
					$quizzes = '';
					if ( !empty( $posts ) )
						foreach( $posts as $p ) {
							$meta = get_post_meta( $p->ID, '_sfwd-quiz' );
							if ( is_array( $meta ) && !empty( $meta ) ) {
								$meta = $meta[0];
								if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_lesson'] ) ) ) {
									if ( $meta['sfwd-quiz_lesson'] == $post->ID ) {
										$quizids[$p->ID] = $p->ID;
										if(learndash_is_quiz_notcomplete(null, array($p->ID => 1 )))
											$class = 'notcompleted';
										else
											$class = 'completed';
										$quizzes .= "<a class='$class' href='" . get_permalink( $p->ID ) . "'>{$p->post_title}</a><br />";
									}
								}
						}
					}

					if ( !empty( $quizzes ) )
						$content .= "<br /><p id='learndash_quizzes'><b>" . __('Quizzes', 'learndash') ."</b></p><p>$quizzes</p>";

					if(learndash_lesson_progression_enabled() && !is_previous_complete($post))
					$content = "<span id='learndash_complete_prev_lesson'>" . __('Please go back and complete the previous lesson.', 'learndash') ."</span><br>";
					
					if(!empty($quizids))
					$quiz_notcomplete = learndash_is_quiz_notcomplete(null, $quizids);
					
					if(empty($quiz_notcomplete))
					$content .= "<br>".learndash_mark_complete($post);
					$content .= "<br><p id='learndash_next_prev_link'>".learndash_previous_post_link()."&nbsp;&nbsp;&nbsp;&nbsp;  ".learndash_next_post_link()."</p>";
				}
			} else {
				// archive
				$content = $this->get_archive_content( $content );
			}
			
			return '<div class="learndash">'.$content.'</div>';
		}
		
		function template_redirect_access() {
			global $wp;
		    global $post;
			if ( get_query_var('post_type') )
		        $post_type = get_query_var( 'post_type' );
		    else
		        if ( !empty( $post ) )
		            $post_type = $post->post_type;
			if ( empty( $post_type ) ) return;
			if ( $post_type == $this->post_type ) {
				if ( is_robots() )
			       do_action('do_robots');
				elseif ( is_feed() )
			       do_feed();
				elseif ( is_trackback() )
			       include( ABSPATH . 'wp-trackback.php' );
				elseif( !empty( $wp->query_vars["name"]) ) {
					// single
					if ( ( $post_type == 'sfwd-quiz' ) || ( $post_type == 'sfwd-lessons' ) ) {
						global $post;
						sfwd_lms_access_redirect( $post->ID );
					}
				}
					// archive
			}
			
			if ( ( $this->post_type == 'sfwd-quiz' ) && ( $post_type == 'sfwd-certificates' ) ) {
				global $post;
				$id = $post->ID;
				if ( !empty( $_GET ) && !empty( $_GET['quiz'] ) ) $id = $_GET['quiz'];
				$meta = get_post_meta( $id, '_sfwd-quiz' );
				if ( !empty( $post ) && is_single() ) {
					$print_cert = false;
					$cert_post = '';
					if ( is_array( $meta ) && !empty( $meta ) ) {
						$meta = $meta[0];
						if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_certificate'] ) ) )
							$cert_post = $meta['sfwd-quiz_certificate'];
					}
					
					if ( empty( $cert_post ) && !empty( $this->options["{$this->prefix}certificate_post"] ) )
						$cert_post = $this->options["{$this->prefix}certificate_post"];

					if ( !empty( $cert_post ) && ( $cert_post == $post->ID ) ) {
						if ( ( !empty( $_GET ) ) && ( !empty( $_GET['print'] ) 
							&& ( wp_verify_nonce( $_GET['print'], $id . get_current_user_id() ) ) ) ) {
								$print_cert = true;
						}
					}
					
					if ( $print_cert ) {
						require_once( 'conv_pdf.php' );
						post2pdf_conv_post_to_pdf();
						die();
					} else {
						if ( !current_user_can('level_8') ) {
							echo __('Access to certificate page is disallowed.', 'learndash');
							die();							
						}
					}
				}
			}
		    
		}
		/*
		function template_redirect() {
		    global $wp;
		    global $post;
			if ( get_query_var('post_type') )
		        $post_type = get_query_var( 'post_type' );
		    else
		        if ( !empty( $post ) )
		            $post_type = $post->post_type;
			if ( empty( $post_type ) ) return;
			if ( $post_type == $this->post_type ) {
				if ( is_robots() )
			       do_action('do_robots');
				elseif ( is_feed() )
			       do_feed();
				elseif ( is_trackback() )
			       include( ABSPATH . 'wp-trackback.php' );
				elseif( !empty( $wp->query_vars["name"]) ) {
			       if ( include( plugin_dir_path( __FILE__ ) . "/single-{$post_type}.php" ) )
					die();
				} else
			       if ( include( plugin_dir_path( __FILE__ ) . "/{$post_type}.php" ) )
					die();
			}
			
			if ( ( $this->post_type == 'sfwd-quiz' ) && ( $post_type == 'sfwd-certificates' ) ) {
				global $post;
				$id = $post->ID;
				if ( !empty( $_GET ) && !empty( $_GET['quiz'] ) ) $id = $_GET['quiz'];
				$meta = get_post_meta( $id, '_sfwd-quiz' );
				if ( !empty( $post ) && is_single() ) {
					$print_cert = false;
					$cert_post = '';
					if ( is_array( $meta ) && !empty( $meta ) ) {
						$meta = $meta[0];
						if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_certificate'] ) ) )
							$cert_post = $meta['sfwd-quiz_certificate'];
					}
					
					if ( empty( $cert_post ) && !empty( $this->options["{$this->prefix}certificate_post"] ) )
						$cert_post = $this->options["{$this->prefix}certificate_post"];

					if ( !empty( $cert_post ) && ( $cert_post == $post->ID ) ) {
						if ( ( !empty( $_GET ) ) && ( !empty( $_GET['print'] ) 
							&& ( wp_verify_nonce( $_GET['print'], $id . get_current_user_id() ) ) ) ) {
								$print_cert = true;
						}
					}
					
					if ( $print_cert ) {
						require_once( 'conv_pdf.php' );
						post2pdf_conv_post_to_pdf();
						die();
					} else {
						if ( !current_user_can('level_8') ) {
							echo "Access to certificate page is disallowed.";
							die();							
						}
					}
				}
			}
		}
		*/
		function pre_posts() {
			global $wp_query;
			if ( is_post_type_archive( $this->post_type ) ) {
				foreach ( Array( 'orderby', 'order', 'posts_per_page' ) as $field )
					if ( $this->option_isset( $field ) )
						$wp_query->set( $field, $this->options[ $this->prefix . $field ] );
			} elseif ( ( $this->post_type == 'sfwd-quiz' ) && ( is_post_type_archive( 'post') || is_home() ) && !empty( $this->options["{$this->prefix}certificate_post"] ) ) {
				$post_not_in = $wp_query->get( 'post__not_in' );
				if ( !is_array( $post_not_in ) ) $post_not_in = Array();
				$post_not_in = array_merge( $post_not_in, Array( $this->options["{$this->prefix}certificate_post"] ) );
				$wp_query->set( 'post__not_in', $post_not_in );
			}
		}
	}
}

if ( !class_exists( 'SFWD_SlickQuiz' ) ) {
    class SFWD_SlickQuiz {

        var $quiz = null;
        var $status = null;
        var $pageQuizzes = array();
		var $publishedJson = '[]';
		var $options = array();
		var $debug = false;
        // Constructor
        function __construct() {

            // Add Shortcodes
            add_shortcode( 'slickquiz', array( &$this, 'show_slickquiz_handler' ) );

            // Filter the post/page/widget content for the shortcode, load resources ONLY if present
            add_filter( 'the_content', array( &$this, 'load_resources' ) );
            add_filter( 'widget_text', array( &$this, 'load_resources' ) );
            // Add the script and style files
            add_action( 'admin_enqueue_scripts', array( &$this, 'load_admin_resources' ) );

            // Make sure dynamic quiz scripts gets loaded below jQuery
            add_filter( 'wp_footer', array( &$this, 'load_quiz_script' ), 5000 );

	    add_action( 'wp_ajax_create_quiz', array( &$this, 'create_quiz' ) );
            add_action( 'wp_ajax_update_quiz', array( &$this, 'update_quiz' ) );
            add_action( 'wp_ajax_revert_quiz', array( &$this, 'revert_quiz' ) );
            add_action( 'wp_ajax_publish_quiz', array( &$this, 'publish_quiz' ) );
            add_action( 'wp_ajax_unpublish_quiz', array( &$this, 'unpublish_quiz' ) );
            add_action( 'wp_ajax_delete_quiz', array( &$this, 'delete_quiz' ) );
            add_action( 'wp_ajax_finish_quiz', array( &$this, 'finish_quiz' ) );

        }

        function create_quiz() {
            if ( isset( $_POST['json'] ) ) {
                $this->save_working_copy( $_POST['json'] );
                $quiz = $this->get_last_quiz_by_user( get_current_user_id() );
                echo $quiz->id;
            } else {
                echo __('Something went wrong, please try again.', 'learndash');
            }
            die();
        }

        function update_quiz() {
            if ( isset( $_POST['json'] ) ) {
                $quiz      = $this->get_quiz_by_id( $_GET['post'] );
                $published = $this->get_quiz_status( $quiz ) != 'publish' ? false : true;
                $this->update_working_copy( $_POST['json'], $quiz, $published );
                echo $quiz->ID;
            } else {
                echo __('Something went wrong, please try again.', 'learndash');
            }
            die();
        }

        function finish_quiz() {
            if ( isset( $_POST['score'] ) ) {
				$user_id = $_POST["userID"];
				$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
				$usermeta = maybe_unserialize( $usermeta );
				if ( !is_array( $usermeta ) ) $usermeta = Array();
				
				$quiz = get_post_meta($_POST["postID"], '_sfwd-quiz', true);
				$passingpercentage = intVal($quiz['sfwd-quiz_passingpercentage']);
				$pass = ($_POST["score"]*100/$_POST["questionCount"] >= $passingpercentage)? 1:0;
				$quizdata = Array( "quiz" => $_POST["postID"], "score" => $_POST["score"], "count" => $_POST["questionCount"], "pass" => $pass, "rank" => $_POST["levelRank"], "time" => time() );
				$usermeta[] = $quizdata;
				$quizdata['quiz'] = get_post($_POST["postID"]);
				$courseid = learndash_get_course_id($_POST["postID"]);
				$quizdata['course'] = get_post($courseid);
				
				do_action("learndash_quiz_completed", $quizdata); //Hook for completed quiz
				
				update_user_meta( $user_id, '_sfwd-quizzes', $usermeta );
				echo __('Quiz data was saved!', 'learndash');
            } else {
                echo __('Something went wrong, please try again.', 'learndash');
            }
            die();
        }

        function revert_quiz()
        {
            $quiz = $this->get_quiz_by_id( $_GET['id'] );
            $this->revert_to_published_copy( $quiz->publishedJson, $quiz->id, $quiz->publishedDate );
            die();
        }

        function publish_quiz()
        {
            $quiz = $this->get_quiz_by_id( $_GET['id'] );
            $this->update_published_copy( $quiz->workingJson, $quiz->id );
            die();
        }

        function unpublish_quiz()
        {
            $this->unpublish( $_GET['id'] );
            die();
        }

        function delete_quiz()
        {
            $this->delete( $_GET['id'] );
            die();
        }

        function get_all_quizzes() {
            return get_posts( Array( 'numberposts' => -1, 'post_type' => 'sfwd-quiz' ) );
        }

        function get_last_quiz_by_user( $user_id ) {
            return get_posts( Array( 'numberposts' => 1, 'post_type' => 'sfwd-quiz', 'author' => $user_id ) );
        }

		function insert( $set ) {
			
		}

		function update( $set, $args ) {
;
			if ( !empty( $args ) ) {
				if ( !empty( $args['id'] ) ) {
					$id = $args['id'];
					return update_post_meta( $id, '_quizdata', $set );
				}
			}
			return false;
		}

        function save_working_copy( $json, $user_id = null )
        {
            global $wpdb, $data;

            $data    = json_decode( stripcslashes( $json ) );
            $set     = array();
            $now     = date( 'Y-m-d H:i:s' );
            $user_id = $user_id ? $user_id : get_current_user_id();

            $set['createdDate']     = $now;
            $set['createdBy']       = $user_id;
            $set['lastUpdatedDate'] = $now;
            $set['lastUpdatedBy']   = $user_id;
            $set['name']            = $this->get_name();
            $set['workingQCount']   = $this->get_question_count();
            $set['workingJson']     = $json;

            $this->insert( $set );
        }

	function get_name() {
		return 'Test';
	}

	function get_question_count() {
		return 10;
	}

        function update_working_copy( $json, $quiz, $published = false ) {
			global $wpdb, $data;
			$id = $quiz->ID;
            $data = json_decode( stripcslashes( $json ) );

            $set  = array();

            if ( !$published ) {
                $set['name'] = $this->quiz->post_title;
            }


            $set['lastUpdatedDate'] = date( 'Y-m-d H:i:s' );
            $set['lastUpdatedBy']   = get_current_user_id();
            $set['workingQCount']   = count((Array)$data->questions);
            $set['workingJson']     = $json;
	
            $this->update( $set, array( 'id' => $id ) );
        }

        function update_published_copy( $json, $id )
        {
            global $wpdb, $data;

            $data    = json_decode( $json );
            $set     = array();
            $now     = date( 'Y-m-d H:i:s' );
            $user_id = get_current_user_id();

            $set['name']             = $this->get_name();
            $set['workingQCount']    = $this->get_question_count();
            $set['publishedQCount']  = $set['workingQCount'];
            $set['workingJson']      = $json;
            $set['publishedJson']    = $set['workingJson'];
            $set['hasBeenPublished'] = 1;
            $set['publishedDate']    = $now;
            $set['publishedBy']      = $user_id;
            $set['lastUpdatedDate']  = $now;
            $set['lastUpdatedBy']    = $user_id;

            $this->update( $set, array( 'id' => $id ) );
        }

        function revert_to_published_copy( $json, $id, $updatedOn )
        {
            global $wpdb, $data;

            $data = json_decode( stripcslashes( $json ) );
            $set  = array();

            $set['lastUpdatedDate'] = $updatedOn;
            $set['workingQCount']   = $this->get_question_count();
            $set['workingJson']     =  $json  ;

            $this->update( $set, array( 'id' => $id ) );
        }

        function unpublish( $id )
        {
            global $wpdb, $data;

            $set = array();

            $set['publishedQCount'] = null;
            $set['publishedJson']   = null;
            $set['lastUpdatedDate'] = date( 'Y-m-d H:i:s' );
            $set['lastUpdatedBy']   = get_current_user_id();

            $wpdb->update( $set, array( 'id' => $id ) );
        }

        function delete( $id ) {
			wp_delete_post( $id );
        }

        // Add Admin JS and styles
        function load_admin_resources( $content ) {

            // Only load resources when a shortcode is on the page

            preg_match( '/post.php/is', $_SERVER['REQUEST_URI'], $matches );
            if ( count( $matches) == 0 ) return;
			global $post;
			$quizmeta = get_post_meta( $post->ID, '_sfwd-quiz' , true);
			
			if(!empty($quizmeta['sfwd-quiz_quiz_pro']))
			{
				?>
				
				<style type="text/css">
					#sfwd-quiz_passingpercentage {
						display:none;
					}
				</style>
				<?php
				return;
			}
				
            // Scripts
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'slickquiz_admin_js', plugins_url( 'admin.js', __FILE__ ) );

            // Styles
            wp_enqueue_style( 'slickquiz_admin_css', plugins_url( 'admin.css', __FILE__ ) );
            
		}
	
	function debug($msg) {
		$original_log_errors = ini_get('log_errors');
		$original_error_log = ini_get('error_log');
		ini_set('log_errors', true);
		ini_set('error_log', dirname(__FILE__).DIRECTORY_SEPARATOR.'debug.log');
		
		global $processing_id;
		if(empty($processing_id))
		$processing_id	= time();
		
		if(isset($_GET['debug']) || !empty($this->debug))
		
		error_log("[$processing_id] ".print_r($msg, true)); //Comment This line to stop logging debug messages.
		
		ini_set('log_errors', $original_log_errors);
		ini_set('error_log', $original_error_log);		
	}
        function load_resources( $content ) {
		global $sfwd_lms;
	    $sfwd_quiz = $sfwd_lms->post_types['sfwd-quiz'];
	    $quiz_prefix = $sfwd_quiz->get_prefix();
	    $prefix_len = strlen( $quiz_prefix );
	    $quiz_options = $sfwd_quiz->get_current_options();

	    foreach( $quiz_options as $k => $v ) {
		    if ( strpos( $k, $quiz_prefix ) === 0 ) {
			    $quiz_options[ substr( $k, $prefix_len ) ] = $v;
			    unset( $quiz_options[$k] );
		    }
	    }
	    $this->options = $quiz_options;


            // Only load resources when a shortcode is on the page
	    global $post;
		if(empty($post->post_type))
		return $content;
		
	    if (!$post->post_type == 'sfwd-quiz' )
		if ( preg_match( '/\[\s*slickquiz[^\]]*\]/is', $content, $matches ) )
            	    if ( !count( $matches) ) return $content;

			
            // Scripts
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'sfwd_slickquiz_js', plugins_url( 'slickQuiz.js', __FILE__ ) );
			wp_localize_script('sfwd_slickquiz_js', 'LDLMS', array( 'siteurl' => get_bloginfo('wpurl') ));

            // Styles
            wp_enqueue_style( 'sfwd_slickquiz_css', plugins_url( 'slickQuiz.css', __FILE__ ) );
            wp_enqueue_style( 'sfwd_slickquiz_front_css', plugins_url( 'front.css', __FILE__ ) );

            return $content;
        }

	function get_admin_option( $option ) {
		return $this->options[$option];
	}

        function load_quiz_script() {
            global $pageQuizzes;
            $out = '';
	    $quiz_options = $this->options;
	    extract( $this->options );
		if ( !empty( $quiz ) ) foreach( Array( 'level1', 'level2', 'level3', 'level4', 'level5' ) as $level ) $quiz['info'][$level] = $quiz_options[$level];
            if ( count( $pageQuizzes ) ) {
                foreach ( $pageQuizzes as $id => $quizStat ) {
					$post   = $quizStat[0];
                    $status = $quizStat[1];
					$quiz['info']['name'] = $post->post_title;
					$quiz['info']['main'] = $post->post_title;
					$cf = get_post_meta( $post->ID, '_quizdata', true );
					$quiz_json = $cf['workingJson'];
					if ( empty( $quiz_json ) ) {
						$quiz_json = json_encode( $quiz );
					}
					$certificate_details = learndash_certificate_details($post->ID);
					
						$out .= '
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                $("#slickQuiz' . $post->ID . '").slickQuiz({
                                    json:                        ' . $quiz_json . ', // $quiz->publishedJson
                                    checkAnswerText:             "' . $this->get_admin_option( 'check_answer_text' ) . '",
                                    nextQuestionText:            "' . $this->get_admin_option( 'next_question_text' ) . '",
                                    backButtonText:              "' . $this->get_admin_option( 'back_button_text' ) . '",
                                    randomSortQuestions:         ' . ( $this->get_admin_option( 'random_sort_questions' ) ? 'true' : 'false' ) . ',
                                    randomSortAnswers:           ' . ( $this->get_admin_option( 'random_sort_answers' ) ? 'true' : 'false' ) . ',
                                    randomSort:                  ' . ( $this->get_admin_option( 'random_sort' ) ? 'true' : 'false' ) . ',
                                    preventUnanswered:           ' . ( $this->get_admin_option( 'disable_next' ) ? 'true' : 'false' ) . ',
                                    disableResponseMessaging:    ' . ( $this->get_admin_option( 'disable_responses' ) ? 'true' : 'false' ) . ',
                                    completionResponseMessaging: ' . ( $this->get_admin_option( 'completion_responses' ) ? 'true' : 'false' ) . ',
									certificateLink:	"' . $certificate_details['certificateLink'] . '",
									certificateThreshold:	"' . $certificate_details['certificate_threshold'] . '",
									postID:	"' . $post->ID . '",
									userID:	"' . get_current_user_id() . '"
                                });
                            });
							</script>';
                }
            }

            echo $out;
        }

        function show_slickquiz_handler( $atts )
        {
            extract( shortcode_atts( array(
                'id' => 0,
            ), $atts ) );

            $out = $this->show_slickquiz( $id );

            return $out;
        }

		function get_quiz_by_id( $id ) {
			return get_post( $id );
		}
		
		function get_quiz_status( $quiz ) {
			if ( !empty( $quiz ) && !empty( $quiz->ID ) )
				return get_post_status( $quiz->ID );
			return false;
		}
		



        function show_slickquiz( $id )
        {
            global $quiz, $status, $pageQuizzes;

            $quiz = $this->get_quiz_by_id( $id );
			$returnLink  = learndash_quiz_continue_link($id);

            if ( $quiz ) {

                $pageQuizzes[$id] = array( $quiz, null );
                    $out = '
                        <div class="slickQuizWrapper" id="slickQuiz' . $quiz->ID . '">
                            <h2 class="quizName"></h2>
                            <div class="quizArea">
                                <div class="quizHeader">
                                    <div class="buttonWrapper"><a class="button startQuiz">' . $this->get_admin_option( 'start_button_text' ) . '</a></div>
                                </div>
                            </div>

                            <div class="quizResults">
                                <div class="quizResultsCopy">
                                    <h3 class="quizScore">' . $this->get_admin_option( 'your_score_text' ) . ' <span>&nbsp;</span></h3>
                                    <h3 class="quizLevel">' . $this->get_admin_option( 'your_ranking_text' ) . ' <span>&nbsp;</span></h3>
                                </div>
                            <div class="quizReturn">
								'.$returnLink.'
							</div>								
                            </div>

                        </div>';

            } else {
                $out = "<p class='quiz-$id notFound'>" . $this->get_admin_option( 'missing_quiz_message' ) . "</p>";
            }
            return $out;
        }

    }
}

$sfwd_lms = new SFWD_LMS();
$sfwd_slickquiz = new SFWD_SlickQuiz();

function learndash_previous_post_link($prevlink='', $url = false) {
	global $post;
	
	if(!is_singular() || empty($post) || $post->post_type != "sfwd-lessons")
		return $prevlink;

	$lessons = learndash_get_lesson_list();
	
	foreach($lessons as $k=>$lesson) {
			if($lesson->ID == $post->ID)
			{
				$found_at = $k;
				break;
			}
	}

	if(isset($found_at) && !empty($lessons[$found_at-1]))
	{
		$permalink = get_permalink( $lessons[$found_at-1]->ID );
		if($url) 
		return $permalink;
		else
		{
			$link = '<a href="'.$permalink.'" rel="prev"><span class="meta-nav">&larr;</span> ' . __('Previous Lesson', 'learndash') . '</a>';
			return apply_filters('learndash_previous_post_link', $link);
		}
	}
	else
		return $prevlink;
		
}

function learndash_next_post_link($prevlink='', $url = false) {
	global $post;
	
	if(!is_singular() || empty($post) || $post->post_type != "sfwd-lessons")
		return $prevlink;

	$lessons = learndash_get_lesson_list();
	
	foreach($lessons as $k=>$lesson) {
			if($lesson->ID == $post->ID)
			{
				$found_at = $k;
				break;
			}
	}

	if(isset($found_at) && !empty($lessons[$found_at+1]))
	{
		$permalink = get_permalink( $lessons[$found_at+1]->ID );
		if($url) 
		return $permalink;
		else
		{
			$link = '<a href="'.$permalink.'" rel="next">' . __('Next Lesson', 'learndash') . ' <span class="meta-nav">&rarr;</span></a>';
			return apply_filters('learndash_previous_post_link', $link);
		}
	}
	else
		return $prevlink;
		
}

function learndash_clear_prev_next_links($prevlink=''){
	global $post;
	
	if(!is_singular() || empty($post) || $post->post_type != "sfwd-lessons")
		return "";
}
add_filter('previous_post_link', 'learndash_clear_prev_next_links', 1, 2);
add_filter('next_post_link', 'learndash_clear_prev_next_links', 1, 2);
function learndash_get_lesson_list($id = null){
	global $post;

	if(empty($id))
	$id = $post->ID;
	$terms = wp_get_post_terms($id, 'courses');

	if(!is_array($terms) || empty($terms) || empty($terms[0]) || empty($terms[0]->slug))
		return array();
		
	$courseslug = $terms[0]->slug;
	global $wpdb;
	
	$term_taxonomy_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `term_taxonomy_id` FROM $wpdb->term_taxonomy tt, $wpdb->terms t 
		 WHERE slug = %s 
		 AND t.term_id = tt.term_id
		",
	        $courseslug 
        )
	);
	
	$course_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `ID` FROM $wpdb->term_relationships, $wpdb->posts 
		 WHERE `ID` = `object_id`
		 AND `term_taxonomy_id` = %d
		 AND `post_type` = 'sfwd-courses'
		 AND `post_status` = 'publish' 
		",
	        $term_taxonomy_id
        )
	);
	$lessons = sfwd_lms_get_post_options( 'sfwd-lessons' );
	$course_options = get_post_meta($course_id, "_sfwd-courses", true);
	$course_orderby = $course_options["sfwd-courses_course_lesson_orderby"];
	$course_order = $course_options["sfwd-courses_course_lesson_order"];
	
	$orderby = (empty($course_orderby))? $lessons['orderby']:$course_orderby;
	$order = (empty($course_order))? $lessons['order']:$course_order;
	
	switch($orderby) {
		case "title": $orderby = "post_title"; break;
		case "date": $orderby = "post_date"; break;
	}

	
	$lessons = $wpdb->get_results( 
	$wpdb->prepare( 
		"
         SELECT * FROM  $wpdb->term_relationships, $wpdb->posts
		 WHERE `ID` = `object_id`
		 AND `term_taxonomy_id` = %d
		 AND `post_type` = 'sfwd-lessons'
		 AND `post_status` = 'publish' 
		 ORDER BY 
		".$orderby ." ". $order,
	        $term_taxonomy_id
        )
	);

return $lessons;	
}
/*
function is_quiz_completed($id, $user_id = null)
{
		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			if(empty($current_user->ID))
			return null;
			
			$user_id = $current_user->ID;
		}
		$quiz_results = get_user_meta($user_id, '_sfwd-quizzes', true); 
		if(!empty($quiz_results))
		foreach($quiz_results as $quiz) {
			if($quiz['quiz'] == $id)
			return 1;
		}
		return 0;
}
*/
function learndash_get_global_quiz_list($id = null){
	global $post;
	if(empty($id))
	$id = $post->ID;
	$terms = wp_get_post_terms($id, 'courses');

	if(empty($terms) || empty($terms[0]) || empty($terms[0]->slug))
		return array();
		
	$courseslug = $terms[0]->slug;
	global $wpdb;
	
	$term_taxonomy_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `term_taxonomy_id` FROM $wpdb->term_taxonomy tt, $wpdb->terms t 
		 WHERE slug = %s 
		 AND t.term_id = tt.term_id
		",
	        $courseslug 
        )
	);
	
	$course_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `ID` FROM $wpdb->term_relationships, $wpdb->posts 
		 WHERE `ID` = `object_id`
		 AND `term_taxonomy_id` = %d
		 AND `post_type` = 'sfwd-courses'
		 AND `post_status` = 'publish' 
		",
	        $term_taxonomy_id
        )
	);
	

	
	$quizzes = $wpdb->get_results( 
	$wpdb->prepare( 
		"
         SELECT * FROM  $wpdb->term_relationships, $wpdb->posts
		 WHERE `ID` = `object_id`
		 AND `term_taxonomy_id` = %d
		 AND `post_type` = 'sfwd-quiz'
		 AND `post_status` = 'publish' 
		",
	        $term_taxonomy_id
        )
	);

	$quizzes_new = array();
	foreach($quizzes as $k=>$quiz)
	{
			$quizmeta = get_post_meta( $quiz->ID, '_sfwd-quiz' , true);

			$quiz_lesson = $quizmeta['sfwd-quiz_lesson'];
			if(empty($quiz_lesson))
			$quizzes_new[] = $quizzes[$k];
	}

	return $quizzes_new;	
}

function learndash_get_course_id($id = null){
	global $post;
 
	if(empty($id))
	$id = $post->ID;
	
	$terms = wp_get_post_terms($id, 'courses');
	
	if(empty($terms) || empty($terms[0]) || empty($terms[0]->slug))
		return 0;
		
	$courseslug = $terms[0]->slug;
	
	global $wpdb;
	
	$term_taxonomy_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `term_taxonomy_id` FROM $wpdb->term_taxonomy tt, $wpdb->terms t 
		 WHERE slug = %s 
		 AND t.term_id = tt.term_id
		",
	        $courseslug 
        )
	);
	
	$course_id = $wpdb->get_var( 
	$wpdb->prepare( 
		"
         SELECT `ID` FROM $wpdb->term_relationships, $wpdb->posts 
		 WHERE `ID` = `object_id`
		 AND `term_taxonomy_id` = %d
		 AND `post_type` = 'sfwd-courses'
		 AND `post_status` = 'publish' 
		",
	        $term_taxonomy_id
        )
	);
	return $course_id;
}
if(!function_exists('sfwd_lms_get_post_options')) {
function sfwd_lms_get_post_options( $post_type ) {
	global $sfwd_lms;
	$cpt = $sfwd_lms->post_types[$post_type];
	$prefix = $cpt->get_prefix();
	$options = $cpt->get_current_options();
	$ret = Array( 'order' => '', 'orderby' => '', 'posts_per_page' => '' );
	foreach( $ret as $k => $v )
		if ( !empty( $options["{$prefix}{$k}"] ) )
			$ret[$k] = $options["{$prefix}{$k}"];
	return $ret;			
}
}

function ld_update_course_access($user_id, $course_id) {
	$meta = get_post_meta( $course_id, '_sfwd-courses', true );
	$access_list = $meta['sfwd-courses_course_access_list'];
	
	if ( empty( $access_list ) )
		$access_list = $user_id;
	else
		$access_list .= ",$user_id";
	
	$meta['sfwd-courses_course_access_list'] = $access_list;
	update_post_meta( $course_id, '_sfwd-courses', $meta );
	return $meta;
}
// Load the auto-update class
add_action('init', 'nss_plugin_updater_activate_sfwd_lms');

function learndash_certificate_details($post_id, $user_id = null) {
		$user_id = !empty($user_id)? $user_id:get_current_user_id();
		
		$certificateLink = '';
		$post = get_post($post_id);
		$meta = get_post_meta( $post_id, '_sfwd-quiz' );
		$cert_post = '';
		$certificate_threshold = '0.8';
		if ( is_array( $meta ) && !empty( $meta ) ) {
			$meta = $meta[0];
			if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_certificate'] ) ) )
				$certificate_post = $meta['sfwd-quiz_certificate'];
			if ( is_array( $meta ) && ( !empty( $meta['sfwd-quiz_threshold'] ) ) )
				$certificate_threshold = $meta['sfwd-quiz_threshold'];
		}
		
		if ( !empty( $certificate_post ) )
			$certificateLink = get_permalink( $certificate_post );

		if ( !empty( $certificateLink ) )
		{
			$certificateLink .= (strpos("a".$certificateLink,"?"))? "&":"?";
			$certificateLink .= "quiz={$post->ID}&print=" . wp_create_nonce( $post->ID . $user_id);
		}
		return array('certificateLink' => $certificateLink, 'certificate_threshold' => $certificate_threshold);
}
function nss_plugin_updater_activate_sfwd_lms()
{
	if(!class_exists('nss_plugin_updater'))
    require_once ('wp_autoupdate.php');
	
	$nss_plugin_updater_plugin_remote_path = 'http://support.learndash.com/';
    $nss_plugin_updater_plugin_slug = plugin_basename(__FILE__);

    new nss_plugin_updater ($nss_plugin_updater_plugin_remote_path, $nss_plugin_updater_plugin_slug);
}

	/*** Function to add quiz continued link **/
	function learndash_quiz_continue_link($id) {
		global $status, $pageQuizzes;

		$quizmeta = get_post_meta( $id, '_sfwd-quiz' , true);
		if(!empty($quizmeta['sfwd-quiz_lesson']))
		$return_id = $quiz_lesson = $quizmeta['sfwd-quiz_lesson'];
		
		if(empty($quiz_lesson))
		{
			$return_id = $course_id = learndash_get_course_id($id);
			$url = get_permalink( $return_id );
			$url .= strpos("a".$url, "?")? "&":"?";
			$url .= 'quiz_type=global&quiz_redirect=1&course_id='.$course_id.'&quiz_id='.$id;
			$returnLink = '<a href="'.$url.'">' . __('Click Here to Continue ', 'learndash') . '</a>';
		}
		else
		{
			$url = get_permalink( $return_id );
			$url .= strpos("a".$url, "?")? "&":"?";
			$url .= 'quiz_type=lesson&quiz_redirect=1&lesson_id='.$return_id.'&quiz_id='.$id;
			$returnLink = '<a href="'.$url.'">' . __('Click Here to Continue ', 'learndash') . '</a>';
		}
		$version = get_bloginfo('version');
		if($version >= '1.5.1')
		return apply_filters('learndash_quiz_continue_link', $returnLink, $url);
		else
		return apply_filters('learndash_quiz_continue_link', $returnLink);
	}
	
	function ld_remove_lessons_and_quizses_page($content) {
		if(is_archive() && !is_admin())  {
			$post_type = get_post_type();
			if($post_type == 'sfwd-lessons' || $post_type == 'sfwd-quiz')
			{
				wp_redirect(home_url());
				exit;
			}
		}
	}
	add_action("wp", 'ld_remove_lessons_and_quizses_page');
		if(!function_exists('ld_debug')) {
		function ld_debug($msg) {
		$original_log_errors = ini_get('log_errors');
		$original_error_log = ini_get('error_log');
		ini_set('log_errors', true);
		ini_set('error_log', dirname(__FILE__).DIRECTORY_SEPARATOR.'debug.log');
		
		global $processing_id;
		if(empty($processing_id))
		$processing_id	= time();
		
		if(isset($_GET['debug']))
		
		error_log("[$processing_id] ".print_r($msg, true)); //Comment This line to stop logging debug messages.
		
		ini_set('log_errors', $original_log_errors);
		ini_set('error_log', $original_error_log);		
	}
	}