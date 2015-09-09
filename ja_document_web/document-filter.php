<?php
//
// PostgreSQLのドキュメントにバージョン間のリンクを付加するフィルタです。
// 質問は Hiroki Kataoka に聞いてください。
//

// 初期設定

$docsdir = '/var/www/old.postgresql.jp/html/document/';

// 関数定義

function h($string) {
  return preg_replace(array("/\n/", "/\r/"), array('&#xA;', '&#xD;'), htmlspecialchars($string));
}

function error($code) {
  switch ($code) {
  case 404:
    header('HTTP/1.0 404 Not Found');
?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL <?= h($_SERVER['REQUEST_URI']) ?> was not found on this server.</p>
</body></html>
<?
    exit;
  default:
    header('HTTP/1.0 500 Internl Server Error');
?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>500 Internl Server Error</title>
</head><body>
<h1>Internl Server Error</h1>
</body></html>
<?
    exit;
  }
}

// PATH_INFOがなかったらダメ

if (!isset($_SERVER['PATH_INFO']))
  error(404);

// 存在するドキュメントのバージョン情報を取得

$sects = array(
  '/html',
  '/admin',
  '/developer',
  '/programmer',
  '/tutorial',
  '/user',
  '/reference'
);

$verinfos = array();
$dh = opendir($docsdir);
while (($f = readdir($dh)) !== false) {
  if (preg_match('/^pg([0-9])([0-9])([0-9])?doc$/', $f, $matchs)) {
    if (!is_dir($docsdir . $f) || !file_exists($docsdir . $f . '/index.html'))
      continue;
    $major1 = (int)$matchs[1];
    $major2 = (int)$matchs[2];
    @$minor = (int)$matchs[3];
    $verid = sprintf('%d%03d', $major1, $major2);
    if ($verid < 7002)
      continue;
    if (isset($verinfos[$verid]) && $minor < $verinfos[$verid]['minor'])
      continue;
    $verinfos[$verid] = array(
      'major1' => $major1,
      'major2' => $major2,
      'minor' => $minor,
      'name' => $f,
      'path' => $docsdir . $f
    );
  }
}
closedir($dh);
krsort($verinfos);

// 要求されたバージョンとPATHを確認

if (preg_match('~^/current(/[^/]+)(/.*)?$~', $_SERVER['PATH_INFO'], $matchs)) {
  $verinfo = array_slice($verinfos, 0, 1);
  $verinfo = $verinfo[0];
  $major1 = $verinfo['major1'];
  $major2 = $verinfo['major2'];
  $minor = $verinfo['minor'];
  $sect = (string)$matchs[1];
  $file = (string)$matchs[2];
  $verid = sprintf('%d%03d', $major1, $major2);
} else if (preg_match('~^/([0-9]+)(?:\.([0-9]+)(?:\.([0-9]+))?)?(/[^/]+)(/.*)?$~', $_SERVER['PATH_INFO'], $matchs)) {
  $major1 = (int)$matchs[1];
  $major2 = (int)$matchs[2];
  if ((string)$matchs[3] === '')
    $minor = null;
  else
    $minor = (int)$matchs[3];
  $sect = (string)$matchs[4];
  $file = (string)$matchs[5];
  $verid = sprintf('%d%03d', $major1, $major2);
  // 要求されたバージョンが処理対象がどうか
  if (!isset($verinfos[$verid]))
    error(404);
  $verinfo = $verinfos[$verid];
} else
  error(404);

// ファイル名の正規化？とファイルの存在チェック

if (preg_match('~/$~', $file))
  $file .= 'index.html';
else if (is_dir($verinfo['path'] . $sect . $file))
  header('Location: ' . $_SERVER['REQUEST_URI'] . '/');
if (!file_exists($verinfo['path'] . $sect . $file))
  error(404);

// マイナーバージョンの指定があったら省略したURL（つまり最新版）にリダイレクト

if ($minor !== null)
  header('Location: ' . str_repeat('../', substr_count($sect . $file, '/')) . $verinfo['major1'] . '.' . $verinfo['major2'] . $sect . $file);

// ファイルを読み込み

$data = file_get_contents($verinfo['path'] . $sect . $file);

// ファイルタイプの判定 

if (preg_match('~\.([^.]+)$~', $file, $matchs))
  $ext = $matchs[1];
else
  $ext = '';
