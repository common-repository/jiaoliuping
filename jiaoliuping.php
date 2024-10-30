<?php
/**
 Plugin Name: 交流瓶插件
 Plugin URI: http://wordpress.org/extend/plugins/jiaoliuping/
 Description: 该插件是交流瓶(jiaoliuping.com)的DISCUZ客户端, 能将社区的热门帖子转播到微博平台，利用微博来快速传播，并产生大量转发与评论, 支持微博评论回流到社区中，实现评论的双倍增长，继而给社区带来更多人气.支持腾讯微博，新浪微博，网易微博，搜狐微博，人人网，开心网，豆瓣网及其它安装了插件的discuz站，wordpress站
 Author: 易点互联
 Version: 0.2.2
 Author URI: http://yidianhulian.com/
License: GPLv2 or later
*/


include_once 'functions.php';

/**
 * 没有同步的显示可同步的网站，已经同步的显示已经同步去的网站
 * 
 * @author leeboo
 * 
 * @param unknown $post
 * 
 * @return
 */
function jiaoliuping_drift_to( $post ) {
	$jiaoliuping_drift_to = get_post_meta($post->ID,"jiaoliuping_drift_to", true);

	$session = SessionModel::getSessionOf(get_option("jiaoliuping_admin_id"));
	
	$client = new YDHL_HttpClient();
	$json = $client->get(JIAOLIUPING_API_BINDSITES,  array("access_token"=>$session['access_token'], "site_name"=>urlencode(get_option('blogname'))));
	//var_dump($json);
	$sites = json_decode($json);
	if($jiaoliuping_drift_to=="-1"){
		echo "该文章从交流瓶同步而来";
	}else if($jiaoliuping_drift_to){
		$jiaoliuping_drift_to = explode(",", $jiaoliuping_drift_to);
		echo "已同步到:<br/>";
		foreach ($sites->data as $site){
			if(in_array($site->site_id, $jiaoliuping_drift_to)){
				echo $site->site_name."(".$site->user_name.")";
			}
		}
	}else{
		$site_html = "";
		foreach ($sites->data as $site){
			$site_url = preg_replace("{^https?://}","", site_url());
			if(rtrim($site->site_url,"/")==rtrim($site_url,"/"))continue;
			$site_html .= '<label for="site'.$site->site_id.'">';
			$site_html .= '<input type="checkbox" id="site'.$site->site_id.'" name="drift_to[]" value="'.$site->site_id.'"  />'.$site->site_name."(".$site->user_name.")";
			$site_html .= '</label> ';
		}
		if($site_html){
			echo $site_html;
		}else{
			echo "要把博文同步到其它网站，请联系管理员先绑定这些网站的帐号";
		}
	}
	
}

/**
 * 发布文章时显示可同步网站表单
 * 
 * @author leeboo
 * 
 * @param unknown $post_type
 * @param string $context
 * 
 * @return
 */
function jiaoliuping_meta_boxes($post_type, $context="normal"){
	if($post_type == "comment")return;
	add_meta_box("jiaoliuping","选择要同步的网站", "jiaoliuping_drift_to", $post_type, $context, 'high');
}


/**
 * 发布post时通知交流瓶，只有在有drift_to数据是才通知
 */
function jiaoliuping_insert_post($post_id){
	global $table_prefix, $wpdb;

	// 检查权限
/* 	if ( 'page' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) )
			return;
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;
	}
 */
	if ( @!$_POST['drift_to'] )	return;//没有选择同步网站不同步，同时非后台发布文章的也不同步

	$drift_to = join(",", $_POST['drift_to'] );
	$post = get_post($post_id);
	
	$client = new YDHL_HttpClient();
	$session = SessionModel::getSessionOf(get_option("jiaoliuping_admin_id"));
	$json = $client->post(JIAOLIUPING_API_POST, array(
			"access_token"	=>$session['access_token'],
			"msg"			=>strip_tags($post->post_content),
			"site_name"		=>urlencode(get_option('blogname')),
			"rid"			=>$post_id,
			"msg_url"		=>$post->guid,
			"drift_to"		=>$drift_to
			));
	$rst = json_decode($json);
	if($rst->success){
		update_post_meta($post_id, 'jiaoliuping_drift_to', $drift_to);
	}
}

