<?php
require_once(__DIR__ . "/connect.php");

/*
 * 1. 당첨번호 가져오기
 * 2. 고객번호 당첨금액 update
 * 3. 로또픽번호 당첨 여부 update
 */

$db = connect_db();

echo date("Y-m-d H:i:s"). " 실행 \n";

// 파워볼 : 수(3), 토(6) 오후 11시, 메가밀리언 : 화(2), 금(5) 오후 11시
// 오후 11시에 당첨발표, 새벽12시에 배치돌림.
$yoil = date("w");
if($yoil == 4 || $yoil == 0 ) { // powerball
    $game = 'pb';
    $result = get_number_history_pb();

} else if($yoil == 3 || $yoil == 6 ) {  // mega
    $game = 'mm';
    $result = get_number_history_mm();
}

if(isset($result) && is_array($result)) {
    echo "save_number_history : true";

	$game = $result[0];
	$drawDt = $result[1];

	// 당첨번호 
	$qry = "SELECT * FROM NUMBER_HISTORY WHERE game = '{$game}' AND drawDt='{$drawDt}'";
    $query = $db->query($qry);
    $data = $query->fetch_assoc();
	if(is_array($data)) {

		// 고객번호 당첨금액 update
		update_user_game($data);

		// 로또픽 번호 당첨여부 update
		update_user_lottopick($data);
	}
} else {
    echo "save_number_history : false";
}

echo "\n=========================\n";

/* 파워볼 당첨번호 가져오기 */
function get_number_history_pb()
{
	$data = file_get_contents('https://www.powerball.com/api/v1/numbers/powerball/recent');
	$data = json_decode($data);
	if(!isset($data[0])) exit;

	$drawDt = trim($data[0]->field_draw_date);
	$number = $data[0]->field_winning_numbers;

	$whiteBall = explode(",", str_replace(" ", "", $number));
	$powerBall = $whiteBall[5];
	unset($whiteBall[5]);
	$powerPlay = trim($data[0]->field_multiplier);

    return save_number_history('pb', $drawDt, $whiteBall, $powerBall, $powerPlay, 0, 0);
}

/* 메가밀리언 당첨번호 가져오기 */
function get_number_history_mm()
{
    $text = file_get_contents('http://www.megamillions.com/Media/Static/winning-numbers/winning-numbers.json');
    $text = json_decode($text);
    $data = $text->numbersList[0];

    $drawDt = substr(trim($data->DrawDate),0,10);
    $whiteBall[] = (int)trim($data->WhiteBall1);
    $whiteBall[] = (int)trim($data->WhiteBall2);
    $whiteBall[] = (int)trim($data->WhiteBall3);
    $whiteBall[] = (int)trim($data->WhiteBall4);
    $whiteBall[] = (int)trim($data->WhiteBall5);
    $powerBall = (int)trim($data->MegaBall);
    $powerPlay = (int)trim($data->Megaplier);
    unset($data);

    $data = $text->nextDraw;
    $prize = trim($data->JackpotAnnuityAmount) == "" ? 0 : trim($data->JackpotAnnuityAmount);	// 당첨금액
    $cashvalue = trim($data->JackpotCashAmount) == "" ? 0 : trim($data->JackpotCashAmount);	// 당첨금액 cash value
    unset($data);

    return save_number_history('mm', $drawDt, $whiteBall, $powerBall, $powerPlay, $prize, $cashvalue);
}

/* 당첨 번호 DB 저장하기 */
function save_number_history($game, $drawDt, $whiteBall, $powerBall, $powerPlay, $prize, $cashvalue) 
{
	global $db;

    asort($whiteBall);
    $wb = array();
    foreach($whiteBall as $v) {
        $wb[] = $v;
    }

    // 저장된 번호인지 체크하기
    $qry = "SELECT COUNT(1) cnt FROM NUMBER_HISTORY WHERE game='$game' AND drawDt='$drawDt'";
    $query = $db->query($qry);
    $data = $query->fetch_array();
    if($data['cnt'] == 0) {

		// current_status 에서 1등 당첨 예상금액 가져와서 넣기
		if($prize == 0 || $cashvalue == 0) {
			$qry2 = "SELECT jackpot, cashvalue FROM CURRENT_STATUS WHERE game = '{$game}' AND drawDt = '{$drawDt}'";
			$query2 = $db->query($qry2);
			$data2 = $query2->fetch_array();

			if(isset($data2['jackpot']) && $prize == 0) {
				$prize = $data2['jackpot'];
			}
			if(isset($data2['cashvalue']) && $cashvalue == 0) {
				$cashvalue = $data2['cashvalue'];
			}
			unset($data2);
		}

        $qry = "
        INSERT INTO NUMBER_HISTORY (game, drawDt, whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5, powerBall, powerPlay, winningPrize, cashValue) VALUES 
        ('$game', '$drawDt', '$wb[0]', '$wb[1]', '$wb[2]', '$wb[3]', '$wb[4]', $powerBall, $powerPlay, $prize, $cashvalue)
        ";
        $result = $db->query($qry);

        if($result) {
            return array($game, $drawDt);    // 저장 ok

        } else {
            return false;   // 저장 실패
        }

    } else {
        return array($game, $drawDt);    // 이미 등록된 경우
    }
}


