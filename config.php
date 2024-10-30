<?php
use OAuth2\Util\SecureKey;

include_once 'functions.php';

if($_POST['regsite']){
	do_post();
}else{
	do_get();
}



////////////////////////  功能处理函数  /////////////////////////////

function jiaoliuping_reguser($site_url, $accessToken, $accessTokenExpires, $accessTokenExpiresIn,$refreshToken){
	
	$curr_user = wp_get_current_user();
	$user_info = array(
			"site_name"			=> urlencode(get_option('blogname')),
			"access_token"		=> $accessToken,
			"uid"				=> get_current_user_id(),
			"user_name"		=> $curr_user->display_name,
			"user_home"		=> $site_url,
			"user_avatar_url"	=>  'http://gravatar.com/avatar/' . md5( strtolower( trim ( $curr_user->user_email ) ) ),
			"access_token_expire"			=>  $accessTokenExpiresIn,
			"refresh_token"		=> $refreshToken);
	
	$client 	= new YDHL_HttpClient();
	$json	= $client->post(JIAOLIUPING_API_REGUSER, $user_info);
	$user_info = json_decode($json);
	
	if(!$user_info->success){//注册失败，删除插入的oauth会话及client信息，重新生成
		wp_die("注册用户失败: ".$user_info->msg." ".$json);
	}
}

/**
 * 处理表单提交
 * 
 * @author leeboo
 * 
 * 
 * @return
 */
