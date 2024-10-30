<?php
require 'sp/ClientInterface.php';

class ClientModel implements \OAuth2\Storage\ClientInterface {
	
	public function getClient($clientId = null, $clientSecret = null, $redirectUri = null)
	{
		global $wpdb, $table_prefix;
		if($clientId && $clientSecret && $redirectUri){
			$row = $wpdb->get_row($wpdb->prepare("SELECT e.client_id, c.secret, e.redirect_uri,c.name 
				FROM {$table_prefix}ydhl_oauth_clients as c
				LEFT JOIN  {$table_prefix}ydhl_oauth_client_endpoints as e ON e.client_id = c.id 
				WHERE c.id = %s AND c.secret = %s AND e.redirect_uri=%s", $clientId, $clientSecret, $redirectUri), ARRAY_A);
		}elseif($clientId && $redirectUri){
			$row =  $wpdb->get_row($wpdb->prepare("SELECT e.client_id, c.secret, e.redirect_uri,c.name
					FROM {$table_prefix}ydhl_oauth_clients as c
					LEFT JOIN  {$table_prefix}ydhl_oauth_client_endpoints as e ON e.client_id = c.id
					WHERE c.id = %s AND e.redirect_uri=%s", $clientId, $redirectUri), ARRAY_A);
		}
		return $row ? $row : false;
	}
	
	public static function get_by_name($name){
		global $wpdb, $table_prefix;
		return  $wpdb->get_row($wpdb->prepare("SELECT e.client_id, c.secret, e.redirect_uri,c.name
				FROM {$table_prefix}ydhl_oauth_clients as c
				LEFT JOIN  {$table_prefix}ydhl_oauth_client_endpoints as e ON e.client_id = c.id
				WHERE c.name = %s ", $name), ARRAY_A);
	}
	
	public function validateRefreshToken($refresh_token, $client_id){
		global $table_prefix, $wpdb;
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$table_prefix.'ydhl_oauth_sessions WHERE client_id = %s 
				and refresh_token =%s', $client_id, $refresh_token), ARRAY_A);

		if ($row) {
			return true;
		} else {
			return false;
		}
	}

}