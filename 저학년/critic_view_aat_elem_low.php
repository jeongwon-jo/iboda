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

//echo $SQL."<br>";

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

function sum_point($order,$ocode,$schoolGradeQuery){
	global $connect;

	/*$sql = "select wae.point as tot_point FROM wt_apply_analysis waa LEFT JOIN wt_analysis_question waq ON waa.question_seq = waq.question_seq AND waq.gubun = '2103' AND waq.use_yn = 'Y' LEFT JOIN wt_analysis_example wae ON waa.answer_seq = wae.example_seq WHERE waq.question_seq IS NOT NULL AND waa.ocode='".$ocode."' AND ($schoolGradeQuery OR waq.school_grade = '404') and waq.od in (".$order.")";
	//echo "sum_point_sql = ".$sql."<br>";

	$result = mysqli_query($connect, $sql);
    $row = mysqli_fetch_array($result);
	
	$tot_point = $row['tot_point'];*/

	$sql = "select waa.analysis_seq, waa.ocode, waa.question_seq, waq.*, waa.answer_seq, wae.example_nm, waa.answer_text, wae.point, (SELECT group_concat(tmp.example_seq) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as example_list,(SELECT group_concat(tmp.point) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as point_list FROM wt_apply_analysis waa LEFT JOIN wt_analysis_question waq ON waa.question_seq = waq.question_seq AND waq.gubun = '2103' AND waq.use_yn = 'Y' LEFT JOIN wt_analysis_example wae ON waa.answer_seq = wae.example_seq WHERE waq.question_seq IS NOT NULL AND waa.ocode='".$ocode."' AND ($schoolGradeQuery OR waq.school_grade = '404') and waq.od in (".$order.")";
	$result = mysqli_query($connect, $sql);
	$row = mysqli_fetch_array($result);
		
	$input_type    = $row['input_type'];
	$answer_seq    = $row['answer_seq'];
	$example_list  = $row['example_list'];
	$point_list    = $row['point_list'];

	if($input_type == 'B') { // 다중선택
		$tot_point = calcMultiChoicePoint($answer_seq, $example_list, $point_list);
	} else { // 다중선택 아님 
		$tot_point = $row['point'];	
	}

	return $tot_point;

}

function sum_point_calc_per($order,$ocode,$schoolGradeQuery){
	global $connect;

	/*$sql = "select sum(wae.point) as tot_point,COUNT(waq.question_seq) as tot_cnt FROM wt_apply_analysis waa LEFT JOIN wt_analysis_question waq ON waa.question_seq = waq.question_seq AND waq.gubun = '2103' AND waq.use_yn = 'Y' LEFT JOIN wt_analysis_example wae ON waa.answer_seq = wae.example_seq WHERE waq.question_seq IS NOT NULL AND waa.ocode='".$ocode."' AND ($schoolGradeQuery OR waq.school_grade = '404') and waq.od in (".$order.")";
	//echo "sum_point_calc_sql = ".$sql."<br>";

	$result = mysqli_query($connect, $sql);
    $row = mysqli_fetch_array($result);
	
	$tot_point = $row['tot_point']*(100/(3*$row['tot_cnt']));*/

	$sql_cnt = "select question_seq from wt_analysis_question waq where 1 and gubun = '2103' and use_yn = 'Y' and ($schoolGradeQuery OR school_grade = '404') and od in (".$order.")";
	if($order == 1){
		//echo "sql_cnt = ".$sql_cnt."<br>";
	}
	$result_cnt = mysqli_query($connect, $sql_cnt);
	$tot_cnt = mysqli_num_rows($result_cnt); // 해당 문항의 갯수

	$sql = "select waa.analysis_seq, waa.ocode, waa.question_seq, waq.*, waa.answer_seq, wae.example_nm, waa.answer_text, wae.point, (SELECT group_concat(tmp.example_seq) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as example_list,(SELECT group_concat(tmp.point) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as point_list FROM wt_apply_analysis waa LEFT JOIN wt_analysis_question waq ON waa.question_seq = waq.question_seq AND waq.gubun = '2103' AND waq.use_yn = 'Y' LEFT JOIN wt_analysis_example wae ON waa.answer_seq = wae.example_seq WHERE waq.question_seq IS NOT NULL AND waa.ocode='".$ocode."' AND ($schoolGradeQuery OR waq.school_grade = '404') and waq.od in (".$order.")";
	if($order == 1){
		//echo "sql = ".$sql."<br>";
	}
	$result = mysqli_query($connect, $sql);
	
	$tot_point = 0;
	for($i=0; $i<mysqli_num_rows($result); $i++){ // 루프시작 
		$row = mysqli_fetch_array($result);
		
		$input_type    = $row['input_type'];
		$answer_seq    = $row['answer_seq'];
		$example_list  = $row['example_list'];
		$point_list    = $row['point_list'];

		if($input_type == 'B') { // 다중선택
			$point = calcMultiChoicePoint($answer_seq, $example_list, $point_list);
		} else { // 다중선택 아님 
			$point = $row['point'];	
		}
		
		$tot_point = $tot_point+$point;
	} // 루프종료 
	
	$tot_point = $tot_point*(100/(3*$tot_cnt));

	return $tot_point;

}

function sum_point_calc($order,$ocode,$schoolGradeQuery){
	global $connect;

	$sql = "select waa.analysis_seq, waa.ocode, waa.question_seq, waq.*, waa.answer_seq, wae.example_nm, waa.answer_text, wae.point, (SELECT group_concat(tmp.example_seq) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as example_list,(SELECT group_concat(tmp.point) FROM wt_analysis_example tmp WHERE tmp.question_seq = waa.question_seq AND waq.input_type = 'B') as point_list FROM wt_apply_analysis waa LEFT JOIN wt_analysis_question waq ON waa.question_seq = waq.question_seq AND waq.gubun = '2103' AND waq.use_yn = 'Y' LEFT JOIN wt_analysis_example wae ON waa.answer_seq = wae.example_seq WHERE waq.question_seq IS NOT NULL AND waa.ocode='".$ocode."' AND ($schoolGradeQuery OR waq.school_grade = '404') and waq.od in (".$order.")";
	//echo $sql."<br>";
	$result = mysqli_query($connect, $sql);
	
	$tot_point = 0;
	for($i=0; $i<mysqli_num_rows($result); $i++){ // 루프시작 
		$row = mysqli_fetch_array($result);
		
		$input_type    = $row['input_type'];
		$answer_seq    = $row['answer_seq'];
		$example_list  = $row['example_list'];
		$point_list    = $row['point_list'];

		if($input_type == 'B') { // 다중선택
			$point = calcMultiChoicePoint($answer_seq, $example_list, $point_list);
		} else { // 다중선택 아님 
			$point = $row['point'];	
		}
		
		$tot_point = $tot_point+$point;
	} // 루프종료 
	
	return $tot_point;

}

