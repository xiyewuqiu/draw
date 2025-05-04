<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 获取要比较的图片ID
$imageIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

if (empty($imageIds) || count($imageIds) < 2) {
    echo "需要至少两张图片进行比较！";
    exit;
}

// 获取历史记录数据
$historyFile = __DIR__ . '/../api/data/history.json';
$compareImages = [];

if (file_exists($historyFile)) {
    $allImages = json_decode(file_get_contents($historyFile), true);
    
    if (is_array($allImages)) {
        foreach ($allImages as $image) {
            if (in_array($image['id'], $imageIds)) {
                $compareImages[] = $image;
            }
        }
    }
}

// 确保有足够的图片进行比较
if (count($compareImages) < 2) {
    echo "找不到足够的图片进行比较！";
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI画图管理系统 - 图片比较</title>
    <link rel="icon" type="image/png" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary-color: #7c5dfa;
            --primary-light: #9277ff;
            --primary-dark: #5e46d6;
            --bg-color: #f8f8fb;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success-color: #33d69f;
            --warning-color: #ff8f00;
            --error-color: #ec5757;
            --border-radius: 12px;
            --card-shadow: 0 4px 16px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
            --primary-gradient: linear-gradient(to right, var(--primary-color), var(--primary-light));
        }
        
        /* 暗色模式 */
        .dark-mode {
            --bg-color: #141625;
            --white: #1e2139;
            --gray-50: #252945;
            --gray-100: #2a3159;
            --gray-200: #373b53;
            --gray-300: #494e6e;
            --gray-400: #5a6084;
            --gray-500: #7e88c3;
            --gray-600: #888eb0;
            --gray-700: #dfe3fa;
            --gray-800: #f9fafe;
            --gray-900: #ffffff;
            --card-shadow: 0 4px 16px rgba(0,0,0,0.25);
        }
        
        .compare-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .compare-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .compare-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        .compare-actions {
            display: flex;
            gap: 1rem;
        }
        
        .compare-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .compare-card {
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .compare-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        
        .compare-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
        }
        
        .compare-details {
            padding: 1.5rem;
        }
        
        .compare-model {
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            background: rgba(124, 93, 250, 0.1);
            color: var(--primary-color);
            border-radius: 1rem;
            display: inline-block;
            margin-bottom: 0.75rem;
        }
        
        .compare-prompt {
            font-size: 1rem;
            color: var(--gray-700);
            line-height: 1.5;
            margin-bottom: 1rem;
            max-height: 100px;
            overflow-y: auto;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: 8px;
        }
        
        .compare-date {
            font-size: 0.9rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .compare-info {
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .compare-spec {
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        
        .compare-spec b {
            color: var(--gray-700);
        }
        
        .diff-highlight {
            background-color: rgba(255, 143, 0, 0.2);
            padding: 0 2px;
            border-radius: 3px;
        }
        
        .side-by-side-view .compare-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .overlay-view .compare-grid {
            grid-template-columns: 1fr;
            position: relative;
        }
        
        .overlay-slider {
            width: 100%;
            margin: 1rem 0;
        }
        
        .overlay-container {
            position: relative;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .overlay-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .overlay-image.first {
            clip-path: inset(0 50% 0 0);
        }
        
        .overlay-divider {
            position: absolute;
            top: 0;
            left: 50%;
            bottom: 0;
            width: 4px;
            background: white;
            transform: translateX(-50%);
            cursor: ew-resize;
        }
        
        .overlay-circle {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translate(-50%, -50%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            color: var(--gray-800);
        }
        
        .diff-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .diff-table th, .diff-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .diff-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .diff-table tr:last-child td {
            border-bottom: none;
        }
        
        .diff-table tr:hover {
            background: var(--gray-50);
        }
        
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-200);
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .theme-toggle:hover {
            background: var(--gray-300);
        }
        
        .dark-mode .theme-toggle {
            color: var(--warning-color);
        }
        
        .view-toggle-group {
            display: flex;
            background: var(--gray-100);
            border-radius: 8px;
            padding: 0.25rem;
        }
        
        .view-toggle {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            background: transparent;
            border: none;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-toggle.active {
            background: var(--white);
            color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 768px) {
            .compare-grid {
                grid-template-columns: 1fr;
            }
            
            .side-by-side-view .compare-grid {
                grid-template-columns: 1fr;
            }
            
            .compare-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .compare-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="compare-container">
        <div class="compare-header">
            <h1 class="compare-title">图片比较 - <?php echo count($compareImages); ?>张图片</h1>
            
            <div class="compare-actions">
                <div class="view-toggle-group">
                    <button class="view-toggle active" data-view="grid">
                        <i class="fas fa-th"></i> 网格视图
                    </button>
                    <button class="view-toggle" data-view="side-by-side">
                        <i class="fas fa-columns"></i> 并排视图
                    </button>
                    <button class="view-toggle" data-view="overlay">
                        <i class="fas fa-layer-group"></i> 叠加视图
                    </button>
                </div>
                
                <div class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </div>
            </div>
        </div>
        
        <!-- 差异分析表格 -->
        <table class="diff-table">
            <thead>
                <tr>
                    <th>比较项</th>
                    <?php foreach ($compareImages as $index => $image): ?>
                    <th>图片 <?php echo $index + 1; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>AI模型</td>
                    <?php foreach ($compareImages as $image): ?>
                    <td><?php echo isset($image['model']) ? htmlspecialchars($image['model']) : '未知'; ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>尺寸</td>
                    <?php foreach ($compareImages as $image): ?>
                    <td>
                        <?php 
                        echo isset($image['width']) && isset($image['height']) 
                            ? htmlspecialchars($image['width'] . ' x ' . $image['height']) 
                            : '未知'; 
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>生成时间</td>
                    <?php foreach ($compareImages as $image): ?>
                    <td>
                        <?php 
                        echo isset($image['timestamp']) 
                            ? date('Y-m-d H:i:s', $image['timestamp'] / 1000) 
                            : '未知'; 
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td>提示词</td>
                    <?php 
                    $prompts = [];
                    foreach ($compareImages as $image) {
                        $prompts[] = isset($image['prompt']) ? $image['prompt'] : ''; 
                    }
                    
                    // 简单差异高亮
                    $highlightedPrompts = $prompts;
                    if (count($prompts) >= 2) {
                        $words1 = explode(' ', $prompts[0]);
                        $words2 = explode(' ', $prompts[1]);
                        
                        $diff1 = array_diff($words1, $words2);
                        $diff2 = array_diff($words2, $words1);
                        
                        if (!empty($diff1)) {
                            foreach ($diff1 as $word) {
                                $highlightedPrompts[0] = str_replace($word, '<span class="diff-highlight">' . $word . '</span>', $highlightedPrompts[0]);
                            }
                        }
                        
                        if (!empty($diff2)) {
                            foreach ($diff2 as $word) {
                                $highlightedPrompts[1] = str_replace($word, '<span class="diff-highlight">' . $word . '</span>', $highlightedPrompts[1]);
                            }
                        }
                    }
                    
                    foreach ($highlightedPrompts as $prompt):
                    ?>
                    <td><?php echo $prompt; ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        
        <!-- 图片网格 -->
        <div class="compare-grid" id="compareGrid">
            <?php foreach ($compareImages as $index => $image): ?>
            <div class="compare-card">
                <img 
                    src="<?php echo htmlspecialchars($image['imageUrl']); ?>" 
                    alt="AI生成图片 <?php echo $index + 1; ?>" 
                    class="compare-image"
                    data-index="<?php echo $index; ?>"
                >
                <div class="compare-details">
                    <div class="compare-model">
                        <?php echo isset($image['model']) ? htmlspecialchars($image['model']) : '未知模型'; ?>
                    </div>
                    <div class="compare-prompt">
                        <?php echo isset($image['prompt']) ? htmlspecialchars($image['prompt']) : '无提示词'; ?>
                    </div>
                    <div class="compare-date">
                        <i class="far fa-clock"></i>
                        <?php 
                        echo isset($image['timestamp']) 
                            ? date('Y-m-d H:i', $image['timestamp'] / 1000) 
                            : '未知时间'; 
                        ?>
                    </div>
                    <div class="compare-info">
                        <div class="compare-spec">
                            尺寸: <b><?php 
                            echo isset($image['width']) && isset($image['height']) 
                                ? htmlspecialchars($image['width'] . ' x ' . $image['height']) 
                                : '未知'; 
                            ?></b>
                        </div>
                        <div class="compare-spec">
                            画质: <b><?php 
                            echo isset($image['quality']) 
                                ? htmlspecialchars($image['quality']) 
                                : '未知'; 
                            ?></b>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 叠加视图容器 -->
        <div class="overlay-container" id="overlayContainer" style="display: none;">
            <img 
                src="<?php echo htmlspecialchars($compareImages[0]['imageUrl']); ?>" 
                alt="比较图片1" 
                class="overlay-image first"
            >
            <img 
                src="<?php echo htmlspecialchars($compareImages[1]['imageUrl']); ?>" 
                alt="比较图片2" 
                class="overlay-image second"
            >
            <div class="overlay-divider" id="overlayDivider">
                <div class="overlay-circle">
                    <i class="fas fa-arrows-alt-h"></i>
                </div>
            </div>
        </div>
        
        <input type="range" min="0" max="100" value="50" class="overlay-slider" id="overlaySlider" style="display: none;">
        
        <div class="compare-actions" style="margin-top: 2rem; justify-content: center;">
            <a href="images.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> 返回图片管理
            </a>
        </div>
    </div>
    
    <script>
        // 视图切换
        const viewToggles = document.querySelectorAll('.view-toggle');
        const compareGrid = document.getElementById('compareGrid');
        const overlayContainer = document.getElementById('overlayContainer');
        const overlaySlider = document.getElementById('overlaySlider');
        
        viewToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                // 更新按钮状态
                viewToggles.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // 获取视图类型
                const viewType = this.getAttribute('data-view');
                
                // 重置所有视图
                document.body.classList.remove('grid-view', 'side-by-side-view', 'overlay-view');
                overlayContainer.style.display = 'none';
                overlaySlider.style.display = 'none';
                compareGrid.style.display = 'grid';
                
                // 应用新视图
                if (viewType === 'grid') {
                    document.body.classList.add('grid-view');
                } else if (viewType === 'side-by-side') {
                    document.body.classList.add('side-by-side-view');
                } else if (viewType === 'overlay') {
                    document.body.classList.add('overlay-view');
                    compareGrid.style.display = 'none';
                    overlayContainer.style.display = 'block';
                    overlaySlider.style.display = 'block';
                    
                    // 设置叠加容器的尺寸匹配第一张图片
                    const firstImg = new Image();
                    firstImg.onload = function() {
                        const aspectRatio = this.width / this.height;
                        overlayContainer.style.height = overlayContainer.offsetWidth / aspectRatio + 'px';
                    };
                    firstImg.src = document.querySelector('.overlay-image.first').src;
                }
            });
        });
        
        // 叠加视图滑块控制
        const divider = document.getElementById('overlayDivider');
        const slider = document.getElementById('overlaySlider');
        
        slider.addEventListener('input', function() {
            const value = this.value;
            document.querySelector('.overlay-image.first').style.clipPath = `inset(0 ${100-value}% 0 0)`;
            divider.style.left = `${value}%`;
        });
        
        // 拖动分隔线
        let isDragging = false;
        
        divider.addEventListener('mousedown', function(e) {
            isDragging = true;
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            
            const container = overlayContainer.getBoundingClientRect();
            const x = e.clientX - container.left;
            const percent = Math.min(Math.max(0, x / container.width * 100), 100);
            
            slider.value = percent;
            document.querySelector('.overlay-image.first').style.clipPath = `inset(0 ${100-percent}% 0 0)`;
            divider.style.left = `${percent}%`;
        });
        
        document.addEventListener('mouseup', function() {
            isDragging = false;
        });
        
        // 暗色模式切换
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('i');
        
        // 检查本地存储中的主题偏好
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        }
        
        // 切换暗色/亮色模式
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'true');
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                localStorage.setItem('darkMode', 'false');
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        });
    </script>
</body>
</html> 