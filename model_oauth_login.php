<?php
/**
 * 从其它站点登录过来的用户
 * @author leeboo
 *
 */
class OauthLoginUserModel  {
	public static function get_by($site_name, $uid){
		global $wpdb, $table_prefix;
		return  $wpdb->get_row($wpdb->prepare("SELECT *
				FROM {$table_prefix}ydhl_oauth_logins as c
				WHERE c.site = %s and c.uid = %s", $site_name, $uid), ARRAY_A);
	}
	

	public static function saveLogin($name, $avatar, $uid, $site, $psw, $wp_uid)
	{
		global $table_prefix, $wpdb;
		$rst = $wpdb->query($wpdb->prepare('
				INSERT INTO '.$table_prefix.'ydhl_oauth_logins (
				name,
				avatar,
				uid,
				site,
				psw,
				wp_uid
		)
				VALUES (
				%s,
				%s,
				%s,
				%s,
				%s,
				%s
		)',   $name,   $avatar,  $uid,  $site,  $psw, $wp_uid));
		if($rst===false){
			wp_die(mysql_error());
		}
		return $wpdb->insert_id;
	}
}