/* 당첨 금액 update */
function update_user_game($data)
{
    global $db;

    $game = $data['game'];
	$drawDt = $data['drawDt'];

    $qry = "SELECT * FROM USER_GAME WHERE game='{$game}' AND drawDt='{$drawDt}' ORDER BY idx ASC";
    $query = $db->query($qry);
    while ($row = $query->fetch_assoc()) {
        $pdata = calcPrize($data, $row);
        if($pdata['rank'] == 0) continue;	// 당첨안됨.
        $db->query("UPDATE USER_GAME SET rank='".$pdata['rank']."', prize='".$pdata['prize']."' WHERE idx='".$row["idx"]."'");
    }

	echo "\n success update_user_game";
}

/* 로또픽번호 update */
function update_user_lottopick($data) 
{
	global $db;

	$game = $data['game'];
	$drawDt = $data['drawDt'];

	$qry = "SELECT * FROM USER_LOTTOPIC WHERE game='$game' AND drawDt='$drawDt' ORDER BY idx ASC";
	$query = $db->query($qry);
    while ($row = $query->fetch_assoc()) {
        $pdata = calcPrize($data, $row);
        if($pdata['rank'] == 0) continue;	// 당첨안됨.
        $db->query("UPDATE USER_LOTTOPIC SET rank='".$pdata['rank']."', prize='".$pdata['prize']."' WHERE idx='".$row["idx"]."'");
    }

	$qry = "SELECT SUM(prize) total_prize FROM USER_LOTTOPIC WHERE game='$game' AND drawDt='$drawDt'";
	$query = $db->query($qry);
	$total_data = $query->fetch_assoc();
	$total_prize = isset($total_data['total_prize']) ? $total_data['total_prize'] : 0;

	if($total_prize > 0) {
		$qry = "INSERT INTO LOTTOPICK_TOTAL_WINNINGS (game, drawDt, prize) VALUES ('{$game}', '{$drawDt}', '{$total_prize}') ON DUPLICATE KEY UPDATE prize = '{$total_prize}'";
		$db->query($qry);
	}

	echo "\n success update_user_lottopick";
}

/* 당첨금 계산 : 수정시 사용자/관리자에서 쓰고있는 함수도 같이 수정해야함. */
function calcPrize($game_data, $data)
{
    $game_prize = array(
        'pb'	=> array(
            2 => 1000000,
            3 => 50000,
            4 => 100,
            5 => 100,
            6 => 7,
            7 => 7,
            8 => 4,
            9 => 4
        ),
        'mm' => array(
            2 => 1000000,
            3 => 10000,
            4 => 500,
            5 => 200,
            6 => 10,
            7 => 10,
            8 => 4,
            9 => 2
        )
    );

    if(count($game_data) > 0) {
        $cnt = 0;
        $game = $game_data['game'];
        $whiteBall = array($game_data['whiteBall1'], $game_data['whiteBall2'], $game_data['whiteBall3'], $game_data['whiteBall4'], $game_data['whiteBall5']);
        $powerBall = $game_data['powerBall'];

        for($i =1; $i<=5; $i++) {
            if(in_array($data['whiteBall'.$i], $whiteBall)) $cnt++;
        }

        // 랭킹
        if($cnt == 5 && $powerBall == $data['powerBall']) {
            $rank = 1;
        } else if($cnt == 5) {
            $rank = 2;
        } else if($cnt == 4 && $powerBall == $data['powerBall']) {
            $rank = 3;
        } else if($cnt == 4) {
            $rank = 4;
        } else if($cnt == 3 && $powerBall == $data['powerBall']) {
            $rank = 5;
        } else if($cnt == 3) {
            $rank = 6;
        } else if($cnt == 2 && $powerBall == $data['powerBall']) {
            $rank = 7;
        } else if($cnt == 1 && $powerBall == $data['powerBall']) {
            $rank = 8;
        } else if($powerBall == $data['powerBall']) {
            $rank = 9;
        } else {
            $rank = 0;
        }

        // 상금
        if($rank == 0) {
            $prize = 0;	// 탈락
        } else if($rank == 1) {
            $prize = $game_data['cashValue'];	// jackpot
        } else {

            $prize = $game_prize[$game][$rank];

            if($data['powerPlay'] == 'Y') {	// power play
                if($game_data['game'] == 'pb' && $rank == 2) {	// 파워볼에서 2등은 무조건 2배임.
                    $prize = $prize * 2;
                } else if($game_data['powerPlay'] > 0) {
                    $prize = $prize * $game_data['powerPlay'];
                }
            }
        }

        return array('rank' => $rank, 'prize' => $prize);

    } else {
        return array('rank' => 0, 'prize' => 0);		// 추첨전
    }
}

mysqli_close($db);
?>