switch($ext) {
case 'html':
  if (preg_match('~charset=euc-jp~i', $data)) {
    $encoding = 'eucjp-win';
    $type = 'text/html; charset=EUC-JP';
  } else if (preg_match('~charset=utf-8~i', $data)) {
    $data = mb_convert_encoding($data, 'eucjp-win', 'UTF-8');
    $encoding = 'UTF-8';
    $type = 'text/html; charset=UTF-8';
  } else {
    $encoding = 'eucjp-win';
    $type = 'text/html';
  }
  break;
case 'css':
  $type = 'text/css';
  break;
default:
  $type = mime_content_type($verinfo['path'] . $sect . $file);
}

// htmlの場合は加工する

if (preg_match('~^text/html~', $type)) {

  // 他のバージョンのファイルがあるかスキャンしてHTML作成

  $css = '<style type="text/css"><!--
h1.TITLE {clear:both;}
.versions {
  float:right;
  margin-top:-0.2em;
  margin-bottom:0.5em;
  padding:0.4em 1em;
  border:solid 1px #e0e0e0;
  border-radius:0.5em;
  background:#f3f3f3;
  background-image:-webkit-linear-gradient(#fbfbfb, #ebebeb);
  background-image:-moz-linear-gradient(#fbfbfb, #ebebeb);
  background-image:-o-linear-gradient(#fbfbfb, #ebebeb);
  background-image:linear-gradient(#fbfbfb, #ebebeb);
  box-shadow:0 3px 8px -3px #000;
}
.versions .label {
  cursor:pointer;
}
.versions .label:hover {
  text-decoration:underline;
}
.versions .list {
  display:none;
}
.versions .me {
  padding:0 0.2em;
  border:solid 2px #e88;
  border-radius:0.3em;
  background:#fbfbfb;
  font-weight:bold;
}
.versions .other {
  font-weight:bold;
}
.versions .unsup {
  color:#aaa;
}
.versions .unsup a {
  color:#aaa;
}
--></style>';
  $css .= '<style type="text/css" media="print"><!--
a {
  color:inherit;
  text-decoration:none;
}
a:visited {
  color:inherit;
}
.NAVHEADER,
.versions,
.NAVFOOTER {
  display:none;
}
--></style>';

  $js = '<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript"><!--
$(function(){
  $(".versions .label").bind("click", function(){
    $(".versions .list").toggle();
  })
});
--></script>';

  $htmls = array();
  foreach ($verinfos as $i => $v) {
    if ($i == $verid)
      $htmls[] = '<span class="me">' . $v['major1'] . '.' . $v['major2'] . '</span>';
    else if (file_exists($v['path'] . $sect . $file))
      $htmls[] = '<span class="other"><a href="' . h(str_repeat('../', substr_count($sect . $file, '/')) . $v['major1'] . '.' . $v['major2'] . $sect . $file) . '">' . $v['major1'] . '.' . $v['major2'] . '</a></span>';
    else {
      $found_sects = array();
      foreach ($sects as $s)
        if (file_exists($v['path'] . $s . $file))
          $found_sects[] = $s;
      if (count($found_sects) == 1)
        $htmls[] = '<span class="other"><a href="' . h(str_repeat('../', substr_count($sect . $file, '/')) . $v['major1'] . '.' . $v['major2'] . $found_sects[0] . $file) . '">' . $v['major1'] . '.' . $v['major2'] . '</a></span>';
      else
        $htmls[] = '<span class="unsup"><a href="' . h(str_repeat('../', substr_count($sect . $file, '/')) . $v['major1'] . '.' . $v['major2']) . '/index.html">' . $v['major1'] . '.' . $v['major2'] . '</a></span>';
    }
  }
  $html = '<div class="versions">';
  $html .= '<span class="label">他のバージョンの文書</span><span class="list">： ' . implode(' | ', $htmls) . '</span>';
  $html .= '</div>';

  // ファイルへの埋め込み加工

  $data = preg_replace('~(</head.*?>)~isD', $css . $js . '$1', $data, 1);
  $data = preg_replace('~(<h1.*?>)~isD', $html . '$1', $data, 1);
}

// ファイルを出力

if ($encoding == 'UTF-8')
  $data = mb_convert_encoding($data, 'UTF-8', 'eucjp-win');
header('Content-Type: ' . $type);
header('Content-Length: ' . strlen($data));
header('Content-Transfer-Encoding: binary');
echo $data;
