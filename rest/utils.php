<?php

/**
 * @file   rest/utils.php
 * @author Katharine Gillis
 * @date   6/25/2013
 * @brief  Utilities for the webservice.
 */

function OutputResponse($response, $error_code = 0, $error_message = '') {
	if ($error_code != 0) {
        // Send out a error result.
		$response['success'] = false;
		$response['error_code'] = $error_code;
		$response['error_message'] = $error_message;
	} else {
        // Send out a success result.
		$response['success'] = true;
	}

	echo json_encode($response);
	exit;
}

 function RunService($path) {
     // Run the specified path, returning a json response.
	header('Cache-Control: no-cache, must-revalidate');
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Content-type: application/json');

 	if (file_exists($path)) {
 		require_once($path);
 	} else {
		OutputResponse(array(), 1000, 'Service not implemented.');
	}
}