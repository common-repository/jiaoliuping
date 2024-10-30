<?php
/**
 * 如果是post请求，则表示jiaoliuping要推送oauth申请的数据过来，保存到数据库中，key为post提交过的来的key
 * 如果是get请求，则根据get请求中带的key来判断数据库中是否有用户数据，有则表示oauth登录成功，没有表示登录失败，给出提示
 */
session_start();
ini_set('display_errors', true);
error_reporting(-1);
include_once 'functions.php';


$key 	= @$_GET['key'];
$client = new YDHL_HttpClient();
$u 		= $client->get(JIAOLIUPING_API_GET_USER, array("key"=>$key));
$user	= json_decode($u, true);
		

if( ! $u || ! $user || !user['success']){
	wp_die("OAuth 登录失败，<a href='".site_url('wp-login.php')."'>请重试</a>", "登录失败");
}
$user = $user['data'];

$exist_user = OauthLoginUserModel::get_by($user['site'], $user['uid']);
$creds = array();

if( ! $exist_user){
	//TODO 如果就是本站用户
	$user_login = $user['site']."_".$user['uid'];
	$psw = uniqid();
	$wp_uid = wp_insert_user(array(
		'user_pass'  => $psw,
		'user_login'   => $user_login,
		'user_nicename'    => $user['name'],
		'display_name'    => $user['name'],
	));
	
	if ( is_wp_error($wp_uid) ){
		wp_die("登录失败，请重试：".$wp_uid->get_error_message());
	}

	OauthLoginUserModel::saveLogin($user['name'], $user['avatar'], $user['uid'], $user['site'], $psw, $wp_uid);
	
	$creds = array();
	$creds['user_login'] 		= $user_login;
	$creds['user_password'] = $psw;
}else{
	$exist_wp_user = get_userdata($exist_user['wp_uid']);
	$creds['user_login'] 		= $exist_wp_user->user_login;
	$creds['user_password'] = $exist_user['psw'];
}



$creds['remember'] 	= true;
$signon_user = wp_signon( $creds, false );
if ( is_wp_error($signon_user) ){
	wp_die("登录失败，请重试：".$signon_user->get_error_message());
}
wp_redirect(admin_url());

?>