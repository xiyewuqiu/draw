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
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => '无效的数据']);
    exit;
}

$historyFile = __DIR__ . '/data/history.json';
if (!file_exists($historyFile)) {
    http_response_code(404);
    echo json_encode(['error' => '历史记录文件不存在']);
    exit;
}

// 读取历史记录
$history = json_decode(file_get_contents($historyFile), true);
if (!is_array($history)) {
    http_response_code(500);
    echo json_encode(['error' => '历史记录格式错误']);
    exit;
}

// 寻找并删除记录
$found = false;
foreach ($history as $key => $item) {
    if (isset($item['id']) && $item['id'] === $data['id']) {
        unset($history[$key]);
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['error' => '未找到指定记录']);
    exit;
}

// 重新索引数组
$history = array_values($history);

// 写入文件
if (file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => '无法更新历史记录']);
} 