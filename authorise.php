<?php
session_start();
ini_set('display_errors', true);
error_reporting(-1);

include_once 'functions.php';


$server = new \OAuth2\AuthServer(new ClientModel, new SessionModel, new ScopeModel);
$server->addGrantType(new \OAuth2\Grant\AuthCode());

if( ! @$_SESSION['params']){
	try{
		$params = $server->checkAuthoriseParams();
		$_SESSION['params'] = serialize($params);
	}catch(Exception $e){
		wp_die($e->getMessage());
	}
}

if( ! is_user_logged_in()){
	header('Location:'.wp_login_url(site_url()."/jiaoliuping/authorise"));
	die;
}

if(@$_POST['approve'] || @$_POST['deny']){
	if ( ! isset($_SESSION['params'])) {
		wp_die('Missing auth parameters');
	}

	$params = unserialize($_SESSION['params']);

	if (isset($_POST['approve']))	{
		$authCode = $server->newAuthoriseRequest('user', get_current_user_id(), $params);

		header("Location:".OAuth2\Util\RedirectUri::make($params['redirect_uri'], array(
				'code' => $authCode,
				'state'	=> $params['state']
		)));
	}elseif (isset($_POST['deny'])){
		header("Location:".OAuth2\Util\RedirectUri::make($params['redirect_uri'], array(
				'error' => 'access_denied',
				'error_message' => $server::getExceptionMessage('access_denied'),
				'state'	=> $params['state']
		)));
	}
	unset($_SESSION['params']);
	die;
}

if ( ! isset($_SESSION['params'])){
	wp_die('Missing auth parameters');
}

$params = unserialize($_SESSION['params']);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<title><?php
	global $page, $paged;
	echo " 授权请求 | ";
	wp_title( '|', true, 'right' );
	bloginfo( 'name' );
	?></title>
<link rel='stylesheet'   href='<?php echo trim(site_url() , "/")?>/wp-content/plugins/jiaoliuping.com/bootstrap/css/bootstrap.min.css?ver=1' type='text/css' media='all' />
</head>
<body>
	<div class="container">
		<div class="span6">
			<h2 class="page-header">允许 <?php echo $params['client_details']['name']; ?> 进行以下操作</h2>
			
			<div class="alert alert-info">
				<ul>
						<li>获取用户信息</li>
						<li>发布博文</li>
						<li>发布回复</li>
						<li>获取回复</li>
						<li>获取最新博文</li>
				</ul>
			</div>
			
			<div class="form-actions">
				<form method="post" style="display:inline">
					<input type="submit" name="approve" id="approve"  class="btn btn-large btn-primary" value="同意">
				</form>
			
				<form method="post" style="display:inline">
					<input type="button" name="deny" id="deny"   class="btn btn-large" onclick="window.close()" value="拒绝">
				</form>
				
				<?php 
				$user 		= wp_get_current_user();
				?>
				<span  class="offset1">
				<span><?php echo $user->display_name?></span>
				<a href="<?php echo wp_logout_url(wp_login_url(site_url()."/jiaoliuping/authorise"))?>">换个帐号</a>
				</span>
			</div>
		</div>
	</div>
</body>
</html>