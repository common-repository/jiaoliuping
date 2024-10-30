<?php
require 'sp/SessionInterface.php';
class SessionModel implements \OAuth2\Storage\SessionInterface {
	
	public static function getSessionOf($userid){
		global $table_prefix, $wpdb;
		return $wpdb->get_row($wpdb->prepare("
				SELECT * FROM {$table_prefix}ydhl_oauth_sessions WHERE
				owner_type = 'user' AND
				owner_id = %d", $userid), ARRAY_A);
	}
	
	public static function getAllSessions(){
		global $table_prefix, $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_prefix}ydhl_oauth_sessions"), ARRAY_A);
	}

	public function createSession($clientId, $redirectUri, $type = 'user', $typeId = null, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested')
	{
		global $table_prefix, $wpdb;
		$rst = $wpdb->query($wpdb->prepare('
				INSERT INTO '.$table_prefix.'ydhl_oauth_sessions (
				client_id,
				redirect_uri,
				owner_type,
				owner_id,
				auth_code,
				access_token,
				refresh_token,
				access_token_expires,
				stage,
				first_requested,
				last_updated
		)
				VALUES (
				%s,
				%s,
				%s,
				%s,
				%s,
				%s,
				%s,
				%d,
				%s,
				UNIX_TIMESTAMP(NOW()),
				UNIX_TIMESTAMP(NOW())
		)',   $clientId,   $redirectUri,  $type,  $typeId,  $authCode,  $accessToken,  $refreshToken,   $accessTokenExpire, $stage
		));
		if($rst===false){
			wp_die(mysql_error());
		}
		return $wpdb->insert_id;
	}

	public function updateSession($sessionId, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested')
	{
		global $table_prefix, $wpdb;
		$rst = $wpdb->query($wpdb->prepare('
				UPDATE '.$table_prefix.'ydhl_oauth_sessions SET
				auth_code = %s,
				access_token = %s,
				refresh_token = %s,
				access_token_expires = %s,
				stage = %s,
				last_updated = UNIX_TIMESTAMP(NOW())
				WHERE id = %d', $authCode, $accessToken, $refreshToken,  $accessTokenExpire,   $stage, $sessionId
		));
		if($rst===false){
			wp_die(mysql_error());
		}
	}

	public function deleteSession($clientId, $type, $typeId)
	{
		global $table_prefix, $wpdb;
		$rst = $wpdb->query($wpdb->prepare('
				DELETE FROM '.$table_prefix.'ydhl_oauth_sessions WHERE
				client_id = %s AND
				owner_type = %s AND
				owner_id = %s',  $clientId, $type,  $typeId
		));
		if($rst===false){
			wp_die(mysql_error());
		}
	}

	public function validateAuthCode($clientId, $redirectUri, $authCode)
	{
		global $table_prefix, $wpdb;
		return $wpdb->get_row($wpdb->prepare('
				SELECT * FROM '.$table_prefix.'ydhl_oauth_sessions WHERE
				client_id = %s AND
				redirect_uri = %s AND
				auth_code = %s', $clientId, $redirectUri, $authCode
		), ARRAY_A);
	}

	public function validateAccessToken($accessToken){
		global $table_prefix, $wpdb;
		return $wpdb->get_row($wpdb->prepare('
				SELECT * FROM '.$table_prefix.'ydhl_oauth_sessions WHERE 
				access_token = %s',   $accessToken
		), ARRAY_A);
	}

	public function getAccessToken($sessionId)
	{
		// Not needed for this demo
	}

	public function validateRefreshToken($refreshToken, $clientId)
	{
		// Not needed for this demo
	}

	public function updateRefreshToken($sessionId, $newAccessToken, $newRefreshToken, $accessTokenExpires)
	{
		// Not needed for this demo
	}

	public function associateScope($sessionId, $scopeId)
	{
		global $table_prefix, $wpdb;
		$rst = $wpdb->query($wpdb->prepare('INSERT INTO '.$table_prefix.'ydhl_oauth_session_scopes (session_id, scope_id)
				VALUE (%s, %s)', $sessionId, $scopeId
		));
		if($rst===false){
			wp_die(mysql_error());
		}
	}

	public function getScopes($accessToken)
	{
		// Not needed for this demo
	}
}