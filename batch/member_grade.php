<?php
require_once(__DIR__ . "/connect.php");

/*
 * 1. 회원등급/기간 변경처리하기
 * 2. 리포트 기간 변경처리하기
 */

$db = connect_db();
$today = date("Y-m-d");

echo date("Y-m-d H:i:s"). " 실행 \n";

// 기간만료회원 회원등급변경
echo "member_grade_change : ";
$qry = "UPDATE MEMBER SET grade = 0, planSt = null, planEt = null WHERE del = 'N' AND grade > 0 AND planEt < '$today'";
$query = $db->query($qry);
if($query) {
	echo "ok";
} else {
	echo "fail";
}
echo "\n";

// 기간만료 리포트회원 날짜 변경
echo "member_reportdate_change : ";
$qry = "UPDATE MEMBER SET reportSt = null, reportEt = null WHERE del = 'N' AND reportEt < '$today'";
$query = $db->query($qry);
if($query) {
	echo "ok";
} else {
	echo "fail";
}
echo "\n";
echo "=======================\n";

mysqli_close($db);
?>