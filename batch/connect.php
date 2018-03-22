<?php

//date_default_timezone_set('America/New_York');

define('GOOGLE_SERVER_KEY',     'AAAAMcEqu8w:APA91bG0_RGXJ1r8iKZpN1tk0XQP-1XkBIHj3A13UCt8yV1PqkA_KCLUcEJ3BJRLJ4JkwE4pNDN_xAZK78kB_GzWjRiQ30aWslAtnBDTQcGces8ShWQ6a855d3fBFINnOELbOzutI1Dr');

function connect_db() {
    $link = mysqli_connect("10.184.128.132", "lottopia", "fhEh!@#$5678", "lottopia");
    return $link;
}

/* 다음게임 날짜 */
function nextGame($game, $format='Y-m-d') {

    // 추첨시간이 수,토 오후11시, 추첨마감시간이 오후 2시(미국 동부시간기준)
    // 한국시간 기준으로 목,일 오후1시, 추첨마감시간 오전 4시
	$today = date('w');
	$g = date('G'); // 11시 이전에는 next drawing 이 오늘 11시 넘으면 다음날짜
    $t = 23;

	if($game == 'pb') {	// wed 3, sat 6

		if($today <= 3) { 
			if($today == 3 && $g >= $t) $g = 3;
			else $g = 3-$today;			
		} else {
			if($today == 6 && $g >= $t)  $g = 4;
			else $g = 6-$today;
		}

	} else if($game == 'mm') {	// tue 2, fri 5

		if($today <= 2) {
			if($today == 2 && $g >= $t) $g = 3;
			else $g = 2- $today;
		} else if($today == 6) {
			$g = 3;
		} else {
			if($today == 5 && $g >= $t) $g = 2;
			else $g = 5- $today;
		}
		
	}
	$nextdt = strtotime("+".$g." day");
	return strtoupper(date($format, $nextdt));
}