/*$inc_boda_txt_arr_1 = [
	'ERNC' => '있는 그대로 그려내는 전략가'
	,'ERNS' => '있는 그대로 그려내는 옹호자'
	,'ERDC' => '화려하게 꾸미는 전략가'
	,'ERDS' => '화려하게 꾸미는 옹호자'
	,'EANC' => '본질로 바라보는 전략가'
	,'EANS' => '본질로 바라보는 옹호자'
	,'EADC' => '특별하게 주목하는 전략가'
	,'EADS' => '특별하게 주목하는 옹호자'
	,'IRNC' => '있는 그대로 그려내는 사색가'
	,'IRNS' => '있는 그대로 그려내는 몽상가'
	,'IRDC' => '화려하게 꾸미는 사색가'
	,'IRDS' => '화려하게 꾸미는 몽상가'
	,'IANC' => '본질로 바라보는 사색가'
	,'IANS' => '본질로 바라보는 몽상가'
	,'IADC' => '특별하게 주목하는 사색가'
	,'IADS' => '특별하게 주목하는 몽상가'
];*/

$inc_boda_txt_arr_1 = [
	'ERNC' => '있는 그대로의 사상가'
	,'ERNS' => '있는 그대로의 공감자'
	,'ERDC' => '복잡함을 즐기는 사상가'
	,'ERDS' => '복잡함을 즐기는 공감자'
	,'EANC' => '본질을 보는 사상가'
	,'EANS' => '본질을 보는 공감자'
	,'EADC' => '질서를 찾는 사상가'
	,'EADS' => '질서를 찾는 공감자'
	,'IRNC' => '있는 그대로의 성찰자'
	,'IRNS' => '있는 그대로의 몽상가'
	,'IRDC' => '복잡함을 즐기는 성찰자'
	,'IRDS' => '복잡함을 즐기는 몽상가'
	,'IANC' => '본질을 보는 성찰자'
	,'IANS' => '본질을 보는 몽상가'
	,'IADC' => '질서를 찾는 성찰자'
	,'IADS' => '질서를 찾는 몽상가'
];

$inc_boda_txt_arr_2 = [
	'E' => '외부'
	,'I' => '내면'
	,'R' => '재현'
	,'A' => '추상'
	,'N' => '자연'
	,'D' => '장식'
	,'C' => '인지'
	,'S' => '정서'
];

