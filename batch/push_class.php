<?php
class push_class
{
	public $db;
	public $sdate = "";
	public $edate = "";
	public $drawDt = "";
	public $google_server_key = GOOGLE_SERVER_KEY;

	function __construct($db)
    {
		$this->db = $db;
		$this->sdate = date("Y-m-d 13:00:00", time() - 86400);	// 하루전 1시부터
		$this->edate = date("Y-m-d 12:59:59");							// 오늘 오후 1시전까지
		$this->drawDt = date("Y-m-d", time() - 86400);				// 하루전
	}

	/* 공지사항 */
	function notice()
	{
		$qry = "SELECT idx, subject FROM BBS WHERE bid = 'notice' AND showYn = 'Y' AND delYn = 'N' AND (regDt BETWEEN '{$this->sdate}' AND '{$this->edate}')";
		$query = $this->db->query($qry);
		while($row = $query->fetch_assoc()) {		
			echo "notice : ". $row['idx']."\n";
			$this->target_insert(15, 'NOTICE', $row['subject'], '/community/notice_view/'.$row['idx']);	
		}		
	}

	/* 당첨번호 알림 */
	function game_result()
	{
		$qry = "SELECT game, drawDt FROM NUMBER_HISTORY WHERE used = 'Y' AND drawDt = '{$this->drawDt}'";
		$query = $this->db->query($qry);
		while($row = $query->fetch_assoc()) {
			echo "game result : ". $row['drawDt']."\n";
			$game = ($row['game'] == 'pb') ? 'Power Ball' : 'Mega Millions';
			$subject = "Check the {$game} winning numbers ". date("m / d / Y", strtotime($row['drawDt']));

			$this->target_insert(17, 'DRAWING RESULT', $subject, '/utility/winning_numbers/'.$row['game']);
		}
		
	}

	/* 나의 번호 확인 알림 */
	function my_game_result()
	{
		$qry = "SELECT game, drawDt FROM NUMBER_HISTORY WHERE used = 'Y' AND drawDt = '{$this->drawDt}'";
		$query = $this->db->query($qry);
		while($row = $query->fetch_assoc()) {
			echo "my game result : ". $row['drawDt']."\n";
			$game = ($row['game'] == 'pb') ? 'Power Ball' : 'Mega Millions';
			$subject = "Check the My {$game} winning numbers ". date("m / d / Y", strtotime($row['drawDt']));

			$this->target_insert(18, 'MY WINNINGS', $subject, '/mypage/tickets/'.$row['game'], $row['game'], $row['drawDt']);
		}
	}

	/* 리포트 */
	function report()
	{
		$qry = "SELECT idx, game, subject FROM REPORT WHERE showYn = 'Y' AND delYn = 'N' AND drawDt = '{$this->drawDt}'";
		$query = $this->db->query($qry);
		while($row = $query->fetch_assoc()) {		
			echo "report : ". $this->drawDt . "\n";
			$this->target_insert(19, 'ANALYTICS REPORT', $row['subject'], '/report/lists/'.$row['game']);	
		}
	}

