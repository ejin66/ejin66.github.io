<?php
	require_once('DB.php');
	$db = new DB();
	
	if(!$_POST['id'] || !$_POST['content'] || !$_POST['url']) {
		echo 0;
		exit;
	}
	
	$data = array();
	$data["tagId"] = $_POST['id'];
	$data["content"] = $_POST['content'];
	$data["url"] = $_POST['url'];
	$result = $db->insert("ft_content",$data);
	
	//header("Access-Control-Allow-Origin:*");
	echo $result? 1:0;
?>