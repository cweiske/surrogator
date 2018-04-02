#!/usr/bin/env php
<?php
/**
 * Tool to create avatar images in different sizes.
 *
 * Part of Surrogator - a simple libravatar avatar image server
 *
 * PHP version 5
 *
 * @category Tools
 * @package  Surrogator
 * @author   Christian Weiske <cweiske@cweiske.de>
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3 or later
 * @link     https://sourceforge.net/p/surrogator/
 */
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
    } else if ($arg == '-s' || $arg == '--symlink') {
        $useSymlinks = true;
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

/**
 * Echos the --help screen.
 *
 * @return void
 */
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
 -s, --symlink  De-duplicate using symlinks. *
     --version  Show program version

filenames       One or several files whose small images shall get generated.
                If none given, all will be checked

* a. Make the MD5 image sizes symlinks to the SHA256 image sizes.
  b. If the raw image is a symlink which stays in the raw folder, re-use the sizes
  generated for link target image by symlinking them. (The check if the target
  lies within the raw folder is flawed: It just works on the strings, and does not
  resolve ., .., ~ etc.)

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
    if (!mkdir($varDir . '/square', 0755, true)) {
        logErr('cannot create square dir');
        exit(5);
    }
}
log('sizes: ' . implode(', ', $sizes), 2);
foreach ($sizes as $size) {
    if (!is_dir($varDir . '/' . $size)) {
        log('creating size dir: ' . $varDir . '/' . $size);
        mkdir($varDir . '/' . $size, 0755);
    }
}
foreach (array('mm.png', 'default.png') as $resFile) {
    if (!file_exists($rawDir . '/' . $resFile)) {
        log($resFile . ' missing, copying it from res/', 2);
        copy($resDir . '/' . $resFile, $rawDir . '/' . $resFile);
    }
}
foreach (array('index.html', 'robots.txt', 'favicon.ico') as $resFile) {
    if (!file_exists($wwwDir . '/' . $resFile) && is_writable($wwwDir)) {
        log('no www/' . $resFile . ' found, copying default over', 1);
        copy($resDir . '/www/' . $resFile, $wwwDir . '/' . $resFile);
    }
}

