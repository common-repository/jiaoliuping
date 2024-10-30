<?php
include_once 'functions.php';

global $wpdb;
$session = check_access_token($_POST['access_token']);
$msg 	= $_POST['msg'];
$rid 	= $_POST['rid'];
$pic 	= $_POST['pic'];//TODO
if( ! $msg){
	output_json_data(array(), 300, "msg is empty");
}
if( ! $rid){
	output_json_data(array(), 300, "rid is empty");
}

$post = get_post($rid);
$user = get_userdata($post->post_author);

if( ! $post){
	output_json_data(array(), 200, "post has been deleted");
}

$time = current_time('mysql');

$data = array(
		'comment_post_ID' => $rid,
		'comment_author' =>$user->display_name,
		'comment_author_email' => $user->user_email,
		'comment_content' => $msg,
		'user_id' => $user->ID,
		'comment_date' => $time
);

$comment_id = wp_insert_comment($data);
update_comment_meta($comment_id, 'jiaoliuping_comment_has_drift', 1);
$data = array(
		'created_at'		=> $time,
		'rid'				=> $comment_id,
		'message_url'	=> $post->guid,
		'user'=>array(
				'uid'		=>$user->ID,
				'name'	=>$user->display_name,
				'url'		=>$user->user_url,
				'avatar'	=>'http://gravatar.com/avatar/' . md5( strtolower( trim ( $user->user_email ) ) ),
				'description'=>$user->user_description
		)
);

output_json_data($data);