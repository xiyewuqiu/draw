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
if (!$data || !isset($data['prompt'])) {
    http_response_code(400);
    echo json_encode(['error' => '无效的数据']);
    exit;
}

// API配置
$api_key = "xai-mQRCqS5OveZrBmWNXhQLEjOlqDg9kPoXvs9wu3iVF7uBxs7EtWPhh1TkuI4Xa1F44AxFa3fHiUe7DhOA";
$base_url = "https://api.x.ai/v1";

// 获取图片生成数量，默认为1，范围1-10
$n = isset($data['n']) ? (int)$data['n'] : 1;
$n = max(1, min($n, 10)); // 限制n的值在1-10之间

// 准备请求数据
$request_data = [
    'model' => 'grok-2-image-1212',
    'prompt' => $data['prompt'],
    'n' => $n
];

// 发送请求到X.AI API
$ch = curl_init($base_url . '/images/generations');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// 检查请求是否成功
if ($http_code !== 200) {
    http_response_code($http_code);
    echo json_encode([
        'error' => 'Grok API请求失败',
        'details' => $curl_error,
        'response' => $response
    ]);
    exit;
}

// 将Grok响应转换为与原API格式相同的结构
$grok_response = json_decode($response, true);
$formatted_response = [
    'created' => time(),
    'data' => []
];

if (isset($grok_response['data']) && is_array($grok_response['data'])) {
    foreach ($grok_response['data'] as $image) {
        $formatted_response['data'][] = [
            'url' => $image['url'],
            'revised_prompt' => $data['prompt'] // Grok可能没有revised_prompt，所以使用原始prompt
        ];
    }
}

// 返回格式化的响应
echo json_encode($formatted_response); 