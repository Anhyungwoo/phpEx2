<?php

function debug($v) {
  echo "<xmp>";
  print_r($v);
  echo "</xcp";
}

function msg($message) {
  echo "<script>alert('{$message}');</script>";
}

function go($url, $target = "self") {
  echo "<script>{$target}.location.replace('{$url}');</script>";
}
