<?php
/**
* 회원관련
* - 회원가입, 로그인
*/
class Member {
  private static $instance;

  private $db;

  private function __construct(){
    $this->db = DB::getInstance();
  }

  public static function getInstance(){
    if(!self::$instance) {
      $instance = new Member();
    }

    return $instance;
  }

  // 회원가입 처리
  public function join($data) {
    /**
    * 1. 필수 항목 체크
    * 2. 아이디 (자리수 6자리 이상, 알파벳 + 숫자만 허용)
    * 3. 아이디의 중복 여부 체크
    * 4. 비밀번호 (자리수 8자리이상, 특문 + 알파벳 + 숫자만 허용) + 비밀번호 확인
    * 5. 휴대폰번호 (선택사항) - 번호가 입력된 경우->형식 체크
    *                          - 입력 데이터의 통일(숫자만 입력)
    *
    */
    // 1. 필수 항목 체크
    $required = [
      'memId' => "아이디를 입력하세요!",
      'memPw' => "비밀번호를 입력하세요!",
      'memPwRe' => "비밀번호를 확인하세요!",
      'memNm' => "회원명을 입력하세요!",
    ];

    foreach ($required as $field => $msg) {
			if (!isset($data[$field]) || trim($data[$field]) == "") {
				throw new Exception($msg);
			}
		}

    // 2.아이디 체크
    $memId = $data['memId'];
    if (strlen($memId) < 6) {
      throw new Exception ("아이디는 6자리 이상 입력하세요.");
    }

    $pattern = "/[^0-9a-z]/i"; //알파벳, 숫자이외 입력시
    if(preg_match($pattern, $memId)) {
      throw new Exception ("아이디는 알파벳과 숫자로만 입력해주세요");
    }
    // 3. 아이디 중복여부 체크
    $sql = "SELECT COUNT(*) cnt FROM php_member WHERE memId = :memId";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(":memId", $memId);
    $result = $stmt->execute();
    if($result) {
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if($row['cnt'] > 0) {
        throw new Exception("이미 가입된 회원입니다.");
      }
    }

    // 4. 비밀번호 체크
    if(isset($data['memPw']) && $data['memPw']){
    $memPw = $data['memPw'];
    $memPwRe = $data['memPwRe'];
    $this->checkPassword($memPw, $memPwRe);
    }

    // 5. 휴대폰번호 체크
    if(isset($data['cellPhone']) && $data['cellPhone']) {
      $this->checkCellPhone($data['cellPhone']);

      $data['cellPhone'] = preg_replace("/[^0-9]/", "", $data['cellPhone']);
    }

    // 6. 비밀번호 해시 처리 - password_hash
    $hash = password_hash($memPw, PASSWORD_BCRYPT, ["cost" => 10]);

    $sql = "insert into php_member (memId, memPw, memNm, cellPhone)
            values (:memId, :memPw, :memNm, :cellPhone)";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(":memId", $memId);
    $stmt->bindValue(":memPw", $hash);
    $stmt->bindValue(":memNm", $data['memNm']);
    $stmt->bindvalue(":cellPhone", $data['cellPhone']);
    $result = $stmt->execute();
    if(!$result) {
      throw new Exception ("회원가입 실패하였습니다!");
    }

    // 성공시 회원번호를 반환
    $memNo = $this->db->lastInsertId();

    return $memNo;
  }

  /**
  * 비밀번호 체크
  * @throws Exception 유효성 검사 실패시
  */
  public function checkPassword($memPw, $memPwRe) {
    // 8자리 이상 체크
    if(strlen($memPw) < 8) {
      throw new Exception ("비밀번호는 8자리 이상 입력하세요.");
    }

    // 비밀번호의 복잡성 - 알파벳, 숫자, 특문 구성
    if(!preg_match('/[a-z]/i', $memPw) || !preg_match("/[0-9]/", $memPw) || !preg_match("/[~!@#$%^&*()]/", $memPw)) {
      throw new Exception ("비밀번호는 알파벳, 숫자, 특수문자로 구성해주세요.");
    }

    // 비밀번호 확인 일치 여부 확인
    if ($memPw != $memPwRe) {
      throw new Exception ("비밀번호가 일치하지 않습니다.");
    }
  }

  /**
  * 휴대폰번호 유효성 검사
  * @throws Exception
  */
  public function checkCellPhone($cellPhone) {
    /**
    * 1. 휴대폰번호 형식 통일화 -> 숫자로만 변경 =preg_replace
    * 2. 휴대폰번호 형식 체크
    */
    $cellPhone = preg_replace("/[^0-9]/", "", $cellPhone);
    $pattern = "/^01[016789][0-9]{3,4}[0-9]{4}$/";
    if(!preg_match($pattern, $cellPhone)) {
      throw new Exception("휴대전화번호 형식이 잘못되었습니다.");
    }
  }

  /**
  * 로그인 처리 부분
  */
  public function login($memId, $memPw) {
    /**
    * 1. 아이디로 회원 정보 조회
    * 2. 회원정보가 존재하면 비밀번호 일치여부 체크
    * 3. 비밀번호가 일치하면 session에 memNo를 설정
    * 4. 사이트 전역에 회원 정보를 유지
    */

    // 회원정보 조회
    $info = $this->get($memId);
    if(!$info) {
      throw new Exception ("존재하지 않는 회원입니다.");
    }

    // 비밀번호 체크
    $match = password_verify($memPw, $info['memPw']);
    if(!$match) {
      throw new Exception ("비밀번호가 일치하지 않습니다.");
    }

    $_SESSION['memNo'] = $info['memNo'];

  }
    /** 회원정보 조회
    * @param $memId - 정수이면 회원번호(memNo), 아니면 memId
    */
    public function get($memId) {
      $field = "memId";
      if(is_numeric($memId)){ // 회원번호
        $field = "memNo";
      }
      $sql = "select * from php_member where {$field} = :{$field}";
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(":{$field}", $memId);
      $result = $stmt->execute();
      if(!$result){
        return false;
      }

      $info = $stmt->fetch(PDO::FETCH_ASSOC);
      return $info;
  }

  /**
  * 로그인 여부 체크
  */
  public static function isLogin() {
    return (isset($_SESSION['memNo']) && $_SESSION['memNo']?true:false;
  }

  /**
  * 로그인 회원정보 유지
  */
  public static function init() {
    $member = [];
    if(self::isLogin()) {
      $member = self::getInstance()->get($_SESSION['memNo']);
    }

    return $member;
  }
}
 ?>
