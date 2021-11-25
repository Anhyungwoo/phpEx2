<?php
include "../_common.php";
include_once "../Models/Member.php";
$member = Member::getInstance();
$in = Request::all();
try {
	switch(Request::get("mode")) {
		/** 회원 가입 처리 */
		case "join" :
			$result = $member->join($in);
      if (!$result) {
        msg("회원가입에 실패하였습니다.");
      }

      go("../Member/login.php", "parent");
			break;

		/** 로그인 처리 */
		case "login" :
		$memId = Request::get("memId");
		$memPw = Request::get("memPw");
		$member->login($memId, $memPw);
		go("../index.php", "parent");
		break;
	}
} catch (Exception $e) {
	msg($e->getMessage());
}
