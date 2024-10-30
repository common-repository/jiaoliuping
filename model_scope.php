<?php
require 'sp/ScopeInterface.php';
class ScopeModel implements \OAuth2\Storage\ScopeInterface {

	public function getScope($scope)
	{
		global $table_prefix, $wpdb;
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$table_prefix.'ydhl_oauth_scopes WHERE scope = %s', 
				$scope), ARRAY_A);
		
		if ($row) {
			return $row;
		} else {
			return false;
		}

	}

}