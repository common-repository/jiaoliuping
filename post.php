<?php
include_once 'functions.php';

if($_POST){
	do_post();// post 发布消息
}else{
	do_get();// get获取消息
}



function do_post(){
	global $wpdb;
	$session = check_access_token($_POST['access_token']);
	$msg = $_POST['msg'];
	if( ! $msg){
		output_json_data(array(), 300, "msg is empty");
	}

	if( @$_FILES['pic']){
		if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
		$pic = $_FILES['pic'];
		$overrides = array( 'test_form' => false );
		$file = wp_handle_upload( $pic, $overrides );

		if ( ! isset( $file['error'] ) ){
			$msg .= "<img src='".$file['url']."'/>";
		}
	}
	
	$post = array('post_status' => 'publish', 'post_author' => $session['owner_id'],
			'ping_status' => get_option('default_ping_status'),
			'post_content' => $msg, 'post_title' => mb_substr(strip_tags($msg), 0, 20, "UTF-8")."...");
	
	
	$post_id = wp_insert_post($post);
	if($post_id ==0 || is_a($post_id, "WP_Error ")){
		output_json_data(array(), 300, "post error");
	}

	update_post_meta($post_id, 'jiaoliuping_drift_to', "-1");
	$post = get_post($post_id);
	$user = get_userdata($post->post_author);
	$data = array(
			'created_at'=>$post->post_date,
			'rid'			=>$post->ID,
			'message_url'=>$post->guid,
			'user'=>array(
					'uid'		=>$user->ID,
					'name'	=>$user->display_name,
					'url'		=>$user->user_url,
					'avatar'	=>'http://gravatar.com/avatar/' . md5( strtolower( trim ( $user->user_email ) ) ),
					'description'=>$user->user_description
			)
	);
	
	output_json_data($data);
}

function do_get(){
	global $wpdb;
	$session = check_access_token($_GET['access_token']);
	$rid = $_GET['rid'];
	if( ! $rid){
		output_json_data(array(), 300, "rid is empty");
	}
	$post = get_post($rid);
	if( ! $post){
		output_json_data(array(), 200, "post has been deleted");
	}
	$user = get_userdata($post->post_author);
	$data = array(
		'created_at'=>$post->post_date,
		'rid'			=>$rid,
		'message'	=>$post->post_content,
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
	
	output_json_data($data);
}