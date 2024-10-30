<?php
//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

include_once 'functions.php';

global $wpdb, $table_prefix;

$c = ClientModel::get_by_name("交流瓶");
if( $c ){
	$client = new YDHL_HttpClient();
	$client->post(JIAOLIUPING_API_REMOVE, array("app_secret" => $c['secret']));
}

$table_name = $table_prefix.'ydhl_oauth_client_endpoints';
$sql = "DROP TABLE IF EXISTS `{$table_name}`;";
$rs2 = $wpdb->query($sql);

$table_name = $table_prefix.'ydhl_oauth_clients';
$sql = "DROP  TABLE IF EXISTS `{$table_name}`";
$rs1 = $wpdb->query($sql);

$table_name = $table_prefix.'ydhl_oauth_session_scopes';
$sql = "DROP  TABLE IF EXISTS `{$table_name}`;";
$rs2 = $wpdb->query($sql);

$table_name = $table_prefix.'ydhl_oauth_scopes';
$sql = "DROP  TABLE IF  EXISTS `{$table_name}`";
$rs2 = $wpdb->query($sql);


$table_name = $table_prefix.'ydhl_oauth_sessions';
$sql = "DROP  TABLE IF EXISTS `{$table_name}`;";
$rs2 = $wpdb->query($sql);