/**
 * 已经同步的文章发表留言时同步留言
 * 
 * @author leeboo
 * 
 * @param unknown $comment_id
 * @param unknown $comment
 * 
 * @return
 */
function jiaoliuping_insert_comment($comment_id){
	if ( @!$_POST['jiaoliuping_comment_need_drift'] )	return;//没有该项表示不需要同步该留言
	$comment = get_comment($comment_id);
	if($comment->comment_approved !=="1")return;
	$post = get_post($comment->comment_post_ID);

	$client = new YDHL_HttpClient();
	$session = SessionModel::getSessionOf(get_option("jiaoliuping_admin_id"));
	
	$comment_content = strip_tags($comment->comment_content);
	$json = $client->post(JIAOLIUPING_API_REPLY, array(
			"access_token"	=>$session['access_token'],
			"msg"			=> $comment->user_id ? $comment_content : $comment->comment_author."  : ".$comment_content,
			"site_name"		=>urlencode(get_option('blogname')),
			"cid"			=> $comment->ID,
			"rid"			=>$comment->comment_post_ID 
	));

	$rst = json_decode($json);
	if($rst->success){
		update_comment_meta($comment_id, 'jiaoliuping_comment_has_drift', 1);
	}
} 

function jiaoliuping_set_comment_status($comment_id){
	$comment = get_comment($comment_id);

	if($comment->comment_approved !=="1")return;
	
	$post_is_drift = get_post_meta($comment->comment_post_ID,"jiaoliuping_drift_to", true);
	
	if(! $post_is_drift)return;
	
	$comment_is_drift = get_comment_meta($comment_id, "jiaoliuping_comment_has_drift", true);

	
	if($comment_is_drift)return;
	
	$client = new YDHL_HttpClient();
	$session = SessionModel::getSessionOf(get_option("jiaoliuping_admin_id"));
	
	$comment_content = strip_tags($comment->comment_content);
	$json = $client->post(JIAOLIUPING_API_REPLY, array(
			"access_token"	=> $session['access_token'],
			"msg"			=> $comment->user_id ? $comment_content : $comment->comment_author."  : ".$comment_content,
			"site_name"		=> urlencode(get_option('blogname')),
			"cid"			=> $comment->ID,
			"rid"			=> $comment->comment_post_ID
	));
	$rst = json_decode($json);
	if($rst->success){
		update_comment_meta($comment_id, 'jiaoliuping_comment_has_drift', 1);
	}
}

/**
 * 输出决定评论是否要同步的控件
 * 
 * @author leeboo
 * 
 * 
 * @return
 */
function additional_comment_fields ($post_id) {
	$jiaoliuping_drift_to = get_post_meta($post_id,"jiaoliuping_drift_to", true);
	if($jiaoliuping_drift_to){
		echo '<input   name="jiaoliuping_comment_need_drift" type="hidden" value="1"/>';
	}
}


/**
 * 安装插件时建表，定义url映射
 * @author leeboo
 *
 */
class JiaoliupingPlugin {
	function install_db(){
		global $wpdb, $table_prefix;
		$table_name = $table_prefix.'ydhl_oauth_clients';
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE  TABLE IF NOT EXISTS `{$table_name}` (
			`id` VARCHAR(40) NOT NULL DEFAULT '' ,
			`secret` VARCHAR(40) NOT NULL DEFAULT '' ,
			`name` VARCHAR(255) NOT NULL DEFAULT '' ,
			`auto_approve` TINYINT(1) NOT NULL DEFAULT '0' ,
			PRIMARY KEY (`id`) )
			ENGINE = InnoDB
			DEFAULT CHARACTER SET = utf8;";
			$rs1 = $wpdb->query($sql);
		}

