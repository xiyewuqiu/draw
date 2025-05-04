<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 系统设置文件路径
$settingsFile = __DIR__ . '/../api/data/settings.json';

// 默认设置
$defaultSettings = [
    'site' => [
        'title' => 'AI画图生成器',
        'description' => '释放你的创意，选择多种AI模型为你呈现艺术之美',
        'keywords' => 'AI画图,人工智能,图像生成,DALL-E 3,Grok AI,Flux.1-dev,Stable Diffusion',
        'logo' => 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg',
        'theme_color' => '#7c5dfa',
        'footer_text' => '© 2023 AI画图生成器 版权所有'
    ],
    'images' => [
        'max_history' => 1000,
        'default_size' => '1024x1024',
        'allow_download' => true,
        'allow_upscale' => true,
        'max_generations' => 4
    ],
    'api' => [
        'enable_rate_limit' => true,
        'rate_limit_per_hour' => 50,
        'timeout' => 60,
        'log_requests' => true
    ],
    'security' => [
        'enable_filter' => true,
        'filter_words' => '色情,暴力,血腥,政治,敏感,违法,赌博',
        'enable_watermark' => false,
        'watermark_text' => 'AI画图生成'
    ]
];

// 加载或创建设置
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
} else {
    // 如果设置文件不存在，使用默认设置
    $settings = $defaultSettings;
    // 创建配置目录（如果不存在）
    if (!file_exists(dirname($settingsFile))) {
        mkdir(dirname($settingsFile), 0755, true);
    }
    // 保存默认设置
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
}

// 处理设置更新
$updateMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $section = $_POST['action'];
        
        if ($section === 'site') {
            $settings['site']['title'] = $_POST['site_title'] ?? $settings['site']['title'];
            $settings['site']['description'] = $_POST['site_description'] ?? $settings['site']['description'];
            $settings['site']['keywords'] = $_POST['site_keywords'] ?? $settings['site']['keywords'];
            $settings['site']['logo'] = $_POST['site_logo'] ?? $settings['site']['logo'];
            $settings['site']['theme_color'] = $_POST['site_theme_color'] ?? $settings['site']['theme_color'];
            $settings['site']['footer_text'] = $_POST['site_footer_text'] ?? $settings['site']['footer_text'];
        } elseif ($section === 'images') {
            $settings['images']['max_history'] = (int)($_POST['max_history'] ?? $settings['images']['max_history']);
            $settings['images']['default_size'] = $_POST['default_size'] ?? $settings['images']['default_size'];
            $settings['images']['allow_download'] = isset($_POST['allow_download']);
            $settings['images']['allow_upscale'] = isset($_POST['allow_upscale']);
            $settings['images']['max_generations'] = (int)($_POST['max_generations'] ?? $settings['images']['max_generations']);
        } elseif ($section === 'api') {
            $settings['api']['enable_rate_limit'] = isset($_POST['enable_rate_limit']);
            $settings['api']['rate_limit_per_hour'] = (int)($_POST['rate_limit_per_hour'] ?? $settings['api']['rate_limit_per_hour']);
            $settings['api']['timeout'] = (int)($_POST['timeout'] ?? $settings['api']['timeout']);
            $settings['api']['log_requests'] = isset($_POST['log_requests']);
        } elseif ($section === 'security') {
            $settings['security']['enable_filter'] = isset($_POST['enable_filter']);
            $settings['security']['filter_words'] = $_POST['filter_words'] ?? $settings['security']['filter_words'];
            $settings['security']['enable_watermark'] = isset($_POST['enable_watermark']);
            $settings['security']['watermark_text'] = $_POST['watermark_text'] ?? $settings['security']['watermark_text'];
        }
        
        // 保存设置
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        $updateMessage = '系统设置已更新！';
    }
}

