<?php
namespace surrogator;

$cfgFile = __DIR__ . '/../data/surrogator.config.php';
if (!file_exists($cfgFile)) {
    $cfgFile = '/etc/surrogator.config.php';
    if (!file_exists($cfgFile)) {
        err(
            500,
            "Configuration file does not exist.\n"
            . "Copy data/surrogator.config.php.dist to data/surrogator.config.php"
        );
        exit(2);
    }
}
require $cfgFile;

function err($statusCode, $msg)
{
    header('HTTP/1.0 ' . $statusCode . ' ' . $msg);
    header('Content-Type: text/plain');
    echo $msg . "\n";
    exit(1);
}

$uri = $_SERVER['REQUEST_URI'];
$uriParts = explode('/', $uri, 3);
if (count($uriParts) != 3 || $uriParts[1] != 'avatar') {
    err(400, 'URI is wrong, should be avatar/$hash');
}
$reqHash = $uriParts[2];
if (strpos($reqHash, '?') !== false) {
    $reqHash = substr($reqHash, 0, strpos($reqHash, '?'));
}
if (strlen($reqHash) !== 32 && strlen($reqHash) !== 64) {
    err(400, 'Hash has to be 32 or 64 characters long');
}

$reqSize = 80;//default
if (isset($_GET['s'])) {
    $_GET['size'] = $_GET['s'];
}
if (isset($_GET['size'])) {
    if ($_GET['size'] != intval($_GET['size'])) {
        err(400, 'size parameter is not an integer');
    }
    if ($_GET['size'] < 1) {
        err(400, 'size parameter has to be larger than 0');
    }
    $reqSize = intval($_GET['size']);
}

$default     = 'default.png';
$defaultMode = 'local';
if (isset($_GET['d'])) {
    $_GET['default'] = $_GET['d'];
}
if (isset($_GET['default'])) {
    if ($_GET['default'] == '') {
        err(400, 'default parameter is empty');
    } else if (preg_match('#^[a-z0-9]+$#', $_GET['default'])) {
        //special default mode, we support none of them except 404
        if ($_GET['default'] == '404') {
            $defaultMode = '404';
            $default     = '404';
        } else {
            //FIXME: support mm
            //local default image
            $defaultMode = 'local';
            $default     = 'default.png';
        }
    } else {
        //url
        $defaultMode = 'redirect';
        $default     = $_GET['default'];
        //FIXME: validate?
    }
}


$targetSize = 512;
foreach ($sizes as $size) {
    if ($reqSize <= $size) {
        $targetSize = $size;
        break;
    }
}

$imgFile = $varDir . $targetSize . '/' . $reqHash . '.png';
if (!file_exists($imgFile)) {
    if ($defaultMode == '404') {
        err(404, 'File does not exist');
    } else if ($defaultMode == 'redirect') {
        header('Location: ' . $default);
        exit();
    } else if ($defaultMode == 'local') {
        $imgFile = $varDir . $targetSize . '/' . $default;
        if (!file_exists($imgFile)) {
            err(500, 'Default file is missing');
        }
    } else {
        err(500, 'Invalid defaultMode');
    }
}

$stat = stat($imgFile);
$etag = sprintf('%x-%x-%x', $stat['ino'], $stat['size'], $stat['mtime'] * 1000000);

if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
    && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag
) {
    header('Etag: "' . $etag . '"');
    header('HTTP/1.0 304 Not Modified');
    exit();
} else if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
    && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $stat['mtime']
) {
    header('Last-Modified: ' . date('r', $stat['mtime']));
    header('HTTP/1.0 304 Not Modified');
    exit();
}

header('Last-Modified: ' . date('r', $stat['mtime']));
header('Etag: "' . $etag . '"');
header('Content-Type: image/png');
header('Content-Length:' . $stat['size']);

readfile($imgFile);
?>