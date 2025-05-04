<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 模型配置文件路径
$modelsConfigFile = __DIR__ . '/../api/data/models_config.json';

// 默认模型配置
$defaultModels = [
    [
        'id' => 'grok-2-image-1212',
        'name' => 'Grok AI',
        'api_type' => 'xai',
        'active' => true,
        'description' => 'Grok AI是X.AI推出的强大图像生成模型，擅长照片级真实感和创意场景。',
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'sort_order' => 1
    ],
    [
        'id' => 'flux.1-dev',
        'name' => 'Flux.1',
        'api_type' => 'anthropic',
        'active' => true,
        'description' => 'Flux.1是Anthropic推出的高清图像生成模型，擅长艺术创作和逼真照片生成。',
        'max_tokens' => 4000,
        'temperature' => 0.8,
        'sort_order' => 2
    ],
    [
        'id' => 'dall-e-3',
        'name' => 'DALL-E 3',
        'api_type' => 'openai',
        'active' => true,
        'description' => 'OpenAI的DALL-E 3模型，具有出色的细节表现和文本理解能力。',
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'sort_order' => 3
    ],
    [
        'id' => 'siliconflow-1',
        'name' => 'SiliconFlow',
        'api_type' => 'custom',
        'active' => true,
        'description' => '基于最新Stable Diffusion技术的增强模型，适合生成各种艺术风格图像。',
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'sort_order' => 4
    ]
];

// 加载或创建模型配置
$models = [];
if (file_exists($modelsConfigFile)) {
    $models = json_decode(file_get_contents($modelsConfigFile), true);
} else {
    // 如果配置文件不存在，使用默认配置
    $models = $defaultModels;
    // 创建配置目录（如果不存在）
    if (!file_exists(dirname($modelsConfigFile))) {
        mkdir(dirname($modelsConfigFile), 0755, true);
    }
    // 保存默认配置
    file_put_contents($modelsConfigFile, json_encode($models, JSON_PRETTY_PRINT));
}

// 处理模型更新
$updateMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_model') {
        $modelId = $_POST['model_id'] ?? '';
        $modelName = $_POST['model_name'] ?? '';
        $modelDescription = $_POST['model_description'] ?? '';
        $modelActive = isset($_POST['model_active']) ? true : false;
        $modelTemperature = floatval($_POST['model_temperature'] ?? 0.7);
        $modelSortOrder = intval($_POST['model_sort_order'] ?? 0);
        
        // 更新模型
        foreach ($models as &$model) {
            if ($model['id'] === $modelId) {
                $model['name'] = $modelName;
                $model['description'] = $modelDescription;
                $model['active'] = $modelActive;
                $model['temperature'] = $modelTemperature;
                $model['sort_order'] = $modelSortOrder;
                break;
            }
        }
        
        // 保存配置
        file_put_contents($modelsConfigFile, json_encode($models, JSON_PRETTY_PRINT));
        $updateMessage = '模型配置已更新！';
    }
    
    // 处理模型排序调整
    if (isset($_POST['action']) && $_POST['action'] === 'sort_models') {
        usort($models, function($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        });
        
        // 保存配置
        file_put_contents($modelsConfigFile, json_encode($models, JSON_PRETTY_PRINT));
        $updateMessage = '模型排序已更新！';
    }
}

// 获取历史记录数据，用于统计每个模型的使用情况
$historyFile = __DIR__ . '/../api/data/history.json';
$modelUsage = [];

// 初始化每个模型的使用计数
foreach ($models as $model) {
    $modelUsage[$model['id']] = 0;
}

if (file_exists($historyFile)) {
    $history = json_decode(file_get_contents($historyFile), true);
    
    if (is_array($history)) {
        foreach ($history as $item) {
            if (isset($item['model']) && isset($modelUsage[$item['model']])) {
                $modelUsage[$item['model']]++;
            }
        }
    }
}

