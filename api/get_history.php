<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 读取历史记录文件
$historyFile = __DIR__ . '/data/history.json';

if (file_exists($historyFile)) {
    $history = json_decode(file_get_contents($historyFile), true);
    // 按时间戳降序排序
    usort($history, function($a, $b) {
        return $b['timestamp'] <=> $a['timestamp'];
    });
    echo json_encode($history);
} else {
    echo json_encode([]);
} 