$inc_boda_txt_arr_3 = [
	//'E' => '‘외부’ 성향은 사회적, 역사적 이야기 등 내가 아닌 바깥 세계에 대한 내용을 그림의 주제로 선택하여 표현하려는 성향입니다. 말과 글이 아닌 시각적 요소를 활용하여 의미를 전달하는 체계(system)를 ‘시각 언어’라고 부르는데, ‘외부’ 성향을 가진 창작자는 미술활동 안에서 시각언어를 활용해 내용을 객관적으로 전달하고 소통하는데 관심을 보입니다.'
	
	//,'I' => '오랜 기간의 미술의 역사에서 그림은 자신이 아닌 외부의 대상을 잘 모방하여 표현하는 것을 중요한 과제로 생각해왔습니다. 그러나 자신의 내면과 감정 또한 그림을 통해 모방할 수 있다는 ‘표현’주의의 경향이 생기면서, 미술은 개인의 주관적인 느낌과 생각을 작품으로 나타낼 수 있게 되었습니다. ‘내면’성향을 가진 창작자는 자신만의 의미를 담은 상징적 색과 형태의 표현, 독창성 통해 내면을 드러냄으로써 관람자와 소통하는 것을 선호합니다.'
	
	'E' => '‘외부 세계’를 선호하는 성향은 사회적 이슈, 역사적 이야기 등 주변 세계에 대한 내용을 그림의 주제로 발전시켜 표현하려는 성향입니다. 이러한 성향을 가진 창작자는 바깥 세계에 대한 관심과 민감한 관찰력을 가지고 있으며, 미술 활동을 통하여 관람자에게 자신의 메시지를 전달하고 소통하고자 합니다.'
	
	,'I' => '‘내면 세계’를 선호하는 성향은 자신의 내면과 감정, 무의식을 미술을 통해 표현하는 데 관심이 있습니다. 이러한 창작자는 자신만의 의미를 담은 상징적 색과 이미지를 활용하여 주관적인 느낌과 생각을 독창적인 방식으로 나타내어 관람자와 감정적, 정서적으로 소통하는 것을 선호합니다. '
	
	/*,'R' => '대상의 형태와 특징을 있는 그대로 표현하려는 성향입니다. 관람자는 화가의 모사(모방하여 따라 그리는 것)를 통해 표현 대상을 구체적인 형태로 지각하고 경험합니다. 창작자는 표현할 대상을 경험하지 못했거나, 현재 관찰할 수 없다 하더라도 얼마든지 재현할 수 있습니다. 과거의 이야기이든 상상의 이야기이든 화가는 관람자의 눈 앞에 현재(present)하는 것처럼 사실적으로 그려낼 수 있기 때문입니다. 사진이 존재하지 않았던 시기에는 이러한 미술 작품이 사진의 역할을 대신 하였습니다. 현재하지 않는 것이 창작활동을 통해 ‘다시’ 현재할 수 있다(RE-present)면 그것을 재현이라고 말합니다.'
	
	,'A' => '개념 또는 사물을 본질적인 색과 형태로 표현하고자 하는 성향입니다. 3원색(빨강, 노랑, 파랑)과 3원광(빨강, 초록, 파랑)은 혼합을 통해 일상에서 발견되는 거의 모든 색을 만들어 낼 수 있는 근본적이고 ‘본질적인 색’입니다. 형태도 마찬가지 입니다. 주변에서 관찰할 수 있는 다양하고 복잡한 사물의 형태는 동그라미, 세모, 네모와 같은 ‘본질적인 형태’로 단순화하여 표현할 수 있습니다. ‘추상’ 성향의 창작자는 이런 단순화 된 색과 형태를 활용한 작품을 통해 구체적인 묘사와 표현으로는 나타낼 수 없는 개념, 본질, 정신 등을 이야기 하고자 합니다. '*/
	
	,'R' => '대상의 형태와 특징을 있는 그대로 표현하려는 성향입니다. 관람자는 창작자가 모방한 것을 통해  대상을 구체적인 형태로 지각하고 경험합니다. 하지만 반드시 직접 경험했거나, 눈 앞에서 관찰할 수 있는 것만 재현할 수 있는 것은 아닙니다. 창작자는 조형 언어를 활용해서 상상의 이야기나 인물이라도 사실적으로 그려내여 마치 실제 존재하는 것처럼 다시 보여줄 수 있습니다.'
	
	,'A' => '개념 또는 사물을 본질적인 색과 형태로 표현하고자 하는 성향입니다. 주변에서 관찰할 수 있는 다양하고 복잡한 사물의 형태는 동그라미, 세모, 네모와 같은 ‘본질적인 형태’로 단순화하여 표현할 수 있습니다. ‘추상’ 성향의 창작자는 이런 단순화 된 색과 형태를 활용한 작품을 통해 구체적인 묘사와 표현으로는 나타낼 수 없는 개념, 본질, 정신 등을 이야기 하고자 합니다. '
	
	/*,'N' => '주변 세계를 경험적 관찰을 통해 정확하게 표현하려는 성향입니다. 관찰과 정확한 표현, 사실적인 묘사라는 측면은 재현과 비슷한 부분이지만, ‘자연스럽다’는 것은 경험된 것을 꾸미지 않고 ‘있는 그대로 그린다’는 느낌이 강합니다. 완전한 자연주의자라면 없는 것을 인위적으로 만들거나 수정, 보완하는 것을 비교적 선호하지 않을 것입니다. 있는 그대로의 아름다움을 발견하고 표현한다면 그것만으로도 좋은 작품이 되기 때문입니다.'
	
	,'D' => '보이는 그대로 대상을 그려내기 보다 특정 요소의 강조 또는 의미작용을 위한 ‘꾸밈’에 관심을 두는 표현성향입니다. 대상에 대한 ‘꾸밈’행위는 개인의 스타일이 반영되는 것으로 ‘장식’성향의 창작자들은 주로 조형요소(점, 선, 면, 형태 등)와 패턴 등을 활용해 화면에 자신만의 개성을 드러낼 수 있습니다. 자연스러운 것과 장식적인 것을 명확하게 구분하기 어렵지만, 장식적인 요소가 많아질수록 작품은 화려하고 평면적인 특징을 보이게 됩니다.'*/
	
	,'N' => '주변 세계를 경험적 관찰을 통해 정확하게 표현하려는 성향입니다. 관찰과 정확한 표현, 사실적인 묘사를 특징으로 하는 자연주의자는 없는 것을 인위적으로 만들거나 수정, 보완하는 것을 비교적 선호하지 않을 것입니다. 있는 그대로의 아름다움을 발견하고 표현한다면 그것만으로도 좋은 작품이 된다고 생각하기 떄문입니다.'
	
	,'D' => '보이는 그대로 그려내기 보다 특정 요소나 의미를 강조하기 위해 ‘조형성’에 관심을 두는 표현성향입니다. ‘장식적’ 성향의 창작자들은 주로 조형요소(점, 선, 면, 형태 등)와 패턴 등을 활용해 화면에 자신만의 개성을 드러낼 수 있습니다. 장식적인 요소가 많아질수록 작품은 화려하고 평면적인 특징을 보이게 됩니다.'
	
	/*,'C' => '미술(美術)은 문자 그대로 해석하면 ‘아름다움을 표현하는 기술’입니다. 그러나 종종 우리는 작품 안에서 화가의 화려한 기술이 아닌, 숨겨진 의미나 내용을 발견하는 경우가 있습니다. 창작자가 의도하여 관람자에게 사고작용를 불러 일으킬 수도 있고, 창작자의 의도는 아니지만 관람자의 감상과정에서 의미나 사고작용이 생겨날 수 있습니다. 창작 또는 감상의 과정에서, 이렇듯 미술적 주제에 대해 사유하며 생각을 불러 일으키는 작품을 선호한다면 ‘인지’적 성향이라고 할 수 있습니다.'
	
	,'S' => '정서는 기쁨, 분노, 슬픔, 즐거움 등과 같은 일반적인 감정보다 좀 더 섬세한 것입니다. 미적대상과 ‘정서적’으로 교류한다는 것은 일반적인 감정 뿐만 아니라 창작자와 관람자의 지속적이고 의식적인 경험까지도 포함한 개념입니다. 정서를 인위적으로 만들어 내는 것은 매우 어려운 일입니다. 정서는 우리 안에서 일어나는 일이지만, 우리 자신이 일으키는 일은 아니기 때문입니다. 때문에 ‘정서’ 성향의 창작자와 관람자는 효과적인 정서적 교류를 위해 보다 자유롭고 즉흥적인 표현 방식을 선호하며, 직관적으로 교감을 할 수 있는 표현적인 작품을 즐기게 됩니다.'*/
	
	,'C' => '미술은 자신을 표현하는 도구이지만 주변 세계를 이해하는 수단이 되기도 합니다. 시각적인 사고를 통해 수리, 언어, 과학과는 다른 방식으로 세상에 대한 의미와 이야기를 만들 수 있습니다. ‘인지’적 성향의 경우 창작과 감상의 과정에서 미술적 주제에 대해 사유하기를 즐기며 생각을 불러 일으키는 작품을 선호합니다.'
	
	,'S' => '정서는 기쁨, 분노, 슬픔, 즐거움 등과 같은 감정을 포함하여 말로 표현할 수 없는 섬세하고 복잡한 마음의 상태를 뜻합니다. 정서는 감각적인 반응을 통해 일어나는 것으로 이를 인위적으로 만들어 내는 것은 어려운 일입니다. 때문에 ‘정서’ 성향의 창작자는 관람자와의 직관적인 교감을 위해 보다 자유롭고 즉흥적인 표현 방식을 선호합니다.'
];

