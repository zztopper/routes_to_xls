<?php

$request = isset($_GET['q'])?$_GET['q']:'';
if ($request=='')
	exit();
$mysqli = new mysqli('localhost', 'root', 'pEr#Ad*1', 'routing');

if ($mysqli->connect_error) {
	die('Ошибка подключения (' . $mysqli->connect_errno . ') '
		. $mysqli->connect_error);
}
/* изменение набора символов на utf8 */
if (!$mysqli->set_charset("utf8")) {
	die("Ошибка при загрузке набора символов utf8: ". $mysqli->error."\n");
}
$mysqli->query("SET GLOBAL max_allowed_packet=524288000");


$query = "SELECT route_id, route_short_name, route_long_name, route_type from routes where route_short_name LIKE '".$mysqli->real_escape_string($request)."%' ORDER BY route_short_name";
$result=$mysqli->query($query);
$i = 0;
if ($result) {
	while ($row = $result->fetch_array(MYSQLI_NUM)){
		$var['items'][$i]['id']=$row[0];
		$var['items'][$i]['short_name']=$row[1];
		$var['items'][$i]['text']=$row[2];
		switch ($row[3]) {
			case "А": $type="Автобус";
				break;
			case "Тм": $type="Трамвай";
				break;
			case "Тб": $type="Троллейбус";
				break;
		}
		$var['items'][$i]['type']=$type;
		$i++;
	}
}
$result->free();
$var['incomplete_results']=false;
$var['total_count']=$i;
$var['request']=$request;
$var['query']=$query;
header('Content-Type: application/json');
echo json_encode($var);

$mysqli->close();
?>