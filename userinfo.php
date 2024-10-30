<?php
include_once 'functions.php';
global $post, $since_id, $max_id, $count;
$session 	= check_access_token($_GET['access_token']);
$user 		= get_userdata($session['owner_id']);

$data = array(
		'nick'		=>$user->display_name,
		'name'		=>$user->user_login,
		'avatar'		=>'http://gravatar.com/avatar/' . md5( strtolower( trim ( $user->user_email ) ) ),
		'uid'			=>$user->ID,
		'home'		=>$user->user_url,
		'access_token'	=>$session['access_token'],
		'expires_in'		=>31536000,
		'refresh_token'	=>$session['refresh_token']
);
output_json_data($data);