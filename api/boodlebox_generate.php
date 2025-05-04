<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 确保请求方法是POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '仅支持POST请求']);
    exit;
}

try {
    // 读取请求体
    $requestBody = file_get_contents('php://input');
    $requestData = json_decode($requestBody, true);
    
    // 检查必要的参数
    if (!isset($requestData['prompt']) || !isset($requestData['model'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数：prompt或model']);
        exit;
    }
    
    // 设置默认值
    if (!isset($requestData['n'])) {
        $requestData['n'] = 1;
    }
    
    // 配置boodlebox2api服务地址
    $apiUrl = 'http://localhost:10066/v1/images/generations';
    
    // 创建cURL请求
    $ch = curl_init($apiUrl);
    
    // 设置请求参数
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: boodlebox2api'
    ]);
    
    // 执行请求
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // 检查请求是否成功
    if ($httpCode !== 200) {
        $error = curl_error($ch);
        http_response_code($httpCode);
        echo json_encode(['error' => "API请求失败: $error", 'status' => $httpCode]);
        exit;
    }
    
    // 记录生成历史（如果需要）
    if (isset($requestData['save_history']) && $requestData['save_history']) {
        $responseData = json_decode($response, true);
        if (isset($responseData['data'][0]['url'])) {
            // 构建历史记录数据
            $historyData = [
                'prompt' => $requestData['prompt'],
                'model' => $requestData['model'],
                'image_url' => $responseData['data'][0]['url'],
                'timestamp' => time()
            ];
            
            if (isset($responseData['data'][0]['revised_prompt'])) {
                $historyData['revised_prompt'] = $responseData['data'][0]['revised_prompt'];
            }
            
            // 调用add_history.php保存历史记录
            $historyContext = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode($historyData)
                ]
            ]);
            
            @file_get_contents('http://localhost/api/add_history.php', false, $historyContext);
        }
    }
    
    // 返回API响应
    echo $response;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器错误: ' . $e->getMessage()]);
}
?> 