<?php
class report_class
{
    public $db;
	public $idx = 0;			// 현재작성중인 리포트 인덱스
    public $game = 'pb';
	public $gameName = 'PowerBall';
	public $ballName = 'PowerBall';
	public $where = '';
	public $where2 = '';

    public $totalBall;          // 게임별 마지막 번호
	public $powerBall;		// 게임별 마지막 파워볼 
    public $total;				// 전체게임횟수
	public $thisdraw = "";	// 마지막게임 날짜
	
    public $lastGame = array(); // 마지막게임
    public $prevGame = array(); // 직전게임

    public $lastNum = array();
    public $prevNum = array();

    public $last10Game = array();   // 마지막 10 게임

	public $neighbor_type = array('None', 'Neighbor', '3-Neighbor', '4-Neighbor', '5-Neighbor', 'Double', 'Double 3-Neighbor');
	public $pattern_rate = array(0 => 0.0247, 1 => 0.1444, 2 => 0.3168, 3=>0.3267, 4=>0.1584, 5 => 0.0288);	// 출현패턴의 출현확률
	public $digitarr = array("", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten");
	
    function __construct($param)
    {
        foreach($param as $k => $v) {
            $this->{$k} = $v;
        }

        $this->totalBall = $this->game == 'pb' ? 69 : 70;
		$this->powerBall = $this->game == 'pb' ? 26 : 25;
		$this->gameName = $this->game == 'pb' ? 'PowerBall' : 'MegaMillions';
		$this->ballName = $this->game == 'pb' ? 'PowerBall' : 'MegaBall';

		// mm 게임의 기준이 2017-10-31 변경되어 그이후데이터로만 처리한다.
		if($this->game == 'mm') {
			$this->where2 = " AND drawDt >= '2017-10-31'";
			$this->where .= $this->where2;
		}

        // 마지막 게임날짜 가져오기
        $qry = "SELECT * FROM NUMBER_HISTORY WHERE game = '{$this->game}' {$this->where} ORDER BY drawDt DESC LIMIT 2";
        $query = $this->db->query($qry);

        $i = 0;
        while ($row = $query->fetch_assoc()) {
            if($i == 0) $this->lastGame = $row;
            if($i == 1) $this->prevGame = $row;
            $i++;
        }

        $this->lastNum = array(
            $this->lastGame['whiteBall1'],
            $this->lastGame['whiteBall2'],
            $this->lastGame['whiteBall3'],
            $this->lastGame['whiteBall4'],
            $this->lastGame['whiteBall5'],
            $this->lastGame['powerBall']
        );  // 이번회차번호

        $this->prevNum = array(
            $this->prevGame['whiteBall1'],
            $this->prevGame['whiteBall2'],
            $this->prevGame['whiteBall3'],
            $this->prevGame['whiteBall4'],
            $this->prevGame['whiteBall5'],
            $this->prevGame['powerBall']
        );  // 지난회차번호

		$this->thisdraw = date("m/d/Y", strtotime($this->lastGame['drawDt']));


        // 전체 게임 횟수
        $qry = "SELECT COUNT(1) cnt FROM NUMBER_HISTORY WHERE game='{$this->game}' {$this->where}";
        $query = $this->db->query($qry);
        $data = $query->fetch_assoc();
        $this->total = $data['cnt'];

        // 마지막 10 게임
        $this->last10Game = $this->selectGame();
    }

    /* 리포트 생성여부 체크 */
    function report_exist() 
	{
        $qry = "SELECT * FROM REPORT WHERE game='".$this->lastGame['game']."' AND drawDt = '".$this->lastGame['drawDt']."'";
        $query = $this->db->query($qry);
        return $query->fetch_array();        
    }

	/* 리포트 저장 */
	function report_insert($content) 
	{
		$drawDt = $this->lastGame['drawDt'];
		$subject = date("m / d / Y", strtotime($drawDt))." Full Report";
		$content = "";
		
		$qry = "INSERT INTO REPORT (game, drawDt, subject, content, regDt, writer, hit, showYn) VALUES ('{$this->game}', '{$drawDt}', '{$subject}', '{$content}', now(), 'admin', 0, 'N')";
		return $this->db->query($qry);
	}

	/* 리포트 항목별로 update */
	function report_update($idx, $field, $content, $cnt = -1) 
	{
		$add = $cnt > -1 ? ", {$field}Cnt = $cnt" : "";

		$content = serialize($content);
	
		if($field == "streak") {	// 맨마지막 항목을 update 후에 리포트를 노출로 변경해준다.
			$qry = "UPDATE REPORT SET {$field} = '{$content}' {$add} , showYn='Y' WHERE idx = '{$idx}'";
		} else {
			$qry = "UPDATE REPORT SET {$field} = '{$content}' {$add} WHERE idx = '{$idx}'";
		}
		return $this->db->query($qry);
	}

	/* 게임 결과 리스트 */
	function selectGame($limit = 10) 
	{
        $data = array();
        $query = $this->db->query("SELECT * FROM NUMBER_HISTORY WHERE game = '{$this->game}' {$this->where} ORDER BY drawDt DESC LIMIT 0, $limit");
        while($row = $query->fetch_assoc()) {
            $data[] = $row;
        }

        return $data;
    }

    #===========================================================
    # frequency ( 추첨횟수, 게임별 마지막번호, 현재게임)
    # 이전게임에서 번호별 추첨횟수
    #===========================================================
    function funcFrequency ()
    {
        $qry = "
        SELECT      
          sum({$this->lastNum[0]} IN (whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5)) AS cnt1,
          sum({$this->lastNum[1]} IN (whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5)) AS cnt2,
          sum({$this->lastNum[2]} IN (whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5)) AS cnt3,
          sum({$this->lastNum[3]} IN (whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5)) AS cnt4,
          sum({$this->lastNum[4]} IN (whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5)) AS cnt5,
          sum({$this->lastNum[5]} IN (powerBall)) AS cnt6  
        FROM (
          SELECT * FROM NUMBER_HISTORY WHERE game='{$this->game}' {$this->where}
        ) TBL";
        $query = $this->db->query($qry);
        $data = $query->fetch_assoc();

        $frequency = array($data['cnt1'], $data['cnt2'], $data['cnt3'], $data['cnt4'], $data['cnt5'], $data['cnt6']);

        // combination average
        $cv['wb'] = round(($data['cnt1']+$data['cnt2']+$data['cnt3']+$data['cnt4']+$data['cnt5'])/5, 2);
        $cv['pb'] = $data['cnt6'];

        // expected value = total * 5 / 69
        $ev['wb'] = round($this->total * 5 / $this->totalBall, 2);
        $ev['pb'] = round($this->total * 1 / $this->powerBall, 2);

        // grade = combination average / expected value
        $grade['wb'] = $this->funcGrade('frequency', $cv['wb'], $ev['wb']);
        $grade['pb'] = $this->funcGrade('frequency', $cv['pb'], $ev['pb']);

        // frequency statistic
		$statistic = array();
		for($i=1; $i<=$this->totalBall; $i++ ) {
			$query = $this->db->query("SELECT COUNT(1) cnt FROM NUMBER_HISTORY WHERE game ='{$this->game}' {$this->where} AND {$i} IN (whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5)");
			$row = $query->fetch_assoc();
			$statistic['wb'][$i] = $row['cnt'];
		}
		for($i=1; $i<=$this->powerBall; $i++ ) {
			$query = $this->db->query("SELECT COUNT(1) cnt FROM NUMBER_HISTORY WHERE game ='{$this->game}' {$this->where} AND powerBall = {$i}");
			$row = $query->fetch_assoc();
			$statistic['pb'][$i] = $row['cnt'];
		}

		$_statistic = $statistic;
		
		arsort($_statistic['wb']);
		arsort($_statistic['pb']);

		// wb hot / cold
		$seq = 0;
		$rank = 0;
		$scnt = 0;
		$arr = array();
		foreach($_statistic['wb'] as $num => $cnt) {
			if($scnt != $cnt) $rank = $seq+1;
			$arr[$rank][] = $num;
			$scnt = $cnt;
			$seq++;
		}
		$cnt = 0;
		foreach($arr as $rank => $sarr) {
			if($cnt >= 5) break;
			foreach($sarr as $key => $num) $arr[$rank][$key] = array($num, 'hot');			
			$cnt += count($sarr);			
		}
		$cnt = 0;
		$sarr = end($arr);
		for($i=0; $i<5;$i++) {
			if($cnt >= 5) break;
			$cnt += count($sarr);
			$rank = key($arr);
			foreach($sarr as $key => $num) $arr[$rank][$key] = array($num, 'cold');
			$sarr = prev($arr);
		}
		$statistic2['wb'] = array();
		foreach($arr as $rank => $sarr) {
			foreach($sarr as $num) {
				if(is_array($num)) $statistic2['wb'][] = array($rank, $num[0], $num[1]);
				else $statistic2['wb'][] = array($rank, $num);
			}
		}
	
		// pb hot / cold
		$seq = 0;
		$rank = 0;
		$scnt = 0;
		$arr = array();
		foreach($_statistic['pb'] as $num => $cnt) {
			if($scnt != $cnt) $rank = $seq+1;
			$arr[$rank][] = $num;
			$scnt = $cnt;
			$seq++;
		}
		$cnt = 0;
		foreach($arr as $rank => $sarr) {
			if($cnt >= 5) break;
			foreach($sarr as $key => $num) $arr[$rank][$key] = array($num, 'hot');
			$cnt += count($sarr);
		}
		$cnt = 0;
		$sarr = end($arr);
		for($i=0; $i<5;$i++) {
			if($cnt >= 5) break;
			$cnt += count($sarr);
			$rank = key($arr);
			foreach($sarr as $key => $num) $arr[$rank][$key] = array($num, 'cold');
			$sarr = prev($arr);
		}
		$statistic2['pb'] = array();
		foreach($arr as $rank => $sarr) {
			foreach($sarr as $num) {
				if(is_array($num)) $statistic2['pb'][] = array($rank, $num[0], $num[1]);
				else $statistic2['pb'][] = array($rank, $num);
			}
		}
		
		// ment
		$ment = $this->frequencyMent($frequency, $ev['wb']);

        return array($frequency, $cv, $grade, $statistic, $statistic2, $ment);
    }

    // grade 계산방법
    function funcGrade ($type, $cv, $ev)
    {
        $v = round($cv / $ev,2);

		if($v < 0.8) $t = '1';	// Very Low, Very Short
		else if($v < 0.9) $t = '2'; // Low, Short
		else if($v < 1.1) $t = '3';	// Normal
		else if($v < 1.2) $t = '4'; // High, Long
		else $t = '5'; // Very High,  Very Long

        return $t;
    }

    #===========================================================
    # odd / even ( 홀짝 )
    # return : 홀수, 짝수, 빈도, actual appearance, grade
    #===========================================================
    function funcOddEven()
    {
        $odd = array();     // 홀수
        $even = array();    // 짝수
        foreach($this->lastNum as $k => $v) {
            if($k == 5) break;

            if($v % 2 == 0) $even[] = $v;
            else $odd[] = $v;
        }

		// 전체게임에서 게임별 홀수갯수를 가져온다. 
        $qry = "
        SELECT odd_cnt, COUNT(1) cnt FROM (
            SELECT 
            (mod(whiteBall1,2) + mod(whiteBall2,2) + mod(whiteBall3,2) + mod(whiteBall4,2) + mod(whiteBall5,2)) odd_cnt  
            FROM NUMBER_HISTORY 
            WHERE game = '{$this->game}' {$this->where} 
        ) TBL GROUP BY odd_cnt ORDER BY odd_cnt asc
        ";
        $query = $this->db->query($qry);

        $data = array();
        while($row = $query->fetch_assoc()) {			
			$key = $row['odd_cnt'];
            $vlu = $row['cnt'];

			$per = round($vlu / $this->total * 100);
			$data[$key] = array($vlu, $per);
        }
		for($i=0; $i<= 5; $i++) {
			if(!isset($data[$i])) $data[$i] = array(0, 0);	
		}

		// 최근 10게임에서 홀수갯수를 가져온다.
		$total = count($this->last10Game);
		$data2 = array();
		for($i=0; $i<= 5; $i++) $data2[$i] = array(0, 0);
		foreach($this->last10Game as $row) {
			$key = ($row['whiteBall1']%2) + ($row['whiteBall2'])%2 + ($row['whiteBall3']%2) + ($row['whiteBall4']%2) + ($row['whiteBall5']%2);
			$data2[$key][0]++;

			$per = round($data2[$key][0] / $total * 100);
			$data2[$key][1] = $per;
		}

        // actual appearance : odd / even 비율이 출현한 횟수
        $cnt = count($odd);
		$appear = $data[$cnt][0];
		
        // expected value : 추첨횟수 * odd/ even 출현패턴의 출현확률
        $ev = $this->total * $this->pattern_rate[count($odd)];

        // grade : appear / ev;
        $grade = $this->funcGrade('oddeven', $appear, $ev);
		
        // ment
        $ment = $this->oddevenMent(count($odd), count($even));

        return array($odd, $even, $data, $data2, $appear, $grade, $ment);
    }


    #===========================================================
    # high / low
    # return : high, low, 빈도, appearance, grade
    #===========================================================
    function funcHighLow()
    {
        $high = array();
        $low = array();
        $center = $this->game == 'pb' ? 35 : 36;

        foreach($this->lastNum as $k => $v) {
            if($k == 5) break;

            if($v < $center) {
				$low[] = $v;
            } else { 
				$high[] = $v;
			}
        }

		// 전체 게임에서 게임별 high 갯수를 가져온다.
        $qry = "
        SELECT high_cnt, COUNT(1) cnt FROM (
            SELECT 
              (FLOOR(whiteBall1/$center)+FLOOR(whiteBall2/$center)+FLOOR(whiteBall3/$center)+FLOOR(whiteBall4/$center)+FLOOR(whiteBall5/$center)) high_cnt  
            FROM NUMBER_HISTORY 
            WHERE game = '{$this->game}' {$this->where} 
        ) TBL GROUP BY high_cnt ORDER BY high_cnt asc
        ";
        $query = $this->db->query($qry);

        $data = array();
        while($row = $query->fetch_assoc()) {
			$key = $row['high_cnt'];
			$vlu = $row['cnt'];
			$per = round($vlu / $this->total * 100);

            $data[$key] = array($vlu, $per);
        }
		for($i=0; $i<= 5; $i++) {
			if(!isset($data[$i])) $data[$i] = array(0, 0);
		}

		// 최근 10게임에서 high갯수를 가져온다.
		$total = count($this->last10Game);
		$data2 = array();
		for($i=0; $i<= 5; $i++) $data2[$i] = array(0, 0);
		foreach($this->last10Game as $row) {
			$key = floor($row['whiteBall1']/$center) + floor($row['whiteBall2']/$center) + floor($row['whiteBall3']/$center) + floor($row['whiteBall4']/$center) + floor($row['whiteBall5']/$center);
			$data2[$key][0]++;

			$per = round($data2[$key][0] / $total * 100);
			$data2[$key][1] = $per;
		}
		
        // actual appearance
        $cnt = count($high);
		$appear = $data[$cnt][0];
		
        // expected value : 추첨횟수 * high / low 출현패턴의 출현확률
        $ev = $this->total * $this->pattern_rate[count($high)];

        $grade = $this->funcGrade('highlow', $appear, $ev);

        // ment
        $ment = $this->highlowMent(count($high), count($low));

        return array($high, $low, $data, $data2, $appear, $grade, $ment);
    }


    #===========================================================
    # Streak
    #===========================================================
    function funcStreak()
    {
        $wb = array();
        $pb = array();
        foreach($this->lastNum as $k => $v) {

            if($k == 5) {
                if($v == $this->prevNum[5]) $pb[] = $v;
            } else {
                $_prevNum = $this->prevNum;
                unset($_prevNum[5]);
                if (in_array($v, $_prevNum)) $wb[] = $v;
            }
        }

        // wb grade
        if (count($wb) == 0) $grade1 = '1'; // Low
        else if (count($wb) == 1) $grade1 = '2'; // High
        else $grade1 = '3';	// Very High

        // pb grade
        if(count($pb) == 0) $grade2 = '1'; // Low
        else $grade2 = '2'; // High
        
        // 최근 10회 흐름
		$lst = array();
		$data = $this->selectGame(11);
		for($i = 0; $i <= count($data)-2; $i++) {
			$data1 = $data[$i];			// 현재
			$data2 = $data[($i+1)];	// 이전

			$s_pb = array();
			$s_wb = array();
			for($k=1;$k<=5;$k++) {
				if(in_array($data1['whiteBall'.$k], array($data2['whiteBall1'], $data2['whiteBall2'], $data2['whiteBall3'], $data2['whiteBall4'], $data2['whiteBall5']))) $s_wb[] = $data1['whiteBall'.$k];
			}
			if($data1['powerBall'] == $data2['powerBall']) $s_pb[] = $data1['powerBall'];

			$lst[] = array('wb' => count($s_wb), 'pb' => count($s_pb));
		}

        // ment
        $ment = $this->streakMent($wb, $pb, $lst);

        return array($wb, $pb, $grade1, $grade2, $lst, $ment);
    }

    #===========================================================
    # plus minus 
    # return : white ball, power ball, wb grade, pb grade, 최근 10회 흐름, ment
    #===========================================================
    function funcPlusMinus()
    {
        $wb = array();
        $pb = array();
        foreach($this->lastNum as $k => $v) {
            if($k == 5) {
                if($v-1 == $this->prevNum[5] || $v+1 == $this->prevNum[5]) $pb[] = $v;
            } else {
                $_prevNum = $this->prevNum;
                unset($_prevNum[5]);
                if(in_array($v-1, $_prevNum) || in_array($v+1, $_prevNum)) $wb[] = $v;
            }
        }

        // wb grade
        if(count($wb) == 0) $grade1 = '1';	// Low
        else if(count($wb) == 1) $grade1 = '2'; // High
        else $grade1 = '3'; // Very High

        // pb grade
        if(count($pb) == 0) $grade2 = '1'; // Low
        else $grade2 = '2'; // High

        // 최근 10회 흐름
        $lst = array();
		$data = $this->selectGame(11);
		for($i = 0; $i <= count($data)-2; $i++) {
			$data1 = $data[$i];	// 현재
			$data2 = $data[($i+1)];	// 이전

			$s_wb = array();
			$s_pb = array();
			for($k=1;$k<=5;$k++) {
				$j = $data1['whiteBall'.$k];
				$arr = array($data2['whiteBall1'], $data2['whiteBall2'], $data2['whiteBall3'], $data2['whiteBall4'], $data2['whiteBall5']);
				if(in_array($j-1, $arr) || in_array($j+1, $arr)) $s_wb[] = $j;
			}
			if($data1['powerBall']+1 == $data2['powerBall'] || $data1['powerBall']-1 == $data2['powerBall']) $s_pb[] = $data1['powerBall'];
			
			$lst[] = array('wb' => count($s_wb), 'pb' => count($s_pb));
		}

		// ment
        $ment = $this->plusMinusMent($wb, $pb, $lst);

        return array($wb, $pb, $grade1, $grade2, $lst, $ment);
    }

    #===========================================================
    # last digit
    # return : 최근 10게임, last n
    #===========================================================
    function funcLastDigit()
    {
        // 최근 10개 게임
        $data = $this->last10Game;

        // last5, last10, last30, last50
        // 최근 10회 동안 가장많이 나온 끝자리수
        $limit = array(5=>array(), 10=>array(), 30=>array(), 50=>array());
        foreach($limit as $k => $v) {
			if($this->total < $k) continue;	// 총횟수가 $k보다 안될때는 넘어가기
            for($i=0; $i<10; $i++) $v[$i] = 0;

            $qry = "
            SELECT 
              right(whiteBall1, 1) last1, 
              right(whiteBall2, 1) last2, 
              right(whiteBall3, 1) last3, 
              right(whiteBall4, 1) last4, 
              right(whiteBall5, 1) last5 
            FROM NUMBER_HISTORY 
            WHERE game = '{$this->game}' {$this->where}
            ORDER BY drawDt 
            DESC LIMIT ".$k;
            $query = $this->db->query($qry);
            while($row = $query->fetch_array()) {
                for($i=0; $i<5; $i++) {
                    $j = $row[$i];
                    $v[$j]++;
                }
            }
            ksort($v);  // 키로 정렬
            $limit[$k] = $v;
        }

        // ment 
        $ment = $this->lastDigitMent($limit[10]);


        return array($data, $limit, $ment);
    }

    #===========================================================
    # skip
    #===========================================================
    function funcSkip() {
		$skip = array();
		$drawDt = $this->lastGame['drawDt'];	// 마지막꺼 제외하고 검색

		// whiteball
		for($i=1; $i<=$this->totalBall; $i++) {
			$nums = $this->lastNum;
			unset($nums[5]);

			$qry = "
			SELECT num FROM (
				SELECT 
					@rownum := @rownum+1 as num, a.*
				FROM NUMBER_HISTORY a, (SELECT @rownum := 0) r 
				WHERE game='{$this->game}' AND drawDt < '$drawDt' {$this->where2} 
				ORDER BY drawDt ASC
			) TBL 
			WHERE {$i} in (whiteBall1, whiteBall2, whiteBall3, whiteBall4, whiteBall5) 
			ORDER BY num DESC LIMIT 1
			";
			$query = $this->db->query($qry);
			$row = $query->fetch_array();
			$s = $this->total - $row[0];
			if(in_array($i, $nums)) $s--;
			$skip['wb'][$i] = $s;
		}

		// powerball
		for($i=1; $i<= $this->powerBall; $i++) {
			$qry = "
			SELECT num FROM (
				SELECT 
					@rownum := @rownum+1 as num, a.*
				FROM NUMBER_HISTORY a, (SELECT @rownum := 0) r 
				WHERE game='{$this->game}'  AND drawDt < '$drawDt' {$this->where2}
			) TBL 
			WHERE powerBall = {$i}
			ORDER BY num DESC LIMIT 1
			";
			$query = $this->db->query($qry);
			$row = $query->fetch_array();
			$s = $this->total - $row[0];
			if($i == $this->lastNum[5]) $s--;
			$skip['pb'][$i] = $s;
		}

		// COMBINATION AVERAGE
		$sum = 0;
		for($i=0;$i<5;$i++) {
			$j = $this->lastNum[$i]; 
			$sum += $skip['wb'][$j];
		}
		$average['wb'] = round($sum/5,1);

		$j = $this->lastNum[5]; 
		$average['pb'] = $skip['pb'][$j];

		// grade
		$ev = round($this->totalBall / 5,1);
		$grade['wb'] = $this->funcGrade('skip', $average['wb'], $ev);
		$grade['pb'] = $this->funcGrade('skip', $average['pb'], $this->powerBall);	
		
		// ment
		$ment = $this->skipMent($skip['wb'], $grade['wb']);

		return array($skip, $average, $grade, $ment);
    }

    #===========================================================
    # gap
    # return : actual appearance, average in last 30 draw
    #===========================================================
    function funcGap() {

		// actual appearance 
        $appear = array(
            'I1' => $this->lastNum[1] - $this->lastNum[0],
            'I2' => $this->lastNum[2] - $this->lastNum[1],
            'I3' => $this->lastNum[3] - $this->lastNum[2],
            'I4' => $this->lastNum[4] - $this->lastNum[3]
        );

        // average in last 30 draw
        // 최근 30회 1구-2구 차이 평균, 2구-3구 차이 평균, 3구-4구 차이 평균, 4구-5구 차이 평균
        $gap = array('I1' => 0, 'I2' => 0, 'I3' => 0, 'I4' => 0);
        $data = $this->selectGame(30);
        $cnt = 0;
        foreach($data as $row) {
            $cnt++;
            $ball = array($row['whiteBall1'], $row['whiteBall2'], $row['whiteBall3'], $row['whiteBall4'], $row['whiteBall5']);
            asort($ball);

            $gap['I1'] += ($ball[1] - $ball[0]);
            $gap['I2'] += ($ball[2] - $ball[1]);
            $gap['I3'] += ($ball[3] - $ball[2]);
            $gap['I4'] += ($ball[4] - $ball[3]);

        }

        $gap['I1'] = round($gap['I1'] / $cnt, 1);
        $gap['I2'] = round($gap['I2'] / $cnt, 1);
        $gap['I3'] = round($gap['I3'] / $cnt, 1);
        $gap['I4'] = round($gap['I4'] / $cnt, 1);

        // ment
        $ment = $this->gapMent($appear, $gap);

        return array($appear, $gap, $ment);
    }


    #===========================================================
    # 10-interval
    # return 최근 10게임 interval
    #===========================================================
    function funcInterval() {

        // 최근 10회 interval
        $data = array();
        foreach($this->last10Game as $row) {
            $nums = array($row['whiteBall1'], $row['whiteBall2'], $row['whiteBall3'], $row['whiteBall4'], $row['whiteBall5']);
            $srow = array();
            foreach($nums as $k => $v) {				
                $i = floor(($v-1) / 10);
                $srow[$i][] = $v;
            }

            $data[] = array(
				"drawDt" => $row['drawDt'],
                "number" => $nums,
                "interval" => $srow
            );
        }

        // ment
        $ment = $this->intervalMent($data);

        return array($data, $ment);
    }


    #===========================================================
    # neighbor
    # return 최근 10게임 & neighbor
    #===========================================================
    function funcNeighbor()
    {
        // 최근 10회
        $data = array();
        foreach($this->last10Game as $row) {
            $nums = array($row['whiteBall1'],$row['whiteBall2'],$row['whiteBall3'],$row['whiteBall4'],$row['whiteBall5']);
            $neighbor = array();
            $tmp = array();
            for($i=1; $i<5; $i++) {
                if($nums[$i]-$nums[$i-1] == 1) {
                    if(count($tmp) == 0) $tmp[] = $nums[$i-1];
                    $tmp[] = $nums[$i];
                } else {
                    if(count($tmp) > 0) $neighbor[] = $tmp;
                    $tmp = array();
                }
            }
            if(count($tmp) > 0) $neighbor[] = $tmp;

            $row['neighbor'] = $neighbor;
            $neighbor_type = 0;   // none
            if(count($neighbor) == 1) {
                if(count($neighbor[0]) == 5) $neighbor_type = 4;
                else if(count($neighbor[0]) == 4) $neighbor_type = 3;
                else if(count($neighbor[0]) == 3) $neighbor_type = 2;
                else $neighbor_type = 1;
            } else if(count($neighbor) == 2) {
                if(count($neighbor[0]) == 3 || count($neighbor[1]) == 3) $neighbor_type = 6;
                else $neighbor_type = 5;
            }
            $row['neighbor_type'] = $neighbor_type;
			$row['neighbor_name'] = $this->neighbor_type[$neighbor_type];
            $data[] = $row;
        }

        // ment
        $ment = $this->neighborMent($data);
        return array($data, $ment);
    }

	function ment_replace($ment) 
	{
		return str_replace("'","&acute;",$ment);
	}

    #===========================================================
    # frequency ment - 3개중에서 로테이션 된다.
    # n, m : expected value - frequency 의 절대값이 큰번호가 n, 동일한 번호가 있을경우에만 m 노출
	# freq : #n의 출현횟수
    # k, l : 최근 10회중 가장 많이 출현한 번호
	# n의 값이 출현빈도가 높을때는 most, 낮은번호일경우에는 least
    #===========================================================
    function frequencyMent($frequency, $ev)
    {
		// n, m
		$absnum = array();
		for($i=0; $i<5; $i++) {
			$absnum[$i] = abs($frequency[$i]-$ev);
		}

		arsort($absnum);
		$key = key($absnum);	
		$n = $this->lastNum[$key];	// 출현빈도가 가장 높은 수
		$freq = $frequency[$key];	// 출현빈도

		arsort($frequency);
		$max_freq = current($frequency);	// 가장 높은 출현빈도

		$arr = array($n);
		for($i=0; $i<5; $i++) {
			if($key != $i && $absnum[$key] == $absnum[$i]) $arr[] = $this->lastNum[$i];
		}
		$n = "#". join(" and #", $arr);
		
		// k, l
		$statistic = array();
		foreach($this->last10Game as $key => $row) {
			for($i=1; $i<=5; $i++) {
				$num = $row['whiteBall'.$i];
				if(isset($statistic[$num])) $statistic[$num]++;
				else $statistic[$num] = 1;
			}
		}
		arsort($statistic);
		$k = key($statistic);
		$arr = array($k);
		foreach($statistic as $sk => $sn) {
			if($sk != $k && $statistic[$k] == $sn) $arr[] = $sk;
		}
		$k = "#". join(", #", $arr);
		
		$s = ($this->total-1) % 3;		
		if($s == 0) {
			$sub_title = $max_freq == $freq ? "most" : "least";	
			$ment = "<strong>{$n}</strong>, which is one of the {$sub_title} frequent number in whiteball, have an appearance in <strong>{$this->thisdraw}</strong> draw jackpot number. 
				<strong>{$n}`s</strong> frequency is <strong>{$freq}</strong> times. You need to watch whiteball <strong>{$k}</strong>. It have {$sub_title} frequency in last 10 draw.";
		
		} else if($s == 1) {
			$sub_title = $max_freq == $freq ? "strong" : "weak";
			$sub_title2 = $this->game == 'pb' ? '10/07/2015' : '10/31/2017';

			$ment = "In this draw, notable number is <strong>{$n}</strong>. 
				<strong>{$n}</strong> is a {$sub_title} in all {$this->gameName} draw since {$sub_title2}. 
				In the case of recent draw, <strong>{$k}</strong> appear as jackpot number quite often.";
		
		} else if($s == 2) {
			$sub_title = $max_freq == $freq ? "powerful" : "powerless";
			$sub_title2 = $max_freq == $freq ? "more" : "less";
			$sub_title3 = $max_freq == $freq ? "most" : "least";	

			$ment = "This draw have a {$sub_title} number. It is <strong>{$n}</strong>. 
				<strong>{$n}</strong> have {$sub_title2} frequency than expected value on this draw. 
				It is necessary for you to see <strong>{$k}</strong>. 
				Recent draw tell you that <strong>{$k}</strong> is the {$sub_title3} frequency number.";
		}

        return $this->ment_replace($ment);
    }

    #===========================================================
    # odd / even ment
    # n / m : 현재 게임 odd/ even
    # k / l : 최근 10회기준 가장많이 출현한 odd / even
    # times : 최근 10회기준 n / m 이 출현한 횟수
    #===========================================================
    function oddevenMent($n, $m)
    {
		// 최근 10회 추첨에서 $n:$m의 비율이 몇번나왔는지, 마지막 10회차의 날짜
        $lst = array();
		$lastdraw = "";
        foreach($this->last10Game as $key => $row) {
            $odd = 0;
            for($i=1;$i<=5;$i++) $odd += ($row['whiteBall'.$i]%2);
            $lst[$odd] = isset($lst[$odd]) ? $lst[$odd]+1 : 1;
			$lastdraw = $row['drawDt'];
        }
        $times = isset($lst[$n]) ? $lst[$n] : 0;
		$lastdraw = date("m/d/Y", strtotime($lastdraw));

		// odd / even의 비율이 출현한 회차의 다음회차에 어떤 비율로 출현했는지 체크 (k : l)
		$qry = "
		SELECT drawDt, num+1 as nextnum FROM (
			SELECT 
				@rownum := @rownum+1 as num,
				A.drawDt,  
				(mod(A.whiteBall1,2) + mod(A.whiteBall2,2) + mod(A.whiteBall3,2) + mod(A.whiteBall4,2) + mod(A.whiteBall5,2)) odd_cnt 
			FROM NUMBER_HISTORY A, (SELECT @rownum :=0) R 
			WHERE A.game = '{$this->game}' {$this->where} 
		) TBL 
		WHERE odd_cnt = '$n' 
		ORDER BY drawDt DESC LIMIT 1, 10
        ";
        $query = $this->db->query($qry);
		$next = array();
		while($row = $query->fetch_assoc()) $next[] = $row['nextnum'];
		$nextnum = join(",", $next);
		unset($next);
		
		if($nextnum != "") {	// 다음회차가 있을때
			$qry = "
			SELECT odd_cnt, count(1) cnt FROM (
				SELECT 
					@rownum := @rownum+1 as num,
					A.drawDt,  
					(mod(A.whiteBall1,2) + mod(A.whiteBall2,2) + mod(A.whiteBall3,2) + mod(A.whiteBall4,2) + mod(A.whiteBall5,2)) odd_cnt 
				FROM NUMBER_HISTORY A, (SELECT @rownum :=0) R 
				WHERE A.game = '{$this->game}' {$this->where}
			) TBL 
			WHERE num in ({$nextnum})
			GROUP BY odd_cnt 
			ORDER BY cnt desc
			";
			$query = $this->db->query($qry);
			$k = "";
			$karr = array();
			while($row = $query->fetch_assoc()) {
				if($k == "") {
					$k = $row['odd_cnt']; 
					$karr[$row['odd_cnt']] = $row['cnt'];
				}
				if($row['odd_cnt'] != $k && $karr[$k] == $row['cnt']) $karr[$row['odd_cnt']] = $row['cnt'];
			}
		
			$_karr = "";
			foreach($karr as $key => $vlu) {
				$_karr[] = "Odd ". $key . " / Even ".(5-$key);
			}
			$k = join(", ", $_karr);
			unset($_karr);
		} else {
			$k = "";
		}

		if($this->game == 'mm') {
			$ment = array(
				0 => "
					Odd / Even ratio in <strong>{$this->thisdraw}</strong> draw is <strong>Odd {$n} / Even {$m}</strong>. This pattern have <strong>{$times}</strong> times appearance in last 10 draw.",
				1 => "
					In this draw, <strong>Odd {$n} / Even {$m}</strong> success an appearance. It is currently recording <strong>{$times}</strong> times since <strong>{$lastdraw}</strong> draw.",
				2 => "
					This draw record <strong>Odd {$n} / Even {$m}</strong>. It appear <strong>{$times}</strong> times in last 10 draw."
			);
		} else {
			$ment = array(
				0 => "
					Odd / Even ratio in <strong>{$this->thisdraw}</strong> draw is <strong>Odd {$n} / Even {$m}</strong>. This pattern have <strong>{$times}</strong> times appearance in last 10 draw. 
					For the next draw, you need to watch <strong>{$k}</strong> pattern which have a good grade after <strong>Odd {$n} / Even {$m}</strong>.",
				1 => "
					In this draw, <strong>Odd {$n} / Even {$m}</strong> success an appearance. It is currently recording <strong>{$times}</strong> times since <strong>{$lastdraw}</strong> draw. 
					To the next draw, it is necessary for you to observe <strong>{$k}</strong> because it have a strong trend since then <strong>Odd {$n} / Even {$m}</strong> appear.",
				2 => "
					This draw record <strong>Odd {$n} / Even {$m}</strong>. It appear <strong>{$times}</strong> times in last 10 draw. 
					By the past draw which <strong>Odd {$n} / Even {$m}</strong> appear after, <strong>{$k}</strong> keep up its nice trend.
					You can consider it before you purchase your lottery"
			);
		}
        $s = ($this->total-1) % 3;
        $select_ment = $ment[$s];

		return $this->ment_replace($select_ment);
    }

    #===========================================================
    # high / low ment
    # n, m : 높은숫자 낮은숫자 패턴
    # k, l : 최근 10회기준 높은숫자, 낮은숫자 패턴
    # times : 최근 10회기준 n/m 패턴이 출현한 횟수
    #===========================================================
    function highlowMent($n, $m)
    {
		$center = $this->game == 'pb' ? 35 : 36;

		// 지난 10회 추첨에서 $n : $m의 비율이 몇번 나왔는지, 마지막 10회차의 날짜
        $lst = array();
		$lastdraw = "";
        foreach($this->last10Game as $key => $row) {
            $high = 0;
            for($i=1;$i<=5;$i++) {
                $high += floor($row['whiteBall'.$i] / $center);
            }
            $lst[$high] = isset($lst[$high]) ? $lst[$high]+1 : 1;
			$lastdraw = $row['drawDt'];
        }       
        $times = isset($lst[$n]) ? $lst[$n] : 0;
		$lastdraw = date("m/d/Y", strtotime($lastdraw));

		// high / low 의 비율이 출현한 회차의 다음회차에 어떤 비율로 출현했는지 체크 (k : l)
		$qry = "
		SELECT drawDt, num+1 as nextnum FROM (
			SELECT 
				@rownum := @rownum+1 as num,
				A.drawDt,  
				(FLOOR(whiteBall1/$center)+FLOOR(whiteBall2/$center)+FLOOR(whiteBall3/$center)+FLOOR(whiteBall4/$center)+FLOOR(whiteBall5/$center)) high_cnt  
			FROM NUMBER_HISTORY A, (SELECT @rownum :=0) R 
			WHERE A.game = '{$this->game}' {$this->where} 
		) TBL 
		WHERE high_cnt = '$n' 
		ORDER BY drawDt desc limit 1, 10
        ";
        $query = $this->db->query($qry);
		$next = array();
		while($row = $query->fetch_assoc()) $next[] = $row['nextnum'];
		$nextnum = join(",", $next);
		unset($next);
		
		if($nextnum != "") {	// 다음회차가 있을때
			$qry = "
			SELECT high_cnt, count(1) cnt FROM (
				SELECT 
					@rownum := @rownum+1 as num,
					A.drawDt,  
					(FLOOR(whiteBall1/$center)+FLOOR(whiteBall2/$center)+FLOOR(whiteBall3/$center)+FLOOR(whiteBall4/$center)+FLOOR(whiteBall5/$center)) high_cnt  
				FROM NUMBER_HISTORY A, (SELECT @rownum :=0) R 
				WHERE A.game = '{$this->game}' {$this->where}
			) TBL 
			WHERE num in ({$nextnum})
			GROUP BY high_cnt 
			ORDER BY cnt desc
			";
			$query = $this->db->query($qry);
			$k = "";
			$karr = array();
			while($row = $query->fetch_assoc()) {
				if($k == "") {
					$k = $row['high_cnt']; 
					$karr[$row['high_cnt']] = $row['cnt'];
				}
				if($row['high_cnt'] != $k && $karr[$k] == $row['cnt']) $karr[$row['high_cnt']] = $row['cnt'];
			}
			$_karr = "";
			foreach($karr as $key => $vlu) {
				$_karr[] = "High ". $key . " / Low ".(5-$key);
			}
			$k = join(", ", $_karr);
			unset($_karr);
		} else {
			$k = "";
		}

		// 멘트
		if($this->game == 'mm') {
			$ment = array(
				0 => "In <strong>{$this->thisdraw}</strong> draw, <strong>High {$n} / Low {$m}</strong> success an appearance. It is currently recording <strong>{$times}</strong> times since <strong>{$lastdraw}</strong> draw.",
				1 => "This draw record <strong>High {$n} / Low {$m}</strong>. It appear <strong>{$times}</strong> times in last 10 draw.",
				2 => "High / Low ratio in <strong>{$this->thisdraw}</strong> draw is <strong>High {$n} / Low {$m}</strong>. 
					This pattern have <strong>{$times}</strong> times appearance in last 10 draw."
			);
		} else {
			$ment = array(
				0 => "In <strong>{$this->thisdraw}</strong> draw, <strong>High {$n} / Low {$m}</strong> success an appearance. It is currently recording <strong>{$times}</strong> times since <strong>{$lastdraw}</strong> draw. 
					To the next draw, it is necessary for you to observe <strong>{$k}</strong> because it have a strong trend since then <strong>High {$n} / Low {$m}</strong> appear.",
				1 => "This draw record <strong>High {$n} / Low {$m}</strong>. It appear <strong>{$times}</strong> times in last 10 draw. 
					By the past draw which <strong>High {$n} / Low {$m}</strong> appear after, <strong>{$k}</strong> keep up its nice trend.
					You can consider it before you purchase your lottery.",
				2 => "High / Low ratio in <strong>{$this->thisdraw}</strong> draw is <strong>High {$n} / Low {$m}</strong>. 
					This pattern have <strong>{$times}</strong> times appearance in last 10 draw. 
					For the next draw, you need to watch <strong>{$k}</strong> pattern which have a good grade after <strong>High {$n} / Low {$m}</strong>."
			);
		}
        $s = ($this->total-1) % 3;
        $select_ment = $ment[$s];
		
        return $this->ment_replace($select_ment);
    }

    #===========================================================
    # Streak ment
    # n, m : wb/pb 의 패턴
    # times : 최근 10회에서 n/m 패턴이 출현한 횟수
    #===========================================================
    function streakMent($wb, $pb, $lst)
    {
		$n = count($wb);
		$m = count($pb);
		$allnum1 = "#" . join(", #", $wb);
		$allnum2 = "#" . join(", #", $pb);
		$times = 0;
		$total = 0;		// 출현한것의 합

		$data = array();
		if($n > 0) {
			foreach($lst as $vlu) {
				$key = $vlu['wb'];
				$data[$key] = isset($data[$key]) ? $data[$key]+1 : 1;
				$total += $vlu['wb'];
			}
			$times = isset($data[$n]) ? $data[$n] : 0;	

		} else if($m > 0) {
			foreach($lst as $vlu) {
				$key = $vlu['pb'];
				$data[$key] = isset($data[$key]) ? $data[$key]+1 : 1;
				$total += $vlu['pb'];
			}			
			$times = isset($data[$m]) ? $data[$m] : 0;

		} else {
			foreach($lst as $vlu) {
				if($vlu['wb'] == 0 && $vlu['pb'] == 0) $times++;
			}
		}

		if(count($data) > 0) {			
			arsort($data);
			$max_value = current($data);
			$max_key = array();
			foreach($data as $key => $value) {
				if($max_value == $value) $max_key[] = $key;
			}

			if($n == 0 && $m > 0) {	// pb만 출현
				if($times == 1) $sub_ment = "It is the first advent";	// 첫출현
				else $sub_ment = "It has a strong advent";				// 2회이상출현
				
				$sub_ment2 = $total >= 2 ? "good" : "bad";
			} else {

				if($times == 1) $sub_ment = "It is the first advent";									// 패턴이 최근 10회이내 첫출현
				else if(in_array($n, $max_key)) $sub_ment = "It is the most advent";		// 패턴이 최근 10회 이내 가장 많은 출현
				else if($times >= 3) $sub_ment = "It has a strong advent";						// 패턴이 최근 10회 이내 3회이상 출현
				else $sub_ment = "It records a bearish trend";											// 패턴이 최근 10회 이내 2회 출현

				$sub_ment2 = $total >= 3 ? "good" : "bad";
			}
		}
				
		if ($n > 0 && $m > 0) { // wb, pb 둘다 있을때
			$ment_type = 1;
			$ment = array(
				0 => "Streak number appear WhiteBall <strong>{$n}</strong>, {$this->ballName} <strong>{$m}</strong> in this draw.
					WhiteBall Streak pattern with <strong>{$n}</strong> number show <strong>{$times}</strong> times appearance in last 10 draw.
					{$sub_ment} in last 10 draw.
					{$this->ballName} Streak number also success an appearance.",
				1 => "In <strong>{$this->thisdraw}</strong> draw, WhiteBall <strong>{$n}</strong> number which is <strong>{$allnum1}</strong>, 
					{$this->ballName} <strong>{$m}</strong> number which is <strong>{$allnum2}</strong> agree with last draw. 
					It means that figure of Streak pattern is WhiteBall <strong>{$n}</strong>, {$this->ballName} <strong>{$m}</strong>.
					You can see Streak pattern's recent result which is showing a {$sub_ment2} tendency.
					Thus, you need to watch Streak number."
			);

		} else if($n > 0 && $m == 0) {	// wb만 있을때
			$ment_type = 2;
			$ment = array(
				0 => "Streak number appear WhiteBall <strong>{$n}</strong> in this draw.
					Streak pattern with <strong>{$n}</strong> number show <strong>{$times}</strong> times appearance in last 10 draw.
					{$sub_ment} in last 10 draw.",
				1 => "In <strong>{$this->thisdraw}</strong> draw, WhiteBall <strong>{$n}</strong> number which is WhiteBall <strong>{$allnum1}</strong> agree with last draw. 
					It means that figure of Streak pattern is <strong>{$n}</strong>.
					You can see WhiteBall Streak pattern's recent result which is showing a {$sub_ment2} tendency.
					Thus, you need to watch Streak number."
			);

		} else if($n == 0 && $m > 0) {	// pb만 있을때
			$ment_type = 3;
			$ment = array(
				0 => "Streak number appear {$this->ballName} <strong>{$m}</strong> in this draw.
					Streak pattern with <strong>{$m}</strong> number show <strong>{$times}</strong> times appearance in last 10 draw.					
					{$sub_ment} in last 10 draw.",
				1 => "In <strong>{$this->thisdraw}</strong> draw, {$this->ballName} <strong>{$m}</strong> number which is {$this->ballName} <strong>{$allnum2}</strong> agree with last draw. 
					It means that figure of Streak pattern is <strong>{$m}</strong>.
					You can see {$this->ballName} Streak pattern's recent result which is showing a {$sub_ment2} tendency.
					Thus, you need to watch Streak number."
			);

		} else {	// wb, pb 둘다 없을때
			$ment = "This draw have no Streak number within WhiteBall and {$this->ballName}. This case record <strong>{$times}</strong> times in last 10 draw. ";
			if($times >= 7) {
				$ment .= "Due to recent slump of Streak pattern, you don't have to see it maybe.";
			} else {
				$ment .= "But Streak pattern have some good trend in recent draw. It will be useful that you loot at Streak pattern.";
			}

			$select_ment = $ment;
		}

		if(is_array($ment)) {			
			// 이전멘트 확인하기
			$query = $this->db->query("SELECT COUNT(1) cnt FROM REPORT WHERE game='{$this->game}' AND idx < '{$this->idx}'  AND streakCnt = '{$ment_type}'");
			$rcnt = $query->fetch_assoc();
			$s = $rcnt['cnt'] % 2;

			$select_ment = $ment[$s];
		}

		return $this->ment_replace($select_ment);
    }

    #===========================================================
    # plus minus  ment
    #===========================================================
    function plusMinusMent($wb, $pb, $lst)
    {
		$n = count($wb);
		$m = count($pb);
		$allnum1 = "#" . join(", #", $wb);
		$allnum2 = "#" . join(", #", $pb);
		$times = 0;
		$total = 0;

		$data = array();
		if($n > 0) {	// 1:0, 1:1
			foreach($lst as $vlu) {
				$key = $vlu['wb'];
				$data[$key] = isset($data[$key]) ? $data[$key]+1 : 1;
				$total += $vlu['wb'];
			}
			$times = isset($data[$n]) ? $data[$n] : 0;	

		} else if($m > 0) { // 0:1
			foreach($lst as $vlu) {
				$key = $vlu['pb'];
				$data[$key] = isset($data[$key]) ? $data[$key]+1 : 1;
				$total += $vlu['pb'];
			}			
			$times = isset($data[$m]) ? $data[$m] : 0;

		} else {	// 0:0
			foreach($lst as $vlu) {
				if($vlu['wb'] == 0 && $vlu['pb'] == 0) $times++;
			}
		}

		if(count($data) > 0) {
			arsort($data);
			$max_value = current($data);
			$max_key = array();
			foreach($data as $key => $value) {
				if($max_value == $value) $max_key[] = $key;
			}

			if($n == 0 && $m > 0) {	// pb만 있을때
				if($times == 1) $sub_ment = "It is the first advent";									// 패턴이 최근 10회이내 첫출현
				else $sub_ment = "It has a strong advent";												// 패턴이 최근 10회 이내 2회이상 출현

				$sub_ment2 = $times >= 2 ? "good" : "bad";

			} else {
				if($times == 1) $sub_ment = "It is the first advent";									// 패턴이 최근 10회이내 첫출현
				else if(in_array($n, $max_key)) $sub_ment = "It is the most advent";		// 패턴이 최근 10회 이내 가장 많은 출현
				else if($times >= 5) $sub_ment = "It has a strong advent";						// 패턴이 최근 10회 이내 5회이상 출현
				else $sub_ment = "It records a bearish trend";											// 패턴이 최근 10회 이내 2~4회 출현

				$sub_ment2 = $times >= 6 ? "good" : "bad";
			}
		}

		if ($n > 0 && $m > 0) {			// wb, pb 둘다 있을때
			$ment_type = 1;
			$ment = array(
				0 => "In <strong>{$this->thisdraw}</strong> draw, <strong>WhiteBall {$n}</strong> number which is <strong>{$allnum1}</strong>, 
					<strong>{$this->ballName} {$m}</strong> number which is <strong>{$allnum2}</strong> agree with last draw. 
					It means that figure of Plus / Minus pattern is <strong>WhiteBall {$n}, {$this->ballName} {$m}</strong>.
					You can see Plus / Minus pattern's recent result which is showing a {$sub_ment2} tendency.
					Thus, you need to watch Plus / Minus number.",
				1 => "Plus / Minus number appear <strong>WhiteBall {$n}, {$this->ballName} {$m}</strong> in this draw.
					WhiteBall Plus / Minus pattern with <strong>{$n}</strong> number show <strong>{$times}</strong> times appearance in last 10 draw.
					{$sub_ment} in last 10 draw.
					{$this->ballName} Plus / Minus number also success an appearance."
			);

		} else if($n > 0 && $m == 0) {	// wb만 있을때
			$ment_type = 2;
			$ment = array(
				0 => "In <strong>{$this->thisdraw}</strong> draw, <strong>WhiteBall {$n}</strong> number which is WhiteBall <strong>{$allnum1}</strong> agree with last draw. 
					It means that figure of Plus / Minus pattern is <strong>{$n}</strong>.
					You can see WhiteBall Plus / Minus pattern's recent result which is showing a {$sub_ment2} tendency.
					Thus, you need to watch Plus / Minus number.",
				1 => "Plus / Minus number appear <strong>WhiteBall {$n}</strong> in this draw.
					Plus / Minus pattern with <strong>{$n}</strong> number show <strong>{$times}</strong> times appearance in last 10 draw.
					{$sub_ment} in last 10 draw."
			);

		} else if($n == 0 && $m > 0) {	// pb만 있을때
			$ment_type = 3;
			$ment = array(
				0 => "In <strong>{$this->thisdraw}</strong> draw, <strong>{$this->ballName} {$m}</strong> number which is {$this->ballName} <strong>{$allnum2}</strong> agree with last draw. 
					It means that figure of Plus / Minus pattern is <strong>{$m}</strong>.
					You can see {$this->ballName} Plus / Minus pattern's recent result which is showing a {$sub_ment2} tendency.
					Thus, you need to watch Plus / Minus number.",
				1 => "Plus / Minus number appear <strong>{$this->ballName} {$m}</strong> in this draw.
					Plus / Minus pattern with <strong>{$m}</strong> number show <strong>{$times}</strong> times appearance in last 10 draw.					
					{$sub_ment} in last 10 draw."
			);

		} else {

			$ment = "This draw, there is no Plus / Minus number within WhiteBall and {$this->ballName}. This case record <strong>{$times}</strong> times in last 10 draw.";
			if($times >= 5) {
				$ment .= "Due to weak trend of Plus / Minus pattern, it is good to avoid this pattern.";
			} else {
				$ment .= "But Plus / Minus pattern show a nice advent in last 10 draw. Thus, you need to see it.";
			}

			$select_ment = $ment;
		}

		if(is_array($ment)) {
			
			// 이전멘트 확인하기
			$query = $this->db->query("SELECT COUNT(1) cnt FROM REPORT WHERE game='{$this->game}' AND idx < '{$this->idx}'  AND plusminusCnt = '{$ment_type}'");
			$rcnt = $query->fetch_assoc();
			$s = $rcnt['cnt'] % 2;

			$select_ment = $ment[$s];
		}

		return $this->ment_replace($select_ment);
    }


    #===========================================================
    # last digit ment
    # n : 마지막 게임 끝수 배열
    # k : 최근 10회에서 가장 많이 출현한 끝수 ( 파워포인트에서 #n/#m 이라고 표시된것 )
    # l : 최근 10회에서 가장 적게나온 끝수
    #===========================================================
    function lastDigitMent($lst)
    {
        $lastNum = $this->lastNum;
        unset($lastNum[5]);

        // 최근 10회차 last digit
        arsort($lst);
        $max_key = key($lst);
		$times = $max_vlu = current($lst);
		asort($lst);	
        $min_key = key($lst);
		$min_vlu = current($lst);

		$karr = array();
		$larr = array();
		foreach($lst as $a => $b) {
			if($b == $max_vlu) $karr[] = $a;
			if($b == $min_vlu) $larr[] = $a;
		}
		asort($karr);
		asort($larr);
		$k = "#". join(" and #", $karr);	// 최근 10회에서 가장 많이 나온 끝수
		$l = "#" . join(" and #", $larr);	// 최근 10회에서 가장 적게 나온 끝수

		$lastdigit = array();
		$showdigit = array();
        foreach($lastNum as $vlu) {
            $vlu = $vlu % 10;
            if(!in_array($vlu, $lastdigit)) $lastdigit[] = $vlu; 	// 끝번호 중복제거할때
			if(in_array($vlu, $karr))	$showdigit[] = $vlu;			// 최근 10회에서 가장 많이 나온 끝수 중 출현 성공한 끝번호
        }
        asort($lastdigit);
        $lastdigit = "#" . join(", #", $lastdigit);

        $s = ($this->total-1) % 3;
        if($s == 0) {
			$cnt = count($showdigit);
            if($cnt > 0 ) {
				$sub_title = $this->digitarr[$cnt]." of them";
			} else {
				$sub_title = "But except this";
			}

            $ment = "This draw, Last Digit <strong>{$lastdigit}</strong> have a jackpot number respectively. 
                {$sub_title}, Last Digit <strong>{$k}</strong> have the most repeatedly appearance in last 10 draw.
                So, it is important for you to consider to pick Last Digit <strong>{$k}</strong> for next draw.
                Please remember Last Digit <strong>{$l}</strong> because it is a week Last Digit in last 10 draw.";

        } else if($s == 1) {

            if(count($showdigit)) $sub_title = "were";
            else $sub_title = "weren't";

            $ment = "In <strong>{$this->thisdraw}</strong> draw, you {$sub_title} able to observe specificity pattern Last Digit <strong>{$k}</strong> which record the most frequency in last 10 draw. 
                It keeps up their advent steadily. 
                The weakest Last Digit pattern is <strong>{$l}</strong>, you also watch this.";

        } else {
            $ment = "In last 10 draw, main Last Digit is <strong>{$k}</strong>. It have seen a bull trend with <strong>{$times}</strong> appearance. 
                It is a striking contrast from Last Digit <strong>{$l}</strong> which show a weak power. Thus, you maybe consider to Last Digit's pattern when you purchase your lottery tickets.";

        }		

        return $this->ment_replace($ment);
    }

	#===========================================================
    # skip ment
    #===========================================================
	function skipMent($skip, $grade) 
	{
		$grade = strtolower($grade);

		$max_skip = 0;		// 이번게임에서 많이 스킵한 횟수
		for($i=0; $i<5; $i++) {
			$v = $this->lastNum[$i];
			if($max_skip < $skip[$v]) {
				$max_skip = $skip[$v];
			}
		}

		// 이번게임에서 가장 오랜만에 나온 숫자, 위치
		$n = array();
		$pos = array();
		$posarr = array("", "1<sup>st</sup>", "2<sup>nd</sup>", "3<sup>rd</sup>", "4<sup>th</sup>", "5<sup>th</sup>");
		for ($i=0; $i<5; $i++ ) {
			$v = $this->lastNum[$i];
			if($skip[$v] == $max_skip) {
				$n[] = $v;
				$pos[] = $posarr[$i+1];
			}
		}
		$n = "#" . join(" and #", $n);
		$pos = join(" and ", $pos);	
		
		// drawdt : n이 노출되었던 날짜
		if($max_skip > 0) {
			$query = $this->db->query("SELECT * FROM (SELECT drawDt FROM NUMBER_HISTORY WHERE game = '{$this->game}' {$this->where} ORDER BY drawDt DESC LIMIT ".($max_skip+2).") TBL ORDER BY drawDt ASC LIMIT 1");
			$row = $query->fetch_assoc();
			$drawdt = date("m/d/Y", strtotime($row['drawDt']));

		} else {
			$drawdt = $this->thisdraw;
		}

		// 최장기 미출수 3수, 같은횟수는 다 표시
		arsort($skip);
		$marr = array();
		$skipnum = 0;
		foreach($skip as $key => $vlu) {
			$cnt = count($marr);
			if($cnt >= 3 && $vlu < $skipnum) break;
			$marr[] = $key;
			$skipnum = $vlu;
		}		
		$no_show = "#" . join(", #", $marr);
		unset($marr);

		$s = ($this->total-1) % 3;
		if($s == 0) {
			$ment = "In this draw, <strong>{$n}</strong> is produced as jackpot number. It appear the first time when it was located <strong>{$pos}</strong> in <strong>{$drawdt}</strong> draw. 
				When you buy a lottery in next draw, you have to see <strong>{$no_show}</strong>. It is long term non-advent number.";

		} else if($s == 1) {
			$ment = "WhiteBall advent cycle of this draw is <strong>{$grade}</strong>. Among others jackpot number, <strong>{$n}</strong> unseen for a long time.
				It is also necessary for you to see <strong>{$no_show}</strong> because the numbers are the longest non-appearance number.
				You can pick one of them.";

		} else if($s == 2) {
			$ment = "<strong>{$n}</strong>, which is the longest non-advent number, become a jackpot number in <strong>{$this->thisdraw}</strong> draw.
				Its advent is the first time since <strong>{$drawdt}</strong>. To next draw, long term non-advent numbers are <strong>{$no_show}</strong>. You can select one of that.";
		}

		return $this->ment_replace($ment);

	}


    #===========================================================
    # gap ment
    #===========================================================
    function gapMent($appear, $gap)
    {
        $n = 0; // interval #n
        $v = -1; // 가장큰 gap값.
        for($i=1; $i<=4; $i++) {
            $g = abs($appear['I'.$i] - $gap['I'.$i]);
            if($v == -1 || $g > $v) {
                $v = $g;
                $n = $i;
            }
        }

        $v = $appear['I'.$n];
        $s = ($this->total-1) % 3;
        $per = round($appear['I'.$n] / $gap['I'.$n] * 100,2);

        if($s == 0) {
            if($per >= 100) $sub_title = 'more';
            else $sub_title = 'less';

            $ment = "<strong>Gap #{$n}</strong> of Gap pattern record an unusual value.
                <strong>Gap #{$n}'s</strong> value is <strong>{$v}</strong> on this draw, which is {$sub_title} than average in last 30 draw. <strong>{$v} is {$per}%</strong> compared to average in last 30 draw.";

        } else if($s == 1) {
            if($per >= 100) $sub_title = 'bigger';
            else $sub_title = 'smaller';
			
            $ment = "Gap pattern have an interesting value in their subsection.
                <strong>Gap #{$n}</strong> have {$sub_title} value than their last 30 draw average.";

        } else {
            if($per >= 100) $sub_title = 'biggest';
            else $sub_title = 'smallest';

            $average = $gap['I'.$n];

            $ment = "In this draw, <strong>Gap #{$n}</strong> of Gap pattern have the {$sub_title} value in all interval.
                <strong>Gap #{$n}</strong> in this draw is <strong>{$v}</strong> but its average in last 30 draw is <strong>{$average}</strong> value.";

        }

        return $this->ment_replace($ment);
    }

    #===========================================================
    # 10-interval ment
    #===========================================================
    function intervalMent($data)
    {
        $row = $data[0];    // 현재게임  
				
        if(count($row['interval']) == 5) {

			// 이전멘트 확인하기
			$query = $this->db->query("SELECT COUNT(1) cnt FROM REPORT WHERE game = '{$this->game}' AND idx < '{$this->idx}'  AND interval10Cnt = 5");
			$rcnt = $query->fetch_assoc();
			$s = $rcnt['cnt'] % 2;

			// 미출현구간
			$missing = array();
			for($i = 0; $i <=6; $i++) {
				$last = $i == 6 ? $this->totalBall : $i*10+10;
				if(!isset($row['interval'][$i])) $missing[] = ($i*10+1)."~".$last;
			}

			$missing = "#" . join(" and #", $missing);
			$ment = array(
				0 => "In this draw, all jackpot numbers are scattered five 10-interval pattern. Except <strong>{$missing}</strong>, each 10-interval have a jackpot number respectively.",
				1 => "10-interval pattern display a tendency with spread. Only two 10-interval have no jackpot number."
			);

			$select_ment = $ment[$s];

        } else {

			// 이전멘트 확인하기
			$query = $this->db->query("SELECT COUNT(1) cnt FROM REPORT WHERE game = '{$this->game}' AND idx < '{$this->idx}'  AND interval10Cnt != 5");
			$rcnt = $query->fetch_assoc();
			$s = $rcnt['cnt'] % 2;

			// 최다출구간(여러개일경우 다 표시)
			$n = 0;	// 구간
			$v = 0;	// 갯수
			$shows = array();
			foreach($row['interval'] as $key => $value) {
				$start = $key*10+1;
				$last = $key == 6 ? $this->totalBall : $key*10+10;

				if($v == 0 || $v < count($value)) {
					$n = $key;
					$v = count($value);
					$arr = array($start."~".$last);
				} else if ($v == count($value)) {
					$arr[] = $start."~".$last;
				}
			}
			$n = "#" . join(" and #", $arr);

			// 최다출구간갯수
			$digit = strtolower($this->digitarr[count($arr)]);
						
            $ment = array(
				0 => "This draw, <strong>{$digit}</strong> of 10-interval make a good result. It is <strong>{$n}</strong>. It has <strong>{$v}</strong> jackpot numbers in last draw.",
				1 => "<strong>{$n}</strong> record a good advent. It has <strong>{$v}</strong> jackpot numbers in last draw. You should look at this."
			);

			$select_ment = $ment[$s];
        }

        return $this->ment_replace($select_ment);
    }

    #===========================================================
    # neighbor ment
    #===========================================================
    function neighborMent($data)
    {
		$row = $data[0];	// 마지막게임
		
		// times : 출현횟수 ( 지난 10개 게임에서 동일한 패턴 출현횟수 )
		$times = 0;
		foreach($data as $srow) {
			if($srow['neighbor_type'] == $row['neighbor_type']) $times++;
		}
        
        if($row['neighbor_type'] == 0) { // 출현안함.

            // min : 간격이 가장 작은 두개번호
			$gap = array(
				$row['whiteBall2']-$row['whiteBall1'],
				$row['whiteBall3']-$row['whiteBall2'],
				$row['whiteBall4']-$row['whiteBall3'],
				$row['whiteBall5']-$row['whiteBall4']
			);
			asort($gap);
			$min = key($gap);
			$min = "#" . $row['whiteBall'.($min+2)]." and #".$row['whiteBall'.($min+1)];
            
            $ment = "Recent draw, Neighbor pattern fail to advent. Between all gap for jackpot numbers in <strong>{$this->thisdraw}</strong> draw are more than 2 such as <strong>{$min}</strong>.
                None-Neighbor pattern have <strong>{$times}</strong> advent in last 10 draw, you can consider to pick a number its result.";
			$select_ment = $ment;
        } else {

			// neighbor 번호들
			$neighbors = "";
			foreach($row['neighbor'] as $srow) {
				$neighbors .= "#". join(" and #", $srow) .", ";
			}
			$neighbors = substr($neighbors,0,-2);

			// pattern
			$pattern = $row['neighbor_name'];

			// 패턴의 최근 10회 기준 출현 횟수
			if($times >= 3) {
				$sub_title = "good";
				$sub_title2 = "big";
			} else {
				$sub_title = "bad";
				$sub_title2 = "small";
			}

			// 이전멘트 확인하기
			$query = $this->db->query("SELECT COUNT(1) cnt FROM REPORT WHERE game = '{$this->game}' AND idx < '{$this->idx}'  AND neighborCnt != 0");
			$rcnt = $query->fetch_assoc();
			$s = $rcnt['cnt'] % 2;

			$ment = array(
				0 => "This draw have a Neighbor pattern. <strong>{$neighbors}</strong> appear this draw, it corresponds to <strong>{$pattern}</strong> pattern. In last 10 draw, <strong>{$pattern}</strong> pattern record <strong>{$times}</strong> times appearance.
						It is a {$sub_title} tendency, so you need to watch it.",
				1 => "<strong>{$pattern}</strong> pattern success an appearance in last draw. Its pattern consist of <strong>{$neighbors}</strong>. 
						<strong>{$pattern}</strong> pattern have <strong>{$times}</strong> times advent in last 10 draw, this record is a {$sub_title2} value compare to expectation about <strong>{$pattern}</strong> pattern about 2.65 times in 10 draw."
			);

			$select_ment = $ment[$s];
        }

		return $this->ment_replace($select_ment);
    }
}