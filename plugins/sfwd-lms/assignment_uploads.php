<?php

/**
* Upload button Helper
*
**/
			
if(isset($_POST['uploadfile']) && isset($_POST['post'])){
	$post_id = $_POST['post'];
	$file = $_FILES['uploadfiles'];
	$name = $file['name'];	
	if(!empty($file['name'][0])){
		$file_desc = learndash_fileupload_process($file, $post_id);
		$file_name = $file_desc['filename'];
		$file_link = $file_desc['filelink'];
		$params = array(
			'filelink' => $file_link,
			'filename' => $file_name,
		);
	}
}

//function to get recently uploaded file link
function learndash_get_uploaded_assignments($post){
		global $wpdb;
		$post_id = $post->ID;
		$assinment_meta = get_post_meta ($post_id, 'sfwd_lessons-assignment');
		$current_user = wp_get_current_user(); 
		$loginid = $current_user->user_login;
		$userid	  = $current_user->ID;
		$dest = '<h2>'.__("Files you have uploaded","learndash").'</h2><table><thead>';
		if(!empty($assinment_meta)){
		foreach($assinment_meta[0]['assignment'] as $k=>$v){
			if($loginid == $v['user_name']){
				if($v['file_link'] != 'not available'){
					$dest .= '<tr><a href="'.$v['file_link'].'" target="_blank">'.$v['file_name'].'</a><br/></tr>';
					}
				}
			}
		}	
		$dest .= '</thead></table>';
		return $dest;
}

function learndash_show_assignments_list($post){
	$post_id = $post->ID;
	ob_start();
	if(lesson_hasassignments($post)){
		$assignment_meta = get_post_meta ($post_id, 'sfwd_lessons-assignment');
		if(!empty($assignment_meta)){
			$assignment_data = $assignment_meta[0]['assignment'];
			?>
			<table>
			<tr>
					<th><b><?php _e("Assignments", "learndash"); ?></b></th>
			</tr>
			<tr>
					<th><?php _e("Filename", "learndash"); ?></th>
					<th><?php _e("Download Link", "learndash"); ?></th>
					<th><?php _e("User Login", "learndash"); ?></th>
					<th><?php _e("User Name", "learndash"); ?></th>
					<th><?php _e("Status", "learndash"); ?></th>
			</tr>	
			<?php
			foreach($assignment_data as $k=>$v)
				{
				 ?>
					<tr>
						<td><?php echo $v['file_name'] ?></td>
						<?php if($v['file_link'] != 'not available'){ ?>
						<td><a href="<?php echo $v['file_link']  ?>" target="_blank"><?php _e("Click here", "learndash"); ?></a></td>
						<?php }else{ ?>
						<td><?php _e("File does not exist", "learndash"); ?></td>
						<?php } ?>
						<td><?php echo $v['user_name'] ?></td>
						<td><?php echo $v['disp_name'] ?></td>
						<td>
						<?php 
							if(!empty($v['user_name'])){
								$user = get_user_by("login",$v['user_name']);
							}	
							$progress = learndash_get_course_progress($user->ID, $post->ID);
							if(!empty($progress['this']->completed)){
						?>
						<?php _e('Completed', 'learndash') ?>
						<?php }else{ ?>
						<form id='sfwd-mark-complete' method='post' action=''>
							<input type='hidden' value='<?php echo $post->ID ?>' name='post'/>
							<input type='hidden' value='<?php echo $user->ID ?>' name='userid'/>
							<input type='submit' value='<?php _e('Mark Complete', 'learndash') ?>' name='sfwd_mark_complete'/>
						</form>
						<?php } ?>	
						</td>	
					
					</tr>
					<?php
				 }
				?>
			 
			 </table>
			<?php			 
			}
	}
	return ob_get_clean();
}

