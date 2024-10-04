<? include_once $_SERVER["DOCUMENT_ROOT"] . "/common/include/program/include.php"; ?>
<?php
$ocode = gRequest("ocode");

// 관리자에서 접근 시 모두 조회가능 , 회원은 본인 데이터만 조회 가능
if(strpos($_SERVER['HTTP_REFERER'], 'wcms') !== false) {
    $se_query = " WHERE tb1.ocode = '$ocode'  ";
} else if($_REQUEST['isApp'] == true) { // 어플 접근 시 조회
    $se_query = " WHERE tb1.ocode = '$ocode'  ";
} else {
    if(!$sesCustSeq){
        echo "<script>alert('로그인 후 이용해주세요.'); self.close(); location.href='/main/main.php';</script>";
    }
    $se_query = " WHERE tb1.ocode = '$ocode' AND tb1.cust_seq = '$sesCustSeq' ";
}

$SQL	 = "SELECT tb1.* , tb2.*,	";
$SQL  	.=	"	TIMESTAMPDIFF(YEAR, tb3.birth_day1, CURDATE()) AS age	";
$SQL  	.=	"	FROM wt_order_info tb1										";
$SQL  	.=	"		JOIN wt_apply_analysis tb2 ON tb1.ocode = tb2.ocode		";
$SQL  	.=	"		JOIN wt_member tb3 ON tb3.user_id = tb1.user_id				";	//tb3 생년월일 출력 위해 join
$SQL	.= $se_query;



$result = mysqli_query($connect, $SQL);
if (mysqli_num_rows($result) === 0) {
    echo "<script>alert('현재 결과 내용은 삭제되었거나 잘못된 경로로 들어오셨습니다.'); self.close(); location.href='/main/main.php';</script>";
}
$row = mysqli_fetch_array($result);
$Name=$row['or_name'];

$pageNm = '미술성향검사_'.$Name;
?>
<? include_once $_SERVER["DOCUMENT_ROOT"] . "/common/include/default/meta.php"; ?>
<!DOCTYPE html>
<?



	/*함수 모음*/

	##영역분석 별 높은 점수와 분야 추출
	function field_MAX($array){
		$field = array();

		foreach($array as $row) :
			$push = array( $row['code_nm2'] => $row['result_cd'] );
			$field = array_merge($field, $push);
		endforeach;
		return $field;
	}

	## 두번째 큰 값 함수
	function secondMax($array, $max_title){
		unset($array[$max_title]);		//최대값 삭제(중복일시 에러로 삭제)

		$tmpArray = $array;
		//unset( $tmpArray, max($tmpArray) );
		rsort($tmpArray);
		//echo (array_search($tmpArray[0],$array));

		return array_search($tmpArray[0],$array);
	}

	/*함수 모음*/



$school_grade=$row['school_grade'];
if($row['evaluation_keywords'] && $row['etc_memo_keywords'] && $row['etc_memo_keywords'] !== 'null' && $row['etc_memo_keywords'] !== 'null') :
	$evaluation_keywords = json_decode($row['evaluation_keywords'], TRUE);
	$etc_memo_keywords = json_decode($row['etc_memo_keywords'], TRUE);
	foreach ($etc_memo_keywords as $key => $val) {
		if (isset($evaluation_keywords[$key])) {
			$evaluation_keywords[$key] += $val;
		} else {
			$evaluation_keywords[$key] = $val;
		}
	}
	arsort($evaluation_keywords);
endif;


## 2801 강점, 2802 약점, 2803 선호도
function recomm_SQL($ocode, $recommend_gb, $connect){
	$SQL = "SELECT * FROM wt_order_recomm_program WHERE ocode = '$ocode' AND del_yn = 'N' AND use_yn = 'Y' AND recommend_gb = '$recommend_gb'";
	return mysqli_query($connect, $SQL);
}

$result_STR = recomm_SQL($ocode,'2801', $connect);
$result_WEAK = recomm_SQL($ocode,'2802', $connect);
$result_PREFER = recomm_SQL($ocode,'2803', $connect);


$i = 0;
while ($list = mysqli_fetch_assoc($result_STR)) {
	$STR_list[$i] = $list['product_cd'];
	$i++;
}
$i = 0;
while ($list = mysqli_fetch_assoc($result_WEAK)) {
	$WEAK_list[$i] = $list['product_cd'];
	$i++;
}
$i = 0;
while ($list = mysqli_fetch_assoc($result_PREFER)) {
	$PREFER_list[$i] = $list['product_cd'];
	$i++;
}

if ($STR_list) : $j = 0;
	foreach ($STR_list as $STR_row) {
		$SQL_STR = "SELECT * FROM wt_product WHERE product_cd = '$STR_row'"; //강점 - 추천
		$result_STR = mysqli_query($connect, $SQL_STR);
		$row_STR[$j] = mysqli_fetch_assoc($result_STR);
		$j++;
	}
endif;

if ($WEAK_list) : $j = 0;
	foreach ($WEAK_list as $WEAK_row) {
		$SQL_WEAK = "SELECT * FROM wt_product WHERE product_cd = '$WEAK_row'"; //약점 - 추천
		$result_WEAK = mysqli_query($connect, $SQL_WEAK);
		$row_WEAK[$j] = mysqli_fetch_assoc($result_WEAK);
		$j++;
	}
endif;

if ($PREFER_list) : $j = 0;
	foreach ($PREFER_list as $PREFER_row) {
		$SQL_PREFER = "SELECT * FROM wt_product WHERE product_cd = '$PREFER_row'"; //선호 - 추천
		$result_PREFER = mysqli_query($connect, $SQL_PREFER);
		$row_PREFER[$j] = mysqli_fetch_assoc($result_PREFER);
		$j++;
	}
