<?php
require_once(dirname(__FILE__).'/wp-pro-quiz/wp-pro-quiz.php');

class LD_QuizPro {
	public $debug = false;
	function __construct() {
		add_action("wp_head", array($this, 'certificate_details'));
		add_action("wp_pro_quiz_completed_quiz", array($this, 'wp_pro_quiz_completed'));
		//add_action("the_content", array($this, 'certificate_link'));
	}
		function debug($msg) {
		$original_log_errors = ini_get('log_errors');
		$original_error_log = ini_get('error_log');
		ini_set('log_errors', true);
		ini_set('error_log', dirname(__FILE__).DIRECTORY_SEPARATOR.'debug.log');
		
		global $processing_id;
		if(empty($processing_id))
		$processing_id	= time();
		
		if( isset($_GET['debug']) || !empty($this->debug))
		
		error_log("[$processing_id] ".print_r($msg, true)); //Comment This line to stop logging debug messages.
		
		ini_set('log_errors', $original_log_errors);
		ini_set('error_log', $original_error_log);		
	}
	function wp_pro_quiz_completed() {
		$this->debug($_REQUEST);
		$this->debug($_SERVER);

		if(!isset($_REQUEST['quizId']) || !isset($_REQUEST['results']['comp']['points']))
			return;
		
		$quiz_id = $_POST['quizId'];
		$score = $_REQUEST['results']['comp']['correctQuestions'];
		$points = $_REQUEST['results']['comp']['points'];
		$result = $_REQUEST['results']['comp']['result'];
		$timespent = isset($_POST['timespent'])? $_POST['timespent']:null;

		
		$question = new WpProQuiz_Model_QuestionMapper();
		$questions = $question->fetchAll($quiz_id);
		$this->debug($questions);
		$total_points = 0;
		foreach($questions as $q) {
			$total_points += $q->getPoints();
		}
		$count = count($questions);
		
		if(empty($user_id))
		{
			$current_user = wp_get_current_user();
			if(empty($current_user->ID))
			return null;
			
			$user_id = $current_user->ID;
		}
		$usermeta = get_user_meta( $user_id, '_sfwd-quizzes', true );
		$usermeta = maybe_unserialize( $usermeta );
		if ( !is_array( $usermeta ) ) $usermeta = Array();
		
		if(empty($_SERVER['HTTP_REFERER']))
			return;
			
		$ld_quiz_id = $this->get_ld_quiz_id($quiz_id);
		
		if(empty($ld_quiz_id))
		return;
		
		$quiz = get_post_meta($ld_quiz_id, '_sfwd-quiz', true);
		$passingpercentage = intVal($quiz['sfwd-quiz_passingpercentage']);
		$pass = ($result >= $passingpercentage)? 1:0;
		$this->debug(array( "quiz" => $ld_quiz_id, "score" => $score, "count" => $total_points, "pass" => $pass, "rank" => '-', "time" => time() , 'pro_quizid' => $quiz_id));
		$quizdata = array( "quiz" => $ld_quiz_id, "score" => $score, "count" => $count, "pass" => $pass, "rank" => '-', "time" => time(), 'pro_quizid' => $quiz_id, 'points' => $points, 'total_points' => $total_points, 'percentage' => $result, 'timespent' => $timespent);
		$usermeta[] = $quizdata;

		$quizdata['quiz'] = get_post($ld_quiz_id);
		$courseid = learndash_get_course_id($ld_quiz_id);
		$quizdata['course'] = get_post($courseid);		
		$quizdata['questions'] = $questions;
		do_action("learndash_quiz_completed", $quizdata); //Hook for completed quiz

		update_user_meta( $user_id, '_sfwd-quizzes', $usermeta );
				
	}
	function get_ld_quiz_id($pro_quizid) {
		$quizzes = SFWD_SlickQuiz::get_all_quizzes();
		//$this->debug($quizzes);
		foreach($quizzes as $quiz) {
			$quizmeta = get_post_meta( $quiz->ID, '_sfwd-quiz' , true);
			if(!empty($quizmeta['sfwd-quiz_quiz_pro']) && $quizmeta['sfwd-quiz_quiz_pro'] == $pro_quizid)
				return $quiz->ID;
		}
	}
	function get_quiz_list(){
		$quiz = new WpProQuiz_Model_QuizMapper();
		$quizzes = $quiz->fetchAll();
		$list = array();
		if(!empty($quizzes))
		foreach($quizzes as $q) {
			$list[$q->getId()] = $q->getName();
		}
		return $list;
	}
	function certificate_details(){
		global $post;
		if(empty($post) || empty($post->ID) || empty($post->post_type) )
		return;
		
		$certificate_details = learndash_certificate_details($post->ID);
		$continue_link  = learndash_quiz_continue_link($post->ID);
		if($post->post_type == 'sfwd-quiz') {
		echo "<script>";
		echo "var certificate_details = ".json_encode(learndash_certificate_details($post->ID)).";";
		echo "</script>";
		
		/** Continue link will appear threw javascript **/
		echo "<script>";
		echo "var continue_details ='" . $continue_link ."';";
		echo "</script>";
		}
	}
	function certificate_link($content){
		global $post;
		if(empty($post->ID))
		return $content;

		$cd  = learndash_certificate_details($post->ID);
		$ret = "<a href='".$cd['certificateLink']."' target='_blank'>".__('PRINT YOUR CERTIFICATE!','learndash')."</a>";
			$ret = $content.$ret;
			return $ret;
		}
	}


new LD_QuizPro();