		$table_name = $table_prefix.'ydhl_oauth_client_endpoints';
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE  TABLE IF NOT EXISTS `{$table_name}` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
			`client_id` VARCHAR(40) NOT NULL DEFAULT '' ,
			`redirect_uri` VARCHAR(255) NULL DEFAULT NULL ,
			PRIMARY KEY (`id`) ,
			INDEX `client_id` (`client_id` ASC) ,
			CONSTRAINT `ydhl_oauth_client_endpoints_ibfk_1`
			FOREIGN KEY (`client_id` )
			REFERENCES  `{$table_prefix}ydhl_oauth_clients` (`id` )
			ON DELETE CASCADE
			ON UPDATE CASCADE)
			ENGINE = InnoDB
			DEFAULT CHARACTER SET = utf8;";
			$rs2 = $wpdb->query($sql);
		}

		$table_name = $table_prefix.'ydhl_oauth_scopes';
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE  TABLE IF NOT EXISTS `{$table_name}` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
			`scope` VARCHAR(255) NOT NULL DEFAULT '' ,
			`name` VARCHAR(255) NOT NULL DEFAULT '' ,
			`description` VARCHAR(255) NULL DEFAULT '' ,
			PRIMARY KEY (`id`) ,
			UNIQUE INDEX `scope` (`scope` ASC) )
			ENGINE = InnoDB
			DEFAULT CHARACTER SET = utf8;";
			$rs2 = $wpdb->query($sql);

			$sql = "INSERT INTO `{$table_name}` (`id`, `scope`, `name`, `description`)
			VALUES (NULL, 'basic', 'basic', 'read, create and comment');";
			$rs2 = $wpdb->query($sql);
		}


		$table_name = $table_prefix.'ydhl_oauth_sessions';
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE  TABLE IF NOT EXISTS `{$table_name}` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
			`client_id` VARCHAR(40) NOT NULL DEFAULT '' ,
			`redirect_uri` VARCHAR(250) NULL DEFAULT '' ,
			`owner_type` ENUM('user','client') NOT NULL DEFAULT 'user' ,
			`owner_id` VARCHAR(255) NULL DEFAULT '' ,
			`auth_code` VARCHAR(40) NULL DEFAULT '' ,
			`access_token` VARCHAR(40) NULL DEFAULT '' ,
			`refresh_token` VARCHAR(40) NULL DEFAULT '' ,
			`access_token_expires` INT(10) NULL DEFAULT NULL ,
			`stage` ENUM('requested','granted') NOT NULL DEFAULT 'requested' ,
			`first_requested` INT(10) UNSIGNED NOT NULL ,
			`last_updated` INT(10) UNSIGNED NOT NULL ,
			PRIMARY KEY (`id`) ,
			INDEX `client_id` (`client_id` ASC) )
			ENGINE = InnoDB
			DEFAULT CHARACTER SET = utf8;";
			$rs2 = $wpdb->query($sql);
		}

		$table_name = $table_prefix.'ydhl_oauth_session_scopes';
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE  TABLE IF NOT EXISTS `{$table_name}` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
			`session_id` INT(11) UNSIGNED NOT NULL ,
			`scope_id` INT(11) UNSIGNED NOT NULL ,
			PRIMARY KEY (`id`) ,
			INDEX `session_id` (`session_id` ASC) ,
			INDEX `scope_id` (`scope_id` ASC) ,
			CONSTRAINT `ydhl_oauth_session_scopes_ibfk_5`
			FOREIGN KEY (`scope_id` )
			REFERENCES `{$table_prefix}ydhl_oauth_scopes` (`id` )
			ON DELETE CASCADE,
			CONSTRAINT `ydhl_oauth_session_scopes_ibfk_4`
			FOREIGN KEY (`session_id` )
			REFERENCES  `{$table_prefix}ydhl_oauth_sessions` (`id` )
			ON DELETE CASCADE)
			ENGINE = InnoDB
			DEFAULT CHARACTER SET = utf8;";
			$rs2 = $wpdb->query($sql);
		}
		
		$table_name = $table_prefix.'ydhl_oauth_logins';
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE  TABLE IF NOT EXISTS `{$table_name}` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
			`name` varchar(45)  NOT NULL ,
			`avatar` varchar(299) NOT NULL,
			`uid` varchar(299) NOT NULL,
			`psw` varchar(299) NOT NULL,
			`site` varchar(299) NOT NULL,
			`wp_uid` BIGINT(20) NOT NULL,
			PRIMARY KEY (`id`))
			ENGINE = InnoDB
			DEFAULT CHARACTER SET = utf8;";
			$rs2 = $wpdb->query($sql);
		}
	}
	function activate() {
		global $wp_rewrite;
		$this->install_db();
		$this->flush_rewrite_rules();
		update_option("jiaoliuping_admin_id", get_current_user_id());//谁安装的插件
	}

	// Took out the $wp_rewrite->rules replacement so the rewrite rules filter could handle this.
	function create_rewrite_rules($rules) {
		global $wp_rewrite;
		$newRule = array('jiaoliuping/(.+)' => 'index.php?jiaoliuping='.$wp_rewrite->preg_index(1));
		$newRules = $newRule + $rules;
		return $newRules;
	}

	function add_query_vars($qvars) {
		$qvars[] = 'jiaoliuping';
		return $qvars;
	}

	function flush_rewrite_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	function template_redirect_intercept() {
		global $wp_query;
		if (in_array($wp_query->get('jiaoliuping'), 
				array("authorise", "access_token","post","reply","update","replylist","userinfo","signin"))) {
			include dirname(__FILE__)."/".$wp_query->get('jiaoliuping').".php";
			exit;
		}
	}
	
}

