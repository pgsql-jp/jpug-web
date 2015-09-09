<?php

class DocFilter
{
    const ERR_NOTFOUND = 404;
    const ERR_INVALID = 500;

    const BASE_DIR = '/var/www/old.postgresql.jp/html/document/';
    const URL_DOCBASE = '/document';

    protected $versionInfos = null;
    protected $sections = array(
        '/html',
        '/admin',
        '/developer',
        '/programmer',
        '/tutorial',
        '/user',
        '/reference',
    );

    public static function escape($string)
    {
        return preg_replace(
            array("/\n/", "/\r/"),
            array('&#xA;', '&#xD;'),
            htmlspecialchars($string));
    }

    public static function genVersionId($major1, $major2)
    {
        return sprintf('%d%03d', $major1, $major2);
    }

    public function error($code)
    {
        switch ($code) {
            case self::ERR_NOTFOUND:
                header('HTTP/1.0 404 Not Found');
                $url = self::escape($_SERVER['REQUEST_URI']);
                $cont = sprintf(self::getError404Document(), $url);
                break;
            case self::ERR_INVALID:
                header('HTTP/1.0 500 Internl Server Error');
                $cont = self::getError500Document();
                break;
        }
        echo $cont;
        exit;
    }

    public static function redirect($path)
    {
        header('Location: ' . $path);
        var_dump('Redirect:' . $path);
        exit;
    }

    public static function getError404Document()
    {
        $error404html = <<<ERROR404HTML
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
 <html><head>
  <title>404 Not Found</title>
 </head><body>
  <h1>Not Found</h1>
    <p>The requested URL %s was not found on this server.</p>
</body></html>
ERROR404HTML;
        return $error404html;
    }

    public static function getError500Document()
    {
        $error500html = <<<ERROR500HTML
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
  <title>500 Internl Server Error</title>
  </head><body>
  <h1>Internl Server Error</h1>
</body></html>
ERROR500HTML;
        return $error500html;
    }

    public static function additionalCss()
    {
        $customCss = <<<CUSTOM_CSS
<style type="text/css"><!--
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
--></style>
CUSTOM_CSS;
        return $customCss;
    }

    public static function getEmbedJs()
    {
        $embedJs = <<<EMBED_JS
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script type="text/javascript"><!--
  $(function(){
      $(".versions .label").bind("click", function(){
          $(".versions .list").toggle();
      })
  });
--></script>
EMBED_JS;
        return $embedJs;
    }
  
    public function getDocVersionInfo($dir, $name)
    {
        $path = $dir . $name;

        if (!is_dir($path)) {
            return false;
        }
        if (!preg_match('/^pg([0-9])([0-9])([0-9])?doc$/', $name, $matchs)) {
            return false;
        }
        if (!file_exists($path . '/index.html')) {
            return false;
        }

        $major1 = (int)$matchs[1];
        $major2 = (int)$matchs[2];
        $minor = (int)$matchs[3];
        $ver_id = self::genVersionId($major1, $major2);

        $info = array(
            'ver_id' => $ver_id,
            'major1' => $major1,
            'major2' => $major2,
            'minor' => $minor,
            'name' => $name,
            'path' => $path,
        );
        return $info;
    }

    public function getExistsVersions($dir)
    {
        $infos = array();
        $dh = opendir($dir);
        while (($name = readdir($dh)) !== false) {
            $info = $this->getDocVersionInfo($dir, $name);
            if ($info === false) {
                // not document directory
                continue;
            }
            $ver_id = $info['ver_id'];
            if ($ver_id < 7002) {
                // ignore early of 7.2
                continue;
            }
            if (isset($infos[$ver_id]) &&
                $info['minor'] < $infos[$ver_id]['minor']) {
                // ignore old minor version
                continue;
            }
            $infos[$ver_id] = $info;
        }
        closedir($dh);

        krsort($infos);
        return $infos;
    }

    public function hasVerId($ver_id)
    {
        return isset($this->versionInfos[$ver_id]);
    }

    public function getCurrentVersionInfo()
    {
        if (empty($this->versionInfos)) {
            throw new Exception('Invalid versionInfo', self::ERR_INVALID);
        }
        $top = array_slice($this->versionInfos, -1, 1);
        return $top[0];
    }

    public function setup()
    {
        $this->versionInfos = $this->getExistsVersions(self::BASE_DIR);
    }

    protected function parseReqCurrent($pathinfo)
    {
        preg_match('!^/current(/[^/]+)(/.*)?$!', $pathinfo, $matches);
        $verinfo = $this->getCurrentVersionInfo();

        $params = array(
            'ver_id' => $verinfo['ver_id'],
            'major1' => $verinfo['major1'],
            'major2' => $verinfo['major2'],
            'minor' => $verinfo['minor'],
            'section' => (string)$matches[1],
            'file' => (string)$matches[2],
            'path' => $verinfo['path'],
            'name' => $verinfo['name'],
        );
        return $params;
    }

    protected function parseReqVersion($pathinfo)
    {
        if (!preg_match('!^/([0-9]+)(?:\.([0-9]+)(?:\.([0-9]+))?)?(/[^/]+)(/.*)?$!', $pathinfo, $matches)) {
            throw new Exception('Document not exits', self::ERR_NOTFOUND);
        }

        $major1 = (int)$matches[1];
        $major2 = (int)$matches[2];
        if ((string)$matches[3] === '') {
            $minor = null;
        } else {
            $minor = (int)$matches[3];
        }
        $sect = (string)$matches[4];
        $file = (string)$matches[5];
        $ver_id = $this->genVersionId($major1, $major2);

        if (!isset($this->versionInfos[$ver_id])) {
            throw new Exception('Unknown version of document',
                self::ERR_NOTFOUND);
        }
        $verinfo = $this->versionInfos[$ver_id];

        $params = array(
            'ver_id' => $ver_id,
            'major1' => $major1,
            'major2' => $major2,
            'minor' => $minor,
            'section' => $sect,
            'file' => $file,
            'path' => $verinfo['path'],
            'name' => $verinfo['name'],
        );
        return $params;
    }
    
