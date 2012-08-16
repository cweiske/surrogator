<?php
namespace surrogator;
require __DIR__ . '/data/surrogator.config.php';

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


if (!is_dir($varDir . '/square')) {
    log('Creating square dir: ' . $varDir . '/square');
    mkdir($varDir . '/square', 0755, true);
}
log('sizes: ' . implode(', ', $sizes));
foreach ($sizes as $size) {
    if (!is_dir($varDir . '/' . $size)) {
        log('Creating size dir: ' . $varDir . '/' . $size);
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

    log('processing ' . $fileName);
    if (image_uptodate($origPath, $squarePath)) {
        log(' image up to date');
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

    log(' Creating sizes for ' . $fileName);
    log(' md5:    ' . $md5);
    log(' sha256: ' . $sha256);
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
    if (!file_exists($targetPath)) {
        return false;
    }

    if (filemtime($sourcePath) > filemtime($targetPath)) {
        //source newer
        return false;
    }

    return true;
}

function log($msg)
{
    echo $msg . "\n";
}

function logErr($msg)
{
    file_put_contents('php://stderr', $msg . "\n");
}

?>