if (count($files)) {
    $fileInfos = array();
    foreach ($files as $file) {
        $fileInfos[] = new \SplFileInfo($file);
    }
} else {
    $fileInfos = new \RegexIterator(
        new \DirectoryIterator($rawDir),
        '#^.+\.(png|jpg)$#'
    );
}
foreach ($fileInfos as $fileInfo) {

    $origPath   = $fileInfo->getPathname();
    $fileName   = $fileInfo->getFilename();
    $ext        = $fileInfo->getExtension();

    $isSymlink  = false;

    // Double store b/c will be overwritten if it's a symlink.
    $targetName = $fileName;
    $targetPath = $origPath;

    if ($useSymlinks && is_link($origPath) && ($linkTarget = readlink($origPath))) {

        $isSymlink = true;

        //FIXME fail on absoulte links, links that do not point to current dir
        $origPath = dirname($origPath) . '/' . $linkTarget;
        $fileName = basename($linkTarget);
    }

    $fileNameOnly = basename($fileName, '.' . $ext);
    $targetNameOnly = basename($targetName, '.' . $ext);

    $readableName = rawurldecode($targetNameOnly);
    $squarePath = getSquarePath($targetNameOnly);
    $hashes = getHashes($targetName);

    log('processing ' . $readableName .' ('. $targetName .')', 1);

    log(' md5:    ' . $hashes[0], 3);
    log(' sha256: ' . $hashes[1], 3);

    if($isSymlink) {

        if(is_link($squarePath)){
            log(' square link exists, does not look like something is to do.', 2);
            continue;
        }

        makeLink(getSquarePath($fileNameOnly), getSquarePath($targetNameOnly));

        log(' using sizes of ' . $fileName . ' for ' . $targetName . '.', 2);

        $srcHashes = getHashes($fileName);

        foreach ($sizes as $size) {
            log(' size ' . $size, 3);

            $tgtSizes = getSizePaths($size, $hashes);
            $srcSizes = getSizePaths($size, $srcHashes);

            array_map('surrogator\makeLink', $srcSizes, $tgtSizes);
        }

        continue;

    }

    if (imageUptodate($targetPath, getSquarePath($targetNameOnly))) {

        $tgtDir = basename(dirname($targetPath));
        $sqrDir = basename(dirname($squarePath));

        log(' image up to date ' . $tgtDir . ' <> '. $sqrDir, 2);
        continue;
    }

    if (!createSquare($targetPath, $ext, $squarePath, $maxSize)) {
        log(' could not create square');
        continue;
    }


    log(' creating sizes for ' . $fileName, 2);
    $imgSquare = imagecreatefrompng($squarePath);
    foreach ($sizes as $size) {
        log(' size ' . $size, 3);

        list($sizePathMd5, $sizePathSha256) = getSizePaths($size, $hashes);

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

/**
 * Get path to square image for a file name.
 * @param string $fileName file name w/o extension (will always be .png).
 * @return string
 */
function getSquarePath($fileName)
{
    // squares are size images with size "square" and hash "file name";
    return getSizePaths("square", [$fileName])[0];
}

function makeLink($target, $link)
{
    global $forceUpdate;

    // make link short, if possible
    $target = dirname($target) == dirname($link) ? basename($target) : $target;

    if(!$forceUpdate && is_link($link) && readlink($link) == $target) return;

    if(is_file($link)) unlink($link);

    symlink($target, $link);
}

/**
 * Create and return image size paths form an array of hashes.
 *
 * @param array $hashes
 * @param string $size the image size
 *
 * @return array the $hashes array mapped to size paths.
 */
function getSizePaths($size, $hashes)
{
    global $varDir;
    $ret = [];

    foreach($hashes as $hash)
    {
     $ret[] = sprintf('%s/%s/%s.png', chop($varDir, "/"), $size, $hash);
    }

    return $ret;

}

/**
 * Create and return md5 and sha256 hashes from a filename.
 *
 * @param string $fileName filename without path, e.g. "foo@example.org.png"
 *
 * @return array Array with 2 values: md5 and sha256 hash
 */
function getHashes($fileName)
{
    if (in_array($fileName, ['default.png', 'mm.png'])) {
        return array_fill(0, 2, pathinfo($fileName, PATHINFO_FILENAME));
    }

    //OpenIDs have their slashes "/" url-encoded
    $fileName = rawurldecode($fileName);

    $fileNameNoExt = substr($fileName, 0, -strlen(strrpos($fileName, '.')) - 2);
    $emailAddress  = trim(strtolower($fileNameNoExt));

    return array(
        md5($emailAddress), hash('sha256', $emailAddress)
    );
}

/**
 * Creates the square image from the given image in maximum size.
 * Scales the image up or down and makes the non-covered parts transparent.
 *
 * @param string  $origPath   Full path to original image
 * @param string  $ext        File extension ("jpg" or "png")
 * @param string  $targetPath Full path to target image file
 * @param integer $maxSize    Maxium image size the server supports
 *
 * @return boolean True if all went well, false if there was an error
 */
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

/**
 * Check if the target file is newer than the source file.
 *
 * @param string $sourcePath Full source file path
 * @param string $targetPath Full target file path
 *
 * @return boolean True if target file is newer than the source file
 */
function imageUptodate($sourcePath, $targetPath)
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

/**
 * Write a log message to stdout
 *
 * @param string  $msg   Message to write
 * @param integer $level Log level - 1 is important, 3 is unimportant
 *
 * @return void
 */
function log($msg, $level = 1)
{
    global $logLevel;
    if ($level <= $logLevel) {
        echo $msg . "\n";
    }
}

/**
 * Write an error message to stderr
 *
 * @param string $msg Message to write
 *
 * @return void
 */
function logErr($msg)
{
    file_put_contents('php://stderr', $msg . "\n");
}

?>