function do_post(){
	global $wpdb, $table_prefix;
	$client = new YDHL_HttpClient();
	$session = SessionModel::getSessionOf(get_option("jiaoliuping_admin_id"));
	$site_url = get_option("siteurl");

	$endpoint = ClientModel::get_by_name("交流瓶");

	$site_domain = preg_replace("{^https?://}","", rtrim($site_url, "/"));
	$redirect_uri = JIAOLIUPING_URI."/signin/".base64_encode($site_domain);
	
	if( ! $endpoint){
		$client_id = SecureKey::make();
		$secret = SecureKey::make();
		
		//1 注册网站
		$site_info = array(
				"site_name"		=> urlencode(get_option('blogname')),
				"site_url"		=> $site_domain,
				"authorise_url"	=> $site_url."/jiaoliuping/authorise/",
				"access_token_url"=> $site_url."/jiaoliuping/access_token/",
				"app_key"		=> $client_id,
				"app_secret"	=> $secret,
				"scope"			=> "basic",
				"site_type"		=> "wordpress",
				"post_url"		=> $site_url."/jiaoliuping/post/",//post
				"reply_url"		=> $site_url."/jiaoliuping/reply/",
				"userinfo_url"	=> $site_url."/jiaoliuping/userinfo/",
				"replylist_url"	=> $site_url."/jiaoliuping/replylist/",
				"msg_url"		=> $site_url."/jiaoliuping/post/",//get
				"update_url"	=> $site_url."/jiaoliuping/update/",
				"site_logo"		=> $_POST["logo"]);
		
		
		$json = $client->post(JIAOLIUPING_API_REGSITE, $site_info);
		$site_info = json_decode($json);
		if(  ! @$site_info->success){//注册失败删除之前插入的记录
			wp_die("注册网站失败: " . $site_info->msg." ".$json);
		}
		
		//2 生成oauth client信息
		$rst = $wpdb->query($wpdb->prepare('
					INSERT INTO '.$table_prefix.'ydhl_oauth_clients ( id, secret, name, auto_approve )
					VALUES ( %s, %s, %s, 0)',   $client_id,   $secret,  "交流瓶"));
		
		if($rst===false){
			wp_die(mysql_error());
		}
		
		$rst = $wpdb->query($wpdb->prepare('
					INSERT INTO '.$table_prefix.'ydhl_oauth_client_endpoints ( client_id, redirect_uri)
					VALUES ( %s, %s)',   $client_id,   $redirect_uri));
		if($rst===false){
			wp_die(mysql_error());
		}
	}else{//update
		$client_id 	= $endpoint['client_id'];
		$secret 	= $endpoint['secret'];
		
		//1 注册网站
		$site_info = array(
				"site_name"		=> urlencode(get_option('blogname')),
				"site_url"		=> $site_domain,
				"authorise_url"	=> $site_url."/jiaoliuping/authorise",
				"access_token_url"=> $site_url."/jiaoliuping/access_token",
				"app_key"		=> $client_id,
				"app_secret"	=> $secret,
				"scope"			=> "basic",
				"post_url"		=> $site_url."/jiaoliuping/post/",//post
				"reply_url"		=> $site_url."/jiaoliuping/reply/",
				"userinfo_url"	=> $site_url."/jiaoliuping/userinfo/",
				"replylist_url"	=> $site_url."/jiaoliuping/replylist/",
				"msg_url"		=> $site_url."/jiaoliuping/post/",//get
				"update_url"	=> $site_url."/jiaoliuping/update/",
				"site_type"		=> "wordpress",
				"site_logo"		=> $_POST["logo"]);
		
		
		$json = $client->post(JIAOLIUPING_API_REGSITE, $site_info);
		$site_info = json_decode($json);
		if(  ! @$site_info->success){//注册失败删除之前插入的记录
			wp_die("更新网站失败: " . $site_info->msg);
		}
		
		//2 生成oauth client信息
		$rst = $wpdb->query($wpdb->prepare("
				UPDATE {$table_prefix}ydhl_oauth_clients set secret='%s' where id = %s",   
				$secret,  $client_id));
		
		if($rst===false){
			wp_die(mysql_error());
		}
		
		$rst = $wpdb->query($wpdb->prepare("
					UPDATE {$table_prefix}ydhl_oauth_client_endpoints set redirect_uri='%s'
					where client_id = %s",   $redirect_uri,   $client_id));
		if($rst===false){
			wp_die(mysql_error());
		}
	}
	
	//3. 注册用户
	$site_url = get_option("siteurl");
	$accessToken = SecureKey::make();
	$refreshToken = SecureKey::make();
	$accessTokenExpires = time() + 31536000;
	$accessTokenExpiresIn = 31536000;
	jiaoliuping_reguser($site_url, $accessToken, $accessTokenExpires, $accessTokenExpiresIn, $refreshToken);
	
	//4 生成oauth 用户信息
	$server = new \OAuth2\AuthServer(new ClientModel, new SessionModel, new ScopeModel);
	$server->addGrantType(new \OAuth2\Grant\AuthCode());
	$server->addGrantType(new \OAuth2\Grant\RefreshToken());
	
	$scopeDetails = \OAuth2\AuthServer::getStorage('scope')->getScope("basic");
	$params = array('client_id'=>$client_id, 'redirect_uri'=>$redirect_uri, 'response_type'=>"code", 'scopes'=>array($scopeDetails));
	$authCode = $server->newAuthoriseRequest('user', get_current_user_id(), $params);
	$session =\OAuth2\AuthServer::getStorage('session')->validateAuthCode($client_id, $redirect_uri, $authCode);
	

	\OAuth2\AuthServer::getStorage('session')->updateSession($session['id'], null, $accessToken, $refreshToken, $accessTokenExpires, 'granted');
	
	echo "<p class='alert'>注册成功了, <a class='button' href='".rtrim(get_option("siteurl"), "/")."/wp-admin/admin.php?page=jiaoliuping'>就差绑定帐号了</a></p>";
}


/**
 * get请求显示界面,检查本站是否在jiaoliuping上注册了
 * 如果没有则显示注册界面
 * 如果注册了则显示注册信息界面及已经绑定的帐号
 * 
 * @author leeboo
 * 
 * 
 * @return
 */
function do_get(){

	if(@$_GET['jlpaction']){
		show_register_form();return;
	}
	
	$client = new YDHL_HttpClient();
	$session = SessionModel::getSessionOf(get_option("jiaoliuping_admin_id"));
	$oauthClient = ClientModel::get_by_name("交流瓶");
	if($oauthClient){
		$json = $client->get(JIAOLIUPING_API_SITEINFO, array("secret"=>$oauthClient['secret'], "site_name"=>urlencode(get_option('blogname'))));
		$site_info = json_decode($json);
		if( ! $site_info->success){
			$site_url = get_option("siteurl");
			wp_die("访问出错：".$site_info->msg.", <a href='{$site_url}/wp-admin/admin.php?page=jiaoliuping&jlpaction=update'>重新注册？</a>");
		}
		$site_info = $site_info->data;
	}
	
	if( ! empty($site_info) && !@$_GET['jlpaction']){//has register
		show_site_info($site_info, $session);
	}else{//not register
		show_register_form();
	}
}

/**
 * 显示注册的网站及绑定的帐号
 * 
 * @author leeboo
 * 
 * 
 * @return
 */
function show_site_info($site_info, $session){
	$client = new YDHL_HttpClient();
	$bind_sites = json_decode($client->get(JIAOLIUPING_API_BINDSITES, array("access_token"=>$session['access_token'], "site_name"=>urlencode(get_option('blogname')))));

	if( ! $bind_sites || ! $bind_sites->success){//尝试注册一个用户
		$site_url = get_option("siteurl");
		$accessToken = SecureKey::make();
		$refreshToken = SecureKey::make();
		$accessTokenExpires = time() + 31536000;
		$accessTokenExpiresIn = 31536000;
		jiaoliuping_reguser($site_url, $accessToken, $accessTokenExpires, $accessTokenExpiresIn, $refreshToken);
		$bind_sites = json_decode($client->get(JIAOLIUPING_API_BINDSITES, array("access_token"=>$accessToken, "site_name"=>urlencode(get_option('blogname')))));
	}
	$endpoint = ClientModel::get_by_name("交流瓶");
?>
<div class="span8">
	<h3 class="page-header">&nbsp;<img src="<?php echo plugin_dir_url(__FILE__)."jiaoliuping.png"?>"/>交流瓶配置</h3>

	<fieldset><legend>注册信息</legend>
		<div class="alert <?php echo  $site_info->is_valid ? "alert-success" :"alert-error" ?>">网站已经注册成功，
		<?php echo $site_info->is_valid ? "现在可以进行同步了" : "但不能进行同步，原因是：".$site_info->invalid_reason?><br/>
		如果您的网站名改变了，请<a href="<?php echo trim(get_site_url(), "/")?>/wp-admin/admin.php?page=jiaoliuping&jlpaction=update">更新注册信息</a>
		<br/>
		<br/>
		欢迎到我们的小组讨论: <a href="http://www.douban.com/group/469579/" target="_blank">豆瓣小组</a>
		
		</div>
		<table class="table table-striped" >
			<tr><td width="80px"><strong>网站名</strong></td><td><?php echo $site_info->open_name?></td></tr>
			<tr><td><strong>网站地址</strong></td><td><?php echo $site_info->site_url?></td></tr>
			<tr> <td><strong>网站logo</strong></td> <td><img width="50" src="<?php echo $site_info->site_logo?>"/></td></tr>
			<tr> <td><strong>交流瓶Client ID</strong></td> <td><?php echo $endpoint['client_id']?> <span class="alert alert-error">请注意保密</span></td></tr>
			<tr> <td><strong>交流瓶Callback</strong></td> <td><?php echo $endpoint['redirect_uri']?></td></tr>
		</table>
	</fieldset>
	<br/><br/>
	<fieldset><legend>已绑定的网站</legend>
	<p>现在发布文章时便可以把文章同步到下面的网站上去。您还可以绑定更多的网站，让消息传播更广。<a class="btn btn-primary"  target="_blank" 
	href="<?php echo JIAOLIUPING_URI?>/api/quick_signin?access_token=<?php echo $session['access_token']?>&site_name=<?php echo urlencode($site_info->open_name)?>">绑定更多网站</a></p>
	<p class="alert">提示：同一个网站可以绑定多个帐号, 建议以网站的身份绑定帐号，不要绑定自己的个人帐号</p>
		<table class="table table-striped span4">
		<thead>
			<tr><th>网站</th><th>帐号</th></tr>
		</thead>
		<tbody>
	<?php 
	foreach ((array)@$bind_sites->data as $site){
?>
		<tr>
			<td><a href="http://<?php echo $site->site_url?>" target="_blank"><?php echo $site->site_name?></a></td>
			<td><a href="<?php echo $site->user_home?>" target="_blank"><img src="<?php echo $site->user_avatar_url?>" width="50"/><br/><?php echo $site->user_name?></a></td>
		</tr>
<?php 
	}
	?>
		</tbody>
		</table>
	</fieldset>
</div>
	<?php 
}

/**
 * 显示注册表单
 * 
 * @author leeboo
 * 
 * 
 * @return
 */
function show_register_form(){
	$site_url = get_option("siteurl");
?>
<div class="span8">
	<h3 class="page-header">&nbsp;<img src="<?php echo plugin_dir_url(__FILE__)."jiaoliuping.png"?>"/>交流瓶配置</h3>
	
	<ul class="breadcrumb">
	  <li class="active">第一步：注册到交流瓶 <span class="divider">/</span></li>
	  <li>第二步：绑定开放网站帐号 <span class="divider">/</span></li>
	</ul>
	
	<fieldset><legend>注册到交流瓶</legend>
		<form method="post"  class="form-horizontal">
			<div class="control-group">
				<label class="control-label">网站名</label>
				<div class="controls">
					<input type="text" disabled value="<?php echo get_option('blogname')?>"/>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" >网站地址</label>
				<div class="controls">
					<input type="text" disabled value="<?php echo $site_url?>"/>
				</div>
			</div>
			<div class="control-group">
				<label class="control-label" for="inputPassword">网站logo</label>
				<div class="controls">
					<input type="text" name="logo" class="regular-text"/>
					<p class="help-block">显示在交流瓶上, 便于用户区别出您的网站</p>
				</div>
				
			</div>
			
			<div class="form-actions">
				<input type="submit" value="注册" class="btn btn-primary btn-large" name="regsite"/>
			</div>
			
		</form>
	</fieldset>
</div>
<?php 
}