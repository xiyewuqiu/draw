<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 如果是OPTIONS请求（预检请求），直接返回成功
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '方法不允许']);
    exit;
}

// 获取POST数据
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 验证数据
if (!$data || 
    !isset($data['prompt']) || 
    !isset($data['model']) || 
    !isset($data['imageUrl'])) {
    http_response_code(400);
    echo json_encode(['error' => '无效的数据']);
    exit;
}

// 准备历史记录项
$historyItem = [
    'id' => uniqid(),
    'prompt' => $data['prompt'],
    'model' => $data['model'],
    'imageUrl' => $data['imageUrl'],
    'revisedPrompt' => $data['revisedPrompt'] ?? '',
    'timestamp' => time() * 1000 // 使用毫秒级时间戳与前端一致
];

// 读取现有历史记录
$historyFile = __DIR__ . '/data/history.json';
$history = [];
if (file_exists($historyFile)) {
    $history = json_decode(file_get_contents($historyFile), true);
    if (!is_array($history)) {
        $history = [];
    }
}

// 添加新记录
array_unshift($history, $historyItem); // 添加到开头

// 限制历史记录数量，最多保存1000条
if (count($history) > 1000) {
    $history = array_slice($history, 0, 1000);
}

// 写入文件
if (file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'id' => $historyItem['id']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => '无法保存历史记录']);
} 