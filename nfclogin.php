<?php
	if(session_id() == '') {
	    	session_start();
		session_destroy();
	}

	$arg = $_GET["a"];
	
	header('Location: index.html?a=' . $arg);