?>
<link rel="stylesheet" href="css/critic_view_aat_elem_low.css">
</head>
<body>
<? include_once $_SERVER["DOCUMENT_ROOT"] . "/common/include/default/skip.php"; ?>
<section class="wrapper" id="wrapper">

	<!--  contents_wrap  -->
	<section class="contents my_studio critic_view_page" id="contents">
		<div class="wrap aat_wrap">
			<!--<div class="critic_top">
				<img src="/common/images/mystudio/critic_view_cover.png" alt="크리틱 결과지">
				<button type="button" class="close_btn" onclick="self.close();">레이어닫기</button>
			</div>-->

            <?
            ##-- 미술적성검사 항목 조회 (gubun = '2103') - 학년등급 공통(404)항목과, 신청자의 school_grade 에 해당되는 미술적성검사 항목만 조회되도록 수정-2022-04-28-kjy
            $schoolGradeQuery = '';
            if($school_grade == '403') $schoolGradeQuery = "waq.school_grade = '402'";
            else $schoolGradeQuery = "waq.school_grade = '".$school_grade."'";        

			//$new_type_point_123 = sum_point_calc_per("1,2,3",$ocode,$schoolGradeQuery);
			$new_type_point_123 = sum_point_calc_per("1",$ocode,$schoolGradeQuery);
			//echo "new_type_point_123 = ".$new_type_point_123."<br>";
			$type1 = $new_type_point_123 >= 50 ? 'I' : 'E';
            $type1Txt = $new_type_point_123 >= 50 ? '내면 세계' : '외부 세계';
            $type1TxtShort = $new_type_point_123 >= 50 ? '내면' : '외부';
            $direction1 = $new_type_point_123 >= 50 ? 'right' : 'left';
            $type1Val = number_format(abs($new_type_point_123));
			
			//$new_type_point_456 = sum_point_calc_per("4,5,6",$ocode,$schoolGradeQuery);
			$new_type_point_456 = sum_point_calc_per("2",$ocode,$schoolGradeQuery);
			//echo "new_type_point_456 = ".$new_type_point_456."<br>";
			$type2 = $new_type_point_456 >= 50 ? 'R' : 'A';
            $type2Txt = $new_type_point_456 >= 50 ? '재현적' : '추상적';
            $type2TxtShort = $new_type_point_456 >= 50 ? '재현' : '추상';
            $direction2 = $new_type_point_456 >= 50 ? 'left' : 'right';
            $type2Val = number_format(abs($new_type_point_456));

			//$new_type_point_789 = sum_point_calc_per("7,8,9",$ocode,$schoolGradeQuery);
			$new_type_point_789 = sum_point_calc_per("3",$ocode,$schoolGradeQuery);
			//echo "new_type_point_789 = ".$new_type_point_789."<br>";
			$type3 = $new_type_point_789 >= 50 ? 'D' : 'N';
            $type3Txt = $new_type_point_789 >= 50 ? '장식적' : '자연주의적';
            $type3TxtShort = $new_type_point_789 >= 50 ? '장식' : '자연';
            $direction3 = $new_type_point_789 >= 50 ? 'right' : 'left';
            $type3Val = number_format(abs($new_type_point_789));

			//$new_type_point_1012 = sum_point_calc_per("10,11,12",$ocode,$schoolGradeQuery);
			$new_type_point_1012 = sum_point_calc_per("4",$ocode,$schoolGradeQuery);
			//echo "new_type_point_1012 = ".$new_type_point_1012."<br>";
			$type4 = $new_type_point_1012 >= 50 ? 'C' : 'S';
			$type4Txt = $new_type_point_1012 >= 50 ? '인지적' : '정서적';
			$type4TxtShort = $new_type_point_1012 >= 50 ? '인지' : '정서';
			$direction4 = $new_type_point_1012 >= 50 ? 'left' : 'right';
			$type4Val = number_format(abs($new_type_point_1012));

			//$new_type_point_1315 = sum_point_calc_per("13,14,15",$ocode,$schoolGradeQuery);
			$new_type_point_1315 = sum_point_calc_per("5",$ocode,$schoolGradeQuery);
			//echo "new_type_point_1315 = ".$new_type_point_1315."<br>";
			$type5 = $new_type_point_1315 >= 50 ? 'O' : 'G';
			$type5Txt = $new_type_point_1315 >= 50 ? '유기적' : '기하학적';
			$type5TxtShort = $new_type_point_1315 >= 50 ? '유기' : '기하학';
			$direction5 = $new_type_point_1315 >= 50 ? 'left' : 'right';
			$type5Val = number_format(abs($new_type_point_1315));

			//$new_type_point_1618 = sum_point_calc_per("16,17,18",$ocode,$schoolGradeQuery);
			$new_type_point_1618 = sum_point_calc_per("6",$ocode,$schoolGradeQuery);
			//echo "new_type_point_1618 = ".$new_type_point_1618."<br>";
			$type6 = $new_type_point_1618 >= 50 ? '2' : '3';
			$type6Txt = $new_type_point_1618 >= 50 ? '평면적' : '입체적';
			$type6TxtShort = $new_type_point_1618 >= 50 ? '평면' : '입체';
			$direction6 = $new_type_point_1618 >= 50 ? 'left' : 'right';
			$type6Val = number_format(abs($new_type_point_1618));

			//$new_type_point_2022 = sum_point_calc_per("20,21,22",$ocode,$schoolGradeQuery);
			$new_type_point_2022 = sum_point_calc_per("7",$ocode,$schoolGradeQuery);
			$type7 = $new_type_point_2022 >= 50 ? '난색' : '한색';
			$type7Easy = $new_type_point_2022 >= 50 ? '따뜻한 색' : '차가운 색';
			$type7Txt = $new_type_point_2022 >= 50 ? '난색' : '한색';
			$colorTxt2_new = $new_type_point_2022 >= 50 ? '밝음, 따뜻함, 포근한' : '어두움, 차가움, 냉철한';
			$direction7 = $new_type_point_2022 >= 50 ? 'left' : 'right';
			$type7Val = number_format(abs($new_type_point_2022));

			//$new_type_point_2325 = sum_point_calc_per("23,24,25",$ocode,$schoolGradeQuery);
			$new_type_point_2325 = sum_point_calc_per("8",$ocode,$schoolGradeQuery);
			$type8 = $new_type_point_2325 >= 50 ? '보색대비' : '유사대비';
			$type8Txt = $new_type_point_2325 >= 50 ? '명쾌하고 화려한' : '부드럽고 정적인';
			$direction8 = $new_type_point_2325 >= 50 ? 'left' : 'right';
			$type8Val = number_format(abs($new_type_point_2325));

			$new_type_point_19 = sum_point("19",$ocode,$schoolGradeQuery);
			switch($new_type_point_19){
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

			$new_type_point_2630 = sum_point_calc("26,27,28,29,30",$ocode,$schoolGradeQuery);
			$literacy1Point = ($new_type_point_2630 * 2);
			$new_type_point_30 = sum_point_calc("30",$ocode,$schoolGradeQuery);
			$literacy1Per = number_format(abs($new_type_point_30 * 100 / 14), 2);

			$new_type_point_3132 = sum_point_calc("31,32",$ocode,$schoolGradeQuery);
			$literacy2Point = ($new_type_point_3132 * 1);
			$literacyPoint = $literacy1Point + $literacy2Point;
			$literacyPer = number_format(abs($literacyPoint * (100/16)), 2);

			/*$new_type_point_32 = sum_point_calc("32",$ocode,$schoolGradeQuery);
			$literacyPoint = $literacy1Point + $new_type_point_32;
			$literacyPer = number_format(abs($literacyPoint * (100/16)), 2);*/
			
			$new_type_point_3339 = sum_point_calc("33,34,35,36,37,38,39",$ocode,$schoolGradeQuery);
			$visualPoint = $new_type_point_3339;
			//echo "visualPoint = ".$visualPoint."<br>";
			$visualPer = number_format(abs($visualPoint * (100/10)), 2);
			/*$new_type_point_39 = sum_point_calc("39",$ocode,$schoolGradeQuery);
			$visualPer = number_format(abs($new_type_point_39 * (100/10)), 2);*/
						
			$new_type_point_4045 = sum_point_calc("40,41,42,43,44,45",$ocode,$schoolGradeQuery);
			$spatialPoint = $new_type_point_4045;
			//if($order == 45) $spatialPer = number_format(abs($spatialPoint * 100 / 6), 2);
			$spatialPer = number_format(abs($spatialPoint * (100/6)), 2);
			
			$new_type_point_4649 = sum_point_calc("46,47,48,49",$ocode,$schoolGradeQuery);
			$implicitType1Point = $new_type_point_4649;
			//if($order == 49) $implicitType1Per = number_format(abs($implicitType1Point * 100 / 20), 2);
			$implicitType1Per = number_format(abs($implicitType1Point * (100/20)), 2);
			
			$new_type_point_5053 = sum_point_calc("50,51,52,53",$ocode,$schoolGradeQuery);
			$implicitType2Point = $new_type_point_5053;
			//if($order == 53) $implicitType2Per = number_format(abs($implicitType2Point * 100 / 20), 2);
			$implicitType2Per = number_format(abs($implicitType2Point * (100/20)), 2);

			$new_type_point_5457 = sum_point_calc("54,55,56,57",$ocode,$schoolGradeQuery);
			$implicitType3Point = $new_type_point_5457;
            //if($order == 57) $implicitType3Per = number_format(abs($implicitType3Point * 100 / 20), 2);
			$implicitType3Per = number_format(abs($implicitType3Point * (100/20)), 2);

			$new_type_point_5861 = sum_point_calc("58,59,60,61",$ocode,$schoolGradeQuery);
			$achieveType1Point = $new_type_point_5861;
            //if($order == 61) $achieveType1Per = number_format(abs($achieveType1Point * 100 / 20), 2);
			$achieveType1Per = number_format(abs($achieveType1Point * (100/20)), 2);

			$new_type_point_6265 = sum_point_calc("62,63,64,65",$ocode,$schoolGradeQuery);
			$achieveType2Point = $new_type_point_6265;
            //if($order == 65) $achieveType2Per = number_format(abs($achieveType2Point * 100 / 20), 2);
			$achieveType2Per = number_format(abs($achieveType2Point * (100/20)), 2);

			$new_type_point_6669 = sum_point_calc("66,67,68,69",$ocode,$schoolGradeQuery);
			$achieveType3Point = $new_type_point_6669;
            //if($order == 69) $achieveType3Per = number_format(abs($achieveType3Point * 100 / 20), 2);
			$achieveType3Per = number_format(abs($achieveType3Point * (100/20)), 2);

      // 시각적 문해력
      if($literacy2Point == 0) {
          $literacy2Level = 1;
      } else if($literacy2Point == 1) {
          $literacy2Level = 2;
      }else if($literacy2Point == 2) {
          $literacy2Level = 3;
      }

      // 시각이미지의 지각 능력
      if($visualPoint <= 2) {
          $visualLevel = 1;
      }
      else if(3 <= $visualPoint && $visualPoint <= 5 ) {
          $visualLevel = 2;
      }
      else if(6 <= $visualPoint && $visualPoint <= 8 ) {
          $visualLevel = 3;
      }
      else if(9 <= $visualPoint && $visualPoint <= 10 ) {
          $visualLevel = 4;
      }

      // 공간지각능력
      if($spatialPoint <= 2) {
          $spatialLevel = 1;
      }
      else if(3 <= $spatialPoint && $spatialPoint <= 4 ) {
          $spatialLevel = 2;
      }
      else if(5 <= $spatialPoint && $spatialPoint <= 6 ) {
          $spatialLevel = 3;
      }


      if(max($literacy2Level, $visualLevel, $spatialLevel) == $literacy2Level) {
          $visualTxt = '시각적 문해력';
          $visualTxt2 = '시지각 능력';
          $visualTxt3 = '공간 지각 능력';
      } 
      else if(max($literacy2Level, $visualLevel, $spatialLevel) == $visualLevel) {
          $visualTxt = '시지각 능력';
          $visualTxt2 = '시각적 문해력';
          $visualTxt3 = '공간 지각 능력';
      } 
      else if(max($literacy2Level, $visualLevel, $spatialLevel) == $spatialLevel) {
          $visualTxt = '공간 지각 능력';
          $visualTxt2 = '시각적 문해력';
          $visualTxt3 = '시지각 능력';
      } 

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

			<div id="wrap">
        <div class="elem_low_result">
          <div class="print_content">
            <section class="section_result_graph">
              <!-- 왼쪽 종합 영역 -->
              <div class="aat_result_cmp">
                <h5 class="aat_result_title">미술성향검사(AAT) 결과 종합</h5>
                <div class="color_thum">
                  <!-- 결과 컬러 background-color에 삽입 -->
                  <div class="thum_character" style="background-color: <?= $colorCode ?>;">
                    <img src="/common/images/elem_low/result_character.png" alt="결과 캐릭터">
                  </div>
                </div>
                <div class="result_desc">
                  <h2 class="name"><?= $Name ?><span>님</span></h2>
                  <p>나는 어떤 창작자일까?<br>
                    나의 미술성향 발견하기!</p>
                </div>
                <div class="result_summary">
                  <div class="result-summary__inner">
                    <div class="result_content">
                      <div class="result_title">
                        <span class="bar"></span>
                        <h3>미적 감수성</h3>
                        <span class="bar"></span>
                      </div>
                      <div class="result">
                        <div class="result_type"><span><?= $type1.$type2.$type3.$type4 ?> 유형</span></div>
                        <div class="result_type_list">
                          <div class="type">
                            <b><?= $type1 ?></b>
                            <span><?= $type1TxtShort ?></span>
                          </div>
                          <div class="type">
                            <b><?= $type2 ?></b>
                            <span><?= $type2TxtShort ?></span>
                          </div>
                          <div class="type">
                            <b><?= $type3 ?></b>
                            <span><?= $type3TxtShort ?></span>
                          </div>
                          <div class="type">
                            <b><?= $type4 ?></b>
                            <span><?= $type4TxtShort ?></span>
                          </div>
                          <div class="type">
                            <b><?= $type5 ?></b>
                            <span><?= $type5TxtShort ?></span>
                          </div>
                          <div class="type">
                            <b><?= $type6 ?>D</b>
                            <span><?= $type6TxtShort ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="result_content">
                      <div class="result_title">
                        <span class="bar"></span>
                        <h3>시각인지</h3>
                        <span class="bar"></span>
                      </div>
                      <?php
                        $leftvisualPer = max($literacyPer, $visualPer, $spatialPer);
                      ?>
                      <div class="result">
                        <div class="graph_item">
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content active_green left active" style="width: <?= floor($leftvisualPer) ?>%;" data-score="<?= floor($leftvisualPer) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_result_title"><?= $visualTxt ?></div>
                      </div>
                    </div>
                    <div class="result_content">
                      <div class="result_title">
                        <span class="bar"></span>
                        <h3>정서행동</h3>
                        <span class="bar"></span>
                      </div>
                      <?php 
                        $leftimplicitPer = max($implicitType1Per, $implicitType2Per, $implicitType3Per);
                        $leftachievePer = max($achieveType1Per, $achieveType2Per, $achieveType3Per);
                      ?>
                      <div class="result">
                        <div class="graph_item">
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content active_blue left active" style="width: <?= floor($leftimplicitPer) ?>%;" data-score="<?= floor($leftimplicitPer) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_result_title"><?= $implicitTypeTxt ?></div>
                        <div class="graph_item">
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content active_blue left active" style="width: <?= floor($leftachievePer) ?>%;" data-score="<?= floor($leftachievePer) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_result_title"><?= $achieveTypeTxt ?></div>
                      </div>
                    </div>
                    <div class="result_content">
                      <div class="result_title">
                        <span class="bar"></span>
                        <h3>색채성향</h3>
                        <span class="bar"></span>
                      </div>
                      <div class="result">
                        <div class="color_prefer">
                          <div class="prefer">
                            <?php if($type7 == '난색') $left_prefer_img_link = "color_prefer01_img01";
                              else $left_prefer_img_link = "color_prefer01_img02"; 
                              ?>
                            <img src="/common/images/elem_low/<?= $left_prefer_img_link ?>.png" alt="">
                            <span><?= $type7Easy ?></span>
                          </div>
                          <div class="prefer">
                            <?php if($type8 == '유사대비') $left_prefer_img_link2 = "color_prefer02_img01";
                              else $left_prefer_img_link2 = "color_prefer02_img02"; 
                              ?>
                            <img src="/common/images/elem_low/<?= $left_prefer_img_link2 ?>.png" alt="">
                            <span><?= $type8 ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- 오른쪽 그래프영역 -->
              <div class="aat_right_graph">
                <div class="graph_inner">
                  <div class="graph_title">
                    <div class="title">
                      <h3>미적<br>감수성</h3>
                    </div>
                    <div class="description">
                      <p><?= $Name ?> 님은 <span class="report_red"><?= $type1Txt ?>, <?= $type2Txt ?>, <?= $type3Txt ?>, <?= $type4Txt ?></span> 성향이 두드러지게 나타났어요!</p>
                    </div>
                  </div>
                  <div class="graph_content bdb">
                    <?
											if($type1Val >= 50){
												$score_1 = $type1Val;
												$score_2 = 100-$type1Val;
												$direction1 = "left";
											} else {
												$score_1 = $type1Val;
												$score_2 = 100-$type1Val;
												$direction1 = "right";
											}	
										?>
                    <div class="graph_item">
                      <h3 class="tit">표현 대상</h3>
                      <div class="bar-graph">
                        <div class="bar_title <?php if ($direction1 == 'left') { ?> active <?php }?>">
                          <span>외부<br>세계</span>
                        </div>
                        <div class="graph <?php if ($direction1 == 'right') { ?> right <?php }?>">
                          <div class="graph-content red <?= $direction1 ?> active" style="width: <?=max($score_1, $score_2)?>%;" data-score="<?=max($score_1, $score_2)?>"></div>
                        </div>
                        <div class="bar_title <?php if ($direction1 == 'right') { ?> active <?php }?>">
                          <span>내면<br>세계</span>
                        </div>
                      </div>
                    </div>
                    <?
											if($type2Val >= 50){
												$score_1 = $type2Val;
												$score_2 = 100-$type2Val;
												$direction2 = "left";
											} else {
												$score_1 = $type2Val;
												$score_2 = 100-$type2Val;
												$direction2 = "right";
											}	
										?>
                    <div class="graph_item">
                      <h3 class="tit">구상 방식</h3>
                      <div class="bar-graph">
                        <div class="bar_title <?php if ($direction2 == 'left') { ?> active <?php }?>">
                          <span>재현</span>
                        </div>
                        <div class="graph <?php if ($direction2 == 'right') { ?> right <?php }?>">
                          <div class="graph-content red <?= $direction2 ?> active" style="width: <?=max($score_1, $score_2)?>%;" data-score="<?=max($score_1, $score_2)?>"></div>
                        </div>
                        <div class="bar_title <?php if ($direction1 == 'right') { ?> active <?php }?>">
                          <span>추상</span>
                        </div>
                      </div>
                    </div>
                    <?
											if($type3Val >= 50){
												$score_1 = $type3Val;
												$score_2 = 100-$type3Val;
												$direction3 = "left";
											} else {
												$score_1 = $type3Val;
												$score_2 = 100-$type3Val;
												$direction3 = "right";
											}	
										?>
                    <div class="graph_item">
                      <h3 class="tit">표현 양식</h3>
                      <div class="bar-graph">
                        <div class="bar_title <?php if ($direction3 == 'left') { ?> active <?php }?>">
                          <span>자연</span>
                        </div>
                        <div class="graph <?php if ($direction3 == 'right') { ?> right <?php }?>">
                          <div class="graph-content red <?= $direction3 ?> active" style="width: <?=max($score_1, $score_2)?>%;" data-score="<?=max($score_1, $score_2)?>"></div>
                        </div>
                        <div class="bar_title <?php if ($direction3 == 'right') { ?> active <?php }?>">
                          <span>장식</span>
                        </div>
                      </div>
                    </div>
                    <?
											//$type4Val = "49";
											if($type4Val >= 50){
												$score_1 = $type4Val;
												$score_2 = 100-$type4Val;
												$direction4 = "left";
											} else {
												$score_1 = $type4Val;
												$score_2 = 100-$type4Val;
												$direction4 = "right";
											}	
										?>
                    <div class="graph_item">
                      <h3 class="tit">사고 양식</h3>
                      <div class="bar-graph">
                        <div class="bar_title <?php if ($direction4 == 'left') { ?> active <?php }?>">
                          <span>인지</span>
                        </div>
                        <div class="graph <?php if ($direction4 == 'right') { ?> right <?php }?>">
                          <div class="graph-content red <?= $direction4 ?> active" style="width: <?=max($score_1, $score_2)?>%;" data-score="<?=max($score_1, $score_2)?>"></div>
                        </div>
                        <div class="bar_title <?php if ($direction4 == 'right') { ?> active <?php }?>">
                          <span>정서</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?
										//$type5Val = "49";
										if($type5Val >= 50){
											$score_1 = $type5Val;
											$score_2 = 100-$type5Val;
											$direction5 = "left";
										} else {
											$score_1 = $type5Val;
											$score_2 = 100-$type5Val;
											$direction5 = "right";
										}	
									?>
                  <div class="graph_content">
                    <div class="graph_item">
                      <h3 class="tit">선과 형태</h3>
                      <div class="bar-graph">
                        <div class="bar_title <?php if ($direction5 == 'left') { ?> active <?php }?>">
                          <span>유기</span>
                        </div>
                        <div class="graph <?php if ($direction5 == 'right') { ?> right <?php }?>">
                          <div class="graph-content red <?= $direction5 ?> active" style="width: <?=max($score_1, $score_2)?>%;" data-score="<?=max($score_1, $score_2)?>"></div>
                        </div>
                        <div class="bar_title <?php if ($direction5 == 'right') { ?> active <?php }?>">
                          <span>기하</span>
                        </div>
                      </div>
                    </div>
                    <?
                      //$type6Val = "49";
                      if($type6Val >= 50){
                        $score_1 = $type6Val;
                        $score_2 = 100-$type6Val;
                        $direction6 = "left";
                      } else {
                        $score_1 = $type6Val;
                        $score_2 = 100-$type6Val;
                        $direction6 = "right";
                      }	
                    ?>
                    <div class="graph_item">
                      <h3 class="tit">공간</h3>
                      <div class="bar-graph">
                        <div class="bar_title <?php if ($direction6 == 'left') { ?> active <?php }?>">
                          <span>2D</span>
                        </div>
                        <div class="graph <?php if ($direction6 == 'right') { ?> right <?php }?>">
                          <div class="graph-content red <?= $direction6 ?> active" style="width: <?=max($score_1, $score_2)?>%;" data-score="<?=max($score_1, $score_2)?>"></div>
                        </div>
                        <div class="bar_title <?php if ($direction6 == 'left') { ?> active <?php }?>">
                          <span>3D</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="graph_inner">
                  <div class="graph_title">
                    <div class="title green">
                      <h3>시각<br>인지</h3>
                    </div>
                    <div class="description">
                      <p><?= $Name ?> 님은 <span class="report_green"><?= $visualTxt ?></span> 이 강점이에요!</p>
                    </div>
                  </div>
                  <div class="graph_content">
                    <div class="graph_type2_box green">
                      <div class="graph_active_bar">
                        <span class="dot <?php if (max($literacyPer, $visualPer, $spatialPer) == $literacyPer) { ?> active <?php }?>"></span>
                        <span class="dot <?php if (max($literacyPer, $visualPer, $spatialPer) == $visualPer) { ?> active <?php }?>"></span>
                        <span class="dot <?php if (max($literacyPer, $visualPer, $spatialPer) == $literacspatialPerPer) { ?> active <?php }?>"></span>
                      </div>
                      <div class="graph_type2_items">
                        <div class="graph_item">
                          <div class="type2_title">시각적 문해력</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($literacyPer, $visualPer, $spatialPer) == $literacyPer) { ?> active_green <?php } else { ?> green <?php
                              }?> left active" style="width: <?= floor($literacyPer) ?>%;" data-score="<?= floor($literacyPer) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_item">
                          <div class="type2_title">시지각 능력</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($literacyPer, $visualPer, $spatialPer) == $visualPer) { ?> active_green <?php } else { ?> green <?php
                              }?> left active" style="width: <?= floor($visualPer) ?>%;" data-score="<?= floor($visualPer) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_item">
                          <div class="type2_title">공간지각 능력</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($literacyPer, $visualPer, $spatialPer) == $spatialPer) { ?> active_green <?php } else { ?> green <?php
                              }?> left active" style="width: <?= floor($spatialPer) ?>%;" data-score="<?= floor($spatialPer) ?>"></div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="graph_inner">
                  <div class="graph_title">
                    <div class="title blue">
                      <h3>정서<br>행동</h3>
                    </div>
                    <div class="description">
                      <p><?= $Name ?> 님은 <span class="report_blue"><?= $implicitTypeTxt ?>, <?= $achieveTypeTxt ?></span> 이 강점이에요!</p>
                    </div>
                  </div>
                  <div class="graph_content">
                    <div class="graph_type2_box blue">
                      <div class="graph_active_bar">
                        <span class="dot <?php if (max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType1Per) { ?> active <?php }?>"></span>
                        <span class="dot <?php if (max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType2Per) { ?> active <?php }?>"></span>
                        <span class="dot <?php if (max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType3Per) { ?> active <?php }?>"></span>
                      </div>
                      <div class="graph_type2_items">
                        <div class="graph_item">
                          <div class="type2_title">호기심</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType1Per) { ?> active_blue <?php } else { ?> blue <?php
                              }?> left active" style="width: <?= floor($implicitType1Per) ?>%;" data-score="<?= floor($implicitType1Per) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_item">
                          <div class="type2_title">개방성</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType2Per) { ?> active_blue <?php } else { ?> blue <?php
                              }?> left active" style="width: <?= floor($implicitType2Per) ?>%;" data-score="<?= floor($implicitType2Per) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_item">
                          <div class="type2_title">자기결정성</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($implicitType1Per, $implicitType2Per, $implicitType3Per) == $implicitType3Per) { ?> active_blue <?php } else { ?> blue <?php
                              }?> left active" style="width: <?= floor($implicitType3Per) ?>%;" data-score="<?= floor($implicitType3Per) ?>"></div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="graph_type2_box blue">
                      <div class="graph_active_bar">
                        <span class="dot <?php if (max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType1Per) { ?> active <?php }?>"></span>
                        <span class="dot <?php if (max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType2Per) { ?> active <?php }?>"></span>
                        <span class="dot <?php if (max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType3Per) { ?> active <?php }?>"></span>
                      </div>
                      <div class="graph_type2_items">
                        <div class="graph_item">
                          <div class="type2_title">과제집착력</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType1Per) { ?> active_blue <?php } else { ?> blue <?php
                              }?> left active" style="width: <?= floor($achieveType1Per) ?>%;" data-score="<?= floor($achieveType1Per) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_item">
                          <div class="type2_title">도전성</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType2Per) { ?> active_blue <?php } else { ?> blue <?php
                              }?> left active" style="width: <?= floor($achieveType2Per) ?>%;" data-score="<?= floor($achieveType2Per) ?>"></div>
                            </div>
                          </div>
                        </div>
                        <div class="graph_item">
                          <div class="type2_title">자신감</div>
                          <div class="bar-graph">
                            <div class="graph">
                              <div class="graph-content <?php if (max($achieveType1Per, $achieveType2Per, $achieveType3Per) == $achieveType3Per) { ?> active_blue <?php } else { ?> blue <?php
                              }?> left active" style="width: <?= floor($achieveType3Per) ?>%;" data-score="<?= floor($achieveType3Per) ?>"></div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </div>
          <div class="print_content">
            <section id="section_reulst_map" class="section_reulst_map">
              <div class="map_click_modal print-hide">
                <span>지도의 단계별 그림을 클릭해 보세요.</span>
                <button type="button" class="btn_close"></button>
              </div>
              <div class="result_map_content">
                <div class="map_img">
                  <img src="/common/images/elem_low/result_map.png" alt="결과 지도">
                  <div class="map_point point1" onclick="openSelectedModal()">
                    <img src="/common/images/elem_low/result_map_point1.png" alt="지도타입1">
                  </div>
                  <div class="map_point point2" onclick="openSelectedModal()">
                    <img src="/common/images/elem_low/result_map_point2.png" alt="지도타입2">
                  </div>
                  <div class="map_point point3" onclick="openSelectedModal()">
                    <img src="/common/images/elem_low/result_map_point3.png" alt="지도타입3">
                  </div>
                  <div class="map_point point4" onclick="openSelectedModal()">
                    <img src="/common/images/elem_low/result_map_point4.png" alt="지도타입4">
                  </div>
                  <div class="map_point point5" onclick="openSelectedModal()">
                    <img src="/common/images/elem_low/result_map_point5.png" alt="지도타입5">
                  </div>
                  <div class="map_point point6" onclick="openSelectedModal()">
                    <img src="/common/images/elem_low/result_map_point6.png" alt="지도타입6">
                  </div>
                </div>
              </div>
            </section>
            <section class="section_result_text">
              <div class="result_summary_notice">
                <div class="bubble">
                  <img src="/common/images/elem_low/iboda_logo_bubble.png" alt="">
                  <span><?= $Name ?> 님의 미술성향을 보다쌤과 함께 좀 더 자세히 알아봐요!</span>
                </div>
              </div>
              <div class="result_text_summary">
                <div class="title">
                  <span><?=$type1.$type2.$type3.$type4?> 유형</span>
                  <h2>나는 세상을 <?=$inc_boda_txt_arr_1[$type1.$type2.$type3.$type4]?></h2>
                  <img src="/common/images/aat/icon/<?=$type1.$type2.$type3.$type4?>.png" alt="">
                </div>
                <div class="text_summary_list">
                  <div class="text_summary_item">
                    <div class="text_alpha">
                      <h1><?= $type1 ?></h1>
                      <span><?=$inc_boda_txt_arr_2[$type1]?></span>
                    </div>
                    <p>
                      <?=$inc_boda_txt_arr_3[$type1]?>
                    </p>
                  </div>
                  <div class="text_summary_item">
                    <div class="text_alpha">
                      <h1><?= $type2 ?></h1>
                      <span><?=$inc_boda_txt_arr_2[$type2]?></span>
                    </div>
                    <p>
                      <?=$inc_boda_txt_arr_3[$type2]?>
                    </p>
                  </div>
                  <div class="text_summary_item">
                    <div class="text_alpha">
                      <h1><?= $type3 ?></h1>
                      <span><?=$inc_boda_txt_arr_2[$type3]?></span>
                    </div>
                    <p>
                      <?=$inc_boda_txt_arr_3[$type3]?>
                    </p>
                  </div>
                  <div class="text_summary_item">
                    <div class="text_alpha">
                      <h1><?= $type4 ?></h1>
                      <span><?=$inc_boda_txt_arr_2[$type4]?></span>
                    </div>
                    <p>
                      <?=$inc_boda_txt_arr_3[$type4]?>
                    </p>
                  </div>
                </div>
              </div>
            </section>
          </div>
          <div class="print_content print-show">
            <div class="print_select_img_content">
              <div class="select_img_content">
                <div class="select_title">
                  <div class="color_thum">
                    <!-- 결과 컬러 background-color에 삽입 -->
                    <div class="thum_character" style="background-color: <?= $colorCode ?>;">
                      <img src="/common/images/elem_low/result_character.png" alt="결과 캐릭터">
                    </div>
                  </div>
                  <div class="select_title_info">
                    <h4><?= $Name ?> 님이 선택한 작품들을 다시 한 번 감상해 보세요.</h4>
                    <p>
                      아래는 <?= $Name ?>님이 선택한 작품들입니다. <?= $Name ?>님의 성향이 잘 반영되어 있나요? 미술 성향에는 옳고 그름이나, 좋고 나쁨이 있지 않습니다. 중요한 것은 자신이 무엇을 선호하는지 이야기할 수 있는 것이며, 이러한 '선택'을 통해 나를 새롭게 이해하는 것입니다. 자신의 미적 선호에 맞는 다양한 시대의 미술 작품을 찾아 감상해 봅시다. 새로운 현대미술 작품의 감상은 여러분의 미술 성향을 다시 한 번 확인하고 더욱 확장할 수 있는 즐거운 경험이 될 것입니다.
                    </p>
                  </div>
                </div>
                <div class="select_img_list">
                  <div class="tit">
                    <p>표현 대상</p>
                    <span class="bar"></span>
                  </div>
                  <div class="img_list">
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                  </div>
                </div>
                <div class="select_img_list">
                  <div class="tit">
                    <p>구상 방식</p>
                    <span class="bar"></span>
                  </div>
                  <div class="img_list">
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                  </div>
                </div>
                <div class="select_img_list">
                  <div class="tit">
                    <p>표현 양식</p>
                    <span class="bar"></span>
                  </div>
                  <div class="img_list">
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                  </div>
                </div>
                <div class="select_img_list">
                  <div class="tit">
                    <p>사고 양식</p>
                    <span class="bar"></span>
                  </div>
                  <div class="img_list">
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="print_content print-show">
            <div class="print_select_img_content">
              <div class="select_img_content">
                <div class="select_img_list">
                  <div class="tit">
                    <p>선과 형태</p>
                    <span class="bar"></span>
                  </div>
                  <div class="img_list">
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                  </div>
                </div>
                <div class="select_img_list">
                  <div class="tit">
                    <p>공간</p>
                    <span class="bar"></span>
                  </div>
                  <div class="img_list">
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                    <div class="thum">
                      <img src="/common/images/elem_low/result_img_example.png" alt="">
                    </div>
                  </div>
                </div>
                <div class="print_select_memo">
                  <div class="memo__inner">
                    <h2>MEMO</h2>
                    <p>
                      123123123
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal_photo">
        <div class="modal__inner">
          <div class="select_img">
            <div class="select_img_content">
              <button type="button" class="btn_close_modal" onclick="closeSelectedModal()"></button>
              <div class="title">
                <h3>여러분이 선택한 그림을 다시 한 번 감상해 보세요.</h3>
              </div>
              <div class="select_img_list">
                <div class="tit">
                  <p>표현 대상</p>
                  <span class="bar"></span>
                </div>
                <div class="img_list">
                  <div class="thum">
                    <img src="/common/images/elem_low/result_img_example.png" alt="">
                  </div>
                  <div class="thum">
                    <img src="/common/images/elem_low/result_img_example.png" alt="">
                  </div>
                  <div class="thum">
                    <img src="/common/images/elem_low/result_img_example.png" alt="">
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