	/* 당첨번호 나온 다음날 로또픽 번호 2개씩 회원에게 지급해주기 */
	function lottopick_issued()
	{		
		$qry = "SELECT game, drawDt FROM NUMBER_HISTORY WHERE used = 'Y' AND drawDt = '{$this->drawDt}' LIMIT 1";
		$query = $this->db->query($qry);
		$row = $query->fetch_assoc();
		if(!count($row)) return;

		$game = $row['game'];
		$drawDt = nextGame($game);	
		$table = $row['game'] == 'pb' ? 'LOTTOPIC' : 'LOTTOPIC_MM';

		$query = $this->db->query("SELECT COUNT(1) cnt FROM MEMBER WHERE del = 'N' AND IFNULL(lottopicNum, 'N') = 'Y'");
		$row = $query->fetch_assoc();
		$total = $row['cnt'];	// 전체대상 명수
		$limit = 100;
		$total_page = ceil($total / $limit);

		echo "lottopick : ". $total . "\n";

		$idx = 0;
		for($page = 1; $page <= $total_page; $page++) {

			$qry = "SELECT idx, whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5, powerBall FROM {$table} ORDER BY cnt ASC LIMIT ".$limit*2;
			$query2 = $this->db->query($qry);
			$data = array();
			while($row2 = $query2->fetch_assoc()) $data[] = $row2;

			$i = 0;
			$arr = array();
			$where = "AND idx > $idx";
			$query3 = $this->db->query("SELECT idx FROM MEMBER WHERE del = 'N' AND IFNULL(lottopicNum, 'N') = 'Y' {$where} ORDER BY idx ASC LIMIT $limit");
			$qry = "";
			while($row3 = $query3->fetch_assoc()) {				
				if($qry == "") $qry = "INSERT INTO USER_LOTTOPIC (member_idx, game, drawDt, whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5, powerBall, regDt, path) VALUES "; 
				for($j=0;$j<=1;$j++) {
					$prow = $data[$i+$j];
					$qry .= "(".$row3['idx'].", '{$game}', '{$drawDt}', ".$prow['whiteBall1'].", ".$prow['whiteBall2'].", ".$prow['whiteBall3'].", ".$prow['whiteBall4'].", ".$prow['whiteBall5'].", ".$prow['powerBall'].", now(), 'M'),";

					$arr[] = $prow['idx'];
				}

				$i = $i+2;
			}	
			
			if($qry != "") {
				$qry = substr($qry, 0, -1);
				$this->db->query($qry);	// 로또픽 발급
				$this->db->query("UPDATE {$table} SET cnt = cnt+1 WHERE idx in (".join(",", $arr).")");	// 로또픽번호 발급횟수 추가
			}			
		}

		$this->target_insert(16, 'LOTTOPICK NUMBER', "Check the gift (analysis number) provided by LOTTOPIA.", '/mypage/lottopia_number/'.$game, $game, $drawDt);
	}