// 按排序顺序排列模型
usort($models, function($a, $b) {
    return $a['sort_order'] - $b['sort_order'];
});
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI画图管理系统 - 模型管理</title>
    <link rel="icon" type="image/png" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .model-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--gray-200);
        }
        
        .model-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        
        .model-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .model-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.2rem;
        }
        
        .model-body {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
        }
        
        .model-info {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        
        .model-description {
            color: var(--gray-600);
            line-height: 1.6;
        }
        
        .model-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .meta-label {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        
        .meta-value {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .meta-value.highlight {
            color: var(--primary-color);
        }
        
        .model-stats {
            border-left: 1px dashed var(--gray-200);
            padding-left: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-width: 200px;
        }
        
        .stats-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: var(--gray-500);
            font-size: 0.95rem;
            text-align: center;
        }
        
        .model-form {
            padding: 1.5rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: none;
        }
        
        .model-form.active {
            display: block;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.25rem;
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
        
        .form-check {
            display: flex;
            align-items: center;
            margin-top: 2rem;
        }
        
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            margin-right: 0.75rem;
            accent-color: var(--primary-color);
        }
        
        .form-check-label {
            font-weight: 600;
        }
        
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-edit {
            background: none;
            border: none;
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-edit:hover {
            color: var(--primary-dark);
            text-decoration: underline;
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
        
        .sort-models-form {
            padding: 1.5rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }
        
        .sort-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sort-title i {
            color: var(--primary-color);
        }
        
        .form-hint {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .model-body {
                grid-template-columns: 1fr;
            }
            
            .model-stats {
                border-left: none;
                border-top: 1px dashed var(--gray-200);
                padding-left: 0;
                padding-top: 1.5rem;
                margin-top: 1.5rem;
            }
        }
        
        /* 模型图标颜色 */
        .model-icon.flux {
            background: linear-gradient(135deg, #7c5dfa, #9d89fc);
        }
        
        .model-icon.grok {
            background: linear-gradient(135deg, #000000, #333333);
        }
        
        .model-icon.dalle {
            background: linear-gradient(135deg, #11a37f, #00d09c);
        }
        
        .model-icon.silicon {
            background: linear-gradient(135deg, #ef4565, #ff6b8b);
        }
        
        .sort-handle {
            cursor: grab;
            color: var(--gray-400);
            transition: var(--transition);
            margin-right: 0.5rem;
        }
        
        .sort-handle:hover {
            color: var(--gray-700);
        }
        
        .sort-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }
        
        .sort-item.dragging {
            box-shadow: var(--card-shadow);
            border-color: var(--primary-light);
            background: var(--gray-50);
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
                        <a href="models.php" class="menu-link active">
                            <i class="fas fa-brain menu-icon"></i>
                            <span class="menu-text">模型管理</span>
                        </a>
                    </li>
                </ul>
                
                <div class="menu-category">系统</div>
                <ul>
                    <li class="menu-item">
                        <a href="settings.php" class="menu-link">
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
                    <h1 class="page-title">模型管理</h1>
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
                    <div class="breadcrumb-item active">模型管理</div>
                </div>
                
                <?php if ($updateMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $updateMessage; ?>
                </div>
                <?php endif; ?>
                
                <!-- 模型排序 -->
                <div class="sort-models-form">
                    <h3 class="sort-title"><i class="fas fa-sort"></i> 模型显示顺序</h3>
                    <p class="form-hint">拖动模型卡片可调整前台展示顺序，数字越小展示越靠前。</p>
                    
                    <div class="sort-container" id="sortable">
                        <?php foreach ($models as $model): ?>
                        <div class="sort-item" data-id="<?php echo $model['id']; ?>">
                            <i class="fas fa-grip-vertical sort-handle"></i>
                            <div class="sort-name"><?php echo htmlspecialchars($model['name']); ?></div>
                            <div class="meta-item" style="margin-left: auto;">
                                <span class="meta-label">排序值:</span>
                                <span class="meta-value"><?php echo $model['sort_order']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <form action="" method="POST" id="sortForm">
                        <input type="hidden" name="action" value="sort_models">
                        <?php foreach ($models as $model): ?>
                        <input type="hidden" name="sort[<?php echo $model['id']; ?>]" value="<?php echo $model['sort_order']; ?>" id="sort-<?php echo $model['id']; ?>">
                        <?php endforeach; ?>
                        
                        <div class="form-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存排序
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 模型列表 -->
                <?php foreach ($models as $model): ?>
                <?php 
                    $iconClass = 'flux';
                    if (strpos($model['id'], 'grok') !== false) {
                        $iconClass = 'grok';
                    } elseif (strpos($model['id'], 'dall') !== false) {
                        $iconClass = 'dalle';
                    } elseif (strpos($model['id'], 'silicon') !== false) {
                        $iconClass = 'silicon';
                    }
                ?>
                <div class="model-card" id="model-<?php echo $model['id']; ?>">
                    <div class="model-header">
                        <div class="model-title">
                            <div class="model-icon <?php echo $iconClass; ?>">
                                <i class="fas fa-brain"></i>
                            </div>
                            <?php echo htmlspecialchars($model['name']); ?>
                            <?php if ($model['active']): ?>
                            <span class="status status-active"><i class="fas fa-check-circle"></i> 已启用</span>
                            <?php else: ?>
                            <span class="status status-inactive"><i class="fas fa-times-circle"></i> 已禁用</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn-edit" onclick="toggleModelForm('<?php echo $model['id']; ?>')">
                            <i class="fas fa-cog"></i> 编辑配置
                        </button>
                    </div>
                    
                    <div class="model-body">
                        <div class="model-info">
                            <div class="model-description">
                                <?php echo htmlspecialchars($model['description']); ?>
                            </div>
                            
                            <div class="model-meta">
                                <div class="meta-item">
                                    <span class="meta-label">模型ID:</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($model['id']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">API类型:</span>
                                    <span class="meta-value highlight"><?php echo htmlspecialchars($model['api_type']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">温度值:</span>
                                    <span class="meta-value"><?php echo $model['temperature']; ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">排序值:</span>
                                    <span class="meta-value"><?php echo $model['sort_order']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="model-stats">
                            <div class="stats-number"><?php echo $modelUsage[$model['id']] ?? 0; ?></div>
                            <div class="stats-label">已生成图片数</div>
                        </div>
                    </div>
                    
                    <div class="model-form" id="form-<?php echo $model['id']; ?>">
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="update_model">
                            <input type="hidden" name="model_id" value="<?php echo $model['id']; ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="model_name_<?php echo $model['id']; ?>">模型名称</label>
                                    <input type="text" class="form-control" id="model_name_<?php echo $model['id']; ?>" name="model_name" value="<?php echo htmlspecialchars($model['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="model_temperature_<?php echo $model['id']; ?>">温度值</label>
                                    <input type="number" class="form-control" id="model_temperature_<?php echo $model['id']; ?>" name="model_temperature" value="<?php echo $model['temperature']; ?>" min="0" max="2" step="0.1" required>
                                    <p class="form-hint">值越大，生成结果越随机多样（推荐范围: 0.6-1.0）</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="model_sort_order_<?php echo $model['id']; ?>">排序值</label>
                                    <input type="number" class="form-control" id="model_sort_order_<?php echo $model['id']; ?>" name="model_sort_order" value="<?php echo $model['sort_order']; ?>" required>
                                    <p class="form-hint">数值越小展示越靠前</p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="model_description_<?php echo $model['id']; ?>">模型描述</label>
                                <textarea class="form-control" id="model_description_<?php echo $model['id']; ?>" name="model_description" rows="3"><?php echo htmlspecialchars($model['description']); ?></textarea>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="model_active_<?php echo $model['id']; ?>" name="model_active" <?php echo $model['active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="model_active_<?php echo $model['id']; ?>">启用此模型</label>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="button" class="btn btn-light" onclick="toggleModelForm('<?php echo $model['id']; ?>')">
                                    <i class="fas fa-times"></i> 取消
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 保存配置
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
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
        
        // 切换模型表单显示/隐藏
        function toggleModelForm(modelId) {
            const form = document.getElementById(`form-${modelId}`);
            form.classList.toggle('active');
            
            // 滚动到表单位置
            if (form.classList.contains('active')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        // 拖拽排序功能
        document.addEventListener('DOMContentLoaded', function() {
            const sortable = document.getElementById('sortable');
            let draggedItem = null;
            
            // 给每个可拖拽元素添加事件监听器
            document.querySelectorAll('.sort-item').forEach(item => {
                item.setAttribute('draggable', true);
                
                item.addEventListener('dragstart', function() {
                    draggedItem = this;
                    setTimeout(() => {
                        this.classList.add('dragging');
                    }, 0);
                });
                
                item.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                    draggedItem = null;
                    
                    // 更新隐藏表单字段的值
                    updateSortOrder();
                });
                
                item.addEventListener('dragover', function(e) {
                    e.preventDefault();
                });
                
                item.addEventListener('dragenter', function(e) {
                    e.preventDefault();
                    if (draggedItem !== this) {
                        const items = Array.from(sortable.querySelectorAll('.sort-item'));
                        const draggedIndex = items.indexOf(draggedItem);
                        const targetIndex = items.indexOf(this);
                        
                        if (draggedIndex < targetIndex) {
                            sortable.insertBefore(draggedItem, this.nextSibling);
                        } else {
                            sortable.insertBefore(draggedItem, this);
                        }
                    }
                });
            });
            
            // 更新排序值到隐藏表单字段
            function updateSortOrder() {
                const items = document.querySelectorAll('.sort-item');
                items.forEach((item, index) => {
                    const modelId = item.getAttribute('data-id');
                    const input = document.getElementById(`sort-${modelId}`);
                    if (input) {
                        input.value = index + 1;
                        item.querySelector('.meta-value').textContent = index + 1;
                    }
                });
            }
        });
        
        // 自动隐藏成功消息
        const successMessage = document.querySelector('.success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html> 