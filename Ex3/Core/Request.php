<?php

class Request {
  public static function all() {
    return $_REQUEST;
  }
  public static function get($key) {
    $value = $_REQUEST [$key]?$_REQUEST[$key]:"";

    return $value;
  }
}
