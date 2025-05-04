<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 获取历史数据统计
$historyFile = __DIR__ . '/../api/data/history.json';
$totalImages = 0;
$todayImages = 0;
$modelStats = [];
$recentImages = [];

if (file_exists($historyFile)) {
    $history = json_decode(file_get_contents($historyFile), true);
    
    if (is_array($history)) {
        $totalImages = count($history);
        $today = strtotime(date('Y-m-d')) * 1000; // 今天零点的毫秒时间戳
        
        // 处理统计数据
        foreach ($history as $index => $item) {
            // 计算今日图片
            if ($item['timestamp'] >= $today) {
                $todayImages++;
            }
            
            // 统计模型使用情况
            if (isset($item['model'])) {
                if (!isset($modelStats[$item['model']])) {
                    $modelStats[$item['model']] = 0;
                }
                $modelStats[$item['model']]++;
            }
            
            // 获取最近10张图片
            if ($index < 10) {
                $recentImages[] = $item;
            }
        }
    }
}

// 获取各模型图片分布
arsort($modelStats); // 按数量从高到低排序
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI画图管理系统 - 控制面板</title>
    <link rel="icon" type="image/png" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="index.php" class="menu-link active">
                            <i class="fas fa-tachometer-alt menu-icon"></i>
                            <span class="menu-text">控制面板</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="images.php" class="menu-link">
                            <i class="fas fa-images menu-icon"></i>
                            <span class="menu-text">图片管理</span>
                            <span class="menu-badge"><?php echo $totalImages; ?></span>
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
                    <h1 class="page-title">控制面板</h1>
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
                    <div class="breadcrumb-item active">控制面板</div>
                </div>
                
                <!-- 统计卡片 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-images"></i>
                        </div>
                        <div class="stat-number"><?php echo $totalImages; ?></div>
                        <div class="stat-title">生成总图片数</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> 较上月增长 12%
                        </div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-number"><?php echo $todayImages; ?></div>
                        <div class="stat-title">今日生成图片</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> 较昨日增长 5%
                        </div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon warning">
                            <i class="fas fa-brain"></i>
                        </div>
                        <div class="stat-number"><?php echo count($modelStats); ?></div>
                        <div class="stat-title">AI模型总数</div>
                        <div class="stat-change">
                            <i class="fas fa-minus"></i> 持平
                        </div>
                    </div>
                    
                    <div class="stat-card danger">
                        <div class="stat-icon danger">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number">98.2%</div>
                        <div class="stat-title">系统稳定性</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> 较上周提升 0.8%
                        </div>
                    </div>
                </div>
                
                <!-- 图表和统计 -->
                <div class="row">
                    <!-- 模型使用分布 -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-chart-pie"></i> AI模型使用分布
                            </div>
                            <div class="card-tools">
                                <button class="card-tool"><i class="fas fa-redo"></i></button>
                                <button class="card-tool"><i class="fas fa-ellipsis-v"></i></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="modelDistributionChart" height="300"></canvas>
                        </div>
                    </div>
                    
                    <!-- 最近生成的图片 -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-history"></i> 最近生成的图片
                            </div>
                            <div class="card-tools">
                                <a href="images.php" class="btn btn-sm btn-primary">查看全部</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="image-grid">
                                <?php foreach ($recentImages as $image): ?>
                                <div class="image-card">
                                    <div class="image-wrapper">
                                        <img src="<?php echo htmlspecialchars($image['imageUrl']); ?>" alt="AI生成图片">
                                        <div class="image-overlay">
                                            <div class="image-actions">
                                                <a href="<?php echo htmlspecialchars($image['imageUrl']); ?>" target="_blank" class="image-action">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="#" class="image-action" data-id="<?php echo htmlspecialchars($image['id']); ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="image-info">
                                        <div class="image-model"><?php echo htmlspecialchars($image['model']); ?></div>
                                        <div class="image-title"><?php echo htmlspecialchars($image['prompt']); ?></div>
                                        <div class="image-date">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('Y-m-d H:i', $image['timestamp'] / 1000); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
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
        
        // 模型分布图表
        const modelDistributionCtx = document.getElementById('modelDistributionChart').getContext('2d');
        const modelDistributionChart = new Chart(modelDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    foreach (array_keys($modelStats) as $model) {
                        echo "'" . htmlspecialchars($model) . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        foreach ($modelStats as $count) {
                            echo $count . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#7c5dfa',
                        '#ff8a00',
                        '#38bec9',
                        '#f5b70a',
                        '#ef4565',
                        '#6548e5',
                        '#ff6b8b',
                        '#5eead4'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                family: "'PingFang SC', 'Microsoft YaHei', 'Inter', sans-serif",
                                size: 13
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, curr) => acc + curr, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // 删除图片功能
        document.querySelectorAll('.image-action[data-id]').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                
                if (confirm('确定要删除这张图片吗？此操作不可恢复。')) {
                    try {
                        const response = await fetch('../api/delete_history.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ id })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // 从页面中移除图片卡片
                            this.closest('.image-card').remove();
                            alert('图片删除成功！');
                        } else {
                            alert('删除失败：' + (result.error || '未知错误'));
                        }
                    } catch (error) {
                        alert('请求错误：' + error.message);
                    }
                }
            });
        });
    </script>
</body>
</html> 