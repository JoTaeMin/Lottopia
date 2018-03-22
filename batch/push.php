<?php
require_once(__DIR__ . "/connect.php");
require_once(__DIR__ . "/push_class.php");

$db = connect_db();


$push = new push_class($db);

echo date("Y-m-d H:i:s"). " 실행 \n";

// 공지사항 알림(15) : 전체회원에 메세지, Accept promotional information 체크한회원에게 푸쉬
$push->notice();

// 당첨번호 알림(17) : 전체회원에 메세지, Get the latest winning result 체크한회원에게 푸쉬
$push->game_result();

// 나의 번호 확인(18) : 티켓등록한 회원에게 메세지, Notify the result of my number 체크한회원에게만 푸쉬
$push->my_game_result();

// 리포트(19) : 리포트대상에게 메세지, Accept promotional information 체크한 회원에게만 푸쉬
$push->report();

// 무료 로또픽번호 발급 : Get a lottopick number 체크한 회원에게만 무료로 로또픽번호 2개씩 지급해준다. / 결과발표 다음날 새로운 로또픽 번호가 생성되고 난후에 번호 지급
$push->lottopick_issued();

echo "==============================\n";

mysqli_close($db);
?>