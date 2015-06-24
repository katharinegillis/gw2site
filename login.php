<?php

// Check for an already logged in client.
session_start();

if ($_SESSION['loggedIn'] && (time() - $_SESSION['lastActivity']) < 30 * 60) {
    // Redirect the logged in user to the map.
	header('location: index.php');
	exit;
}

// If they are submitting the password, check it and set up the session if it is correct, then send them to the map.
if (isset($_POST['submit'])) {
	if ($_POST['password'] == "%password%") {
		$_SESSION['loggedIn'] = true;
		$_SESSION['lastActivity'] = time();
		header("location: index.php");
	}
}
?>
<html>
<head>
	<title>PhantomUtils ALPHA</title>
</head>
<body>
	<form action="login.php" method="POST">
		<p>
			Password: <input type="password" name="password"><br>
			<input type="submit" name="submit" value="Log In">
		</p>
	</form>
</body>
</html>