    public function parseRequest($pathinfo)
    {
        if (preg_match('!^/current!', $pathinfo)) {
            $req_params = $this->parseReqCurrent($pathinfo);
        } else {
            $req_params = $this->parseReqVersion($pathinfo);
        }
        return $req_params;
    }

    public function normalize($params)
    {
        $file = $params['file'];
        $section = $params['section'];
        $path = $params['path'];
        
        // file ends by '/' then add 'index.html'
        if (preg_match('|/$|', $file)) {
            $file .= 'index.html';
        }
        // request is directory, redirect
        if (is_dir($path . $section . $file)) {
            self::redirect($_SERVER['REQUEST_URI'] . '/');
        }
        // minor version is given, redirect to newer in same major
        if ($params['minor'] != null) {
            $major1 = $params['major1'];
            $major2 = $params['major2'];
            self::redirect('/'. $major1 . '.' . $major2 . '/' . 
                $section . $file);
        }
        // check contents exists
        if (!file_exists($path . $section . $file)) {
            throw new Exception('Not found', self::ERR_NOTFOUND);
        }

        $params['file'] = $file;
        $params['type'] = $this->getContentType($params);
        return $params;
    }

    protected function getContentCharset($params)
    {
        $charset = 'UTF-8'; // default
        if ($params['ver_id'] < 9004 || $params['name'] == 'pg940doc') {
            // before and just pg940doc, using EUC-JP
            $charset = 'EUC-JP';
        }
        return $charset;
    }

    protected function getContentType($params)
    {
        if (preg_match('~\.([^.]+)$~', $params['file'], $matches)) {
            $ext = $matches[1];
        } else {
            $ext = '';
        }

        switch($ext) {
            case 'html':
                $charset = $this->getContentCharset($params);
                $type = 'text/html; charset=' . $charset;
                break;
            case 'css':
                $type = 'text/css';
                break;
            default:
                $type = mime_content_type(
                    $params['path'] .
                    $params['section'] .
                    $params['file']);
        }
        return $type;
    }

    protected function adjustHtml($data, $params)
    {
        $section = $params['section'];
        $file = $params['file'];

        $items = array();
        foreach ($this->versionInfos as $ver_id => $info) {

            $target_version = $info['major1'] . '.' . $info['major2'];
            $fullpath = $info['path'] . $section . $file;

            if ($ver_id == $params['ver_id']) {
                $items[] = '<span class="me">' . $target_version . '</span>';
            } else if (file_exists($fullpath)) {
                $href = self::URL_DOCBASE .
                    '/'. $target_version . $section . $file;
                $items[] = sprintf(
                    '<span class="other"><a href="%s">%s</a><span>',
                    $this->escape($href), $target_version);
            } else {
                $found = null;
                foreach ($this->sections as $anothersec) {
                    $path = $info['path'] . $anothersec . $file;
                    if (file_exists($path)) {
                        $found = $anothersec;
                        break;
                    }
                }
                if (!empty($found)) {
                    $href = self::URL_DOCBASE .
                        '/' . $target_version . $anothersec . $file;
                    $items[] = sprintf(
                        '<span class="other"><a href="%s">%s</a></span>',
                        $this->escape($href), $target_version);
                } else {
                    $href = self::URL_DOCBASE .
                        '/' . $target_version . '/index.html';
                    $items[] = sprintf(
                        '<span class="unsup"><a href="%s">%s</a></span>',
                        $this->escape($href), $target_version);
                }
            }
        }

        $snippet =
            '<div class="versions">' .
            '<span class="label">他のバージョンの文書</span>' .
            '<span class="list">： ' . implode(' | ', $items) . '</span>' .
            '</div>';

        $charset = $this->getContentCharset($params);
        if ($charset != 'UTF-8') {
            $snippet = mb_convert_encoding($snippet, $charset, 'UTF-8');
        }

        $js = $this->getEmbedJs();
        $css = $this->additionalCss();
        
        // embemd snippet
        $data = preg_replace('~(</head.*?>)~isD', $css . $js . '$1', $data, 1);
        $data = preg_replace('~(<h1.*?>)~isD', $snippet . '$1', $data, 1);

        return $data;
    }

    protected function readContents($params)
    {
        $fullpath = $params['path'] . $params['section'] . $params['file'];
        $contents = file_get_contents($fullpath);

        if (preg_match('|^text/html|', $params['type'])) {
            $contents = $this->adjustHtml($contents, $params);
        }
        return $contents;
    }

    public function outContents($params)
    {
        $data = $this->readContents($params);

        header('Content-Type: ' . $params['type']);
        header('Content-Length: ' . strlen($data));
        header('Content-Transfer-Encoding: binary');
        echo $data;
    }

    // for debugging
    public function getAllVersionInfo()
    {
        return $this->versionInfos;
    }
}

try {
    $docFilter = new DocFilter;
    $docFilter->setup();

    $pathinfo = $_SERVER['PATH_INFO'];

    $params = $docFilter->parseRequest($pathinfo);
    $params = $docFilter->normalize($params);

    $docFilter->outContents($params);
    
} catch (Exception $e) {
    if ($e->getCode() == DocFilter::ERR_NOTFOUND) {
        DocFilter::error(DocFilter::ERR_NOTFOUND);
    } else {
        DocFilter::error(DocFilter::ERR_INVALID);
    }
}