	/* 
	타켓 리스트 insert 
	1. 공지사항(15)				: 전체회원에 메세지, Accept promotional information 체크한회원에게 푸쉬
	2. 당첨번호(17)				: 전체회원에 메세지, Get the latest winning result 체크한회원에게 푸쉬
	3. 나의 번호 확인(18)		: 티켓등록한회원에게 메세지, Notify the result of my number 체크한회원에게만 푸쉬
	4. 리포트(19)				: 리포트대상에게 메세지, Accept promotional information 체크한 회원에게만 푸쉬
	*/
	function target_insert($type, $title, $message, $link="", $game = "", $drawDt = "") 
	{
		$qry = "INSERT INTO PUSH (type, target, users, title, message, link, writer, regDt) VALUES ($type, 0, 0, '$title', '$message', '$link', 'admin', now()) ";
		$query = $this->db->query($qry);
		if(!$query) return false;

		$idx = $this->db->insert_id;
		
		$qry = "INSERT INTO PUSH_USER (pushIdx, member_idx, token, regDt) ";
		if($type == 15) { // 공지사항
			$qry .= "
			SELECT $idx as idx, M.idx member_idx, CASE WHEN IFNULL(M.promotion,'N') = 'Y' THEN T.token ELSE NULL END token, now() FROM MEMBER M 
			LEFT JOIN MEMBER_TOKENS T ON M.idx = T.member_idx 
			WHERE M.del = 'N'
			";
		} else if($type == 17) {	// 당첨번호 확인
			$qry .= "
			SELECT $idx as idx, M.idx member_idx, CASE WHEN IFNULL(M.winResult,'N') = 'Y' THEN T.token ELSE NULL END token, now() FROM MEMBER M 
			LEFT JOIN MEMBER_TOKENS T ON M.idx = T.member_idx 
			WHERE M.del = 'N'
			";
		} else if($type == 18) { // 나의 번호 확인

			$qry .= "
			SELECT $idx as idx, M.idx member_idx, CASE WHEN IFNULL(M.myNum,'N') = 'Y' THEN T.token ELSE NULL END token, now() 
			FROM (SELECT DISTINCT(member_idx) member_idx FROM USER_GAME WHERE game='$game' AND drawDt = '$drawDt') G 
			JOIN MEMBER M ON G.member_idx = M.idx 
			LEFT JOIN MEMBER_TOKENS T ON M.idx = T.member_idx 
			WHERE M.del = 'N';
			";
		} else if($type == 19) {	// 리포트
			$dd = date("Y-m-d");
			$qry .= "
			SELECT $idx as idx, M.idx member_idx, CASE WHEN IFNULL(M.promotion,'N') = 'Y' THEN T.token ELSE NULL END token, now() FROM MEMBER M 
			LEFT JOIN MEMBER_TOKENS T ON M.idx = T.member_idx 
			WHERE M.del = 'N' AND IFNULL(M.reportEt, '0000-00-00') >= '$dd'
			";	
		} else if($type == 16) {	// 로또픽번호 발급
			$qry .= "
			SELECT $idx as idx, L.member_idx, CASE WHEN IFNULL(M.promotion,'N') = 'Y' THEN T.token ELSE NULL END token, now() FROM (
				SELECT distinct(member_idx) FROM USER_LOTTOPIC WHERE game = '$game' AND drawDt = '$drawDt' AND path = 'M'
			) L JOIN MEMBER M ON L.member_idx = M.idx 
			JOIN MEMBER_TOKENS T ON L.member_idx = T.member_idx 
			";

		} else {
			return false;
		}

		if($this->db->query($qry)) {

			echo $type." true \n";

			$qry = "UPDATE PUSH P SET users = (SELECT COUNT(1) cnt FROM PUSH_USER WHERE pushIdx = P.idx) WHERE idx = '$idx'";
			$this->db->query($qry);

			$this->set_push($idx);

			return $idx;
		} else {

			echo $type." false \n";
			return false;
		}	
	}

	/* 푸쉬보내기 */
	function set_push($idx) 
	{
		$qry = "SELECT title, message FROM PUSH WHERE idx = $idx AND status = 'READY'";
		$query = $this->db->query($qry);
		$data = $query->fetch_assoc();
		if(!is_array($data)) return;

		// push 대상 명수
		$qry = "SELECT COUNT(1) cnt FROM PUSH_USER WHERE pushIdx = $idx AND IFNULL(token, '') != ''";
		$query = $this->db->query($qry);
		$sdata = $query->fetch_assoc();
		$total_cnt = $sdata['cnt'];
		$page = 1;
		$limit = 100;
		$total_page = ceil($total_cnt / $limit);

		for($page; $page <= $total_page; $page++) {
			$offset = ($page - 1) * $limit;
			$qry = "SELECT token FROM PUSH_USER WHERE pushIdx = $idx AND IFNULL(token, '') != '' ORDER BY idx ASC LIMIT $offset, $limit";
			$query = $this->db->query($qry);
			$tokens = array();
			while($row = $query->fetch_assoc()) {
				$tokens[] = $row['token'];
			}

			$fields = array (
				'data' => array ("message" => $data['message']),
				'notification' => array ("body" => $data['message'], "title" => $data['title']),
				'priority' => 'high',
				'registration_ids' => $tokens
			);
			$to = json_encode($fields);
			$qry = "INSERT INTO push_transfer (push_id, push_key, payload, created_at, send_at) VALUES ($idx, '{$this->google_server_key}', '$to', now(), now())";
			$this->db->query($qry);

			if($page == $total_page) $this->db->query("UPDATE PUSH SET status='DONE' WHERE idx = $idx");
			else if($page == 1) $this->db->query("UPDATE PUSH SET status='ING' WHERE idx = $idx");
		}	
	}
}