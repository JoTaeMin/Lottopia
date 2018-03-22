<?php
require_once(__DIR__ . "/connect.php");
require_once(__DIR__ . "/report_class.php");

/*
 * 1. 리포트 생성하기
 */
$yoil = date("w");
if($yoil == 4 || $yoil == 0 ) { // powerball 
	$game = 'pb';
} else if($yoil == 3 || $yoil == 6 ) {  // mega 
	$game = 'mm';
} else {
	exit;
}

$report = new report_class(array('db' => connect_db(), 'game' => $game));

// 리포트 생성여부 체크
$rdata = $report->report_exist();

$return = true;
if(!isset($rdata)) {

	$text = array(
		'drawDt' => $report->thisdraw,
		'wb1' => $report->lastGame['whiteBall1'],
		'wb2' => $report->lastGame['whiteBall2'],
		'wb3' => $report->lastGame['whiteBall3'],
		'wb4' => $report->lastGame['whiteBall4'],
		'wb5' => $report->lastGame['whiteBall5'],
		'pb' => $report->lastGame['powerBall'],
		'pp' => $report->lastGame['powerPlay']
	);
	$return = $report->report_insert($text);
	unset($text);

	$rdata = $report->report_exist();
} 
$report->idx = $rdata['idx'];

if($return === false) {
	echo "리포트 생성 실패\n";
	exit;

} else {
	echo $rdata['drawDt']."REPORT \n";
}

/* 1. number frequency : 빈도( 당첨횟수 : 이전 게임에서 번호 당첨 횟수) */
if($rdata['frequency'] == "") {
	list($frequency, $cv, $grade, $statistic, $statistic2, $ment) = $report->funcFrequency();
		
	$text = array(
		'frequency' => $frequency,
		'cv1' => $cv['wb'],
		'cv2' => $cv['pb'],
		'grade1' => $grade['wb'],
		'grade2' => $grade['pb'],
		'statistic1' => $statistic['wb'],
		'statistic2' => $statistic['pb'],
		'hotcold1' => $statistic2['wb'],
		'hotcold2' => $statistic2['pb'],
		'ment' => $ment		
	);
	unset($frequency);
	unset($cv);
	unset($grade);
	unset($statistic);
	unset($ment);
	unset($statistic2);

	$report->report_update($rdata['idx'], 'frequency', $text);
}

/* 2. odd / even : 홀수(odd), 짝수(even) */
if($rdata['oddeven'] == "") {
	
	list($odd, $even, $graph, $graph2, $appear, $grade, $ment) = $report->funcOddEven();

	$text = array(
		'odd' => $odd,
		'even' => $even,
		'appear' => $appear,
		'grade' => $grade,
		'graph' => $graph,
		'graph2' => $graph2,
		'ment' => $ment
	);

	unset($odd);
	unset($even);
	unset($graph);
	unset($graph2);
	unset($appear); 
	unset($grade); 
	unset($ment);

	$report->report_update($rdata['idx'], 'oddeven', $text);
}

/* 3. high / low */
if($rdata['highlow'] == "") {
	
	list($high, $low, $graph, $graph2, $appear, $grade, $ment) = $report->funcHighLow();

	$text = array(
		'high' => $high,
		'low' => $low,
		'appear' => $appear,
		'grade' => $grade,
		'graph' => $graph,
		'graph2' => $graph2,
		'ment' => $ment
	);	
	unset($high);
	unset($low);
	unset($graph);
	unset($graph2);
	unset($appear);
	unset($grade);
	unset($ment);

	$report->report_update($rdata['idx'], 'highlow', $text);
}


/* 4. streak : 지난회차에 겹치는 번호 */
if($rdata['streak'] == "") {
	
	list($appear1, $appear2, $grade1, $grade2, $list, $ment) = $report->funcStreak();

	$text=array(
		'appear1'	=> $appear1,
		'appear2' => $appear2,
		'grade1' => $grade1,
		'grade2' => $grade2,
		'list' => $list,
		'ment' => $ment
	);

	$n = count($appear1);
	$m = count($appear2);
	if ($n > 0 && $m > 0) {
		$cnt = 1;
	} else if($n > 0 && $m == 0) {
		$cnt = 2;
	} else if($n == 0 && $m > 0) {
		$cnt = 3;
	} else {
		$cnt = 0;
	}

	unset($appear1);
	unset($appear2);
	unset($grade1);
	unset($grade2);
	unset($list);
	unset($ment);

	$report->report_update($rdata['idx'], 'streak', $text, $cnt);
}

