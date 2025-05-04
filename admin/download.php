<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 检查下载令牌
if (!isset($_GET['token']) || empty($_GET['token']) || !isset($_SESSION['download_token'])) {
    header("HTTP/1.0 403 Forbidden");
    echo "Access denied";
    exit;
}

$token = $_GET['token'];
$tokenData = $_SESSION['download_token'];

// 验证令牌
if ($token !== md5(basename($tokenData['file'])) || !file_exists($tokenData['file']) || $tokenData['expires'] < time()) {
    header("HTTP/1.0 403 Forbidden");
    echo "Download link expired or invalid";
    exit;
}

// 获取文件路径
$filePath = $tokenData['file'];

// 清除会话中的令牌
unset($_SESSION['download_token']);

// 发送文件
$filename = 'images_' . date('Ymd_His') . '.zip';
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
ob_clean();
flush();
readfile($filePath);

// 删除临时文件
@unlink($filePath);
exit; 