endif;
?>
<link rel="stylesheet" href="css/critic_view_aat.css">
</head>
<body>
<? include_once $_SERVER["DOCUMENT_ROOT"] . "/common/include/default/skip.php"; ?>
<section class="wrapper" id="wrapper">

	<!--  contents_wrap  -->
	<section class="contents my_studio critic_view_page" id="contents">
		<div class="wrap">
			<!--<div class="critic_top">
				<img src="/common/images/mystudio/critic_view_cover.png" alt="크리틱 결과지">
				<button type="button" class="close_btn" onclick="self.close();">레이어닫기</button>
			</div>-->

            <?
            ##--	$sql = " SELECT tb1.*, tb2.answer_seq, tb2.answer_text
            ##--	 FROM wt_analysis_question tb1
            ##--	 LEFT JOIN wt_apply_analysis tb2
            ##--	 ON tb2.question_seq = tb1.question_seq
            ##--	 AND tb2.ocode = '$ocode'
            ##--	 WHERE use_yn = 'Y' AND gubun = '2103'
            ##--	 ORDER BY tb1.od, tb1.question_seq ";

            ##-- 미술적성검사 항목 조회 (gubun = '2103') - 학년등급 공통(404)항목과, 신청자의 school_grade 에 해당되는 미술적성검사 항목만 조회되도록 수정-2022-04-28-kjy
            $schoolGradeQuery = '';
            if($school_grade == '403') $schoolGradeQuery = "waq.school_grade = '402'";
            else $schoolGradeQuery = "waq.school_grade = '".$school_grade."'";

            $sql = "SELECT waa.analysis_seq, waa.ocode, waa.question_seq, waq.*,
                            waa.answer_seq, wae.example_nm, waa.answer_text, wae.point,
                            (SELECT group_concat(tmp.example_seq) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as example_list,
                            (SELECT group_concat(tmp.point) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as point_list
                        FROM wt_apply_analysis waa
                        LEFT JOIN wt_analysis_question waq ON waa.question_seq = waq.question_seq AND waq.gubun = '2103' AND waq.use_yn = 'Y'
                        LEFT JOIN wt_analysis_example wae ON waa.answer_seq = wae.example_seq
                        WHERE waq.question_seq IS NOT NULL AND waa.ocode = '$ocode'
                            AND ($schoolGradeQuery OR waq.school_grade = '404')
                        ORDER BY waq.od";

            //if( $_SERVER["REMOTE_ADDR"] == "112.216.230.42" ){
            //	echo "school_grade: " . $school_grade . "<br>";
            //	echo "sql: " . $sql . "<br>";
            //}


            $result = mysqli_query($connect, $sql);
            $row = mysqli_fetch_array($result);

            function calcMultiChoicePoint($ans, $exl, $expl){ // 다중선택인 경우 점수 계산
                $rp = 0;

                $ansList = explode(',', $ans);

                $exList = explode(',', $exl);
                $expList = explode(',', $expl);

                for($i=0; $i<count($exList); $i++){
                    $ex = $exList[$i];
                    $exp = $expList[$i];

                    if(in_array($ex, $ansList)) $rp += (int)$exp;
                }

                return $rp;
            }

            if ($row) {
                $i = 0;
                $cnt = 0;
                $typeTotalPoint = 0;
                $literacyPoint = 0;
                $visualPoint = 0;
                $spatialPoint = 0;
                while ($row) {
                    $order         = (int)$row['od'];
                    $question_seq  = $row['question_seq'];
                    $question_nm   = $row['question_nm'];
                    $gubun         = $row['gubun'];
                    $input_type    = $row['input_type'];
                    $etc_yn        = $row['etc_yn'];
                    $answer_seq    = $row['answer_seq'];
                    $answer_text   = $row['answer_text'];
                    $point         = $row['point'];
                    $example_list  = $row['example_list'];
                    $point_list    = $row['point_list'];
                    ?>
                    <?
                    if ($input_type == 'A' || $input_type == 'B') {

                        if($input_type == 'B') { // 다중선택
                            $point = calcMultiChoicePoint($answer_seq, $example_list, $point_list);
                        }

                        if($order <= 25 && $order != 19) {
                            $cnt++;
                            if($cnt % 3 == 1) $typeTotalPoint = 0;
                            $typeTotalPoint += $point;
                            if($order == 1 || $order == 2 || $order == 3){
                                $type1 = $typeTotalPoint < 0 ? 'I' : 'E';
                                $type1Txt = $typeTotalPoint < 0 ? '내면 세계' : '외부 세계';
                                $direction1 = $typeTotalPoint < 0 ? 'right' : 'left';
                                $type1Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }else if($order == 4 || $order == 5 || $order == 6){
                                $type2 = $typeTotalPoint < 0 ? 'R' : 'A';
                                $type2Txt = $typeTotalPoint < 0 ? '재현적' : '추상적';
                                $direction2 = $typeTotalPoint < 0 ? 'left' : 'right';
                                $type2Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }else if($order == 7 || $order == 8 || $order == 9){
                                $type3 = $typeTotalPoint < 0 ? 'D' : 'N';
                                $type3Txt = $typeTotalPoint < 0 ? '장식적' : '자연주의적';
                                $direction3 = $typeTotalPoint < 0 ? 'right' : 'left';
                                $type3Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }else if($order == 10 || $order == 11 || $order == 12){
                                $type4 = $typeTotalPoint < 0 ? 'C' : 'S';
                                $type4Txt = $typeTotalPoint < 0 ? '인지적' : '정서적';
                                $direction4 = $typeTotalPoint < 0 ? 'left' : 'right';
                                $type4Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }else if($order == 13 || $order == 14 || $order == 15){
                                $type5 = $typeTotalPoint < 0 ? 'O' : 'G';
                                $type5Txt = $typeTotalPoint < 0 ? '유기적' : '기하학적';
                                $direction5 = $typeTotalPoint < 0 ? 'left' : 'right';
                                $type5Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }else if($order == 16 || $order == 17 || $order == 18){
                                $type6 = $typeTotalPoint < 0 ? '2' : '3';
                                $type6Txt = $typeTotalPoint < 0 ? '평면적' : '입체적';
                                $direction6 = $typeTotalPoint < 0 ? 'left' : 'right';
                                $type6Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }else if($order == 20 || $order == 21 || $order == 22){
                                $type7 = $typeTotalPoint < 0 ? '난색' : '한색';
                                $type7Txt = $typeTotalPoint < 0 ? '난색' : '한색';
                                $direction7 = $typeTotalPoint < 0 ? 'left' : 'right';
                                $type7Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }else if($order == 23 || $order == 24 || $order == 25){
                                $type8 = $typeTotalPoint < 0 ? '보색대비' : '유사대비';
                                $type8Txt = $typeTotalPoint < 0 ? '명쾌하고 화려한' : '부드럽고 정적인';
                                $direction8 = $typeTotalPoint < 0 ? 'left' : 'right';
                                $type8Val = number_format(abs($typeTotalPoint * 100 / 9), 2);
                            }
                        }else if($order == 19){
                            switch($point){
                                case 1 :
                                    $color = '빨강';
                                    $colorCode = '#f00';
                                    $colorTxt1 = '강한, 용감한, 활기찬';
                                    $colorTxt2 = '소심한, 화를 내는, 성급한';
                                    $colorTxt3 = '독립적인 성격으로 결과 지향적이고 즉각적인 보상이 있는 것에 흥미를 보임';
                                    $colorTxt4 = '관념적인 것보다는 실용적인 교과를 선호함';
                                    break;
                                case 2 :
                                    $color = '주황';
                                    $colorCode = '#ff4c00';
                                    $colorTxt1 = '사교적인, 창조적인, 열성적인';
                                    $colorTxt2 = '과시적인, 단정치 못한, 환경에 예민한';
                                    $colorTxt3 = '호기심이 많고 활동적이며, 재미있고 창조적인 것을 만드는 것을 선호함';
                                    $colorTxt4 = '이론적 수업에서도 창조적 활동을 병행하는 것이 효과적임';
                                    break;
                                case 3 :
                                    $color = '노랑';
                                    $colorCode = '#ffe150';
                                    $colorTxt1 = '낙천적인, 지식을 추구하는, 친절한';
                                    $colorTxt2 = '냉담한, 의심이 많은, 계산적인';
                                    $colorTxt3 = '지식에 대한 의욕이 높고 행복하고 충동적임';
                                    $colorTxt4 = '빨리 배우며 친구와 함께하는 것을 즐김';
                                    break;
                                case 4 :
                                    $color = '초록';
                                    $colorCode = '#07b31a';
                                    $colorTxt1 = '조화로운, 열정적인, 행복한';
                                    $colorTxt2 = '걱정이 많은, 질투하는, 민감한';
                                    $colorTxt3 = '민감하고 감정이 풍부하며 약한 사람을 보호하고 타인을 돌보는 것을 좋아함';
                                    $colorTxt4 = '직관력이 뛰어나고 전체적으로 생각하며 실내활동을 즐김';
                                    break;
                                case 5 :
                                    $color = '파랑';
                                    $colorCode = '#4358A5';
                                    $colorTxt1 = '개방적인, 용감한, 창조적인';
                                    $colorTxt2 = '지나치게 야심찬, 냉소적인';
                                    $colorTxt3 = '상황을 정확하게 파악하여 아이디어를 실현하는 방법을 알고 있으며, 예술과 아름다움을 좋아하는 예술 수집가의 기질을 가짐';
                                    $colorTxt4 = '지적이고 질문을 많이 하며, 독창적인 문제해결을 즐김';
                                    break;
                                case 6 :
                                    $color = '남색';
                                    $colorCode = '#0c1a91';
                                    $colorTxt1 = '이상적인, 대담한, 예민한';
                                    $colorTxt2 = '우울한, 관계를 멀리하는, 훈련되지 않은';
                                    $colorTxt3 = '옳고 그림에 대한 확고한 판단을 가지고 있으며, 진실을 추구함';
                                    $colorTxt4 = '탐구적인 성향을 가지며 예민한 편이라 혼자 있는 경우가 있음';
                                    break;
                                case 7 :
                                    $color = '보라';
                                    $colorCode = '#7102d8';
                                    $colorTxt1 = '카리스마가 있는, 예술적인, 인도주의적인';
                                    $colorTxt2 = '거만한, 광적인, 공상가 같은';
                                    $colorTxt3 = '에너지가 넘치고 타고난 매력이 있는 지도자 형으로 직관적인 이상주의자 성향을 보임';
                                    $colorTxt4 = '상상하는 활동에 에너지를 쏟고 예술적인 영감을 많이 받음';
                                    break;
                            }

                        }else if($order == 26 || $order == 27 || $order == 28 || $order == 29 || $order == 30){
                            $literacy1Point += ($point * 2);
                            if($order == 30) $literacy1Per = number_format(abs($literacy1Point * 100 / 14), 2);
                        }else if($order == 31 || $order == 32){
                            $literacy2Point += $point;
                            if($order == 32) {
                                $literacyPoint = $literacy1Point + $literacy2Point;
                                $literacyPer = number_format(abs($literacyPoint * 100 / 16), 2);
                            }
                        }else if( $order == 33 || $order == 34 || $order == 35 || $order == 36 || $order == 37 || $order == 38 || $order == 39 ){
                            $visualPoint += $point;
                            if($order == 39) $visualPer = number_format(abs($visualPoint * 100 / 10), 2);
                        }else if($order == 40 || $order == 41 || $order == 42 || $order == 43 || $order == 44 || $order == 45){
                            $spatialPoint += $point;
                            if($order == 45) $spatialPer = number_format(abs($spatialPoint * 100 / 6), 2);
                        }else if($order == 46 || $order == 47 || $order == 48 || $order == 49){
                            $implicitType1Point += $point;
                            if($order == 49) $implicitType1Per = number_format(abs($implicitType1Point * 100 / 20), 2);
                        }else if($order == 50 || $order == 51 || $order == 52 || $order == 53){
                            $implicitType2Point += $point;
                            if($order == 53) $implicitType2Per = number_format(abs($implicitType2Point * 100 / 20), 2);
                        }else if($order == 54 || $order == 55 || $order == 56 || $order == 57){
                            $implicitType3Point += $point;
                            if($order == 57) $implicitType3Per = number_format(abs($implicitType3Point * 100 / 20), 2);
                        }else if($order == 58 || $order == 59 || $order == 60 || $order == 61){
                            $achieveType1Point += $point;
                            if($order == 61) $achieveType1Per = number_format(abs($achieveType1Point * 100 / 20), 2);
                        }else if($order == 62 || $order == 63 || $order == 64 || $order == 65){
                            $achieveType2Point += $point;
                            if($order == 65) $achieveType2Per = number_format(abs($achieveType2Point * 100 / 20), 2);
                        }else if($order == 66 || $order == 67 || $order == 68 || $order == 69){
                            $achieveType3Point += $point;
                            if($order == 69) $achieveType3Per = number_format(abs($achieveType3Point * 100 / 20), 2);
                        }

                    }
                    ?>
                    <?
                    $row = mysqli_fetch_array($result);
                }
            }
            ?>

			<div class="inner">
                <!--미술성향검사 결과안내-->
                <div class="result__announce">
                    <div class="print_content">
                        <div class="att_title">
                            i보다 미술성향검사 (AAT) 안내
                        </div>
                        <div class="aat_intro_wrap">
                            <div class="aat_intro_con">
                            <div class="announce_title">
                                1. 결과를 보기 전에 꼭 읽어주세요!
                            </div>
                            <div class="announce_text">
                                미술성향은 타고난 기질, 교육환경과 경험, 신체적 조건 등 다양한 개인적, 사회적 요소에 의해 복합적으로 형성됩니다. i<span class="red">보다</span> AAT는 누구나 미술에 대한 자신만의 선호와 강점을 가지고 있다는 전제에서
                                출발합니다.
                                또한, <b class="fontweight800">미술 능력과 취향은 태어나면서 완전히 결정되는 것이 아니라, 환경에 따라 변화하며 언제든 개발될 수 있다는 믿음에 기초</b>합니다. i<span class="red">보다</span> AAT는
                                미술에 대한 각자의 성향과 강점을 정확히 파악하여 미술적 잠재력을
                                개발하는 데 활용할 수 있는 기초 자료를 제공하는 데 목적이 있습니다.
                            </div>
                            </div>
                            <div class="aat_intro_con">
                            <div class="announce_title">
                                2. AAT는 세 가지 척도를 활용해 검사자의 성향을 분석해요!
                            </div>
                            <div class="aat_standard_wrap">
                                <div class="aat_standard_con">
                                <div class="aat_standard_title_con">
                                    <div class="aat_box_title one">미적 감수성 영역</div>
                                    <div class="aat_standard_sub_title">
                                    <span class="point" style="--circle : #ED9D9A;"><span>A</span></span><span>esthetic
                                        Sensibility</span>
                                    </div>
                                </div>
                                <div class="announce_text red">
                                    미적 감수성 영역에서는 다양한 미술작품과 이미지의 주제, 양식, 조형 언어에 대한 개인의 미적 판단에 따라 개별적인 성향을 분석합니다. 또한 미술에서의 관심 주제와 이를 전달하는 방식, 그리고 조형적 특성에 대한
                                    개인적 선호를 파악합니다.
                                </div>
                                </div>
                                <div class="aat_standard_con">
                                <div class="aat_standard_title_con">
                                    <div class="aat_box_title two">시각 인지 영역</div>
                                    <div class="aat_standard_sub_title">
                                    <span>Visual&nbsp;</span> <span class="point"
                                        style="--circle : #BCDCB8;"><span>C</span></span><span>ognition</span>
                                    </div>
                                </div>
                                <div class="announce_text red">
                                    시각 인지 영역에서는 미술 활동에서 요구되는 시각적 사고의 특성을 파악합니다. 시각적 문해력 평가를 통해 이미지를 관찰, 분석, 해석하는 능력을 파악합니다. 공간지각은 서로 다른 이미지를 비교하고 인지적으로 조작하는
                                    것으로 이 능력의 평가를 통해 개인의 창작과 감상 활동에서의 강점을 발견합니다.
                                </div>
                                </div>
                                <div class="aat_standard_con">
                                <div class="aat_standard_title_con">
                                    <div class="aat_box_title three">정서행동 영역</div>
                                    <div class="aat_standard_sub_title">
                                    <span class="point" style="--circle : #CEECFF;"><span>E</span></span><span>motional
                                        Behavior</span>
                                    </div>
                                </div>
                                <div class="announce_text">
                                    정서행동 영역에서는 자기주도적 미술 활동에서 중요한 요소가 되는 내적 동기와 성취 동기를 파악합니다. 내적 동기는 미술 자체에 목적으로 두고 창작 과정에 스스로 참여하는 것입니다. 성취 동기는 작품을 완수하고자 하는
                                    열정과 자신감을 의미합니다. 이를 통해 미술 활동에 대한 자신의 정서적, 행동적 특성과 태도를 이해할 수 있습니다.
                                </div>
                                </div>
                            </div>
                            </div>
                            <div class="aat_intro_con">
                            <div class="announce_title">
                                3. 검사 결과의 이해를 위한 도움말
                                <span class="important">중요!!</span>
                            </div>
                            <ul class="list_style_0">
                                <li>
                                본 결과는 검사를 실시하는 시점에서의 경향성을 분석한 것으로, 평생 지속되는 것으로 단정할 수 없으며 성장 과정에서 변화할 수 있습니다.
                                </li>
                                <li>
                                유사한 성향을 가진 사람들 가운데에도 많은 개별적 차이가 존재합니다.
                                </li>
                                <li>
                                본 결과는 미술 능력에 대한 평가가 아닌 독특한 성향을 분석한 것으로 향후 창작과 미술 학습을 위한 자료로 활용될 수 있습니다.
                                </li>
                                <li>
                                어떤 성향인지를 파악하는 것 못지않게 이를 어떻게 개발할 것인지가 중요합니다. 따라서 발달 단계에 따른 적절한 자극과 교육적 경험이 중요합니다.
                                </li>
                            </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!--미술성향검사 결과-->
                <div class="aat_result_wrap">
                    <!--결과-->
                    <div class="aat_result_con">
                        <div class="aat_result_title">iBoda 미술성향검사(AAT) 결과 종합</div>
                        <div class="aat_detail_wrap">
                            <div class="aat_detail_con">
                                <div class="print_content">
                                    <div class="print_title analyze">
                                        <h1>iBoda 미술성향검사(AAT) 결과 종합</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="result_title">
                                            <img src="./_img/common/iboda_result.png" alt="결과 아이콘">
                                            <h1>i보다 미술성향검사에 대한 <span><?= $Name ?>님의 종합결과</span> 입니다.</h1>
                                        </div>
                                        <div class="aat_result">
                                            <h3 class="result_sub-tit">미적감수성 영역</h3>
                                            <div class="result_summary subtract">
                                                <h4><?= $Name ?>님의 미적성향은 <span class="result">[<?= $type1.$type2.$type3.$type4 ?>]</span>입니다.</h4>
                                                <h4><?= $Name ?>님의 미적선호는 <span class="result">[<?= $type5 ?>-<?= $type6 ?>]</span>입니다.</h4>
                                            </div>
                                        </div>
                                        <div class="result1_alpha">
                                            <div class="result-alpha part1">
                                                <div class="alpha">
                                                    <span class="alpha-tit">표현주제</span>
                                                    <b><?= $type1 ?></b>
                                                    <span><?= $type1Txt ?></span>
                                                </div>
                                                <div class="alpha">
                                                    <span class="alpha-tit">구성방식</span>
                                                    <b><?= $type2 ?></b>
                                                    <span><?= $type2Txt ?></span>
                                                </div>
                                                <div class="alpha">
                                                    <span class="alpha-tit">표현양식</span>
                                                    <b><?= $type3 ?></b>
                                                    <span><?= $type3Txt ?></span>
                                                </div>
                                                <div class="alpha">
                                                    <span class="alpha-tit">감정이입</span>
                                                    <b><?= $type4 ?></b>
                                                    <span><?= $type4Txt ?></span>
                                                </div>
                                            </div>
                                            <span class="dash">/</span>
                                            <div class="result-alpha part2">
                                                <div class="alpha">
                                                    <span class="alpha-tit">선 및 형태</span>
                                                    <b><?= $type5 ?></b>
                                                    <span><?= $type5Txt ?></span>
                                                </div>
                                                <div class="alpha">
                                                    <span class="alpha-tit">공간</span>
                                                    <b><?= $type6 ?></b>
                                                    <span><?= $type6Txt ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="result_detail">
                                            <div class="result-dtl__alpha">
                                                <div class="dtl-alpha">
                                                    <div class="left <?php if($type1 == 'E') {?> active <?php }?>">
                                                        <span>외부세계</span>
                                                        <span>Extrovert</span>
                                                    </div>
                                                    <span class="line"></span>
                                                    <div class="right <?php if($type1 == 'I') {?> active <?php }?>">
                                                        <span>Introspective</span>
                                                        <span>내면세계</span>
                                                    </div>
                                                </div>
                                                <div class="dtl-alpha">
                                                    <div class="left <?php if($type2 == 'R') {?> active <?php }?>">
                                                        <span>재현적</span>
                                                        <span>Representational</span>
                                                    </div>
                                                    <span class="line"></span>
                                                    <div class="right <?php if($type2 == 'A') {?> active <?php }?>">
                                                        <span>Abstract</span>
                                                        <span>추상적</span>
                                                    </div>
                                                </div>
                                                <div class="dtl-alpha">
                                                    <div class="left <?php if($type3 == 'N') {?> active <?php }?>">
                                                        <span>자연주의적</span>
                                                        <span>Naturalistic</span>
                                                    </div>
                                                    <span class="line"></span>
                                                    <div class="right <?php if($type3 == 'D') {?> active <?php }?>">
                                                        <span>Decorative</span>
                                                        <span>장식적</span>
                                                    </div>
                                                </div>
                                                <div class="dtl-alpha">
                                                    <div class="left <?php if($type4 == 'C') {?> active <?php }?>">
                                                        <span>인지적</span>
                                                        <span>Cognition</span>
                                                    </div>
                                                    <span class="line"></span>
                                                    <div class="right <?php if($type4 == 'S') {?> active <?php }?>">
                                                        <span>Sensitive</span>
                                                        <span>정서적</span>
                                                    </div>
                                                </div>
                                                <div class="dtl-alpha">
                                                    <div class="left <?php if($type5 == 'O') {?> active <?php }?>">
                                                        <span>유기적</span>
                                                        <span>Organic</span>
                                                    </div>
                                                    <span class="line"></span>
                                                    <div class="right <?php if($type5 == 'G') {?> active <?php }?>">
                                                        <span>Geometric</span>
                                                        <span>기하학적</span>
                                                    </div>
                                                </div>
                                                <div class="dtl-alpha">
                                                    <div class="left <?php if($type6 == '2D') {?> active <?php }?>">
                                                        <span>평면적</span>
                                                        <span>2-Dimensional</span>
                                                    </div>
                                                    <span class="line"></span>
                                                    <div class="right <?php if($type6 == '3D') {?> active <?php }?>">
                                                        <span>3-Dimensional</span>
                                                        <span>입체적</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="result_text">
                                                <?= $Name ?>님은 작품의 주제를 탐색할 때 <span class="active"><?= $type1Txt ?></span>에 관심이 있으며, 이를 <span class="active"><?= $type2Txt ?></span>인 방식으로 나타내는 것을 선호합니다. 작품을 창작할 때 <span class="active"><?= $type3Txt ?></span> 표현 양식을 선호하며, 작품을 통해 관람자와 <span class="active"><?= $type4Txt ?></span> 교감을 갖고자 합니다.
                                            </p>
                                            <p class="result_text">
                                                <?= $Name ?>님은 <span class="point0"><?= $type5Txt ?></span> 형태를 <?= $type6Txt ?>적 공간에서 탐구하는 것을 선호합니다. 당신의 선호색인 <span class="point0"><?= $color ?></span>은 <span class="point0"><?= $colorTxt1 ?></span> 느낌과 연관됩니다. 또한 <span class="point0"><?= $colorTxt1 ?></span> 이미지를 전달하는 <span class="point0"><?= $type7Txt ?></span>을 선호하며 <span class="point0"><?= $type8Txt ?></span> 느낌을 나타내는 <?= $type8 ?>를 선호합니다. <?= $Name ?>님이 선호하는 이러한 선, 형, 공간, 색채의 다양한 시각적 효과를 탐구하여 자신만의 독창적인 조형 양식을 발전시켜봅시다.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="print_content">
                                    <div class="print_title analyze">
                                        <h1>iBoda 미술성향검사(AAT) 결과 종합</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="aat_result green">
                                            <h3 class="result_sub-tit">시각 인지 영역</h3>
                                            <div class="result_summary">
                                                <h4><?= $Name ?>님의 강점은 <span class="result">[<?= $visualTxt ?>]</span> 입니다.</h4>
                                            </div>
                                            <div class="result_word">
                                                <span><?= $visualTxt2 ?></span>
                                                <h2><?= $visualTxt ?></h2>
                                                <span><?= $visualTxt3 ?></span>
                                            </div>
                                            <div class="result_detail">
                                                <p class="result_text">
                                                    <?= $Name ?>님은 이미지를 말로 표현하거나 시각 정보를 읽어내는 <span class="active">시각적 문해력</span>이 <?= $literacyLevelTxt ?>
                                                    또한 시각 이미지의 지각 능력은 <?= $visualLevelTxt ?>
                                                    시각 이미지를 머릿속에서 재생, 조작, 변형하는 공간지각능력은 <?= $spatialLevelTxt ?>
                                                    주변의 시각 이미지를 주의 깊게 관찰하고 분석하는 습관을 가져봅시다.
                                                    일상 생활 속에서 ‘보는 감각’을 적극적으로 활용하고 많은 흥미로운 것을 발견함으로써 창작활동에 영감을 얻을 수 있습니다.
                                                </p>
                                            </div>
                                        </div>
                                        <div class="aat_result purple">
                                            <h3 class="result_sub-tit">정서 행동 영역</h3>
                                            <div class="result_summary">
                                                <h4>ooo님의 강점은 <span class="result">[<?= $implicitTypeTxt ?>]</span> 입니다.</h4>
                                            </div>
                                            <div class="result_word">
                                                <span><?= $implicitTypeTxt2 ?></span>
                                                <h2><?= $implicitTypeTxt ?></h2>
                                                <span><?= $implicitTypeTxt3 ?></span>
                                            </div>
                                            <div class="result_detail">
                                                <p class="result_text">
                                                    창작자에 필요한 정서행동적 특성 중 <?= $Name ?>님은 <span class="active"><?= $implicitTypeTxt ?></span>이 높으며, <?= $achieveTypeTxt ?>이 높은 성향을 가지고 있습니다.
                                                    앞으로 미술의 창작과 감상 과정에서 자기결정력과 자신감을 가질 수 있도록 관심을 가져보세요.
                                                    자신의 강점을 활용하고 부족한 특성을 보완한다면 <?= $Name ?>님의 미술 세계가 더욱 넓어질 것입니다.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- 1. 미적(aesthetic) 감수성 영역 -->
                    <div class="aat_result_con aesthetic">
                        <div class="aat_result_title pink">
                            1. 미적 감수성 영역 ( Aesthetic Sensibility )
                        </div>
                        <div class="aat_detail_wrap wide">
                            <div class="aat_detail_con">
                                <div class="print_content">
                                    <div class="print_title pink">
                                        <h1>1. 미적 감수성 영역 ( Aesthetic Sensibility )</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="aesthetic__title">
                                            <h3>1.1 미적 판단 분석</h3>
                                            <div class="info">
                                                <p>
                                                    미적판단이란? 사람은 사물을 시각적으로 대면할 때, 쾌와 불쾌에 대한 주관적 판정 내립니다. 아래 결과는 자신을 유쾌하게 하게 하는 시각적 대상을 가려내는 기준 또는 근거이며, 이는 대상에 대한 이해나
                                                    가치의
                                                    판단과는 무관합니다.
                                                </p>
                                            </div>
                                        </div>
                                        <div class="aesthetic_bar-graph">
                                            <div class="bar-graph__inner">
                                                <h3 class="tit">표현주제</h3>
                                                <div class="bar-graph">
                                                    <div class="bar_title <?php if ($direction1 == 'left') { ?> active <?php }?>">
                                                        <span>외부세계(E)</span>
                                                        <b class="percent"><?= $type1Val ?>%</b>
                                                    </div>
                                                    <div class="graph">
                                                        <div class="graph-content left <?php if ($direction1 == 'left') { ?> active <?php }?>" style="width: <?= $type1Val ?>%;"></div>
                                                        <div class="graph-content right <?php if ($direction1 == 'right') { ?> active <?php }?>" style="width: <?= 100 - $type1Val ?>%"></div>
                                                    </div>
                                                    <div class="bar_title <?php if ($direction1 == 'right') { ?> active <?php }?>">
                                                        <span>내면세계(I)</span>
                                                        <b class="percent"><?= 100 - $type1Val ?>%</b>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bar-graph__inner">
                                                <h3 class="tit">구성방식</h3>
                                                <div class="bar-graph">
                                                    <div class="bar_title <?php if ($direction2 == 'left') { ?> active <?php }?>">
                                                        <span>재현적(R)</span>
                                                        <b class="percent"><?= $type2Val ?>%</b>
                                                    </div>
                                                    <div class="graph">
                                                        <div class="graph-content left <?php if ($direction2 == 'left') { ?> active <?php }?>" style="width: <?= $type2Val ?>%;"></div>
                                                        <div class="graph-content right <?php if ($direction2 == 'right') { ?> active <?php }?>" style="width: <?= 100 - $type2Val ?>%"></div>
                                                    </div>
                                                    <div class="bar_title <?php if ($direction2 == 'right') { ?> active <?php }?>">
                                                        <span>추상적(A)</span>
                                                        <b class="percent"><?= 100 - $type2Val ?>%</b>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bar-graph__inner">
                                                <h3 class="tit">표현양식</h3>
                                                <div class="bar-graph">
                                                    <div class="bar_title <?php if ($direction3 == 'left') { ?> active <?php }?>">
                                                        <span>자연주의적(N)</span>
                                                        <b class="percent"><?= $type3Val ?>%</b>
                                                    </div>
                                                    <div class="graph">
                                                        <div class="graph-content left <?php if ($direction3 == 'left') { ?> active <?php }?>" style="width: <?= $type3Val ?>%;"></div>
                                                        <div class="graph-content right <?php if ($direction3 == 'right') { ?> active <?php }?>" style="width: <?= 100 - $type3Val ?>%"></div>
                                                    </div>
                                                    <div class="bar_title <?php if ($direction3 == 'right') { ?> active <?php }?>">
                                                        <span>장식적(D)</span>
                                                        <b class="percent"><?= 100 - $type3Val ?>%</b>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bar-graph__inner">
                                                <h3 class="tit">감정이입</h3>
                                                <div class="bar-graph">
                                                    <div class="bar_title <?php if ($direction4 == 'left') { ?> active <?php }?>">
                                                        <span>인지적(C)</span>
                                                        <b class="percent"><?= $type4Val ?>%</b>
                                                    </div>
                                                    <div class="graph">
                                                        <div class="graph-content left <?php if ($direction4 == 'left') { ?> active <?php }?>" style="width: <?= $type4Val ?>%;"></div>
                                                        <div class="graph-content right <?php if ($direction4 == 'right') { ?> active <?php }?>" style="width: <?= 100 - $type4Val ?>%"></div>
                                                    </div>
                                                    <div class="bar_title <?php if ($direction4 == 'right') { ?> active <?php }?>">
                                                        <span>정서적(S)</span>
                                                        <b class="percent"><?= 100 - $type4Val ?>%</b>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="aesthetic_short">
                                            <p>
                                                <?= $Name ?>님은 <span><?= $type1Txt ?>, <?= $type2Txt ?>, <?= $type3Txt ?>, <?= $type4Txt ?></span>
                                                이 두드러지게 나타났습니다.
                                            </p>
                                        </div>
                                        <div class="aesthetic__detail">
                                            <div class="dtl_content">
                                                <?php if($type1 == 'E'){ ?>
                                                    <span class="alpha">I</span>
                                                    <p>
                                                        <span class="pink">외부 세계(Extrovert)란,</span> 사회적, 역사적 이야기 등 외부 세계에 관한 주제를 시각 언어를 활용하여 객관적으로 전달하고 소통하는 데에 관심을 나타내는 성향입니다.
                                                    </p>
                                                <?php } else { ?>
                                                    <span class="alpha">I</span>
                                                    <p>
                                                        <span class="pink">내면 세계(Introspective)란,</span> 독창적, 상징적 표현을 통해 감정과 감동을 전달하고 주제에 관한 주관적인 느낌과
                                                        생각을 표현하는 것을 선호하는 성향입니다.
                                                    </p>
                                                <?php } ?>
                                            </div>
                                            <div class="dtl_content">
                                                <?php if($type1 == 'R'){ ?>
                                                    <span class="alpha">R</span>
                                                    <p>
                                                        <span class="pink">재현적 성향(Representational)이란,</span> 외양에서 보이는 특징을 있는 그대로 재현하여 관람자에게 대상이 구상적으로 지각되거나 경험되도록 하는 표상
                                                        형식을 선호하는 성향입니다.
                                                    </p>
                                                <?php } else { ?>
                                                    <span class="alpha">A</span>
                                                    <p>
                                                        <span class="pink">추상적 성향(Abstract)이란,</span> 사물의 본질적인 형태를 찾아 자신의 정신 작용을 통해 대상을 단순화하거나 개념적인 형상을 새롭게 창조하는 표상 형식을 선호하는 성향입니다.
                                                    </p>
                                                <?php } ?>
                                            </div>
                                            <div class="dtl_content">
                                                <?php if($type1 == 'N'){ ?>
                                                    <span class="alpha">N</span>
                                                    <p>
                                                        <span class="pink">자연주의적 성향(Naturalistic)이란,</span> 주변 세계를 경험적으로 관찰하고 대상의 특징이나 아름다움을 있는 그대로, 비교적 정확하게 다시 그려내는 표현 양식을 선호하는 성향입니다.
                                                    </p>
                                                <?php } else { ?>
                                                    <span class="alpha">D</span>
                                                    <p>
                                                        <span class="pink">장식적 성향(Decorative)이란,</span> 조형 요소와 원리를 활용하여 화면을 장식적으로 구성하고 대상의 조형적인
                                                        특성을 살려 형상화하는 방식에 관심을 두는 성향입니다.
                                                    </p>
                                                <?php } ?>
                                            </div>
                                            <div class="dtl_content">
                                                <?php if($type1 == 'C'){ ?>
                                                    <span class="alpha">C</span>
                                                    <p>
                                                        <span class="pink">인지적 성향(Cogitative)이란,</span> 미술적 주제에 대한 사고와 사유 과정에 관심을 두고, 관람자에게 생각을 불러일으키는 작품을 선호하는 성향입니다.
                                                    </p>
                                                <?php } else { ?>
                                                    <span class="alpha">S</span>
                                                    <p>
                                                        <span class="pink">정서적(Sensitive)이란,</span> 비합리적이거나 자유롭고 즉흥적인 표현 방식을 즐기고 정서적인 교감이 가능한
                                                        작품의 창작을 선호하는 성향입니다.
                                                    </p>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="print_content">
                                    <div class="print_title pink">
                                        <h1>1. 미적 감수성 영역 ( Aesthetic Sensibility )</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="aesthetic_work">
                                            <div class="aesthetic-work__inner">
                                                <p><?= $Name ?>님의 미적 성향과 관련된 미술작품을 감상해 보세요.</p>
                                                <div class="aesthetic-work__list">
                                                    <div class="aesthetic_img">
                                                        <div class="card <?= $type1 == 'E' ? 'active' : '' ?>">
                                                            <h3>외부 세계</h3>
                                                            <span>Extrovert</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_000.jpg" alt="외부 세계 이미지">
                                                                <img src="/common/images/mystudio/aat_img_001.jpg" alt="외부 세계 이미지">
                                                            </div>
                                                        </div>
                                                        <span class="dash"></span>
                                                        <div class="card <?= $type1 == 'I' ? 'active' : '' ?>">
                                                            <h3>내면 세계</h3>
                                                            <span>Introspective</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_002.jpg" alt="내면 세계 이미지">
                                                                <img src="/common/images/mystudio/aat_img_003.jpg" alt="내면 세계 이미지">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="aesthetic_img">
                                                        <div class="card <?= $type1 == 'R' ? 'active' : '' ?>">
                                                            <h3>재현적</h3>
                                                            <span>Representational</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_004.jpg" alt="재현적 이미지">
                                                                <img src="/common/images/mystudio/aat_img_005.jpg" alt="재현적 이미지">
                                                            </div>
                                                        </div>
                                                        <span class="dash"></span>
                                                        <div class="card <?= $type1 == 'A' ? 'active' : '' ?>">
                                                            <h3>추상적</h3>
                                                            <span>Abstract</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_006.jpg" alt="추상적 이미지">
                                                                <img src="/common/images/mystudio/aat_img_007.jpg" alt="추상적 이미지">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="aesthetic_img">
                                                        <div class="card <?= $type1 == 'N' ? 'active' : '' ?>">
                                                            <h3>자연주의적</h3>
                                                            <span>Naturalistic</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_008.jpg" alt="자연주의적 이미지">
                                                                <img src="/common/images/mystudio/aat_img_009.jpg" alt="자연주의적 이미지">
                                                            </div>
                                                        </div>
                                                        <span class="dash"></span>
                                                        <div class="card <?= $type1 == 'D' ? 'active' : '' ?>">
                                                            <h3>장식적</h3>
                                                            <span>Decorative</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_010.jpg" alt="장식적 이미지">
                                                                <img src="/common/images/mystudio/aat_img_011.jpg" alt="장식적 이미지">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="aesthetic_img">
                                                        <div class="card <?= $type1 == 'C' ? 'active' : '' ?>">
                                                            <h3>인지적</h3>
                                                            <span>Cognitive</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_012.jpg" alt="인지적 이미지">
                                                            </div>
                                                        </div>
                                                        <span class="dash"></span>
                                                        <div class="card <?= $type1 == 'S' ? 'active' : '' ?>">
                                                            <h3>정서적</h3>
                                                            <span>Sensitive</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_013.jpg" alt="정서적 이미지">
                                                            <img src="/common/images/mystudio/aat_img_014.jpg" alt="정서적 이미지">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="aat_detail_con">
                                <div class="print_content">
                                    <div class="print_title pink">
                                        <h1>1. 미적 감수성 영역 ( Aesthetic Sensibility )</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="aesthetic__title">
                                            <h3>1.2 미적 선호 분석</h3>
                                            <div class="info">
                                                <p>
                                                    미적선호란? 미적 판단이 개별 대상에 대한 쾌와 불쾌의 주관적 판정이라면, 미적선호는 여러 미적 대상을 분류하고 선택하는 행위와 연관됩니다. 심미안이 고도화 될수록 분류가 세밀해지고, 선택의 우선순위에 개인 간의 차이가 생기게 됩니다.
                                                </p>
                                            </div>
                                        </div>
                                        <div class="aesthetic_bar-graph">
                                            <div class="bar-graph__inner">
                                                <h3 class="tit">선 및 형태</h3>
                                                <div class="bar-graph">
                                                    <div class="bar_title <?php if ($direction5 == 'left') { ?> active <?php }?>">
                                                        <span>유기적 성향(O)</span>
                                                        <b class="percent"><?= $type5Val ?>%</b>
                                                    </div>
                                                    <div class="graph">
                                                        <div class="graph-content left <?php if ($direction5 == 'left') { ?> active <?php }?>" style="width: <?= $type5Val ?>%;"></div>
                                                        <div class="graph-content right <?php if ($direction5 == 'right') { ?> active <?php }?>" style="width: <?= 100 - $type5Val ?>%"></div>
                                                    </div>
                                                    <div class="bar_title active">
                                                        <span>기하학적 성향(G)</span>
                                                        <b class="percent"><?= 100 - $type5Val ?>%</b>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bar-graph__inner">
                                                <h3 class="tit">공간</h3>
                                                <div class="bar-graph">
                                                    <div class="bar_title <?php if ($direction6 == 'left') { ?> active <?php }?>">
                                                        <span>평면적(2D)</span>
                                                        <b class="percent"><?= $type6Val ?>%</b>
                                                    </div>
                                                    <div class="graph">
                                                        <div class="graph-content left <?php if ($direction6 == 'left') { ?> active <?php }?>" style="width: <?= $type6Val ?>%;"></div>
                                                        <div class="graph-content right <?php if ($direction6 == 'right') { ?> active <?php }?>" style="width: <?= 100 - $type6Val ?>%"></div>
                                                    </div>
                                                    <div class="bar_title active">
                                                        <span>입체적(3D)</span>
                                                        <b class="percent"><?= 100 - $type6Val ?>%</b>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="aesthetic_short">
                                            <p>
                                                <?= $Name ?>님은 <span><?= $type5Txt ?>, <?= $type6Txt ?></span>이 두드러지게 나타났습니다.
                                            </p>
                                        </div>
                                        <div class="aesthetic__detail">
                                            <div class="dtl_content">
                                                <?php
                                                if($type5 == 'O'){
                                                    ?>
                                                    <span class="alpha">O</span>
                                                    <p>
                                                        <span class="pink">유기적 성향(Organic)이란,</span> 자연의 생명체와 같이 구성요소들이 서로 밀접한 관련성 속에서 조화로운 전체를 구성하는 것을 강조하며, 불규칙적이고 복잡다양한 형태를 선호하는 성향입니다.
                                                    </p>
                                                    <?php
                                                }else{
                                                    ?>
                                                    <span class="alpha">G</span>
                                                    <p>
                                                        <span class="pink">기하학적 성향(Geometric)이란,</span> 수학적 원리와 구조를 통해 주변 세계에 질서를 부여하는 것을 강조하며, 규칙적이고 단순 명료한 형태를 선호하는
                                                        성향입니다.
                                                    </p>
                                                    <?php
                                                }
                                                ?>
                                            </div>
                                            <div class="dtl_content">
                                                <?php
                                                if($type6 == '2'){
                                                    ?>
                                                    <span class="alpha">2D</span>
                                                    <p>
                                                        <span class="pink">평면적 성향(2-Dimensional)이란,</span> 사물이나 인물 외면의 형태나 성질을 참조로 하여 대상의 구체적인 특징이 그대로
                                                        지각되거나 경험될 수 있도록 표현양식을 선호하는 성향입니다.
                                                    </p>
                                                    <?php
                                                }else{
                                                    ?>
                                                    <span class="alpha">3D</span>
                                                    <p>
                                                        <span class="pink">입체적 성향(3-Dimensional)이란,</span> 조각, 설치, 건축과 같이 실제적인 깊이를 가지는 물리적 공간 안에서 양감, 질감, 동세를 활용한 표현을 선호하는 성향입니다.
                                                    </p>
                                                    <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="aesthetic_work">
                                            <div class="aesthetic-work__inner">
                                                <div class="aesthetic-work__list">
                                                    <div class="aesthetic_img">
                                                        <div class="card <?= $type5 == 'O' ? 'active' : '' ?>">
                                                            <h3>유기적</h3>
                                                            <span>Organic</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_000.jpg" alt="외부 세계 이미지">
                                                                <img src="/common/images/mystudio/aat_img_001.jpg" alt="외부 세계 이미지">
                                                            </div>
                                                        </div>
                                                        <span class="dash"></span>
                                                        <div class="card <?= $type5 == 'G' ? 'active' : '' ?>">
                                                            <h3>기하학적</h3>
                                                            <span>Geometric</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_000.jpg" alt="외부 세계 이미지">
                                                                <img src="/common/images/mystudio/aat_img_001.jpg" alt="외부 세계 이미지">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="aesthetic_img">
                                                        <div class="card <?= $type6 == '2' ? 'active' : '' ?>">
                                                            <h3>평면적</h3>
                                                            <span>2D</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_000.jpg" alt="외부 세계 이미지">
                                                                <img src="/common/images/mystudio/aat_img_001.jpg" alt="외부 세계 이미지">
                                                            </div>
                                                        </div>
                                                        <span class="dash"></span>
                                                        <div class="card <?= $type6 == '3' ? 'active' : '' ?>">
                                                            <h3>입체적</h3>
                                                            <span>3D</span>
                                                            <div class="img_list">
                                                                <img src="/common/images/mystudio/aat_img_000.jpg" alt="외부 세계 이미지">
                                                                <img src="/common/images/mystudio/aat_img_001.jpg" alt="외부 세계 이미지">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="aat_detail_con">
                                <div class="print_content">
                                    <div class="print_title pink">
                                        <h1>1. 미적 감수성 영역 ( Aesthetic Sensibility )</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="aesthetic__title color">
                                            <h3>1.3 색채 성향 분석</h3>
                                            <div class="info">
                                                <p>
                                                    <?= $Name ?>님의 색채 성향을 분석한 결과입니다.
                                                </p>
                                            </div>
                                        </div>
                                        <div class="aesthetic-color__imgs">
                                            <?php if($type7 == '난색'){ ?>
                                                <img src="/common/images/mystudio/color_prefer_img_000.png" alt="">
                                            <?php }else {?>
                                                <img src="/common/images/mystudio/color_prefer_img_001.png" alt="">
                                            <?php } ?>

                                            <?php if($type8 == '유사대비'){ ?>
                                                <img src="/common/images/mystudio/color_prefer_img_002.png" alt="">
                                            <?php }else {?>
                                                <img src="/common/images/mystudio/color_prefer_img_003.png" alt="">
                                            <?php } ?>
                                        </div>
                                        <div class="aesthetic_short color">
                                            <p>
                                                <?= $Name ?>님은 <span><?= $type7 ?>과 <?= $type8 ?></span>를 선호하는 것으로 나타났습니다.
                                            </p>
                                        </div>
                                        <div class="aesthetic_detail color">
                                            <p class="dtl_text">
                                                다양한 색채는 각기 다른 파장과 에너지를 가지고 있습니다. 따라서 사람들의 생각과 행동에 영향을 미치기도 하고, 반대로 개개인의 성격을 나타내는 하나의 기호가 되기도 합니다. 여기에서 중요한 것은 특정한 색이
                                                긍정적
                                                혹은 부정적 성향을 나타내는 것이 아니라는 점입니다. 일반적인 색의 선호에 따른 상반된 특성을 통해 자신을 보다 객관적으로 바라볼 수 있습니다. <?= $Name ?>님이 선택한 색상의 특징을 다음과 같습니다.
                                            </p>
                                            <div class="color_detail">
                                                <div class="title" style="background-color: <?= $colorCode ?>;"><?= $color ?></div>
                                                <div class="desc">
                                                    <div class="color_desc">
                                                        <span>긍정적 키워드</span>
                                                        <p><?= $colorTxt1 ?></p>
                                                    </div>
                                                    <div class="color_desc">
                                                        <span>부정적 키워드</span>
                                                        <p><?= $colorTxt2 ?></p>
                                                    </div>
                                                    <div class="color_desc">
                                                        <span>성격</span>
                                                        <p><?= $colorTxt3 ?></p>
                                                    </div>
                                                    <div class="color_desc">
                                                        <span>학습적 특징</span>
                                                        <p><?= $colorTxt4 ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="dtl_text">
                                                두 가지 이상의 색을 어울리게 배치하는 것을 배색이라고 합니다. 색을 어떻게 조합하는지에 따라 이미지의 전체적인 톤과 느낌이 달라집니다. 따라서 선호하는 배색의 종류에 따라 시각적으로 어떤 느낌에서 즐거움을
                                                느끼는지, 혹은 어떤 느낌으로 표현하는 것을 좋아하는지 알 수 있습니다. 당신이 선택한 배색에 대한 선호 분석입니다.
                                            </p>
                                            <div class="color_prefer">
                                                <div class="prefer">
                                                    <div class="img">
                                                        <img src="./_img/prefer1.png" alt="">
                                                    </div>
                                                    <div class="desc">
                                                        <p><?= $Name ?>님은 <span><?= $type7 ?></span>을 선호하는 것으로 보입니다.</p>
                                                        <?php if ($type7 == '난색') { ?>
                                                            <p>난색은 빨강, 주황, 노랑, 갈색 등의 따뜻한 느낌을 주는 계열의 색을 의미합니다. 난색은 따뜻함, 열정, 포근함의 이미지를 전달합니다.</p>
                                                        <?php } else {?>
                                                            <p>한색은 파랑, 남색, 보라 등의 차가운 느낌을 주는 계열의 색을 의미합니다. 한색은 어두움, 차가움, 냉철한 이미지를 전달합니다.</p>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                                <div class="prefer">
                                                    <div class="img">
                                                        <img src="./_img/prefer2.png" alt="">
                                                    </div>
                                                    <div class="desc">
                                                        <p><?= $Name ?>님은 <span><?= $type8 ?></span>을 선호하는 것으로 보입니다.</p>
                                                        <?php if ($type8 == '유사대비') { ?>
                                                            <p>
                                                                유사대비는 인적한 거리에 있는 색의 대비입니다.<br/>
                                                                부드럽고 정적인 변화를 선호합니다.
                                                            </p>
                                                        <?php } else {?>
                                                            <p>
                                                                보색대비는 강렬한 색상의 대비 효과를 보여줍니다.<br/>
                                                                색상의 차이가 커서 명쾌하고 화려한 느낌을 선호합니다.
                                                            </p>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--2. 시각 인지 영역-->
                    <div class="aat_result_con visual">
                        <div class="aat_result_title green">
                            2. 시각 인지 영역 (Visual Cognition)
                        </div>
                        <div class="aat_detail_wrap visual">
                            <div class="aat_detail_con">
                                <div class="aat_detail_box">
                                    <div class="print_content">
                                        <div class="print_title green">
                                            <h1>2. 시각 인지 영역 (Visual Cognition)</h1>
                                        </div>
                                        <div class="print_cnts">
                                            <div class="visual__title">
                                                <p><?= $Name ?>님의 시지각 영역을 분석한 결과입니다.</p>
                                            </div>
                                            <div class="visual_bar-graph">
                                                <div class="visual-graph__title">
                                                    <h3>인지적-시각적 성향 영역</h3>
                                                </div>
                                                <div class="bar_graph_wrap">
                                                    <div class="bar_graph_table">
                                                        <div class="bar_graph_con">
                                                            <div class="bar_graph_tbody">
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">100</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">90</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">80</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">70</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">60</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">50</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">40</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">30</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">20</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                                <div class="bar_graph_tr">
                                                                    <div class="bar_graph_td">
                                                                        <span class="bar_graph_axis">10</span>
                                                                    </div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                    <div class="bar_graph_td"></div>
                                                                </div>
                                                            </div>
                                                            <div class="bar_graph_box">
                                                                <div class="bar_graph none">
                                                                    <div class="graph"></div>
                                                                </div>
                                                                <div class="bar_graph">
                                                                    <div class="graph" style="--barPercent: <?= $literacyPer ?>;"></div>
                                                                </div>
                                                                <div class="bar_graph">
                                                                    <div class="graph" style="--barPercent: <?= $visualPer ?>;"></div>
                                                                </div>
                                                                <div class="bar_graph">
                                                                    <div class="graph" style="--barPercent: <?= $spatialPer ?>;"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="bar_graph_tfoot">
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">원점수</div>
                                                                <div class="bar_graph_td"><?= $literacyPoint ?></div>
                                                                <div class="bar_graph_td"><?= $visualPoint ?></div>
                                                                <div class="bar_graph_td"><?= $spatialPoint ?></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">T점수</div>
                                                                <div class="bar_graph_td"><?= $literacyPer ?></div>
                                                                <div class="bar_graph_td"><?= $visualPer ?></div>
                                                                <div class="bar_graph_td"><?= $spatialPer ?></div>
                                                            </div>
                                                            <div class="bar_graph_tr no_border">
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td">시각적<br/>문해력</div>
                                                                <div class="bar_graph_td">시각적 이미지<br/>지각능력</div>
                                                                <div class="bar_graph_td">공간지각<br/>능력</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="visual__short">
                                                <!-- TODO -->
                                                <p><?= $Name ?>님의 강점은 <span>시각적 문해력</span>입니다.</p>
                                            </div>
                                            <div class="visual__detail">
                                                <div class="visual_txt">
                                                    <div class="icon">
                                                        <img src="./_img/visual1.png" alt="">
                                                    </div>
                                                    <div class="desc">
                                                        <h3>시각적 문해력이란?</h3>
                                                        <p>
                                                            글(text)을 읽고 해석하는 것, 즉 글에서 맥락과 의미를 찾아내는 것을 ‘문해’ 라고 합니다. 일상의 다양한 시각적 이미지에도 이런 맥락과 의미가 숨어있다면, 그 안에도 글(text)이 담겨 있다고
                                                            할 수
                                                            있습니다. 이미지 안에 숨어있는 맥락과 의미를 찾아내고 나아가 표현하는 능력을 ‘시각적 문해력’이라고 합니다.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="visual_txt">
                                                    <div class="icon">
                                                        <img src="./_img/visual2.png" alt="">
                                                    </div>
                                                    <div class="desc">
                                                        <h3>시지각 능력이란?</h3>
                                                        <p>
                                                            시각을 통해 들어온 정보는 뇌를 통해 재해석 됩니다. 대상의 같음과 차이를 구별하는 ‘변별능력’, 완전하지 못한 시각 정보를 연결하고 완성시키는 ‘종결능력’, 시각적 정보를 명확하게 다시 재생하는
                                                            ‘시각기억능력’
                                                            등이 시지각능력에 포함됩니다.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="visual_txt">
                                                    <div class="icon">
                                                        <img src="./_img/visual3.png" alt="">
                                                    </div>
                                                    <div class="desc">
                                                        <h3>공간지각 능력이란?</h3>
                                                        <p>
                                                            시각으로 포착한 공간의 거리감을 감지하고 반응하는 능력을 말합니다. 비단, 현실의 공간이 아니더라도 눈에 보이지 않는 것을 추상적으로 추론해 이를 머리 속에서 공간을 패턴화 하거나 형상화해내는 능력이
                                                            포함됩니다.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="print_content">
                                        <div class="print_title green">
                                            <h1>iBoda 미술성향검사(AAT) 결과 종합</h1>
                                        </div>
                                        <div class="print_cnts">
                                            <div class="visual__table">
                                                <div class="visual__title">
                                                    <p><?= $Name ?>님의 시지각 영역을 분석한 결과입니다.</p>
                                                </div>
                                                <div class="visual__short print">
                                                    <!-- TODO -->
                                                    <p><?= $Name ?>님의 강점은 <span>시각적 문해력</span>입니다.</p>
                                                </div>
                                                <div class="visual-table__inner">
                                                    <div class="visual-table__title">
                                                        <h3>시각적 문해력</h3>
                                                    </div>
                                                    <div class="mb60 aat_table_style_0_con">
                                                        <?php
                                                        if($literacy2Point == 0) {
                                                            $literacy2Level = 1;
                                                            $literacy2LevelTitle = '부족';
                                                            $literacy2LevelTxt = '부족한 것으로 보입니다.';
                                                        } else if($literacy2Point == 1) {
                                                            $literacy2Level = 2;
                                                            $literacy2LevelTitle = '보통';
                                                            $literacy2LevelTxt = '보통의 수준을 나타냅니다.';
                                                        }else if($literacy2Point == 2) {
                                                            $literacy2Level = 3;
                                                            $literacy2LevelTitle = '우수';
                                                            $literacy2LevelTxt = '우수한 것으로 보입니다.';
                                                        }
                                                        ?>
                                                        <table class="aat_table_style_0">
                                                            <colgroup>
                                                                <col style="width: 85px;">
                                                                <col style="width: auto;">
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th>점수</th>
                                                                    <th>해석</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <th class="point0">*</th>
                                                                    <td class="content">
                                                                        미술 비평은 작품의 특징을 자세히 관찰하는 것으로 시작합니다. 이러한 관찰 능력은
                                                                        미술 작품의 특징을 찾아 구체적인 용어로 서술할 수 있는지를 통해 알 수 있습니다.
                                                                        <?= $Name ?> 님은 미술 작품의 첫 인상과 전체적인 표현 특징을 묘사하는 용어를 <?= $literacy1Per ?>%적절하게 선택하였습니다.
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th class="point0"><?= $literacy2LevelTitle ?></th>
                                                                    <td class="content">
                                                                        많은 정보가 이미지로 전달되는 현대 사회에는 글을 보고 머릿속에 그려내는 능력이나 그림을 말과 글로 설명할 수 있는 능력이 중요합니다.
                                                                        <?= $Name ?> 님은 글과 이미지를 연결하는 시각적 정보 이해 능력이 <?= $literacy2LevelTxt ?>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="visual-table__inner">
                                                    <div class="visual-table__title">
                                                        <h3>시지각 능력</h3>
                                                    </div>
                                                    <div class="mb60 aat_table_style_0_con">
                                                        <?php
                                                        if($visualPoint <= 2) {
                                                            $visualLevel = 1;
                                                            $visualLevelTitle = '부족';
                                                            $visualLevelTxt = '부족한 것으로 보입니다.';
                                                        }
                                                        else if(3 <= $visualPoint && $visualPoint <= 5 ) {
                                                            $visualLevel = 2;
                                                            $visualLevelTitle = '보통';
                                                            $visualLevelTxt = '보통의 수준을 나타냅니다.';
                                                        }
                                                        else if(6 <= $visualPoint && $visualPoint <= 8 ) {
                                                            $visualLevel = 3;
                                                            $visualLevelTitle = '우수';
                                                            $visualLevelTxt = '우수한 것으로 보입니다.';
                                                        }
                                                        else if(9 <= $visualPoint && $visualPoint <= 10 ) {
                                                            $visualLevel = 4;
                                                            $visualLevelTitle = '매우우수';
                                                            $visualLevelTxt = '매우 우수한 것으로 보입니다.';
                                                        }
                                                        ?>
                                                        <table class="aat_table_style_0">
                                                            <colgroup>
                                                                <col style="width: 85px;">
                                                                <col style="width: auto;">
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th>점수</th>
                                                                    <th>해석</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <th class="point0"><?= $visualLevelTitle ?></th>
                                                                    <td class="content">
                                                                        시각 이미지를 파악하는 것은 구성요소의 특징을 관찰하여 형태 간의 관계를 발견하고 이해하는 것입니다. 형태의 유사점과 차이점을 비교하거나, 전체 그림 안에서 부분을 찾아 연결할 수 있는 능력은 시각 이미지에 대한 지각 능력을 나타냅니다.
                                                                        <?= $Name ?> 님은 시각 이미지 지각 능력이 <?= $visualLevelTxt ?>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="visual-table__inner">
                                                    <div class="visual-table__title">
                                                        <h3>공간지각 능력</h3>
                                                    </div>
                                                    <div class="aat_table_style_0_con">
                                                        <?php
                                                        if($spatialPoint <= 2) {
                                                            $spatialLevel = 1;
                                                            $spatialLevelTitle = '부족';
                                                            $spatialLevelTxt = '부족한 것으로 보입니다.';
                                                        }
                                                        else if(3 <= $spatialPoint && $spatialPoint <= 4 ) {
                                                            $spatialLevel = 2;
                                                            $spatialLevelTitle = '보통';
                                                            $spatialLevelTxt = '보통의 수준을 나타냅니다.';
                                                        }
                                                        else if(5 <= $spatialPoint && $spatialPoint <= 6 ) {
                                                            $spatialLevel = 3;
                                                            $spatialLevelTitle = '우수';
                                                            $spatialLevelTxt = '우수한 것으로 보입니다.';
                                                        }


                                                        if(max($literacyLevel, $visualLevel, $spatialLevel) == $literacyLevel) {
                                                            $visualTxt = '시각적 문해력';
                                                            $visualTxt2 = '시각적 이미지 지각능력';
                                                            $visualTxt3 = '공간 지각 능력';
                                                        } 
                                                        else if(max($literacyLevel, $visualLevel, $spatialLevel) == $visualLevel) {
                                                            $visualTxt = '시각적 이미지 지각능력';
                                                            $visualTxt2 = '시각적 문해력';
                                                            $visualTxt3 = '공간 지각 능력';
                                                        } 
                                                        else if(max($literacyLevel, $visualLevel, $spatialLevel) == $spatialLevel) {
                                                            $visualTxt = '공간 지각 능력';
                                                            $visualTxt2 = '시각적 문해력';
                                                            $visualTxt3 = '시각적 이미지 지각능력';
                                                        } 
                                                        ?>
                                                        <table class="aat_table_style_0">
                                                            <colgroup>
                                                                <col style="width: 85px;">
                                                                <col style="width: auto;">
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th>점수</th>
                                                                    <th>해석</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <th class="point0"><?= $spatialLevelTitle ?></th>
                                                                    <td class="content">
                                                                        공간지각 능력은 2차원적, 3차원적 공간 안에서 이미지의 구성요소를 분석, 비교하여 형태, 위치, 거리 등을 파악하는 능력입니다. 이를 통해 머릿속에서 이미지를 재생하고 조작하거나 3차원적으로 이미지를 변환할 수 있습니다.
                                                                        <?= $Name ?> 님은 대상에 대한 공간지각 능력이 <?= $spatialLevelTxt ?>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--3. 정서행동 영역-->
                    <div class="aat_result_con emotional">
                        <div class="aat_result_title purple">
                            3. 정서행동 영역 (Emotional Behavior)
                        </div>
                        <div class="aat_detail_wrap visual">
                            <div class="aat_detail_con">
                                <div class="print_content">
                                    <div class="print_title purple">
                                        <h1>3. 정서행동 영역 (Emotional Behavior)</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="emotional_bar-graph">
                                            <div class="visual__title">
                                                <p><?= $Name ?>님의 정서행동 영역을 분석한 결과입니다.</p>
                                            </div>
                                            <div class="emotional-graph__title">
                                                <h3>내재적 동기 영역</h3>
                                            </div>
                                            <div class="emotional-graph__info">
                                                <p>
                                                    <span class="red">내재적 동기란 다른 보상 없이도 좋은 결과를 달성하려고 노력하는 성향을 의미합니다. 내적 동기가 높은 경우 자신의 즐거움, 만족감, 흥미에 따라 능력을 발휘합니다.</span> 미술 활동에서 내적 동기 수준이 높다는 것은 주변에 대한 호기심이 많고, 새로운 시도를 즐기며, 창작 활동에서 타인 의존도가 낮음을 나타냅니다.
                                                </p>
                                            </div>
                                            <div class="bar_graph_wrap">
                                                <div class="bar_graph_table">
                                                    <div class="bar_graph_con">
                                                        <div class="bar_graph_tbody">
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">100</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">90</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">80</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">70</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">60</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">50</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">40</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">30</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">20</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">10</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                        </div>
                                                        <div class="bar_graph_box">
                                                            <div class="bar_graph none">
                                                                <div class="graph"></div>
                                                            </div>
                                                            <div class="bar_graph">
                                                                <div class="graph" style="--barPercent: <?= $implicitType1Per ?>;"></div>
                                                            </div>
                                                            <div class="bar_graph">
                                                                <div class="graph" style="--barPercent: <?= $implicitType2Per ?>;"></div>
                                                            </div>
                                                            <div class="bar_graph">
                                                                <div class="graph" style="--barPercent: <?= $implicitType3Per ?>;"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="bar_graph_tfoot">
                                                        <div class="bar_graph_tr">
                                                            <div class="bar_graph_td">원점수</div>
                                                            <div class="bar_graph_td"><?= $implicitType1Point ?></div>
                                                            <div class="bar_graph_td"><?= $implicitType2Point ?></div>
                                                            <div class="bar_graph_td"><?= $implicitType3Point ?></div>
                                                        </div>
                                                        <div class="bar_graph_tr">
                                                            <div class="bar_graph_td">T점수</div>
                                                            <div class="bar_graph_td"><?= $implicitType1Per ?></div>
                                                            <div class="bar_graph_td"><?= $implicitType2Per ?></div>
                                                            <div class="bar_graph_td"><?= $implicitType3Per ?></div>
                                                        </div>
                                                        <div class="bar_graph_tr no_border">
                                                            <div class="bar_graph_td"></div>
                                                            <div class="bar_graph_td">호기심</div>
                                                            <div class="bar_graph_td">개방성</div>
                                                            <div class="bar_graph_td">자기결정성</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="emotional__summary">
                                                <!-- TODO -->
                                                <p><?= $Name ?>님의 강점은 <span>호기심</span>입니다.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="print_content">
                                    <div class="print_title purple">
                                        <h1>3. 정서행동 영역 (Emotional Behavior)</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="emotional__table">
                                            <div class="visual__title">
                                                <p><?= $Name ?>님의 정서행동 영역을 분석한 결과입니다.</p>
                                            </div>
                                            <div class="emotional_short print">
                                                <!-- TODO -->
                                                <p><?= $Name ?>님의 강점은 <span>호기심</span>입니다.</p>
                                            </div>
                                            <div class="aat_table_style_0_con">
                                                <?php
                                                if($implicitType1Per <= 35) $implicitType1Level = '낮음';
                                                else if(36 <= $implicitType1Per && $implicitType1Per <= 70 )  $implicitType1Level = '보통';
                                                else if(71 <= $implicitType1Per && $implicitType1Per <= 100 ) $implicitType1Level = '높음';

                                                if($implicitType2Per <= 35) $implicitType2Level = '낮음';
                                                else if(36 <= $implicitType2Per && $implicitType2Per <= 70 )  $implicitType2Level = '보통';
                                                else if(71 <= $implicitType2Per && $implicitType2Per <= 100 ) $implicitType2Level = '높음';

                                                if($implicitType3Per <= 35) $implicitType3Level = '낮음';
                                                else if(36 <= $implicitType3Per && $implicitType3Per <= 70 )  $implicitType3Level = '보통';
                                                else if(71 <= $implicitType3Per && $implicitType3Per <= 100 ) $implicitType3Level = '높음';

                                                if(max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType1Per) {
                                                    $implicitTypeTxt = '호기심';
                                                    $implicitTypeTxt2 = '개방성';
                                                    $implicitTypeTxt3 = '자기 결정성';
                                                } 
                                                else if(max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType2Per) {
                                                    $implicitTypeTxt = '개방성';
                                                    $implicitTypeTxt2 = '호기심';
                                                    $implicitTypeTxt3 = '자기 결정성';
                                                } else if (max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType3Per) {
                                                    $implicitTypeTxt = '자기 결정성';
                                                    $implicitTypeTxt2 = '호기심';
                                                    $implicitTypeTxt3 = '개방성';
                                                }
                                                ?>
                                                <table class="aat_table_style_0 type_2">
                                                    <colgroup>
                                                        <col style="width: 70px;">
                                                        <col style="width: 60px;">
                                                        <col style="width: 60px;">
                                                        <col style="width: auto;">
                                                    </colgroup>
                                                    <thead>
                                                    <tr>
                                                        <th>척도</th>
                                                        <th>백분위</th>
                                                        <th>수준</th>
                                                        <th>해석</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <tr>
                                                        <td>호기심</td>
                                                        <td><?= $implicitType1Per ?></td>
                                                        <td><?= $implicitType1Level ?></td>
                                                        <td class="content">
                                                            호기심은 주변에 대해 많은 관심을 갖고 있으면서 항상 모르는 것을 더 배우고 싶어 하는 마음입니다.
                                                            예술가는 호기심을 통해 일상적인 대상을 자신만의 방식으로 관찰하고 새로운 특성과 의미를 발견하게 됩니다.
                                                            호기심은 감각과 지각을 각성의 상태로 일깨운다는 점에서 독창적인 창작 활동의 출발점이 됩니다.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>개방성</td>
                                                        <td><?= $implicitType2Per ?></td>
                                                        <td><?= $implicitType2Level ?></td>
                                                        <td class="content">
                                                            개방성은 답이 정해져 있지 않은 모호함에 대해 인내하고, 복잡한 것을 즐기고 탐구하는 태도입니다.
                                                            창작 활동은 미리 정해놓은 절차를 기계적으로 수행하여 결과에 도달하는 것이 아니라, 계속해서 방향을 찾아가면서 창의적으로 문제를 해결하는 과정입니다.
                                                            예술가에게 개방적인 태도는 융통성과 즉흥성을 발휘할 수 있는 자질이 됩니다.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>자기<br/>결정성</td>
                                                        <td><?= $implicitType3Per ?></td>
                                                        <td><?= $implicitType3Level ?></td>
                                                        <td class="content">
                                                            자기결정성은 자신을 자기 행동의 주인공이자 조절자로 여기는 신념과 태도를 의미합니다.
                                                            자율성을 가진 예술가는 스스로 목표를 세우고, 작품에서 무엇이 중요한지 선택하며, 자신이 가치 있다고 생각하는 것을 수행할 때 충족감을 느끼게 됩니다.
                                                            자기결정성은 자신의 느낌과 생각을 자신만의 방식으로 표현하는 미술 활동에서 핵심적인 요소가 됩니다.
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="print_content">
                                    <div class="print_title purple">
                                        <h1>3. 정서행동 영역 (Emotional Behavior)</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="emotional_bar-graph mt-block">
                                            <div class="visual__title">
                                                <p><?= $Name ?>님의 정서행동 영역을 분석한 결과입니다.</p>
                                            </div>
                                            <div class="emotional-graph__title">
                                                <h3>성취 동기 영역</h3>
                                            </div>
                                            <div class="emotional-graph__info">
                                                <p>
                                                    <span class="red">성취 동기란 어려운 과제를 해결하고자 하는 의지이며 목표를 달성하는 데 자신의 능력을 충분히 발휘하려는 욕구를 의미합니다.</span> 미술 활동에서 성취 동기가 높은
                                                    학습자는 주변의 시각 환경과 적극적으로 상호작용하며 독창적인 표현을 위한 탐구에 적극적으로 참여합니다. 성취 동기를 통해 자신의 미술적 잠재력을 계속해서 발전시켜 나갈 수 있습니다.
                                                </p>
                                            </div>
                                            <div class="bar_graph_wrap">
                                                <div class="bar_graph_table">
                                                    <div class="bar_graph_con">
                                                        <div class="bar_graph_tbody">
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">100</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">90</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">80</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">70</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">60</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">50</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">40</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">30</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">20</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                            <div class="bar_graph_tr">
                                                                <div class="bar_graph_td">
                                                                    <span class="bar_graph_axis">10</span>
                                                                </div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                                <div class="bar_graph_td"></div>
                                                            </div>
                                                        </div>
                                                        <div class="bar_graph_box">
                                                            <div class="bar_graph none">
                                                                <div class="graph"></div>
                                                            </div>
                                                            <div class="bar_graph">
                                                                <div class="graph" style="--barPercent: <?= $achieveType1Per ?>;"></div>
                                                            </div>
                                                            <div class="bar_graph">
                                                                <div class="graph" style="--barPercent: <?= $achieveType2Per ?>;"></div>
                                                            </div>
                                                            <div class="bar_graph">
                                                                <div class="graph" style="--barPercent: <?= $achieveType3Per ?>;"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="bar_graph_tfoot">
                                                        <div class="bar_graph_tr">
                                                            <div class="bar_graph_td">원점수</div>
                                                            <div class="bar_graph_td"><?= $achieveType1Point ?></div>
                                                            <div class="bar_graph_td"><?= $achieveType2Point ?></div>
                                                            <div class="bar_graph_td"><?= $achieveType3Point ?></div>
                                                        </div>
                                                        <!--<div class="bar_graph_tr">
                                                            <div class="bar_graph_td">T점수</div>
                                                            <div class="bar_graph_td"><?= $achieveType1Per ?></div>
                                                            <div class="bar_graph_td"><?= $achieveType2Per ?></div>
                                                            <div class="bar_graph_td"><?= $achieveType3Per ?></div>
                                                        </div>-->
                                                        <div class="bar_graph_tr no_border">
                                                            <div class="bar_graph_td"></div>
                                                            <div class="bar_graph_td">과제집착력</div>
                                                            <div class="bar_graph_td">도전성</div>
                                                            <div class="bar_graph_td">자신감</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="emotional__summary">
                                                <p><?= $Name ?>님의 강점은 <span>과제집착력</span>입니다.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="print_content">
                                    <div class="print_title purple">
                                        <h1>3. 정서행동 영역 (Emotional Behavior)</h1>
                                    </div>
                                    <div class="print_cnts">
                                        <div class="emotional__table">
                                            <div class="visual__title">
                                                <p><?= $Name ?>님의 정서행동 영역을 분석한 결과입니다.</p>
                                            </div>
                                            <div class="emotional_short print">
                                                <p><?= $Name ?>님의 강점은 <span>과제집착력</span>입니다.</p>
                                            </div>
                                            <div class="aat_table_style_0_con">
                                                <?php
                                                if($achieveType1Per <= 35) $achieveType1Level = '낮음';
                                                else if(36 <= $achieveType1Per && $achieveType1Per <= 70 )  $achieveType1Level = '보통';
                                                else if(71 <= $achieveType1Per && $achieveType1Per <= 100 ) $achieveType1Level = '높음';

                                                if($achieveType2Per <= 35) $achieveType2Level = '낮음';
                                                else if(36 <= $achieveType2Per && $achieveType2Per <= 70 )  $achieveType2Level = '보통';
                                                else if(71 <= $achieveType2Per && $achieveType2Per <= 100 ) $achieveType2Level = '높음';

                                                if($achieveType3Per <= 35) $achieveType3Level = '낮음';
                                                else if(36 <= $achieveType3Per && $achieveType3Per <= 70 )  $achieveType3Level = '보통';
                                                else if(71 <= $achieveType3Per && $achieveType3Per <= 100 ) $achieveType3Level = '높음';

                                                if(max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType1Per) $achieveTypeTxt = '과제 집착력';
                                                else if(max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType2Per) $achieveTypeTxt = '도전성';
                                                else if(max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType3Per) $achieveTypeTxt = '자신감';
                                                ?>
                                                <table class="aat_table_style_0 type_2">
                                                    <colgroup>
                                                        <col style="width: 70px;">
                                                        <col style="width: 60px;">
                                                        <col style="width: 60px;">
                                                        <col style="width: auto;">
                                                    </colgroup>
                                                    <thead>
                                                    <tr>
                                                        <th>척도</th>
                                                        <th>백분위</th>
                                                        <th>수준</th>
                                                        <th>해석</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <tr>
                                                        <td>과제<br/>집착력</td>
                                                        <td><?= $achieveType1Per ?></td>
                                                        <td><?= $achieveType1Level ?></td>
                                                        <td class="content">
                                                            과제집착력은 영재성의 요소 가운데 하나로 어떤 과제를 집중하여 끈질기게 해나가는 에너지를 의미합니다.
                                                            과제집착력을 통해 자신의 창작 활동에 몰입할 수 있으며, 그 과정에서 자기훈련을 통해 선호하는 미술 매체를 숙련된 방식으로 활용할 수 있게 됩니다.
                                                            이러한 태도는 자신의 잠재력을 특별한 미술적 능력으로 변화시키는 데 중요한 역할을 합니다.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>도전성</td>
                                                        <td><?= $achieveType2Per ?></td>
                                                        <td><?= $achieveType2Level ?></td>
                                                        <td class="content">
                                                            도전성은 모험을 즐기고 위험을 감수하면서, 실패했을 때에도 포기하지 않고 이를 받아들이고 이로부터 배우고자 하는 자세를 의미합니다.
                                                            창의적인 학습자는 도전적인 과제를 수행할 때 내부로 관심을 돌리고 자신의 목표를 세워 민감하게 반응합니다.
                                                            또한 만족할 수 있는 성취에 이를 때까지 실수를 하더라고 계속해서 시도하면서 성장해 나갑니다.
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>자신감</td>
                                                        <td><?= $achieveType3Per ?></td>
                                                        <td><?= $achieveType3Level ?></td>
                                                        <td class="content">
                                                            자신감은 자신이 유능하다고 느끼는 자아개념으로, 사회 환경과의 상호작용 속에서 자신의 능력을 사용할 기회를 통해서 충족됩니다.
                                                            창작과 감상활동은 타인의 공감을 통해서 그 의미가 확장됩니다.
                                                            자신감이 있는 학습자는 자신의 작품에 대한 타인의 비평과 피드백을 비판적으로 수용하며 이를 통해 다음의 창작 활동을 계획합니다.
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 보다쌤의 성향분석 -->
                <div class="iboda_analyze">
                    <div class="title">
                    <h1>보다쌤의 성향분석</h1>
                    </div>
                    <div class="analyze__contents">
                    <div class="print_content">
                        <div class="print_title analyze">
                        <h1>보다쌤의 성향분석</h1>
                        </div>
                        <div class="print_cnts">
                        <div class="type_result">
                            <div class="thum">
                            <img src="./_img/common/iboda_logo.png" alt="i보다 로고">
                            </div>
                            <div class="result">
                            <span class="type">ERNC 유형</span>
                            <p>
                                나는 세상을
                                <b>있는 그대로 그려내는 전략가!!</b>
                            </p>
                            </div>
                        </div>
                        <div class="result-dtl__contents">
                            <div class="result-dtl__cnts">
                            <div class="result-dtl_explain">
                                <div class="title">
                                <h2>외부(E)와 내면(I)</h2>
                                <span>표현주제</span>
                                </div>
                                <div class="contents">
                                <p>
                                    미술활동에서 ‘표현주제’를 선택하는 개인의 성향을 나타냅니다. 세상을 나와 내가 아닌 것으로 구분할 수 있다면, 그림의 주제 역시 나와 내가 아닌 것으로 구분할 수 있을 것입니다. 작품을
                                    통해 주관적인
                                    나의 이야기로
                                    소통하고 싶다면 내면성향, 내가 아닌 보다 객관적인 이야기로 소통하고 싶다면 외부성향에 가깝습니다.
                                </p>
                                </div>
                            </div>
                            <div class="result-dtl_result">
                                <div class="result_point">
                                <span class="result_en">E</span>
                                <span class="result_kr">외부</span>
                                </div>
                                <div class="contents">
                                <p>
                                    ‘외부’ 성향은 사회적, 역사적 이야기 등 내가 아닌 바깥 세계에 대한 내용을 그림의 주제로 선택하여 표현하려는 성향입니다. 말과 글이 아닌 시각적 요소를 활용하여 의미를 전달하는
                                    체계(system)를
                                    ‘시각
                                    언어’라고 부르는데, ‘외부’ 성향을 가진 창작자는 미술활동 안에서 시각언어를 활용해 내용을 객관적으로 전달하고 소통하는데 관심을 보입니다.
                                </p>
                                </div>
                            </div>
                            </div>
                            <div class="result-dtl__cnts">
                            <div class="result-dtl_explain">
                                <div class="title">
                                <h2>재현(R)과 추상(A)</h2>
                                <span>구성방식</span>
                                </div>
                                <div class="contents">
                                <p>
                                    개별적인 것과 보편적인 것이 있습니다. 예를 들어, 현정, 은주, 민재, 서영, 승민은 모두 ‘개별’ 사람이면서 동시에 인간이라는 ‘보편’ 개념 안에 속해 있습니다. 그리고 우리는 개별
                                    사람은 본 적이
                                    있지만, 인간
                                    자체는 본 적이 없습니다. 개별자는 관찰이 가능하지만, 보편자는 관찰이 불가능한 것입니다. 때문에 눈에 보이는 ‘개별’적인 것은 무언가를 관찰해서 따라 그리는 ‘재현’을 통해 표현이
                                    가능하며, ‘보편’은
                                    보이지 않는
                                    무언가를 표현하기 위해 ‘추상’의 방법을 사용합니다.
                                </p>
                                </div>
                            </div>
                            <div class="result-dtl_result">
                                <div class="result_point">
                                <span class="result_en">R</span>
                                <span class="result_kr">재현</span>
                                </div>
                                <div class="contents">
                                <p>
                                    ‘재현’은 대상의 형태와 특징을 있는 그대로 표현하려는 성향입니다. 관람자는 화가의 모사(모방하여 따라 그리는 것)를 통해 표현 대상을 구체적인 형태로 지각하고 경험합니다. 창작자는 표현할
                                    대상을 경험하지 못했거나,
                                    현재 관찰할 수 없다 하더라도 얼마든지 재현할 수 있습니다. 과거의 이야기이든 상상의 이야기이든 화가는 관람자의 눈 앞에 현재(present)하는 것처럼 사실적으로 그려낼 수 있기
                                    때문입니다. 사진이 존재하지 않았던
                                    시기에는 이러한 미술 작품이 사진의 역할을 대신 하였습니다. 현재하지 않는 것이 창작활동을 통해 ‘다시’ 현재할 수 있다(RE-present)면 그것을 재현이라고 말합니다.
                                </p>
                                </div>
                            </div>
                            <div class="img_section">
                                <img src="./_img/result_img1.png" alt="">
                                <img src="./_img/result_img2.png" alt="">
                            </div>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="print_content">
                        <div class="print_title analyze">
                        <h1>보다쌤의 성향분석</h1>
                        </div>
                        <div class="print_cnts">
                        <div class="result-dtl__contents">
                            <div class="result-dtl__cnts bottom">
                            <div class="img_section">
                                <img src="./_img/result_img3.png" alt="">
                                <img src="./_img/result_img4.png" alt="">
                            </div>
                            </div>
                            <div class="result-dtl__cnts">
                            <div class="result-dtl_explain">
                                <div class="title">
                                <h2>자연(N)과 장식(D)</h2>
                                </div>
                                <div class="contents">
                                <p>
                                    자연스러운 것과 장식적인 것은 상대적인 것이라 명확하게 구분하기는 어렵습니다. ‘비교적’ 자연스러운 것과 ‘비교적’ 장식적인 것이 있을 뿐입니다. 다만, 자연스럽다고 느끼는 그림들의 공통적인
                                    특징을 발견할 수 있고,
                                    장식적이라고 느끼는 그림들의 주된 특징을 발견할 수 있다면, 창작자는 그것을 통해 자신이 선호하는 표현양식을 선택할 수 있습니다.
                                </p>
                                </div>
                            </div>
                            <div class="result-dtl_result">
                                <div class="result_point">
                                <span class="result_en">N</span>
                                <span class="result_kr">자연</span>
                                </div>
                                <div class="contents">
                                <p>
                                    주변 세계를 경험적 관찰을 통해 정확하게 표현하려는 성향입니다. 관찰과 정확한 표현, 사실적인 묘사라는 측면은 재현과 비슷한 부분분이지만, ‘자연스럽다’는 것은 경험된 것을 꾸미지 않고
                                    ‘있는 그대로 그린다’는
                                    느낌이 강합니다. 완전한 자연주의자라면 없는 것을 인위적으로 만들거나 수정, 보완하는 것을 비교적 선호하지 않을 것입니다. 있는 그대로의 아름다움을 발견하고 표현한다면 그것만으로도 좋은
                                    작품이 되기 때문입니다.
                                </p>
                                </div>
                            </div>
                            </div>
                            <div class="result-dtl__cnts">
                            <div class="result-dtl_explain">
                                <div class="title">
                                <h2>인지(C)와 정서(S)</h2>
                                </div>
                                <div class="contents">
                                <p>
                                    인지와 정서는 세상과 소통하는 두 개의 거울입니다. 인지는 머리의 거울, 정서는 마음의 거울입니다. 머리에 거울이 없다면 본 것을 떠올릴 수 없고, 마음에 거울이 없다면 본 것을 느낄 수
                                    없습니다. 이 두 거울은
                                    사람마다 생긴 것이 조금씩 다르며, 심지어 각자 다른 방식으로 활용합니다. 때문에 같은 미적대상을 대하더라도 모두가 똑같이 바라보지 않고, 모두가 똑같이 느끼지 않는 것입니다.
                                </p>
                                </div>
                            </div>
                            <div class="result-dtl_result">
                                <div class="result_point">
                                <span class="result_en">C</span>
                                <span class="result_kr">인지</span>
                                </div>
                                <div class="contents">
                                <p>
                                    미술(美術)은 문자 그대로 해석하면 ‘아름다움을 표현하는 기술’입니다. 그러나 종종 우리는 작품 안에서 화가의 화려한 기술이 아닌, 숨겨진 의미나 내용을 발견하는 경우가 있습니다. 창작자가
                                    의도하여 관람자에게
                                    사고작용를 불러 일으킬 수도 있고, 창작자의 의도는 아니지만 관람자의 감상과정에서 의미나 사고작용이 생겨날 수 있습니다. 창작 또는 감상의 과정에서, 이렇듯 미술적 주제에 대해 사유하며
                                    생각을 불러 일으키는 작품을
                                    선호한다면 ‘인지’적 성향이라고 할 수 있습니다.
                                </p>
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="result__info">
                            <p>
                            본 결과는 검사를 실시하는 시점의 경향성을 분석한 것으로, 평생 지속되는 것으로 단정할 수 없으며 학습과 훈련, 성장과정 안에서 변화할 수 있습니다. 미술학습분석(ALA)의 전문가 비평과 분석을 통해
                            개인의 미술
                            특성을 보다 자세히 파악할 수 있으며, ooo님에게 적합한 학습방향을 설계할 수 있습니다.
                            </p>
                        </div>
                        </div>
                    </div>
                    <div class="print_content">
                        <div class="print_title analyze">
                        <h1>보다쌤의 성향분석</h1>
                        </div>
                        <div class="print_cnts wide">
                        <div class="result__replay">
                            <div class="title">
                            <div class="thum">
                                <img src="./_img/common/iboda_replay.png" alt="i보다 로고">
                            </div>
                            <div class="replay">
                                <h3>ooo님이 선택한 작품들을 다시 한 번 감상해 보세요!</h3>
                                <p>
                                아래는 ooo님이 선택한 작품들입니다. ooo님의 성향이 잘 반영되어 있나요?
                                오늘 저녁에 무엇을 먹을지 우리의 입맛에 정답이 없는 것처럼, 오늘 어떤 이미지를 선택할지에 대한 시각적 입맛에도 정답은 없습니다. 입맛이 변해가는 것처럼, 이미지를 선택하는 성향 역시도 앞으로
                                얼마든지 변할 수
                                있습니다. 저작권 문제로 검사지에 넣을 수 없었던 현대미술 작품들을 찾아서 함께 감상해보세요. 다양한 현대미술을 통해 여러분의 미적성향을 다시 한 번 확인해보는 것도 즐거운 경험이 될 것입니다.
                                </p>
                            </div>
                            </div>
                            <div class="replay__contents">
                            <div class="img_section">
                                <img src="./_img/replay_img1.png" alt="">
                                <img src="./_img/replay_img2.png" alt="">
                                <img src="./_img/replay_img3.png" alt="">
                                <img src="./_img/replay_img4.png" alt="">
                                <img src="./_img/replay_img5.png" alt="">
                                <img src="./_img/replay_img6.png" alt="">
                                <img src="./_img/replay_img7.png" alt="">
                                <img src="./_img/replay_img8.png" alt="">
                            </div>
                            </div>
                        </div>
                        </div>
                    </div>
                    </div>
                </div>
			</div>
		</div>
	</section>
	<!-- //contents_wrap -->
</section>
</body>
</html>
