<?php
	require_once('DB.php');
	$db = new DB();
	$sql = "select id,tagName from ft_tag where enable = 1 order by id asc";
	$result = $db->query($sql);
	//header("Access-Control-Allow-Origin:*");
	echo json_encode($result);
?>