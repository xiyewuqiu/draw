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

// 获取参数或使用默认值
$prompt = $data['prompt'];
$width = isset($data['width']) ? (int)$data['width'] : 512;
$height = isset($data['height']) ? (int)$data['height'] : 512;
$steps = isset($data['steps']) ? (int)$data['steps'] : 20;
$seed = isset($data['seed']) ? (int)$data['seed'] : 0;
$batch_size = isset($data['batch_size']) ? (int)$data['batch_size'] : 1;

// 验证参数范围
$width = max(256, min($width, 1024));        // 范围256-1024
$height = max(256, min($height, 1024));      // 范围256-1024
$steps = max(10, min($steps, 50));           // 范围10-50
$seed = max(0, min($seed, 999999999));       // 范围0-999999999
$batch_size = max(1, min($batch_size, 4));   // 范围1-4

// API配置
$api_url = "https://flux.comnergy.com/api/generate";

// 准备请求数据
$request_data = [
    'prompt' => $prompt,
    'width' => $width,
    'height' => $height,
    'steps' => $steps,
    'seed' => $seed,
    'batch_size' => $batch_size
];

// 记录API请求日志
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$log_file = $log_dir . '/api_requests.log';
$log_data = date('Y-m-d H:i:s') . " - Flux Request: " . json_encode($request_data) . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// 发送请求到Flux API
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 设置超时时间为120秒

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// 记录API响应日志
$log_data = date('Y-m-d H:i:s') . " - Flux Response Code: $http_code" . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// 检查请求是否成功
if ($http_code !== 200) {
    http_response_code($http_code);
    echo json_encode([
        'error' => 'Flux API请求失败',
        'details' => $curl_error,
        'response' => $response
    ]);
    exit;
}

// 解析响应
$flux_response = json_decode($response, true);

// 格式化响应以与网站其他API保持一致
$formatted_response = [
    'created' => time(),
    'data' => [],
    'no_history' => true // 标记为不需要添加到历史记录
];

// 判断是否有图像数据
if (isset($flux_response['imageUrl']) && $flux_response['imageUrl']) {
    // 直接将返回的base64数据作为URL传递给前端
    $formatted_response['data'][] = [
        'url' => $flux_response['imageUrl'],
        'revised_prompt' => $prompt, // 使用原始prompt
        'is_base64' => true // 标记为base64数据
    ];
}

// 记录返回给前端的数据（不记录实际的base64数据以减少日志大小）
$log_data = date('Y-m-d H:i:s') . " - Flux Formatted Response: 已生成图像" . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// 返回格式化的响应
echo json_encode($formatted_response); 