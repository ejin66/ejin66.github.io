<?php
	require_once('DB.php');
	$db = new DB();
	$sql = "select id,tagName from ft_tag where enable = 1 order by id asc";
	$result = $db->query($sql);
	foreach ($result as $index => $item) {
		$id = $item['id'];
		$sql = "select content,url from ft_content where tagId = $id and enable = 1 order by id asc";
	
		$result2 = $db->query($sql);
		
		if (count($result2) == 0) {
			unset($result[$index]);
			continue;
		}
		
		$result[$index]["items"] = $result2;
	}
	
	
	//header("Access-Control-Allow-Origin:*");
	echo json_encode($result);
?>