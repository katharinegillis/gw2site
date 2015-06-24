<?php

/**
 * @file   rest/session.php
 * @author Katharine Gillis
 * @date   6/25/2013
 * @brief  Runs the session REST webservice.
 */

require_once('utils.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Start up the session, and if it is no longer valid, kick out an invalid session error and exit.
	session_start();
	if (!$_SESSION['loggedIn'] || (time() - $_SESSION['lastActivity']) > 30 * 60) {
		session_unset();
		session_destroy();
		OutputResponse(array(), 1110, "Invalid session");
		exit;
	}

    // Update the session activity time, and then run the GET part of the service.
	$_SESSION['lastActivity'] = time();
	
	RunService('session/get.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start up the session, and if it is no longer valid, kick out an invalid session error and exit.
	session_start();
	if (!$_SESSION['loggedIn'] || (time() - $_SESSION['lastActivity']) > 30 * 60) {
		session_unset();
		session_destroy();
		OutputResponse(array(), 1110, "Invalid session");
		exit;
	}

    // Update the session activity time, and then run the GET part of the service.
	$_SESSION['lastActivity'] = time();
	RunService('session/post.php');
}