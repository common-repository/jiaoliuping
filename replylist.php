<?php
include_once 'functions.php';
global $post, $since_id, $max_id, $count;
$session 	= check_access_token($_GET['access_token']);
$rid 		= $_GET['rid'];
if( ! $rid){
	output_json_data(array(), 300, "rid is empty");
}
$post = get_post($rid);
if( ! $post){
	output_json_data(array(), 200, "post has been deleted");
}


$comments = get_comments("post_id={$rid}");
$data = array();
foreach ($comments as $comment){
	if($comment->user_id){
		$user 		=  get_userdata($comment->user_id);
	}else{
		$user = new stdClass();
		$user->ID = 0;
		$user->display_name = $comment->comment_author;
		$user->user_url = $comment->comment_author_url ;
		$user->user_email = $comment->comment_author_email ;
		$user->user_description = "";
	}
	
	$data[] = array(
		'created_at'=>$comment->comment_date,
		'rid'			=>$comment->comment_ID,
		'message'	=>$comment->comment_content,
		'image'=>array(
				'normal'=>'',
				'big'=>''
		),
		'message_url'=>$post->guid,
		'user'=>array(
				'uid'		=>$user->ID,
				'name'	=>$user->display_name,
				'url'		=>$user->user_url,
				'avatar'	=>'http://gravatar.com/avatar/' . md5( strtolower( trim ( $user->user_email ) ) ),
				'description'=>$user->user_description
		)
	);
}
output_json_data($data);