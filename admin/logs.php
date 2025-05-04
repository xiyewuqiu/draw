<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 日志目录
$logDirectory = __DIR__ . '/../api/logs/';
$apiLogFile = $logDirectory . 'api_requests.log';
$errorLogFile = $logDirectory . 'errors.log';

// 日志类型
$logType = isset($_GET['type']) ? $_GET['type'] : 'api';
$validTypes = ['api', 'error', 'system'];

if (!in_array($logType, $validTypes)) {
    $logType = 'api';
}

// 读取日志文件
function readLogFile($filePath, $limit = 200) {
    $logs = [];
    
    if (file_exists($filePath)) {
        $lines = file($filePath);
        $lines = array_reverse($lines); // 最近的日志在前面
        
        // 限制最大行数
        $lines = array_slice($lines, 0, $limit);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $logs[] = $line;
            }
        }
    }
    
    return $logs;
}

// 格式化JSON字符串为易读格式
function formatJson($json) {
    $result = $json;
    
    // 尝试解析JSON字符串
    $decoded = json_decode($json, true);
    
    if ($decoded && json_last_error() === JSON_ERROR_NONE) {
        // 将数组转换为格式化的JSON字符串
        $result = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    return $result;
}

// 清除日志
function clearLog($filePath) {
    if (file_exists($filePath)) {
        file_put_contents($filePath, '');
        return true;
    }
    return false;
}

// 处理清除日志请求
$clearMessage = '';
if (isset($_POST['clear_log'])) {
    $logFile = '';
    
    if ($_POST['clear_log'] === 'api') {
        $logFile = $apiLogFile;
    } elseif ($_POST['clear_log'] === 'error') {
        $logFile = $errorLogFile;
    }
    
    if ($logFile && clearLog($logFile)) {
        $clearMessage = '日志已成功清除！';
    } else {
        $clearMessage = '日志清除失败或日志文件不存在。';
    }
}

// 获取日志数据
$logs = [];

if ($logType === 'api') {
    $logs = readLogFile($apiLogFile);
} elseif ($logType === 'error') {
    $logs = readLogFile($errorLogFile);
} elseif ($logType === 'system') {
    // PHP系统日志可能需要根据实际情况调整
    $phpErrorLog = ini_get('error_log');
    if (file_exists($phpErrorLog)) {
        $logs = readLogFile($phpErrorLog);
    } else {
        // 尝试读取apache/nginx错误日志（这可能需要root权限）
        $logs = ['系统日志不可用或没有访问权限。'];
    }
}

// 解析API日志
$parsedLogs = [];
if ($logType === 'api') {
    foreach ($logs as $log) {
        $parts = explode(' - ', $log, 3);
        if (count($parts) >= 3) {
            $timestamp = trim($parts[0]);
            $method = trim($parts[1]);
            $details = trim($parts[2]);
            
            // 尝试解析JSON部分
            $detailsParts = explode(' - ', $details, 2);
            $endpoint = trim($detailsParts[0]);
            $params = isset($detailsParts[1]) ? trim($detailsParts[1]) : '';
            
            $parsedLogs[] = [
                'timestamp' => $timestamp,
                'method' => $method,
                'endpoint' => $endpoint,
                'params' => $params
            ];
        } else {
            // 格式不匹配，保持原样
            $parsedLogs[] = [
                'timestamp' => '',
                'method' => '',
                'endpoint' => '',
                'params' => $log
            ];
        }
    }
} elseif ($logType === 'error') {
    foreach ($logs as $log) {
        $parts = explode(' - ', $log, 3);
        if (count($parts) >= 3) {
            $timestamp = trim($parts[0]);
            $level = trim($parts[1]);
            $message = trim($parts[2]);
            
            $parsedLogs[] = [
                'timestamp' => $timestamp,
                'level' => $level,
                'message' => $message
            ];
        } else {
            // 格式不匹配，保持原样
            $parsedLogs[] = [
                'timestamp' => '',
                'level' => 'ERROR',
                'message' => $log
            ];
        }
    }
} else {
    // 系统日志通常格式各异，这里简单处理
    foreach ($logs as $log) {
        $parsedLogs[] = [
            'timestamp' => '',
            'level' => '',
            'message' => $log
        ];
    }
}

// 确定要显示的每页日志条数
$logsPerPage = 50;
$totalLogs = count($parsedLogs);
$totalPages = ceil($totalLogs / $logsPerPage);

// 获取当前页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));

// 提取当前页的日志
$currentLogs = array_slice($parsedLogs, ($page - 1) * $logsPerPage, $logsPerPage);

// 构建分页URL
function buildPaginationUrl($newPage) {
    $params = $_GET;
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI画图管理系统 - 系统日志</title>
    <link rel="icon" type="image/png" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .log-nav {
            display: flex;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }
        
        .log-nav-item {
            flex: 1;
            text-align: center;
            padding: 1rem;
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }
        
        .log-nav-item:hover {
            color: var(--primary-color);
        }
        
        .log-nav-item.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: var(--gray-50);
        }
        
        .log-nav-item i {
            margin-right: 0.5rem;
        }
        
        .logs-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .logs-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logs-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
        }
        
        .logs-title i {
            margin-right: 0.75rem;
            color: var(--primary-color);
        }
        
        .logs-actions {
            display: flex;
            gap: 1rem;
        }
        
        .log-table-container {
            overflow-x: auto;
        }
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .log-table th,
        .log-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: top;
        }
        
        .log-table th {
            background-color: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .log-table tbody tr {
            transition: var(--transition);
        }
        
        .log-table tbody tr:hover {
            background-color: var(--gray-50);
        }
        
        .log-table .timestamp {
            white-space: nowrap;
            color: var(--gray-600);
            font-size: 0.9rem;
        }
        
        .log-table .method {
            white-space: nowrap;
            font-weight: 600;
        }
        
        .log-table .method.get {
            color: var(--success-color);
        }
        
        .log-table .method.post {
            color: var(--primary-color);
        }
        
        .log-table .method.put,
        .log-table .method.patch {
            color: var(--warning-color);
        }
        
        .log-table .method.delete {
            color: var(--error-color);
        }
        
        .log-table .endpoint {
            color: var(--dark-color);
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.9rem;
        }
        
        .log-table .params {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            max-width: 400px;
            color: var(--gray-700);
        }
        
        .log-table .level {
            white-space: nowrap;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .log-table .level.error {
            color: var(--error-color);
        }
        
        .log-table .level.warning {
            color: var(--warning-color);
        }
        
        .log-table .level.info {
            color: var(--primary-color);
        }
        
        .log-table .level.debug {
            color: var(--gray-500);
        }
        
        .log-table .message {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.9rem;
            word-break: break-word;
        }
        
        .log-table .message pre {
            margin: 0;
            white-space: pre-wrap;
        }
        
        .log-empty {
            padding: 3rem;
            text-align: center;
            color: var(--gray-500);
        }
        
        .log-empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-300);
        }
        
        .log-empty h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        
        .page-item {
            display: inline-flex;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--white);
            color: var(--gray-700);
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        
        .page-link:hover {
            background: var(--gray-100);
            color: var(--primary-color);
        }
        
        .page-item.active .page-link {
            background: var(--primary-gradient);
            color: var(--white);
        }
        
        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-info {
            margin-top: 1rem;
            text-align: center;
            color: var(--gray-500);
            font-size: 0.9rem;
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
        
        @media (max-width: 768px) {
            .log-nav {
                flex-direction: column;
            }
            
            .log-nav-item {
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
            }
            
            .log-nav-item:last-child {
                border-bottom: none;
            }
            
            .log-table .endpoint,
            .log-table .params,
            .log-table .message {
                max-width: 200px;
            }
        }
        
        /* 可展开的参数或消息部分 */
        .expandable {
            cursor: pointer;
            position: relative;
        }
        
        .expandable::after {
            content: '\f0fe';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 0;
            right: 0;
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        
        .expandable.expanded::after {
            content: '\f146';
        }
        
        .expandable.expanded {
            max-height: none !important;
            white-space: pre-wrap;
        }
        
        .expandable:not(.expanded) {
            max-height: 120px;
            overflow: hidden;
            display: block;
            text-overflow: ellipsis;
        }
        
        .expandable:not(.expanded)::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1));
            pointer-events: none;
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
                        <a href="settings.php" class="menu-link">
                            <i class="fas fa-cog menu-icon"></i>
                            <span class="menu-text">系统设置</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="logs.php" class="menu-link active">
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
                    <h1 class="page-title">系统日志</h1>
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
                    <div class="breadcrumb-item active">系统日志</div>
                </div>
                
                <?php if ($clearMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $clearMessage; ?>
                </div>
                <?php endif; ?>
                
                <!-- 日志类型导航 -->
                <div class="log-nav">
                    <a href="?type=api" class="log-nav-item <?php echo $logType === 'api' ? 'active' : ''; ?>">
                        <i class="fas fa-exchange-alt"></i> API请求日志
                    </a>
                    <a href="?type=error" class="log-nav-item <?php echo $logType === 'error' ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle"></i> 错误日志
                    </a>
                    <a href="?type=system" class="log-nav-item <?php echo $logType === 'system' ? 'active' : ''; ?>">
                        <i class="fas fa-server"></i> 系统日志
                    </a>
                </div>
                
                <!-- 日志内容 -->
                <div class="logs-card">
                    <div class="logs-header">
                        <h2 class="logs-title">
                            <?php if ($logType === 'api'): ?>
                            <i class="fas fa-exchange-alt"></i> API请求日志
                            <?php elseif ($logType === 'error'): ?>
                            <i class="fas fa-exclamation-triangle"></i> 错误日志
                            <?php else: ?>
                            <i class="fas fa-server"></i> 系统日志
                            <?php endif; ?>
                        </h2>
                        
                        <div class="logs-actions">
                            <?php if ($logType === 'api' || $logType === 'error'): ?>
                            <form action="" method="POST" onsubmit="return confirm('确定要清除所有<?php echo $logType === 'api' ? 'API请求' : '错误'; ?>日志吗？此操作不可撤销。')">
                                <input type="hidden" name="clear_log" value="<?php echo $logType; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash-alt"></i> 清除日志
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <a href="?type=<?php echo $logType; ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-sync"></i> 刷新
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($currentLogs)): ?>
                    <div class="log-empty">
                        <i class="fas fa-file-alt"></i>
                        <h3>没有日志记录</h3>
                        <p>当前没有<?php echo $logType === 'api' ? 'API请求' : ($logType === 'error' ? '错误' : '系统'); ?>日志记录。</p>
                    </div>
                    <?php else: ?>
                    <div class="log-table-container">
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <?php if ($logType === 'api'): ?>
                                    <th width="15%">时间</th>
                                    <th width="10%">方法</th>
                                    <th width="30%">端点</th>
                                    <th width="45%">参数</th>
                                    <?php else: ?>
                                    <th width="15%">时间</th>
                                    <th width="10%">级别</th>
                                    <th width="75%">消息</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentLogs as $log): ?>
                                <?php if ($logType === 'api'): ?>
                                <tr>
                                    <td class="timestamp"><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                    <td class="method <?php echo strtolower($log['method']); ?>"><?php echo htmlspecialchars($log['method']); ?></td>
                                    <td class="endpoint"><?php echo htmlspecialchars($log['endpoint']); ?></td>
                                    <td>
                                        <?php if (!empty($log['params'])): ?>
                                        <pre class="params expandable"><?php echo htmlspecialchars(formatJson($log['params'])); ?></pre>
                                        <?php else: ?>
                                        <span class="text-muted">无参数</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <td class="timestamp"><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                    <td class="level <?php echo strtolower($log['level']); ?>"><?php echo htmlspecialchars($log['level']); ?></td>
                                    <td>
                                        <pre class="message expandable"><?php echo htmlspecialchars($log['message']); ?></pre>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page > 1 ? buildPaginationUrl($page - 1) : '#'; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    if ($start > 1): ?>
                    <div class="page-item">
                        <a class="page-link" href="<?php echo buildPaginationUrl(1); ?>">1</a>
                    </div>
                    <?php if ($start > 2): ?>
                    <div class="page-item disabled">
                        <span class="page-link">...</span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <div class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPaginationUrl($i); ?>"><?php echo $i; ?></a>
                    </div>
                    <?php endfor; ?>
                    
                    <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                    <div class="page-item disabled">
                        <span class="page-link">...</span>
                    </div>
                    <?php endif; ?>
                    <div class="page-item">
                        <a class="page-link" href="<?php echo buildPaginationUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page < $totalPages ? buildPaginationUrl($page + 1) : '#'; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="page-info">
                    显示 <?php echo ($page - 1) * $logsPerPage + 1; ?> - <?php echo min($page * $logsPerPage, $totalLogs); ?> 共 <?php echo $totalLogs; ?> 条日志
                </div>
                <?php endif; ?>
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
        
        // 可展开的日志内容
        document.querySelectorAll('.expandable').forEach(elem => {
            elem.addEventListener('click', function() {
                this.classList.toggle('expanded');
            });
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