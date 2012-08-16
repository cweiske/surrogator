#!/usr/bin/env php
<?php
namespace surrogator;
$cfgFile = __DIR__ . '/data/surrogator.config.php';
if (!file_exists($cfgFile)) {
    $cfgFile = '/etc/surrogator.config.php';
    if (!file_exists($cfgFile)) {
        logErr(
            "Configuration file does not exist.\n"
            . "Copy data/surrogator.config.php.dist to data/surrogator.config.php"
        );
        exit(2);
    }
}
require $cfgFile;

array_shift($argv);
$files = array();
foreach ($argv as $arg) {
    if ($arg == '-v' || $arg == '--verbose') {
        ++$logLevel;
    } else if ($arg == '-vv') {
        $logLevel += 2;
    } else if ($arg == '-q' || $arg == '--quiet') {
        $logLevel = 0;
    } else if ($arg == '-f' || $arg == '--force') {
        $forceUpdate = true;
    } else if ($arg == '-h' || $arg == '--help') {
        showHelp();
        exit(4);
    } else if ($arg == '--version') {
        echo "surrogator 0.0.1\n";
        exit();
    } else if (file_exists($arg)) {
        $files[] = $arg;
    } else {
        logErr('Unknown argument: ' . $arg);
        exit(3);
    }
}

function showHelp()
{
    echo <<<HLP
Usage: php surrogator.php [options] [filename(s)]

surrogator - a simple libravatar server
 Put files in raw/ dir and run surrogator.php to generate different sizes

Options:

 -h, --help     Show help
 -v, --verbose  Be verbose (more log messages, also -vv)
 -q, --quiet    Be quiet (no log messages)
 -f, --force    Force update of all files
     --version  Show program version

filenames       One or several files whose small images shall get generated.
                If none given, all will be checked

HLP;
}

if (!isset($rawDir)) {
    logErr('$rawDir not set');
    exit(1);
}
if (!isset($varDir)) {
    logErr('$varDir not set');
    exit(1);
}
if (!isset($sizes)) {
    logErr('$sizes not set');
    exit(1);
}
if (!isset($maxSize)) {
    logErr('$maxSize not set');
    exit(1);
}
if (!isset($logLevel)) {
    logErr('$logLevel not set');
    exit(1);
}


if (!is_dir($varDir . '/square')) {
    log('creating square dir: ' . $varDir . '/square');
    mkdir($varDir . '/square', 0755, true);
}
log('sizes: ' . implode(', ', $sizes), 2);
foreach ($sizes as $size) {
    if (!is_dir($varDir . '/' . $size)) {
        log('creating size dir: ' . $varDir . '/' . $size);
        mkdir($varDir . '/' . $size, 0755);
    }
}

$dir = new \RegexIterator(
    new \DirectoryIterator($rawDir),
    '#^.+\.(png|jpg)$#'
);
foreach ($dir as $fileInfo) {
    $origPath   = $fileInfo->getPathname();
    $fileName   = $fileInfo->getFilename();
    $ext        = strtolower(substr($fileName, strrpos($fileName, '.') + 1));
    $squarePath = $varDir . '/square/'
        . substr($fileName, 0, -strlen($ext)) . 'png';

    log('processing ' . $fileName, 1);
    if (image_uptodate($origPath, $squarePath)) {
        log(' image up to date', 2);
        continue;
    }

    if (!createSquare($origPath, $ext, $squarePath, $maxSize)) {
        continue;
    }

    if ($fileName == 'default.png') {
        $md5 = $sha256 = 'default';
    } else {
        list($md5, $sha256) = getHashes($fileName);
    }

    log(' creating sizes for ' . $fileName, 2);
    log(' md5:    ' . $md5, 3);
    log(' sha256: ' . $sha256, 3);
    $imgSquare = imagecreatefrompng($squarePath);
    foreach ($sizes as $size) {
        $sizePathMd5    = $varDir . '/' . $size . '/' . $md5 . '.png';
        $sizePathSha256 = $varDir . '/' . $size . '/' . $sha256 . '.png';

        $imgSize = imagecreatetruecolor($size, $size);
        imagealphablending($imgSize, false);
        imagefilledrectangle(
            $imgSize, 0, 0, $size - 1, $size - 1,
            imagecolorallocatealpha($imgSize, 0, 0, 0, 127)
        );
        imagecopyresampled(
            $imgSize, $imgSquare, 
            0, 0, 0, 0,
            $size, $size, $maxSize, $maxSize
        );
        imagesavealpha($imgSize, true);
        imagepng($imgSize, $sizePathMd5);
        imagepng($imgSize, $sizePathSha256);
        imagedestroy($imgSize);
        
    }
    imagedestroy($imgSquare);
}

function getHashes($fileName)
{
    $fileNameNoExt = substr($fileName, 0, -strlen(strrpos($fileName, '.')) - 2);
    $emailAddress  = trim(strtolower($fileNameNoExt));

    return array(
        md5($emailAddress), hash('sha256', $emailAddress)
    );
}


function createSquare($origPath, $ext, $targetPath, $maxSize)
{
    if ($ext == 'png') {
        $imgOrig = imagecreatefrompng($origPath);
    } else if ($ext == 'jpg' || $ext == 'jpeg') {
        $imgOrig = imagecreatefromjpeg($origPath);
    } else {
        //unsupported format
        return false;
    }

    if ($imgOrig === false) {
        logErr('Error loading image file: ' . $origPath);
        return false;
    }

    $imgSquare = imagecreatetruecolor($maxSize, $maxSize);
    imagealphablending($imgSquare, false);
    imagefilledrectangle(
        $imgSquare, 0, 0, $maxSize - 1, $maxSize - 1,
        imagecolorallocatealpha($imgSquare, 0, 0, 0, 127)
    );
    imagealphablending($imgSquare, true);

    $oWidth    = imagesx($imgOrig);
    $oHeight   = imagesy($imgOrig);
    if ($oWidth > $oHeight) {
        $flScale = $maxSize / $oWidth;
    } else {
        $flScale = $maxSize / $oHeight;
    }
    $nWidth  = (int)($oWidth * $flScale);
    $nHeight = (int)($oHeight * $flScale);

    imagecopyresampled(
        $imgSquare, $imgOrig, 
        ($maxSize - $nWidth) / 2, ($maxSize - $nHeight) / 2,
        0, 0,
        $nWidth, $nHeight,
        $oWidth, $oHeight
    );

    imagesavealpha($imgSquare, true);
    imagepng($imgSquare, $targetPath);

    imagedestroy($imgSquare);
    imagedestroy($imgOrig);
    return true;
}

function image_uptodate($sourcePath, $targetPath)
{
    global $forceUpdate;
    if ($forceUpdate) {
        return false;
    }
    if (!file_exists($targetPath)) {
        return false;
    }
    if (filemtime($sourcePath) > filemtime($targetPath)) {
        //source newer
        return false;
    }

    return true;
}

function log($msg, $level = 1)
{
    global $logLevel;
    if ($level <= $logLevel) {
        echo $msg . "\n";
    }
}

function logErr($msg)
{
    file_put_contents('php://stderr', $msg . "\n");
}

?>
