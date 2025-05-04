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
$api_key = "sk-bnmlzhykdbjjpvvdxseojhfelwjsacujapehbmrhzwcvjtuh";
$base_url = "https://api.siliconflow.cn/v1";

// 获取参数值或使用默认值
$image_size = isset($data['image_size']) ? $data['image_size'] : '1024x1024';
$batch_size = isset($data['batch_size']) ? (int)$data['batch_size'] : 1;
$num_inference_steps = isset($data['num_inference_steps']) ? (int)$data['num_inference_steps'] : 20;
$guidance_scale = isset($data['guidance_scale']) ? (float)$data['guidance_scale'] : 7.5;
$negative_prompt = isset($data['negative_prompt']) ? $data['negative_prompt'] : '';
$seed = isset($data['seed']) ? (int)$data['seed'] : mt_rand(1, 9999999999);

// 验证参数范围
$batch_size = max(1, min($batch_size, 4)); // 范围1-4
$num_inference_steps = max(1, min($num_inference_steps, 100)); // 范围1-100
$guidance_scale = max(0, min($guidance_scale, 20)); // 范围0-20
$seed = max(0, min($seed, 9999999999)); // 范围0-9999999999

// 准备请求数据
$request_data = [
    'model' => 'Kwai-Kolors/Kolors',
    'prompt' => $data['prompt'],
    'negative_prompt' => $negative_prompt,
    'image_size' => $image_size,
    'batch_size' => $batch_size,
    'seed' => $seed,
    'num_inference_steps' => $num_inference_steps,
    'guidance_scale' => $guidance_scale
];

// 如果提供了图像，添加到请求中
if (isset($data['image']) && !empty($data['image'])) {
    $request_data['image'] = $data['image'];
}

// 记录API请求日志
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$log_file = $log_dir . '/api_requests.log';
$log_data = date('Y-m-d H:i:s') . " - Request: " . json_encode($request_data) . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// 发送请求到Silicon Flow API
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

// 记录API响应日志
$log_data = date('Y-m-d H:i:s') . " - Response Code: $http_code, Response: " . $response . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// 检查请求是否成功
if ($http_code !== 200) {
    http_response_code($http_code);
    echo json_encode([
        'error' => 'Silicon Flow API请求失败',
        'details' => $curl_error,
        'response' => $response
    ]);
    exit;
}

// 将Silicon Flow响应转换为与原API格式相同的结构
$sf_response = json_decode($response, true);
$formatted_response = [
    'created' => time(),
    'data' => []
];

// 判断是否有images字段
if (isset($sf_response['images']) && is_array($sf_response['images'])) {
    foreach ($sf_response['images'] as $image) {
        if (isset($image['url'])) {
            // 直接使用返回的URL
            $formatted_response['data'][] = [
                'url' => $image['url'],
                'revised_prompt' => $data['prompt'] // 使用原始prompt
            ];
        }
    }
}

// 记录返回给前端的数据
$log_data = date('Y-m-d H:i:s') . " - Formatted Response: " . json_encode($formatted_response) . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// 返回格式化的响应
echo json_encode($formatted_response); 