//Function to handle assignment uploads
//Takes Post ID, filename as arguments(We don't want to store BLOB data there)
function learndash_upload_assigment_init($post_id, $fname){
/**
 * Post Meta Structure
 [
	lesson => current_post_id	
		userid => [
			[
				file_name => file_name,
				file_link => link_of_file,
				user_name => user name of user,
				display_name => display_name_of_user,
			]
	]
 ]
 *
 */
	//Initialize an empty array
	global $wp;
	if(!function_exists('wp_get_current_user')) {
		include(ABSPATH . "wp-includes/pluggable.php"); 
    }
	$new_assignmnt_meta = array();
	$current_user = wp_get_current_user(); 
	$username = $current_user->user_login;
	$dispname = $current_user->display_name;
	$userid	  = $current_user->ID;
	$url_link_arr = wp_upload_dir();
	$url_link = $url_link_arr['baseurl'];
	$dir_link = $url_link_arr['basedir'];
	$file_path = $dir_link.'/assignments/';
	$url_path = $url_link.'/assignments/'.$fname;
	if(file_exists($file_path.$fname))
		$dest = $url_path;
	else
		$dest = 'not available';
		
	$assinment_meta = get_post_meta ($post_id, 'sfwd_lessons-assignment');
	if(!empty($assinment_meta)) $assignments_prev = $assinment_meta[0]['assignment'];
	else $assignments_prev = array();
	if(!empty($assignments_prev)){
		if (is_array($assignments_prev)) {
			$assignmnt= array($userid =>
						array(
							"file_name" => $fname,
							"file_link" => $dest,
							"user_name" => $username,
							"disp_name" => $dispname
						));
			array_merge($assignments_prev,$assignmnt); 
			$appended = array_merge($assignments_prev,$assignmnt); 
			$new_assignmnt_meta['assignment'] = $appended;
		}
	}
	else{
	//There are no assignments. Add this
		$assignmnt = array($userid => 
						array(
							"file_name" => $fname,
							"file_link" => $dest,
							"user_name" => $username,
							"disp_name" => $dispname
						)
		);
		$new_assignmnt_meta['assignment'] = $assignmnt;
	}

	update_post_meta ($post_id, 'sfwd_lessons-assignment', $new_assignmnt_meta );
	
}

function learndash_fileupload_process($uploadfiles, $post_id) { 

  //$allowed_types = array("text/plain","application/zip","application/x-zip-compressed","application/msword");
  if (is_array($uploadfiles)) {

    foreach ($uploadfiles['name'] as $key => $value) {

      // look only for uploded files
      if ($uploadfiles['error'][$key] == 0) {

        $filetmp = $uploadfiles['tmp_name'][$key];

        //clean filename and extract extension
        $filename = $uploadfiles['name'][$key];

        // get file info
        // @fixme: wp checks the file extension....
        $filetype = wp_check_filetype( basename( $filename ), null );
        $filetitle = preg_replace('/\.[^.]+$/', '', basename( $filename ) );
        $filename = $filetitle . '.' . $filetype['ext'];
        $upload_dir = wp_upload_dir();
		$upload_dir_base = $upload_dir['basedir'];
		$upload_url_base = $upload_dir['baseurl'];
		$upload_dir_path = $upload_dir_base.'/assignments';
		$upload_url_path = $upload_url_base.'/assignments/';
		if (!file_exists($upload_dir_path)) {
			mkdir($upload_dir_path);
		}

        /**
         * Check if the filename already exist in the directory and rename the
         * file if necessary
         */
        $i = 0;
        while ( file_exists( $upload_dir_path .'/' . $filename ) ) {
          $i++;
		  $filename = $filetitle . '_' . $i . '.' . $filetype['ext'];
        }
        $filedest = $upload_dir_path . '/' . $filename;
		$destination = $upload_url_path.$filename;

        /**
         * Check write permissions
         */
        if ( !is_writeable( $upload_dir_path ) ) {
          die(__('Unable to write to directory. Is this directory writable by the server?','learndash'));
          return;
        }
		
		/**
         * Save temporary file to uploads dir
         */
        if ( !@move_uploaded_file($filetmp, $filedest) ){
          echo("Error, the file $filetmp could not moved to : $filedest ");
          continue;
        }
		/**
		 * Add upload meta to database
		 *
		 */ 
		learndash_upload_assigment_init($post_id, $filename, $filedest);
		$file_desc = array();
		$file_desc['filename'] = $filename;
		$file_desc['filelink'] = $destination;
		return $file_desc;
      }
    }
  }
}

function lesson_hasassignments($post){
	$post_id = $post->ID;
	$assign_meta = get_post_meta( $post_id, '_sfwd-lessons', true ); 
	if(!empty($assign_meta['sfwd-lessons_lesson_assignment_upload'])){
		$val = $assign_meta['sfwd-lessons_lesson_assignment_upload'];
		if($val == 'on') return true;
		else return false;
	}
	else return False;
}