function jiaoliuping_login_banner(){
	if( ! get_option( 'users_can_register' ))return;
	session_start();

	
	$client = new YDHL_HttpClient();
	$json  = $client->get(JIAOLIUPING_API_SITES,  array());
	$sites = @ json_decode($json, true);
	
	if( ! $sites || !$sites['data'])return;

	$site_html = "";
	$site_url = get_option("siteurl");
	foreach ((array)$sites['data'] as $site){
		$site_html .= "<a  title='{$site['name']}登录' href='{$site['url']}?state=".urlencode($site_url.'/jiaoliuping/signin')."'><img width='32px' src='{$site['logo']}'/></a> ";
	}
	
   	echo '
   		<div style="width: 320px;padding: 10px 0 0;margin: auto;" class="login">
   			<form action="'.JIAOLIUPING_URI.'/signin">
				<label>
   					通过交流瓶支持的网站登录。
   					<br/>
   					<div style="margin:10px">'.$site_html.'
   						<br/>
   						<br/>或，输入安装了交流瓶插件的网站地址
   							<input type="text" name="login_plugin_url"/>
   							<input type="submit" class="button button-primary button-large" value="点击授权"/>
   					</div>
				</label>
   				<input type="hidden" name="state" value="'.urlencode($site_url.'/jiaoliuping/signin').'"/>
   			</form>
		</div>';
}

add_action('login_footer', 'jiaoliuping_login_banner');

$MyPluginCode = new JiaoliupingPlugin();
register_activation_hook( __FILE__, array($MyPluginCode, 'activate') );

// Using a filter instead of an action to create the rewrite rules.
// Write rules -> Add query vars -> Recalculate rewrite rules
add_filter('rewrite_rules_array', array($MyPluginCode, 'create_rewrite_rules'));
add_filter('query_vars',array($MyPluginCode, 'add_query_vars'));

// Recalculates rewrite rules during admin init to save resourcees.
// Could probably run it once as long as it isn't going to change or check the
// $wp_rewrite rules to see if it's active.
add_filter('admin_init', array($MyPluginCode, 'flush_rewrite_rules'));
add_action( 'template_redirect', array($MyPluginCode, 'template_redirect_intercept') );

add_action("publish_post", "jiaoliuping_insert_post");
add_action("wp_insert_comment", "jiaoliuping_insert_comment");//发布时
add_action("wp_set_comment_status", "jiaoliuping_set_comment_status");//审核通过时 
add_action("add_meta_boxes", "jiaoliuping_meta_boxes");
add_action( 'comment_form', 'additional_comment_fields' );

// {------------ 设置admin菜单, 只有admin才能看见 ------------------
if ( !defined('WP_ADMIN') )return;
function jiaoliuping_config_page(){
	include 'config.php';
}

function jiaoliuping_admin_menu(){
	add_menu_page('交流瓶', '交流瓶', 10, 'jiaoliuping', 'jiaoliuping_config_page', plugin_dir_url(__FILE__)."icon.png");
}

add_action('admin_menu', 'jiaoliuping_admin_menu');

// 加载样式
function jiaoliuping_load_js_and_css() {
	global $hook_suffix;
	if($hook_suffix!="toplevel_page_jiaoliuping")return;
	
	wp_register_style( 'bootstrap.min.css', plugin_dir_url(__FILE__) . 'bootstrap/css/bootstrap.min.css', array(), '1');
	wp_enqueue_style( 'bootstrap.min.css');
}
add_action( 'admin_enqueue_scripts', 'jiaoliuping_load_js_and_css' );
// ------------ 设置admin菜单 ------------------}