<?php
session_start();
ini_set('display_errors', true);
error_reporting(-1);

include_once 'functions.php';


// Initiate the Request handler
$request = new \OAuth2\Util\Request();

// Initiate the auth server with the models
$server = new \OAuth2\AuthServer(new ClientModel, new SessionModel, new ScopeModel);

// Enable support for the authorization code grant
$server->addGrantType(new \OAuth2\Grant\AuthCode());
$server->addGrantType(new \OAuth2\Grant\RefreshToken());
$server->setExpiresIn(31536000);


header('Content-type: application/javascript');

try {

	// Issue an access token
	$p = $server->issueAccessToken();
	echo json_encode($p);

}

catch (Exception $e)
{
	// Show an error message
	echo json_encode(array('error' => $e->getMessage(), 'error_code' => $e->getCode()));
}