// 清除缓存
function clearCache() {
    $cacheFolder = __DIR__ . '/../api/cache/';
    if (is_dir($cacheFolder)) {
        $files = glob($cacheFolder . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
    return false;
}

// 处理清除缓存请求
$cacheMessage = '';
if (isset($_POST['clear_cache'])) {
    if (clearCache()) {
        $cacheMessage = '系统缓存已成功清除！';
    } else {
        $cacheMessage = '系统缓存清除失败或缓存目录不存在。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI画图管理系统 - 系统设置</title>
    <link rel="icon" type="image/png" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .settings-nav {
            display: flex;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .settings-nav-item {
            flex: 1;
            text-align: center;
            padding: 1.25rem;
            cursor: pointer;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }
        
        .settings-nav-item:hover {
            color: var(--primary-color);
            background-color: var(--gray-50);
        }
        
        .settings-nav-item.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: var(--gray-50);
        }
        
        .settings-nav-item i {
            margin-right: 0.75rem;
        }
        
        .settings-section {
            display: none;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .settings-section.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        .settings-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .settings-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .settings-title i {
            margin-right: 0.75rem;
            color: var(--primary-color);
        }
        
        .settings-description {
            color: var(--gray-500);
            font-size: 0.95rem;
        }
        
        .settings-body {
            padding: 1.5rem;
        }
        
        .settings-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(124, 93, 250, 0.1);
        }
        
        .color-picker {
            display: flex;
            align-items: center;
        }
        
        .color-swatch {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-right: 1rem;
            border: 1px solid var(--gray-300);
            display: inline-block;
            cursor: pointer;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            margin-right: 0.75rem;
            accent-color: var(--primary-color);
        }
        
        .form-check-label {
            font-weight: 500;
        }
        
        .form-hint {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .success-message {
            padding: 1rem;
            background: rgba(56, 190, 201, 0.1);
            color: var(--success-color);
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        
        .success-message i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }
        
        .cache-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }
        
        .cache-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .cache-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
        }
        
        .cache-title i {
            margin-right: 0.75rem;
            color: var(--primary-color);
        }
        
        .cache-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .cache-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }
        
        .cache-text {
            margin-bottom: 1.5rem;
            color: var(--gray-600);
            max-width: 500px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .settings-nav {
                flex-direction: column;
            }
            
            .settings-nav-item {
                padding: 1rem;
                border-bottom: 1px solid var(--gray-200);
                border-left: 3px solid transparent;
                text-align: left;
            }
            
            .settings-nav-item.active {
                border-bottom-color: var(--gray-200);
                border-left-color: var(--primary-color);
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- 侧边栏 -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-paintbrush"></i>
                    <h1>AI画图管理系统</h1>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-category">主菜单</div>
                <ul>
                    <li class="menu-item">
                        <a href="index.php" class="menu-link">
                            <i class="fas fa-tachometer-alt menu-icon"></i>
                            <span class="menu-text">控制面板</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="images.php" class="menu-link">
                            <i class="fas fa-images menu-icon"></i>
                            <span class="menu-text">图片管理</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="models.php" class="menu-link">
                            <i class="fas fa-brain menu-icon"></i>
                            <span class="menu-text">模型管理</span>
                        </a>
                    </li>
                </ul>
                
                <div class="menu-category">系统</div>
                <ul>
                    <li class="menu-item">
                        <a href="settings.php" class="menu-link active">
                            <i class="fas fa-cog menu-icon"></i>
                            <span class="menu-text">系统设置</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="logs.php" class="menu-link">
                            <i class="fas fa-list menu-icon"></i>
                            <span class="menu-text">系统日志</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="logout.php" class="menu-link">
                            <i class="fas fa-sign-out-alt menu-icon"></i>
                            <span class="menu-text">退出登录</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- 主内容区 -->
        <div class="main-content">
            <!-- 头部 -->
            <header class="header">
                <div class="header-left">
                    <button class="toggle-sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">系统设置</h1>
                </div>
                
                <div class="header-right">
                    <div class="header-icon">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count">3</span>
                    </div>
                    
                    <div class="admin-user">
                        <div class="admin-avatar">
                            <?php echo substr($_SESSION['admin_username'], 0, 1); ?>
                        </div>
                        <div class="admin-info">
                            <div class="admin-name"><?php echo $_SESSION['admin_username']; ?></div>
                            <div class="admin-role">系统管理员</div>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </header>
            
            <!-- 内容区 -->
            <div class="content-wrapper">
                <!-- 面包屑导航 -->
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i></a></div>
                    <div class="breadcrumb-item active">系统设置</div>
                </div>
                
                <?php if ($updateMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $updateMessage; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($cacheMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $cacheMessage; ?>
                </div>
                <?php endif; ?>
                
                <!-- 设置导航 -->
                <div class="settings-nav">
                    <div class="settings-nav-item active" data-section="site">
                        <i class="fas fa-globe"></i> 网站设置
                    </div>
                    <div class="settings-nav-item" data-section="images">
                        <i class="fas fa-image"></i> 图片设置
                    </div>
                    <div class="settings-nav-item" data-section="api">
                        <i class="fas fa-code"></i> API设置
                    </div>
                    <div class="settings-nav-item" data-section="security">
                        <i class="fas fa-shield-alt"></i> 安全设置
                    </div>
                </div>
                
                <!-- 网站设置 -->
                <div class="settings-section active" id="section-site">
                    <div class="settings-header">
                        <h2 class="settings-title"><i class="fas fa-globe"></i> 网站设置</h2>
                        <p class="settings-description">配置网站的基本信息，包括标题、描述、关键词和主题颜色等。</p>
                    </div>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="site">
                        
                        <div class="settings-body">
                            <div class="form-group">
                                <label for="site_title">网站标题</label>
                                <input type="text" class="form-control" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site']['title']); ?>">
                                <p class="form-hint">显示在浏览器标签页和SEO中的网站名称</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_description">网站描述</label>
                                <textarea class="form-control" id="site_description" name="site_description" rows="2"><?php echo htmlspecialchars($settings['site']['description']); ?></textarea>
                                <p class="form-hint">网站的简要描述，用于SEO和分享时的预览</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_keywords">网站关键词</label>
                                <input type="text" class="form-control" id="site_keywords" name="site_keywords" value="<?php echo htmlspecialchars($settings['site']['keywords']); ?>">
                                <p class="form-hint">用逗号分隔的关键词列表，用于SEO优化</p>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="site_logo">网站Logo URL</label>
                                    <input type="text" class="form-control" id="site_logo" name="site_logo" value="<?php echo htmlspecialchars($settings['site']['logo']); ?>">
                                    <p class="form-hint">网站logo的URL地址</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_theme_color">主题颜色</label>
                                    <div class="color-picker">
                                        <span class="color-swatch" id="colorSwatch" style="background-color: <?php echo $settings['site']['theme_color']; ?>"></span>
                                        <input type="text" class="form-control" id="site_theme_color" name="site_theme_color" value="<?php echo htmlspecialchars($settings['site']['theme_color']); ?>">
                                    </div>
                                    <p class="form-hint">网站的主题颜色，格式如：#7c5dfa</p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_footer_text">页脚文本</label>
                                <input type="text" class="form-control" id="site_footer_text" name="site_footer_text" value="<?php echo htmlspecialchars($settings['site']['footer_text']); ?>">
                                <p class="form-hint">显示在网站底部的版权信息等</p>
                            </div>
                        </div>
                        
                        <div class="settings-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 图片设置 -->
                <div class="settings-section" id="section-images">
                    <div class="settings-header">
                        <h2 class="settings-title"><i class="fas fa-image"></i> 图片设置</h2>
                        <p class="settings-description">配置与图片生成和展示相关的参数。</p>
                    </div>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="images">
                        
                        <div class="settings-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_history">最大历史记录数</label>
                                    <input type="number" class="form-control" id="max_history" name="max_history" value="<?php echo htmlspecialchars($settings['images']['max_history']); ?>" min="10" max="10000">
                                    <p class="form-hint">系统保存的最大历史图片数量</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="default_size">默认图片尺寸</label>
                                    <select class="form-control" id="default_size" name="default_size">
                                        <option value="512x512" <?php echo $settings['images']['default_size'] === '512x512' ? 'selected' : ''; ?>>512x512 (小图)</option>
                                        <option value="1024x1024" <?php echo $settings['images']['default_size'] === '1024x1024' ? 'selected' : ''; ?>>1024x1024 (标准)</option>
                                        <option value="1024x1792" <?php echo $settings['images']['default_size'] === '1024x1792' ? 'selected' : ''; ?>>1024x1792 (竖图)</option>
                                        <option value="1792x1024" <?php echo $settings['images']['default_size'] === '1792x1024' ? 'selected' : ''; ?>>1792x1024 (横图)</option>
                                    </select>
                                    <p class="form-hint">默认生成的图片尺寸</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="max_generations">单次最大生成数量</label>
                                    <input type="number" class="form-control" id="max_generations" name="max_generations" value="<?php echo htmlspecialchars($settings['images']['max_generations']); ?>" min="1" max="10">
                                    <p class="form-hint">一次请求最多可生成的图片数量</p>
                                </div>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="allow_download" name="allow_download" <?php echo $settings['images']['allow_download'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_download">允许用户下载图片</label>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="allow_upscale" name="allow_upscale" <?php echo $settings['images']['allow_upscale'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_upscale">允许图片放大处理</label>
                            </div>
                        </div>
                        
                        <div class="settings-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- API设置 -->
                <div class="settings-section" id="section-api">
                    <div class="settings-header">
                        <h2 class="settings-title"><i class="fas fa-code"></i> API设置</h2>
                        <p class="settings-description">配置与API接口相关的参数，包括速率限制和超时设置。</p>
                    </div>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="api">
                        
                        <div class="settings-body">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="enable_rate_limit" name="enable_rate_limit" <?php echo $settings['api']['enable_rate_limit'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_rate_limit">启用速率限制</label>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="rate_limit_per_hour">每小时请求限制</label>
                                    <input type="number" class="form-control" id="rate_limit_per_hour" name="rate_limit_per_hour" value="<?php echo htmlspecialchars($settings['api']['rate_limit_per_hour']); ?>" min="1">
                                    <p class="form-hint">单个用户每小时最大请求次数</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="timeout">API超时时间(秒)</label>
                                    <input type="number" class="form-control" id="timeout" name="timeout" value="<?php echo htmlspecialchars($settings['api']['timeout']); ?>" min="10" max="300">
                                    <p class="form-hint">请求AI模型的最大等待时间</p>
                                </div>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="log_requests" name="log_requests" <?php echo $settings['api']['log_requests'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="log_requests">记录API请求日志</label>
                            </div>
                        </div>
                        
                        <div class="settings-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 安全设置 -->
                <div class="settings-section" id="section-security">
                    <div class="settings-header">
                        <h2 class="settings-title"><i class="fas fa-shield-alt"></i> 安全设置</h2>
                        <p class="settings-description">配置系统安全相关的参数，包括内容过滤和水印设置。</p>
                    </div>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="security">
                        
                        <div class="settings-body">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="enable_filter" name="enable_filter" <?php echo $settings['security']['enable_filter'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_filter">启用内容过滤</label>
                            </div>
                            
                            <div class="form-group">
                                <label for="filter_words">过滤关键词</label>
                                <textarea class="form-control" id="filter_words" name="filter_words" rows="3"><?php echo htmlspecialchars($settings['security']['filter_words']); ?></textarea>
                                <p class="form-hint">使用逗号分隔的敏感关键词列表，含有这些词的提示词将被拒绝</p>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="enable_watermark" name="enable_watermark" <?php echo $settings['security']['enable_watermark'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_watermark">启用图片水印</label>
                            </div>
                            
                            <div class="form-group">
                                <label for="watermark_text">水印文本</label>
                                <input type="text" class="form-control" id="watermark_text" name="watermark_text" value="<?php echo htmlspecialchars($settings['security']['watermark_text']); ?>">
                                <p class="form-hint">添加到生成图片上的水印文字</p>
                            </div>
                        </div>
                        
                        <div class="settings-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 缓存管理 -->
                <div class="cache-card">
                    <div class="cache-header">
                        <h2 class="cache-title"><i class="fas fa-database"></i> 缓存管理</h2>
                    </div>
                    
                    <div class="cache-body">
                        <div class="cache-icon">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <p class="cache-text">清除系统缓存可以释放存储空间并解决某些功能异常。如果您遇到图片加载问题或数据不更新的情况，可以尝试清除缓存。</p>
                        
                        <form action="" method="POST">
                            <input type="hidden" name="clear_cache" value="1">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> 清除系统缓存
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 页脚 -->
            <footer class="footer">
                <div>© 2023 AI画图生成器管理系统 版权所有</div>
                <div>系统版本 v1.0.0</div>
            </footer>
        </div>
    </div>
    
    <script>
        // 侧边栏折叠
        document.querySelector('.toggle-sidebar').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
        
        // 设置导航切换
        document.querySelectorAll('.settings-nav-item').forEach(item => {
            item.addEventListener('click', function() {
                const section = this.getAttribute('data-section');
                
                // 移除所有激活状态
                document.querySelectorAll('.settings-nav-item').forEach(navItem => {
                    navItem.classList.remove('active');
                });
                document.querySelectorAll('.settings-section').forEach(sectionElem => {
                    sectionElem.classList.remove('active');
                });
                
                // 激活当前选中的
                this.classList.add('active');
                document.getElementById('section-' + section).classList.add('active');
            });
        });
        
        // 颜色选择器
        const colorSwatch = document.getElementById('colorSwatch');
        const colorInput = document.getElementById('site_theme_color');
        
        colorInput.addEventListener('input', function() {
            colorSwatch.style.backgroundColor = this.value;
        });
        
        // 在点击色块时打开颜色选择器
        colorSwatch.addEventListener('click', function() {
            const input = document.createElement('input');
            input.type = 'color';
            input.value = colorInput.value;
            
            input.addEventListener('input', function() {
                colorInput.value = this.value;
                colorSwatch.style.backgroundColor = this.value;
            });
            
            input.click();
        });
        
        // 自动隐藏成功消息
        const successMessages = document.querySelectorAll('.success-message');
        successMessages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 300);
            }, 3000);
        });
    </script>
</body>
</html> 