/* 5. plus minus : 직전회차에 출현한 번호중에 이웃한 번호들이 있는경우 */
if($rdata['plusminus'] == "") {
	
	list($appear1, $appear2, $grade1, $grade2, $list, $ment) = $report->funcPlusMinus();

	$text= array(
		'appear1'	=> $appear1,
		'appear2' => $appear2,
		'grade1' => $grade1,
		'grade2' => $grade2,
		'list' => $list,
		'ment' => $ment
	);
	
	$n = count($appear1);
	$m = count($appear2);
	if ($n > 0 && $m > 0) {
		$cnt = 1;
	} else if($n > 0 && $m == 0) {
		$cnt = 2;
	} else if($n == 0 && $m > 0) {
		$cnt = 3;
	} else {
		$cnt = 0;
	}

	unset($appear1);
	unset($appear2);
	unset($grade1);
	unset($grade2);
	unset($list);
	unset($ment);

	$report->report_update($rdata['idx'], 'plusminus', $text, $cnt);
}

/* 6. last digit : 2자리번호일경우 1의자리 숫자 (power ball 제외) */
if($rdata['lastdigit'] == "") {	
	list($list, $last, $ment) = $report->funcLastDigit();
	for($i=0; $i<=9; $i++) {
		$last[30][$i] = isset($last[30][$i]) ? $last[30][$i] : "";
		$last[50][$i] = isset($last[50][$i]) ? $last[50][$i] : "";
	}

	$text= array(
		'list'	=> $list,
		'last' => $last,
		'ment' => $ment
	);
	unset($list);
	unset($last);
	unset($ment);

	$report->report_update($rdata['idx'], 'lastdigit', $text);
}

/* 7. skip : 몇회만에 나왔는지  */
if($rdata['skip'] == "") {
	
	list($skip, $average, $grade, $ment) = $report->funcSkip();

	$_skip = array(
		$skip['wb'][$report->lastNum[0]],
		$skip['wb'][$report->lastNum[1]],
		$skip['wb'][$report->lastNum[2]],
		$skip['wb'][$report->lastNum[3]],
		$skip['wb'][$report->lastNum[4]],
		$skip['pb'][$report->lastNum[5]]
	);

	// 이번에 나온 숫자는 0으로 변경
	for($i=0; $i<=4; $i++) {
		$skip['wb'][$report->lastNum[$i]] = 0;
	}
	$skip['pb'][$report->lastNum[5]] = 0;
	
	$text= array(
		'skip' => $_skip,
		'average1' => $average['wb'],
		'average2' => $average['pb'],
		'grade1' => $grade['wb'],
		'grade2' => $grade['pb'],
		'list' => $skip,
		'ment' => $ment
	);
	unset($_skip);
	unset($skip);
	unset($average);
	unset($grade);
	unset($ment);

	$report->report_update($rdata['idx'], 'skip', $text);
}

/* 8. gap : 번호사이 차 */
if($rdata['gap'] == "") {	
	list($appear, $average, $ment) = $report->funcGap();

	$text= array(
		'appear' => $appear,
		'average' => $average,
		'ment' => $ment
	);
	unset($appear);
	unset($average);
	unset($ment);

	$report->report_update($rdata['idx'], 'gap', $text);
}

/* 9. 10-interval : 10단위로 그룹핑 */
if($rdata['interval10'] == "") {	
	
	list($list, $ment) = $report->funcInterval();
	$text= array(
		'list' => $list,
		'ment' => $ment
	);	
	
	$cnt = count($list[0]['interval']);	// 나타난 구간 갯수

	unset($list);
	unset($ment);

	$report->report_update($rdata['idx'], 'interval10', $text, $cnt);
}

/* 10. neighbor : 서로 연속해 있는 두개 이상의 번호가 함께 출현하는 것 */
if($rdata['neighbor'] == "") {		

	list($list, $ment) = $report->funcNeighbor();

	foreach($list as $key => $row) {
		$neighbor = array();
		foreach($row['neighbor'] as $srow) {
			$neighbor = array_merge($neighbor, $srow);
		}
		$list[$key]['neighbornum'] = $neighbor;
	}

	$text= array(
		'list'	=> $list,
		'ment' => $ment
	);
	$cnt = $list[0]['neighbor_type'];
	unset($list);
	unset($ment);

	$report->report_update($rdata['idx'], 'neighbor', $text, $cnt);
}

echo "==============================\n";