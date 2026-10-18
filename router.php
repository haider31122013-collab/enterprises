<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__;
$file = $root . $uri;
if ($uri !== '/' && file_exists($file) && is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, ['php'], true)) {
        require_once $file;
        return true;
    }
    if (in_array($ext, ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'webp', 'woff', 'woff2', 'ttf', 'otf', 'pdf', 'map'])) {
        $mimeTypes = [
            'css' => 'text/css',
            'js'  => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg'=> 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'webp'=> 'image/webp',
            'woff'=> 'font/woff',
            'woff2'=>'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'pdf' => 'application/pdf',
            'map' => 'application/json',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($file);
        return true;
    }
    return false;
}
if (is_dir($file) && file_exists($file . '/index.php')) {
    require_once $file . '/index.php';
    return true;
}
if (is_dir($file)) {
    $phpFiles = glob($file . '/*.php');
    if (!empty($phpFiles)) {
        usort($phpFiles, function($a,$b){
            $aIdx = (strpos($a,'dashboard')!==false)?0:((strpos($a,'index')!==false)?1:2);
            $bIdx = (strpos($b,'dashboard')!==false)?0:((strpos($b,'index')!==false)?1:2);
            return $aIdx - $bIdx;
        });
        $_SERVER['SCRIPT_NAME'] = str_replace($root, '', $phpFiles[0]);
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        require_once $phpFiles[0];
        return true;
    }
}
if (file_exists($root . '/index.php')) {
    require_once $root . '/index.php';
    return true;
}
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style='font-family:Arial;padding:40px;'>
<h1>404 Not Found</h1><p>The requested URL <code>" . htmlspecialchars($_SERVER['REQUEST_URI']) . "</code> was not found.</p>
<p><strong>Full URL debug:</strong> file=$file exists=" . (file_exists($file)?'YES':'NO') . " is_file=" . (is_file($file)?'YES':'NO') . " is_dir=" . (is_dir($file)?'YES':'NO') . "</p>
<pre>";
if (is_dir($file)) { echo "Directory listing:\n"; foreach (scandir($file) as $f) echo "  $f\n"; }
echo "</pre></body></html>";
return false;