<script>
  let graphContent = document.querySelectorAll('.aat_right_graph .bar-graph .graph-content');

  graphContent.forEach(graph => {
    let score = graph.dataset.score;

    let keyframes = [
      { width: '0%' },
      { width:  `${score}%`},
    ];

    let options = {
      duration: 1000,
      easing: "linear",
      fill: "forwards"
    };

    graph.animate(keyframes, options);
  });

  let isVisible = false;

  window.addEventListener('scroll', () => {
    if (!isVisible && $('#section_reulst_map').is(":visible")) {
      $('.section_reulst_map').addClass('animate')
      isVisible = true
    }
  });


  function openSelectedModal() {
    $('.modal_photo').addClass('open')
    $("body").addClass("fixed");
    const pageY = window.scrollY;
    $("body.fixed").css("position", "fixed");
    $("body.fixed").css("left", "0");
    $("body.fixed").css("top", `${(-(pageY))}px`);
  }

  function closeSelectedModal() {
    $('.modal_photo').removeClass('open')
    const top = $("body").css("top").replace("px", "");
    const topNum = (Number(-top));

    $("body.fixed").css("top", `0px`);
    $("body.fixed").css("position", "static");
    $(window).scrollTop(topNum);
    $("body").removeClass("fixed");
  }
</script>
</body>
</html>
