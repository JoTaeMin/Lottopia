<?php
require_once(__DIR__ . "/connect.php");

/*
 * 1. 회원에게 토큰발급하기
 */
$db = connect_db();
$today = date("Y-m-d");
$issuedDt = date("Y-m-d", time()-(86400*7));

echo date("Y-m-d H:i:s"). " 실행 \n";

// 토큰 50개 이상 보유하면 성취 달성
$aquery = $db->query("SELECT * FROM ACHIEVEMENT WHERE idx = 7 AND used = 'Y' AND CASE WHEN IFNULL(sdate, '0000-00-00') != '0000-00-00' THEN sdate <= '$today' ELSE TRUE END");
$adata = $aquery->fetch_assoc();

// 일주일전에 발급한사람
$qry = "
SELECT A.* 
FROM PAYMENT_LOG A LEFT JOIN PAYMENT B ON A.pIdx = B.idx 
WHERE A.type='plan' 
	AND IFNULL(A.issuedCnt, 0) < A.token 
	AND IFNULL(A.issuedDt,'') = '$issuedDt' 
	AND A.cancelYn='N' 
	AND B.cancelYn='N'
ORDER BY A.idx ASC
";
$query = $db->query($qry);

$cnt_false = 0;
$cnt_true = 0;
while ($row = $query->fetch_assoc()) {
	$issuedCnt = $row['issuedCnt'] == '' ? 0 : $row['issuedCnt'];	// 발급한 토큰
	$remainCnt = $row['token'] - $issuedCnt;	// 남은 토큰
	if($remainCnt < 0) continue;

    $token = $row['doubleup'] > 0 ? 20 : 10;    // 지급할 토큰 (더블업회원은 20개 지급, 일반은 10개지급)
	if($remainCnt < $token) $token = $remainCnt;	
	
    $qry = "INSERT INTO TOKENS_HISTORY (member_idx, type, addCnt, useCnt, regDt) VALUES ('".$row["member_idx"]."', 'A', '$token', 0, now())";
    $query2 = $db->query($qry);
    $idx = $db->insert_id;
    if($query2) {
        if(!$db->query("UPDATE PAYMENT_LOG SET issuedDt = '$today', issuedCnt = issuedCnt + {$token} WHERE idx = '".$row['idx']."'")) {
            $db->query("DELETE FROM TOKENS_HISTORY WHERE idx = '$idx'");
            $cnt_false++;
        } else {
			$cnt_true++;
			
			// achievement 
			if(is_array($adata)) {

				// 받은기록 여부 체크
				$qry = "
				SELECT COUNT(1) cnt 
				FROM ACHIEVEMENT_LOG A LEFT JOIN ACHIEVEMENT B ON A.aIdx = B.idx 
				WHERE A.member_idx = '".$row['member_idx']."' AND A.aIdx = 7  AND CASE WHEN IFNULL(B.sdate, '0000-00-00') != '0000-00-00' THEN A.regDt >= B.sdate ELSE TRUE END 			
				";
				$query3 = $db->query($qry);
				$srow = $query3->fetch_assoc();

				if($srow['cnt'] == 0) {
				
					$where = ($adata['sdate'] != '' && $adata['sdate'] != '0000-00-00') ? " AND regDt >= '".$adata['sdate']."'" : "";

					// 나의 보유 토큰
					// 성취로 받은 토큰 제외
					$qry = "
					SELECT SUM(addCnt) cnt  
					FROM TOKENS_HISTORY 
					WHERE member_idx = '".$row['member_idx']."' {$where} AND type = 'A' AND IFNULL(gubun,'') != 'A'
					";
					$query3 = $db->query($qry);
					$trow = $query3->fetch_assoc();
					$mytoken = $trow['cnt'] == '' ? 0 : $trow['cnt'];
					if($mytoken >= $adata['goal']) {	// 지급
						$query3 = $db->query("INSERT INTO ACHIEVEMENT_LOG (aIdx, member_idx, token, regDt) VALUES ('7', '".$row['member_idx']."', '".$adata['token']."', now())");
						if($query3){
							$db->query("INSERT INTO TOKENS_HISTORY (member_idx, type, addCnt, useCnt, gubun, regDt) VALUES ('".$row['member_idx']."', 'A', '".$adata['token']."', 0, 'A', now())");
						}
					}
				}

			}
		}
    } else {
        continue;
    }
}

echo "true count : ". $cnt_true."\n";
echo "fail count : ". $cnt_false."\n";
if(isset($query2)) {
    if ($query2) {
        echo "success member_tokens_issued";
    } else {
        echo "fail member_tokens_issued";
    }
}

echo "\n==============================\n";
?>

