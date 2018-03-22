<?php
require_once(__DIR__ . "/connect.php");

/*
 * 당첨자수 insert
 */
$db = connect_db();

echo date("Y-m-d H:i:s"). " 실행 \n";

$yoil = date("w");
if($yoil == 4 || $yoil == 0 ) { // powerball
	$game = 'pb';
} else if($yoil == 3 || $yoil == 6 ) {  // mega
	$game = 'mm';
} else {
	exit;
}

/* last game winner data */
if($game == 'mm') {
	$url = 'https://muslapi.musl.com/GameService.svc/GetWinners?gamename=megamillions';	
} else {
	$url = 'https://muslapi.musl.com/GameService.svc/GetWinners?gamename=powerball';	
}
$data = file_get_contents($url);
$data = json_decode($data);

$data = $data->GetWinnersResult;
$drawDt = $data->DrawDate;
$winners = $data->Winners;

$win1 = trim($winners[0]->Value);
$win2 = trim($winners[1]->Value) + trim($winners[9]->Value);	// powerplay no + powerplay yes
$win3 = trim($winners[2]->Value) + trim($winners[10]->Value);
$win4 = trim($winners[3]->Value) + trim($winners[11]->Value);
$win5 = trim($winners[4]->Value) + trim($winners[12]->Value);
$win6 = trim($winners[5]->Value) + trim($winners[13]->Value);
$win7 = trim($winners[6]->Value) + trim($winners[14]->Value);
$win8 = trim($winners[7]->Value) + trim($winners[15]->Value);
$win9 = trim($winners[8]->Value) + trim($winners[16]->Value);


echo "game : ".$game."\n";
echo "drawDt : ".$drawDt."\n";
$query = $db->query("SELECT COUNT(1) cnt FROM GAME_WINNERS WHERE game = '{$game}' AND drawDt = '{$drawDt}'");
$data = $query->fetch_assoc();
if($data['cnt'] == 0) {
	$qry = "INSERT INTO GAME_WINNERS (game, drawDt, win1, win2, win3, win4, win5, win6, win7, win8, win9) VALUES ('{$game}', '{$drawDt}', {$win1}, {$win2}, {$win3}, {$win4}, {$win5}, {$win6}, {$win7}, {$win8}, {$win9})";
	$db->query($qry);	
}


/* next jackpot amount update*/
if($game == 'mm') {
	$text = file_get_contents('http://www.megamillions.com/Media/Static/winning-numbers/winning-numbers.json');
    $text = json_decode($text);
	$data = $text->nextDraw;

	$nextDrawDt = substr(trim($data->NextDrawDate),0,10);
	$nextDrawDt = date("Y-m-d", strtotime($nextDrawDt) - 86400);	// 다음 게임일자
	$est_jackpot = $data->NextJackpotAnnuityAmount;			// 다음 예상 당첨금액
	$est_cashvalue = $data->NextJackpotCashAmount;				// 다음 예상 당첨 cashvalue

	$est_jackpot = intval($est_jackpot);
	$est_cashvalue = intval($est_cashvalue);
	
} else {

	$data = file_get_contents('https://www.powerball.com/api/v1/estimates/powerball');
	$data = json_decode($data);
	if(!isset($data[0])) exit;
	$data = $data[0];

	$nextDrawDt = nextGame('pb');
	//$nextDrawDt = substr(trim($data->field_next_draw_date),0, 10);	// 게임날짜가 아니라 추천데이터가 등록되는 시간을 나타내는것 같음.
	$est_jackpot = str_replace('$', '', str_replace('Million', '', trim($data->field_prize_amount)));
	$est_cashvalue = str_replace('$', '', str_replace('Million', '', trim($data->field_prize_amount_cash)));

	$est_jackpot = intval($est_jackpot);
	$est_cashvalue = intval($est_cashvalue);

	$est_jackpot = $est_jackpot * 1000000;
	$est_cashvalue = $est_cashvalue * 1000000;
}

echo "nextDrawDt : ". $nextDrawDt ."\n";
echo "est_jackpot : ". $est_jackpot ."\n";
echo "est_cashvalue : ". $est_cashvalue ."\n";

$query = $db->query("SELECT COUNT(1) cnt FROM CURRENT_STATUS WHERE game = '{$game}' AND drawDt = '{$nextDrawDt}'");
$data = $query->fetch_assoc();
if($data['cnt'] == 0) {
	$db->query("INSERT INTO CURRENT_STATUS (game, drawDt, jackpot, cashvalue) VALUES ('{$game}','{$nextDrawDt}','{$est_jackpot}','{$est_cashvalue}')");
}

echo "===========================\n";

mysqli_close($db);
?>