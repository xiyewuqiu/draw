<?php
/**
 * 限制检查工具函数库
 * 用于检查用户和模型使用限制
 */

// 配置文件路径
define('CONFIG_FILE', __DIR__ . '/../data/config.json');
define('USAGE_FILE', __DIR__ . '/../data/usage.json');

/**
 * 获取系统配置
 * 
 * @return array 配置数组
 */
function getConfig() {
    // 默认配置
    $defaultConfig = [
        'models' => [
            'grok' => [
                'name' => 'Grok',
                'daily_limit' => 10,
                'status' => 'active'
            ],
            'siliconflow' => [
                'name' => 'SiliconFlow',
                'daily_limit' => 15,
                'status' => 'active'
            ],
            'boodlebox' => [
                'name' => 'BoodleBox',
                'daily_limit' => 20,
                'status' => 'active'
            ]
        ],
        'user_limits' => [
            'daily_limit' => 30,
            'hourly_limit' => 10
        ],
        'system' => [
            'maintenance_mode' => false,
            'maintenance_message' => '系统正在维护中，请稍后再试...',
            'save_ip' => true,
            'auto_delete_days' => 30
        ]
    ];
    
    // 如果配置文件存在，读取配置
    if (file_exists(CONFIG_FILE)) {
        $configData = json_decode(file_get_contents(CONFIG_FILE), true);
        if (is_array($configData)) {
            return array_replace_recursive($defaultConfig, $configData);
        }
    }
    
    return $defaultConfig;
}

/**
 * 获取用户ID（基于IP）
 * 
 * @return string 用户ID
 */
function getUserId() {
    $config = getConfig();
    
    if ($config['system']['save_ip']) {
        // 保存完整IP作为用户ID
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    } else {
        // 对IP进行哈希处理，保护隐私
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return md5($ip . 'salt_for_privacy');
    }
}

/**
 * 获取用户使用情况
 * 
 * @return array 用户使用情况数组
 */
function getUserUsage() {
    $usage = [];
    
    if (file_exists(USAGE_FILE)) {
        $usageData = json_decode(file_get_contents(USAGE_FILE), true);
        if (is_array($usageData)) {
            $usage = $usageData;
        }
    }
    
    return $usage;
}

/**
 * 更新用户使用情况
 * 
 * @param array $usage 使用情况数组
 * @return bool 是否成功更新
 */
function updateUserUsage($usage) {
    return file_put_contents(USAGE_FILE, json_encode($usage, JSON_PRETTY_PRINT)) !== false;
}

/**
 * 清理过期的使用记录
 * 
 * @param array $usage 使用情况数组
 * @return array 清理后的使用情况数组
 */
function cleanupExpiredUsage($usage) {
    $today = date('Y-m-d');
    $currentHour = date('Y-m-d H');
    
    foreach ($usage as $userId => $userData) {
        // 清理过期的日期记录
        if (isset($userData['daily'])) {
            foreach ($userData['daily'] as $date => $counts) {
                if ($date != $today) {
                    unset($usage[$userId]['daily'][$date]);
                }
            }
        }
        
        // 清理过期的小时记录
        if (isset($userData['hourly'])) {
            foreach ($userData['hourly'] as $hour => $counts) {
                if ($hour != $currentHour) {
                    unset($usage[$userId]['hourly'][$hour]);
                }
            }
        }
    }
    
    return $usage;
}

/**
 * 检查系统是否处于维护模式
 * 
 * @return array|bool 如果在维护模式下返回错误信息，否则返回false
 */
function checkMaintenanceMode() {
    $config = getConfig();
    
    if ($config['system']['maintenance_mode']) {
        return [
            'error' => '系统维护',
            'message' => $config['system']['maintenance_message']
        ];
    }
    
    return false;
}

/**
 * 检查模型状态
 * 
 * @param string $model 模型标识
 * @return array|bool 如果模型不可用返回错误信息，否则返回false
 */
function checkModelStatus($model) {
    $config = getConfig();
    
    // 如果模型不存在或被禁用
    if (!isset($config['models'][$model]) || $config['models'][$model]['status'] !== 'active') {
        return [
            'error' => '模型不可用',
            'message' => '所选模型当前不可用，请选择其他模型'
        ];
    }
    
    return false;
}

