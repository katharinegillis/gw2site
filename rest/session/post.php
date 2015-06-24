<?php

// Update the zoom and center in the session.
session_start();
$_SESSION['zoom'] = $_POST['zoom'];
$_SESSION['center'] = $_POST['center'];