<?php

if ( version_compare(PHP_VERSION, '5.3.0', '<') ) {
    wp_die('您的php版本过低，该交流瓶插件需要PHP 5.3.0 以上版本');
};

if ( !extension_loaded("openssl")) {
	wp_die('请开启openssl扩展');
};

include 'consumer/ydhl_oauthclient.php';
include 'model_client.php';
include 'model_scope.php';
include 'model_session.php';
include 'model_oauth_login.php';
include 'sp/Request.php';
include 'sp/AuthServer.php';
include 'sp/AuthCode.php';
include 'sp/RedirectUri.php';
include 'sp/RefreshToken.php';


define("JIAOLIUPING_URI", "http://jiaoliuping.com");
define("JIAOLIUPING_API_SITEINFO", JIAOLIUPING_URI."/api/siteinfo.json");
define("JIAOLIUPING_API_REGSITE", JIAOLIUPING_URI."/api/regsite.json");
define("JIAOLIUPING_API_REGUSER", JIAOLIUPING_URI."/api/reguser.json");
define("JIAOLIUPING_API_BINDSITES", JIAOLIUPING_URI."/api/bindsites.json");
define("JIAOLIUPING_API_SITES", JIAOLIUPING_URI."/api/sites.json");
define("JIAOLIUPING_API_POST", JIAOLIUPING_URI."/api/post.json");
define("JIAOLIUPING_API_REPLY", JIAOLIUPING_URI."/api/reply.json");
define("JIAOLIUPING_API_REMOVE", JIAOLIUPING_URI."/api/remove-site.json");
define("JIAOLIUPING_API_GET_USER", JIAOLIUPING_URI."/api/userinfo.json");


function can_use_jiaoliuping(){
	$client = new YDHL_HttpClient();
	$session = SessionModel::getSessionOf(get_option("jiaoliuping_admin_id"));
	if($session){
		$site_info = json_decode($client->get(JIAOLIUPING_API_SITEINFO, array("access_token"=>$session['access_token'], "site_name"=>urlencode(get_option('blogname')))));
		if(!$site_info->success){
			return false;
		}
		return true;
	}
	return false;
}

function output_json_data($data, $error_code=0, $error=""){
	echo json_encode(array(
			'error_code' => $error_code,
			'error' => $error,
			'data' => $data
	));
	exit();
}
/**
 * 检查access token
 * 
 * @author leeboo
 * 
 * @param unknown $access_token
 * 
 * @return
 */
function check_access_token($access_token){
	if(!$access_token){
		output_json_data(array(), 300, 'access token is empty');
	}
	
	$session = new SessionModel();
	$sessionData = $session->validateAccessToken($access_token);
	
	if(!$sessionData){
		output_json_data(array(), 300, 'access token is invalid');
	}

	if(time() > $sessionData['access_token_expires']){
		output_json_data(array(), 100, 'token Expired');
	}
	
	return $sessionData;
}