/**
 * 检查用户是否达到使用限制
 * 
 * @param string $model 模型标识
 * @return array|bool 如果达到限制返回错误信息，否则返回false
 */
function checkUserLimits($model) {
    $config = getConfig();
    $userId = getUserId();
    $today = date('Y-m-d');
    $currentHour = date('Y-m-d H');
    
    // 获取当前使用情况
    $usage = getUserUsage();
    
    // 初始化用户记录（如果不存在）
    if (!isset($usage[$userId])) {
        $usage[$userId] = [
            'daily' => [],
            'hourly' => [],
            'models' => []
        ];
    }
    
    // 初始化日期记录
    if (!isset($usage[$userId]['daily'][$today])) {
        $usage[$userId]['daily'][$today] = 0;
    }
    
    // 初始化小时记录
    if (!isset($usage[$userId]['hourly'][$currentHour])) {
        $usage[$userId]['hourly'][$currentHour] = 0;
    }
    
    // 初始化模型记录
    if (!isset($usage[$userId]['models'][$model][$today])) {
        $usage[$userId]['models'][$model][$today] = 0;
    }
    
    // 清理过期记录
    $usage = cleanupExpiredUsage($usage);
    
    // 检查每日总限制
    $dailyUsage = $usage[$userId]['daily'][$today];
    if ($dailyUsage >= $config['user_limits']['daily_limit']) {
        return [
            'error' => '达到每日限制',
            'message' => '您今日的图片生成次数已达上限，请明天再试'
        ];
    }
    
    // 检查每小时限制
    $hourlyUsage = $usage[$userId]['hourly'][$currentHour];
    if ($hourlyUsage >= $config['user_limits']['hourly_limit']) {
        return [
            'error' => '达到每小时限制',
            'message' => '您的图片生成频率过高，请稍后再试'
        ];
    }
    
    // 检查模型特定限制
    $modelDailyUsage = $usage[$userId]['models'][$model][$today] ?? 0;
    $modelLimit = $config['models'][$model]['daily_limit'];
    if ($modelDailyUsage >= $modelLimit) {
        return [
            'error' => '达到模型限制',
            'message' => "您今日使用 {$config['models'][$model]['name']} 模型的次数已达上限"
        ];
    }
    
    return false;
}

/**
 * 记录用户使用情况
 * 
 * @param string $model 使用的模型
 * @return bool 是否成功记录
 */
function recordUsage($model) {
    $userId = getUserId();
    $today = date('Y-m-d');
    $currentHour = date('Y-m-d H');
    
    // 获取当前使用情况
    $usage = getUserUsage();
    
    // 初始化用户记录（如果不存在）
    if (!isset($usage[$userId])) {
        $usage[$userId] = [
            'daily' => [],
            'hourly' => [],
            'models' => []
        ];
    }
    
    // 初始化日期记录
    if (!isset($usage[$userId]['daily'][$today])) {
        $usage[$userId]['daily'][$today] = 0;
    }
    
    // 初始化小时记录
    if (!isset($usage[$userId]['hourly'][$currentHour])) {
        $usage[$userId]['hourly'][$currentHour] = 0;
    }
    
    // 初始化模型记录
    if (!isset($usage[$userId]['models'][$model][$today])) {
        $usage[$userId]['models'][$model][$today] = 0;
    }
    
    // 增加计数
    $usage[$userId]['daily'][$today]++;
    $usage[$userId]['hourly'][$currentHour]++;
    $usage[$userId]['models'][$model][$today]++;
    
    // 保存使用情况
    return updateUserUsage($usage);
}

/**
 * 检查使用限制并记录使用情况
 * 
 * @param string $model 使用的模型
 * @return array|bool 如果有限制返回错误信息，否则记录使用并返回false
 */
function checkAndRecordUsage($model) {
    // 检查系统维护模式
    $maintenanceCheck = checkMaintenanceMode();
    if ($maintenanceCheck) {
        return $maintenanceCheck;
    }
    
    // 检查模型状态
    $modelCheck = checkModelStatus($model);
    if ($modelCheck) {
        return $modelCheck;
    }
    
    // 检查用户限制
    $limitCheck = checkUserLimits($model);
    if ($limitCheck) {
        return $limitCheck;
    }
    
    // 记录使用情况
    recordUsage($model);
    
    return false;
} 