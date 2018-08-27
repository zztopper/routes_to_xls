<?php

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


$query = "SELECT DISTINCT trip_short_name from trips ORDER BY trip_short_name ASC";
$result=$mysqli->query($query);
$i = 0;
$var['items'][0]['id']=0;
$var['items'][0]['text']='Любой';
$i++;
if ($result) {
	while ($row = $result->fetch_array(MYSQLI_NUM)){
		$var['items'][$i]['id']=$row[0];
		$var['items'][$i]['text']=$row[0];
		$i++;
	}
}
$result->free();
$var['incomplete_results']=false;
$var['total_count']=$i;
header('Content-Type: application/json');
echo json_encode($var);

$mysqli->close();
?>