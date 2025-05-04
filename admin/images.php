<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 获取历史记录数据
$historyFile = __DIR__ . '/../api/data/history.json';
$images = [];
$totalImages = 0;

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 24; // 每页显示条数可选
$totalPages = 1;

// 默认排序
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'timestamp';
$sortDirection = isset($_GET['direction']) ? $_GET['direction'] : 'desc';

// 筛选参数
$filterModel = isset($_GET['model']) ? $_GET['model'] : '';
$filterDateStart = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filterDateEnd = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$searchPrompt = isset($_GET['prompt']) ? $_GET['prompt'] : '';
$filterQuality = isset($_GET['quality']) ? $_GET['quality'] : '';
$filterAspectRatio = isset($_GET['aspect_ratio']) ? $_GET['aspect_ratio'] : '';
$filterFavorite = isset($_GET['favorite']) ? $_GET['favorite'] : '';

// 可用模型列表
$availableModels = [];
// 可用画质选项
$qualityOptions = ['高清', '标准', '草图'];
// 可用宽高比选项
$aspectRatioOptions = ['1:1', '4:3', '3:4', '16:9', '9:16'];

// 处理批量操作
$batchMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action']) && isset($_POST['selected_images'])) {
    $selectedImages = $_POST['selected_images'];
    $batchAction = $_POST['batch_action'];
    
    if (!empty($selectedImages)) {
        if ($batchAction === 'delete') {
            // 批量删除图片
            $deleteCount = 0;
            $allImages = [];
            
            if (file_exists($historyFile)) {
                $allImages = json_decode(file_get_contents($historyFile), true);
                
                if (is_array($allImages)) {
                    $newImages = [];
                    
                    foreach ($allImages as $image) {
                        if (!in_array($image['id'], $selectedImages)) {
                            $newImages[] = $image;
                        } else {
                            $deleteCount++;
                        }
                    }
                    
                    // 保存更新后的历史记录
                    file_put_contents($historyFile, json_encode($newImages, JSON_PRETTY_PRINT));
                    $batchMessage = "成功删除 {$deleteCount} 张图片！";
                }
            }
        } else if ($batchAction === 'favorite' || $batchAction === 'unfavorite') {
            // 批量添加/移除收藏
            $updateCount = 0;
            $allImages = [];
            
            if (file_exists($historyFile)) {
                $allImages = json_decode(file_get_contents($historyFile), true);
                
                if (is_array($allImages)) {
                    foreach ($allImages as &$image) {
                        if (in_array($image['id'], $selectedImages)) {
                            $image['favorite'] = ($batchAction === 'favorite') ? true : false;
                            $updateCount++;
                        }
                    }
                    
                    // 保存更新后的历史记录
                    file_put_contents($historyFile, json_encode($allImages, JSON_PRETTY_PRINT));
                    $action = ($batchAction === 'favorite') ? '添加到收藏' : '移除收藏';
                    $batchMessage = "成功{$action} {$updateCount} 张图片！";
                }
            }
        } else if ($batchAction === 'tag' && isset($_POST['tag_name']) && !empty($_POST['tag_name'])) {
            // 批量添加标签
            $tagName = trim($_POST['tag_name']);
            $updateCount = 0;
            $allImages = [];
            
            if (file_exists($historyFile)) {
                $allImages = json_decode(file_get_contents($historyFile), true);
                
                if (is_array($allImages)) {
                    foreach ($allImages as &$image) {
                        if (in_array($image['id'], $selectedImages)) {
                            if (!isset($image['tags'])) {
                                $image['tags'] = [];
                            }
                            
                            if (!in_array($tagName, $image['tags'])) {
                                $image['tags'][] = $tagName;
                                $updateCount++;
                            }
                        }
                    }
                    
                    // 保存更新后的历史记录
                    file_put_contents($historyFile, json_encode($allImages, JSON_PRETTY_PRINT));
                    $batchMessage = "成功为 {$updateCount} 张图片添加标签 '{$tagName}'！";
                }
            }
        }
    } else {
        $batchMessage = "请至少选择一张图片进行操作！";
    }
}

// 获取所有标签
$allTags = [];
if (file_exists($historyFile)) {
    $allImages = json_decode(file_get_contents($historyFile), true);
    
    if (is_array($allImages)) {
        foreach ($allImages as $image) {
            if (isset($image['tags']) && is_array($image['tags'])) {
                foreach ($image['tags'] as $tag) {
                    if (!in_array($tag, $allTags)) {
                        $allTags[] = $tag;
                    }
                }
            }
        }
    }
}
sort($allTags);

// 标签筛选
$filterTag = isset($_GET['tag']) ? $_GET['tag'] : '';

if (file_exists($historyFile)) {
    $allImages = json_decode(file_get_contents($historyFile), true);
    
    if (is_array($allImages)) {
        // 筛选图片
        $filteredImages = [];
        foreach ($allImages as $image) {
            $include = true;
            
            // 收集可用模型列表
            if (isset($image['model']) && !in_array($image['model'], $availableModels)) {
                $availableModels[] = $image['model'];
            }
            
            // 按模型筛选
            if ($filterModel && (!isset($image['model']) || $image['model'] !== $filterModel)) {
                $include = false;
            }
            
            // 按提示词筛选
            if ($searchPrompt && (!isset($image['prompt']) || stripos($image['prompt'], $searchPrompt) === false)) {
                $include = false;
            }
            
            // 按日期范围筛选
            if ($filterDateStart || $filterDateEnd) {
                $imageDate = date('Y-m-d', $image['timestamp'] / 1000);
                
                if ($filterDateStart && $imageDate < $filterDateStart) {
                    $include = false;
                }
                
                if ($filterDateEnd && $imageDate > $filterDateEnd) {
                    $include = false;
                }
            }
            
            // 按画质筛选
            if ($filterQuality && (!isset($image['quality']) || $image['quality'] !== $filterQuality)) {
                $include = false;
            }
            
            // 按宽高比筛选
            if ($filterAspectRatio && (!isset($image['aspectRatio']) || $image['aspectRatio'] !== $filterAspectRatio)) {
                // 如果没有明确的宽高比，可以根据图像尺寸计算
                if (isset($image['width']) && isset($image['height'])) {
                    $ratio = calculateAspectRatio($image['width'], $image['height']);
                    if ($ratio !== $filterAspectRatio) {
                        $include = false;
                    }
                } else {
                    $include = false;
                }
            }
            
            // 按收藏状态筛选
            if ($filterFavorite && (!isset($image['favorite']) || $image['favorite'] !== true)) {
                $include = false;
            }
            
            // 按标签筛选
            if ($filterTag && (!isset($image['tags']) || !in_array($filterTag, $image['tags']))) {
                $include = false;
            }
            
            if ($include) {
                $filteredImages[] = $image;
            }
        }
        
        // 排序
        usort($filteredImages, function($a, $b) use ($sortField, $sortDirection) {
            if ($sortField === 'timestamp') {
                $aValue = $a['timestamp'] ?? 0;
                $bValue = $b['timestamp'] ?? 0;
            } else if ($sortField === 'model') {
                $aValue = $a['model'] ?? '';
                $bValue = $b['model'] ?? '';
            } else if ($sortField === 'prompt') {
                $aValue = $a['prompt'] ?? '';
                $bValue = $b['prompt'] ?? '';
            } else {
                $aValue = $a[$sortField] ?? '';
                $bValue = $b[$sortField] ?? '';
            }
            
            if ($sortDirection === 'asc') {
                return $aValue <=> $bValue;
            } else {
                return $bValue <=> $aValue;
            }
        });
        
        // 计算分页
        $totalImages = count($filteredImages);
        $totalPages = ceil($totalImages / $perPage);
        
        // 确保页码在有效范围内
        $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
        
        // 获取当前页的数据
        $startIndex = ($page - 1) * $perPage;
        $images = array_slice($filteredImages, $startIndex, $perPage);
    }
}

// 计算宽高比
function calculateAspectRatio($width, $height) {
    // 简化常见比例
    if ($width === $height) return '1:1';
    if (abs($width / $height - 4/3) < 0.1) return '4:3';
    if (abs($width / $height - 3/4) < 0.1) return '3:4';
    if (abs($width / $height - 16/9) < 0.1) return '16:9';
    if (abs($width / $height - 9/16) < 0.1) return '9:16';
    
    // 如果不是常见比例，返回计算的比例
    $gcd = gcd($width, $height);
    return ($width / $gcd) . ':' . ($height / $gcd);
}

// 最大公约数
function gcd($a, $b) {
    while ($b != 0) {
        $t = $b;
        $b = $a % $b;
        $a = $t;
    }
    return $a;
}

// 构建分页URL
function buildPaginationUrl($newPage) {
    $params = $_GET;
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}

// 构建筛选URL
function buildFilterUrl($params = []) {
    return '?' . http_build_query(array_merge($_GET, $params));
}

// 构建排序URL
function buildSortUrl($field) {
    $params = $_GET;
    
    if (isset($params['sort']) && $params['sort'] === $field) {
        // 切换排序方向
        $params['direction'] = ($params['direction'] === 'asc') ? 'desc' : 'asc';
    } else {
        $params['sort'] = $field;
        $params['direction'] = 'desc'; // 默认降序
    }
    
    return '?' . http_build_query($params);
}

// 批量下载功能 - 创建ZIP文件
function createBatchDownloadZip($selectedImages, $allImages) {
    $zipFile = tempnam(sys_get_temp_dir(), 'img_batch_');
    $zip = new ZipArchive();
    
    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        return false;
    }
    
    foreach ($allImages as $image) {
        if (in_array($image['id'], $selectedImages)) {
            $imageUrl = $image['imageUrl'];
            $imageName = basename($imageUrl);
            
            // 下载图片并添加到ZIP
            $imageContent = @file_get_contents($imageUrl);
            if ($imageContent !== false) {
                $zip->addFromString($imageName, $imageContent);
            }
        }
    }
    
    $zip->close();
    return $zipFile;
}

// 检查是否是AJAX请求
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// 处理AJAX批量下载请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action']) && $_POST['batch_action'] === 'download' && isAjaxRequest()) {
    $selectedImages = isset($_POST['selected_images']) ? $_POST['selected_images'] : [];
    
    if (empty($selectedImages)) {
        echo json_encode(['success' => false, 'message' => '请至少选择一张图片']);
        exit;
    }
    
    if (file_exists($historyFile)) {
        $allImages = json_decode(file_get_contents($historyFile), true);
        
        if (is_array($allImages)) {
            $zipFile = createBatchDownloadZip($selectedImages, $allImages);
            
            if ($zipFile) {
                $downloadToken = md5(basename($zipFile));
                $_SESSION['download_token'] = [
                    'file' => $zipFile,
                    'expires' => time() + 300 // 5分钟过期
                ];
                
                echo json_encode([
                    'success' => true, 
                    'download_url' => 'download.php?token=' . $downloadToken
                ]);
                exit;
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => '创建下载文件失败']);
    exit;
}

// 处理收藏状态切换
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_favorite' && isAjaxRequest()) {
    $imageId = isset($_POST['image_id']) ? $_POST['image_id'] : '';
    
    if (empty($imageId)) {
        echo json_encode(['success' => false, 'message' => '图片ID不能为空']);
        exit;
    }
    
    if (file_exists($historyFile)) {
        $allImages = json_decode(file_get_contents($historyFile), true);
        
        if (is_array($allImages)) {
            $found = false;
            
            foreach ($allImages as &$image) {
                if ($image['id'] === $imageId) {
                    $image['favorite'] = isset($image['favorite']) ? !$image['favorite'] : true;
                    $found = true;
                    $isFavorite = $image['favorite'];
                    break;
                }
            }
            
            if ($found) {
                file_put_contents($historyFile, json_encode($allImages, JSON_PRETTY_PRINT));
                echo json_encode([
                    'success' => true, 
                    'is_favorite' => $isFavorite
                ]);
                exit;
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => '图片不存在或更新失败']);
    exit;
}

// 处理历史记录更新请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_history' && isAjaxRequest()) {
    $history = isset($_POST['history']) ? $_POST['history'] : [];
    
    if (empty($history)) {
        echo json_encode(['success' => false, 'message' => '提供的历史记录数据无效']);
        exit;
    }
    
    if (file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '保存历史记录失败']);
    }
    exit;
}

// 处理导出数据请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_data' && isAjaxRequest()) {
    $selectedImages = isset($_POST['selected_images']) ? $_POST['selected_images'] : [];
    
    if (empty($selectedImages)) {
        echo json_encode(['success' => false, 'message' => '请至少选择一张图片']);
        exit;
    }
    
    if (file_exists($historyFile)) {
        $allImages = json_decode(file_get_contents($historyFile), true);
        
        if (is_array($allImages)) {
            $exportData = [];
            
            foreach ($allImages as $image) {
                if (in_array($image['id'], $selectedImages)) {
                    $exportData[] = $image;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $exportData
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => '导出数据失败']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI画图管理系统 - 图片管理</title>
    <link rel="icon" type="image/png" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.2.0/svgs/solid/paintbrush.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* 主题变量 - 包含亮色和暗色主题 */
        :root {
            /* 亮色主题 */
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
            
            /* 图片卡片尺寸 */
            --card-min-width: 260px;
            --card-small-min-width: 200px;
            --card-large-min-width: 300px;
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
        
        /* 图片管理页面特定样式 */
        .filter-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
        }
        
        .filter-title i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .filter-toggle {
            background: none;
            border: none;
            color: var(--gray-600);
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .filter-toggle:hover {
            color: var(--primary-color);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .filter-actions {
            display: flex;
            align-items: flex-end;
            gap: 0.75rem;
            grid-column: 1 / -1;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.65rem 0.75rem;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }
        
        .empty-icon {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .empty-text {
            color: var(--gray-500);
            margin-bottom: 1.5rem;
        }
        
        .gallery-tools {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .view-options {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-option {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--white);
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        
        .view-option:hover, .view-option.active {
            background: var(--primary-color);
            color: var(--white);
        }
        
        /* 图片网格视图和列表视图 */
        .view-grid .image-grid {
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        }
        
        .view-list .image-grid {
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
        
        .view-masonry .image-grid {
            column-count: 4;
            column-gap: 1.5rem;
        }
        
        @media (max-width: 1200px) {
            .view-masonry .image-grid {
                column-count: 3;
            }
        }
        
        @media (max-width: 768px) {
            .view-masonry .image-grid {
                column-count: 2;
            }
        }
        
        @media (max-width: 500px) {
            .view-masonry .image-grid {
                column-count: 1;
            }
        }
        
        .view-masonry .image-card {
            break-inside: avoid;
            margin-bottom: 1.5rem;
        }
        
        /* 图片详情模态框 */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 2rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            max-width: 90%;
            max-height: 90%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            width: 900px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalIn 0.4s ease-out forwards;
        }
        
        @keyframes modalIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .modal-close:hover {
            color: var(--error-color);
        }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            gap: 2rem;
        }
        
        .modal-image {
            flex: 1;
        }
        
        .modal-image img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .modal-details {
            flex: 1;
        }
        
        .detail-item {
            margin-bottom: 1.5rem;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            font-size: 1rem;
            color: var(--gray-800);
        }
        
        .prompt-text {
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }
        
        .modal-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        /* 新增样式 - 批量操作和增强功能 */
        .batch-actions {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .batch-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .batch-controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .select-all-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .select-all-checkbox {
            width: 1.1rem;
            height: 1.1rem;
            accent-color: var(--primary-color);
        }
        
        .batch-dropdown {
            position: relative;
        }
        
        .batch-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            min-width: 200px;
            z-index: 100;
            margin-top: 0.5rem;
            display: none;
            overflow: hidden;
        }
        
        .batch-menu.active {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }
        
        .batch-menu-item {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-700);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .batch-menu-item:hover {
            background: var(--gray-100);
            color: var(--primary-color);
        }
        
        .batch-menu-item i {
            width: 1.2rem;
            text-align: center;
        }
        
        .badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .tag-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            background: rgba(124, 93, 250, 0.1);
            color: var(--primary-color);
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            gap: 0.5rem;
        }
        
        .tag-badge i {
            font-size: 0.7rem;
            cursor: pointer;
        }
        
        .tag-badge:hover {
            background: rgba(124, 93, 250, 0.2);
        }
        
        .add-tag-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .tag-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
        }
        
        .filter-advanced {
            display: none;
            border-top: 1px solid var(--gray-200);
            padding-top: 1rem;
            margin-top: 1rem;
        }
        
        .filter-advanced.active {
            display: grid;
        }
        
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .sort-label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .sort-option {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            background: var(--gray-100);
            border-radius: 6px;
            font-size: 0.9rem;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .sort-option:hover {
            background: var(--gray-200);
        }
        
        .sort-option.active {
            background: var(--primary-color);
            color: var(--white);
        }
        
        .sort-option i {
            margin-left: 0.5rem;
        }
        
        .pagination-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .per-page-control {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .per-page-select {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--gray-300);
            background: var(--white);
        }
        
        .image-card-actions {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 10;
            display: flex;
            gap: 0.5rem;
        }
        
        .image-select {
            width: 1.25rem;
            height: 1.25rem;
            accent-color: var(--primary-color);
            cursor: pointer;
        }
        
        .favorite-action {
            cursor: pointer;
            color: var(--gray-200);
            background: rgba(0, 0, 0, 0.3);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .favorite-action:hover {
            color: #ff9500;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .favorite-action.active {
            color: #ff9500;
        }
        
        .image-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.5rem;
        }
        
        .image-tag {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            background: rgba(124, 93, 250, 0.08);
            color: var(--primary-color);
            border-radius: 4px;
        }
        
        .tag-dropdown {
            position: relative;
        }
        
        .tag-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            min-width: 200px;
            z-index: 100;
            margin-top: 0.5rem;
            display: none;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .tag-menu.active {
            display: block;
        }
        
        .tag-menu-item {
            padding: 0.6rem 1rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .tag-menu-item:hover {
            background: var(--gray-100);
        }
        
        .tag-menu-item.selected {
            background: rgba(124, 93, 250, 0.1);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .tag-search {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .tag-search-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .empty-tags {
            padding: 1rem;
            text-align: center;
            color: var(--gray-500);
        }
        
        .batch-selection-info {
            background: var(--primary-gradient);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            display: none;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            animation: fadeIn 0.3s ease-out;
        }
        
        .batch-selection-info.active {
            display: flex;
        }
        
        .selection-count {
            font-weight: 600;
        }
        
        .cancel-selection {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .cancel-selection:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .success-message {
            background: rgba(56, 190, 201, 0.1);
            color: var(--success-color);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .modal-body {
                flex-direction: column;
            }
            
            .gallery-tools {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .batch-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sort-controls {
                flex-wrap: wrap;
            }
            
            .pagination-controls {
                flex-direction: column;
                gap: 1rem;
            }
        }
        
        /* 新增暗色模式切换按钮 */
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-200);
            color: var(--gray-600);
            margin-right: 15px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .theme-toggle:hover {
            background: var(--gray-300);
        }
        
        .dark-mode .theme-toggle {
            color: var(--warning-color);
        }
        
        /* 图片卡片增强样式 */
        .image-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .dark-mode .image-card:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        .image-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 12px 12px 0 0;
            aspect-ratio: 1 / 1;
        }
        
        .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .image-card:hover img {
            transform: scale(1.05);
        }
        
        /* 标签系统改进样式 */
        .tag-badge {
            transition: all 0.2s ease;
        }
        
        .tag-badge:hover {
            transform: translateY(-2px);
        }
        
        /* 卡片尺寸调整控件 */
        .size-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .size-option {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: var(--white);
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.8rem;
        }
        
        .size-option:hover, .size-option.active {
            background: var(--primary-color);
            color: var(--white);
        }
        
        /* 图片缩放比例视图 */
        .view-small .image-grid {
            grid-template-columns: repeat(auto-fill, minmax(var(--card-small-min-width), 1fr));
        }
        
        body:not(.view-small):not(.view-large) .image-grid {
            grid-template-columns: repeat(auto-fill, minmax(var(--card-min-width), 1fr));
        }
        
        .view-large .image-grid {
            grid-template-columns: repeat(auto-fill, minmax(var(--card-large-min-width), 1fr));
        }
        
        /* 快速预览悬停效果 */
        .quick-view-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 5;
        }
        
        .quick-view-btn {
            background: var(--white);
            color: var(--gray-800);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            transform: translateY(20px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .image-card:hover .quick-view-overlay {
            opacity: 1;
        }
        
        .image-card:hover .quick-view-btn {
            transform: translateY(0);
        }
        
        /* 增强的批量操作菜单 */
        .batch-menu-item {
            transition: all 0.2s ease;
        }
        
        .batch-menu-item:hover i {
            transform: translateX(3px);
            transition: transform 0.2s ease;
        }
        
        /* 图片加载过渡效果 */
        .image-loading {
            position: relative;
            background: linear-gradient(90deg, var(--gray-100) 0%, var(--gray-200) 50%, var(--gray-100) 100%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* 拖拽排序提示 */
        .drag-handle {
            cursor: grab;
            color: var(--gray-400);
            margin-right: 8px;
            opacity: 0.5;
            transition: opacity 0.2s ease;
        }
        
        .image-card:hover .drag-handle {
            opacity: 1;
        }
        
        .image-card.dragging {
            opacity: 0.8;
            transform: scale(0.95);
        }
        
        /* 图片比较功能样式 */
        .comparison-mode .image-card {
            position: relative;
        }
        
        .comparison-checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            z-index: 10;
        }
        
        .comparison-active {
            box-shadow: 0 0 0 3px var(--primary-color);
            position: relative;
        }
        
        .comparison-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--white);
            padding: 1rem;
            box-shadow: 0 -5px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .comparison-bar.active {
            transform: translateY(0);
        }
        
        .comparison-preview {
            display: flex;
            gap: 1rem;
        }
        
        .comparison-item {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }
        
        .comparison-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .comparison-item .remove-item {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: var(--error-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            cursor: pointer;
        }
        
        /* 进度指示器 */
        .progress-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            z-index: 1000;
            background: transparent;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--primary-gradient);
            width: 0;
            transition: width 0.3s ease;
        }
        
        /* 全屏预览模式 */
        .fullscreen-preview {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1010;
            display: flex;
            flex-direction: column;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .fullscreen-preview.active {
            opacity: 1;
            visibility: visible;
        }
        
        .fullscreen-header {
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .fullscreen-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .fullscreen-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .fullscreen-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }
        
        .fullscreen-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 2rem;
            transform: translateY(-50%);
        }
        
        .fullscreen-nav-btn {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }
        
        .fullscreen-nav-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* 新增动态效果 */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .animate-slide-up {
            animation: slideInUp 0.5s ease forwards;
        }
        
        /* 响应式调整 */
        @media (max-width: 1024px) {
            .view-masonry .image-grid {
                column-count: 3;
            }
            
            .modal-content {
                max-width: 95%;
            }
        }
        
        @media (max-width: 768px) {
            .view-masonry .image-grid {
                column-count: 2;
            }
            
            .modal-body {
                flex-direction: column;
            }
            
            .gallery-tools {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .batch-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sort-controls {
                flex-wrap: wrap;
            }
            
            .pagination-controls {
                flex-direction: column;
                gap: 1rem;
            }
            
            .view-small .image-grid,
            body:not(.view-small):not(.view-large) .image-grid,
            .view-large .image-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .view-masonry .image-grid {
                column-count: 1;
            }
            
            .view-small .image-grid,
            body:not(.view-small):not(.view-large) .image-grid,
            .view-large .image-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="view-grid">
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
                        <a href="images.php" class="menu-link active">
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
                    <h1 class="page-title">图片管理</h1>
                </div>
                
                <div class="header-right">
                    <div class="theme-toggle" id="themeToggle">
                        <i class="fas fa-moon"></i>
                    </div>
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
                    <div class="breadcrumb-item active">图片管理</div>
                </div>
                
                <?php if ($batchMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $batchMessage; ?>
                </div>
                <?php endif; ?>
                
                <!-- 批量选择信息 -->
                <div class="batch-selection-info" id="selectionInfo">
                    <div class="selection-count">已选择 <span id="selectedCount">0</span> 张图片</div>
                    <button class="cancel-selection" id="cancelSelection">取消选择</button>
                </div>
                
                <!-- 批量操作栏 -->
                <div class="batch-actions">
                    <div class="batch-title">
                        <i class="fas fa-layer-group"></i> 批量操作
                    </div>
                    <div class="batch-controls">
                        <label class="select-all-label">
                            <input type="checkbox" class="select-all-checkbox" id="selectAll">
                            <span>全选</span>
                        </label>
                        
                        <div class="batch-dropdown">
                            <button class="btn btn-primary" id="batchMenuToggle">
                                <i class="fas fa-cog"></i> 批量操作
                            </button>
                            
                            <div class="batch-menu" id="batchMenu">
                                <div class="batch-menu-item" data-action="download">
                                    <i class="fas fa-download"></i> 下载选中图片
                                </div>
                                <div class="batch-menu-item" data-action="favorite">
                                    <i class="fas fa-star"></i> 添加到收藏
                                </div>
                                <div class="batch-menu-item" data-action="unfavorite">
                                    <i class="far fa-star"></i> 取消收藏
                                </div>
                                <div class="batch-menu-item" data-action="tag-add">
                                    <i class="fas fa-tag"></i> 添加标签
                                </div>
                                <div class="batch-menu-item" data-action="compare">
                                    <i class="fas fa-exchange-alt"></i> 图片比较
                                </div>
                                <div class="batch-menu-item" data-action="copy-prompts">
                                    <i class="fas fa-clipboard"></i> 复制提示词
                                </div>
                                <div class="batch-menu-item" data-action="export-data">
                                    <i class="fas fa-file-export"></i> 导出图片数据
                                </div>
                                <div class="batch-menu-item" data-action="delete">
                                    <i class="fas fa-trash-alt"></i> 删除图片
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 图片筛选 -->
                <div class="filter-container">
                    <div class="filter-header">
                        <h3 class="filter-title"><i class="fas fa-filter"></i> 筛选图片</h3>
                        <button class="filter-toggle" id="toggleAdvanced">
                            高级筛选 <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    
                    <form action="" method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="model">AI模型</label>
                            <select id="model" name="model" class="form-control">
                                <option value="">全部模型</option>
                                <?php foreach($availableModels as $model): ?>
                                <option value="<?php echo htmlspecialchars($model); ?>" <?php echo $filterModel === $model ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_start">开始日期</label>
                            <input type="date" id="date_start" name="date_start" class="form-control" value="<?php echo $filterDateStart; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_end">结束日期</label>
                            <input type="date" id="date_end" name="date_end" class="form-control" value="<?php echo $filterDateEnd; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="prompt">提示词搜索</label>
                            <input type="text" id="prompt" name="prompt" class="form-control" placeholder="输入关键词搜索" value="<?php echo htmlspecialchars($searchPrompt); ?>">
                        </div>
                        
                        <!-- 高级筛选选项 -->
                        <div class="filter-advanced" id="advancedFilter">
                            <div class="form-group">
                                <label for="quality">图片质量</label>
                                <select id="quality" name="quality" class="form-control">
                                    <option value="">全部质量</option>
                                    <?php foreach($qualityOptions as $quality): ?>
                                    <option value="<?php echo htmlspecialchars($quality); ?>" <?php echo $filterQuality === $quality ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($quality); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="aspect_ratio">宽高比</label>
                                <select id="aspect_ratio" name="aspect_ratio" class="form-control">
                                    <option value="">全部比例</option>
                                    <?php foreach($aspectRatioOptions as $ratio): ?>
                                    <option value="<?php echo htmlspecialchars($ratio); ?>" <?php echo $filterAspectRatio === $ratio ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ratio); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="tag">标签筛选</label>
                                <div class="tag-dropdown">
                                    <input type="text" class="form-control" placeholder="选择标签" id="tagFilter" readonly>
                                    <input type="hidden" name="tag" id="tagInput" value="<?php echo htmlspecialchars($filterTag); ?>">
                                    
                                    <div class="tag-menu" id="tagMenu">
                                        <div class="tag-search">
                                            <input type="text" class="tag-search-input" placeholder="搜索标签" id="tagSearch">
                                        </div>
                                        
                                        <?php if (empty($allTags)): ?>
                                        <div class="empty-tags">没有可用标签</div>
                                        <?php else: ?>
                                        <?php foreach($allTags as $tag): ?>
                                        <div class="tag-menu-item <?php echo $filterTag === $tag ? 'selected' : ''; ?>" data-value="<?php echo htmlspecialchars($tag); ?>">
                                            <?php echo htmlspecialchars($tag); ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="favorite">收藏状态</label>
                                <select id="favorite" name="favorite" class="form-control">
                                    <option value="">全部图片</option>
                                    <option value="true" <?php echo $filterFavorite === 'true' ? 'selected' : ''; ?>>收藏的图片</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="per_page">每页显示</label>
                                <select id="per_page" name="per_page" class="form-control">
                                    <option value="24" <?php echo $perPage === 24 ? 'selected' : ''; ?>>24张</option>
                                    <option value="48" <?php echo $perPage === 48 ? 'selected' : ''; ?>>48张</option>
                                    <option value="96" <?php echo $perPage === 96 ? 'selected' : ''; ?>>96张</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> 筛选
                            </button>
                            <a href="images.php" class="btn btn-light">
                                <i class="fas fa-redo"></i> 重置
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- 图片库工具栏 -->
                <div class="gallery-tools">
                    <div>
                        <span class="badge badge-primary">
                            <i class="fas fa-images"></i> 共 <?php echo $totalImages; ?> 张图片
                        </span>
                        <?php if ($filterModel || $filterDateStart || $filterDateEnd || $searchPrompt || $filterQuality || $filterAspectRatio || $filterFavorite || $filterTag): ?>
                        <span class="badge badge-warning">
                            <i class="fas fa-filter"></i> 已筛选
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sort-controls">
                        <span class="sort-label">排序:</span>
                        <a href="<?php echo buildSortUrl('timestamp'); ?>" class="sort-option <?php echo $sortField === 'timestamp' ? 'active' : ''; ?>">
                            日期
                            <?php if ($sortField === 'timestamp'): ?>
                            <i class="fas fa-<?php echo $sortDirection === 'desc' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo buildSortUrl('model'); ?>" class="sort-option <?php echo $sortField === 'model' ? 'active' : ''; ?>">
                            模型
                            <?php if ($sortField === 'model'): ?>
                            <i class="fas fa-<?php echo $sortDirection === 'desc' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="view-options">
                        <div class="view-option grid-view active" title="网格视图">
                            <i class="fas fa-th"></i>
                        </div>
                        <div class="view-option list-view" title="列表视图">
                            <i class="fas fa-th-list"></i>
                        </div>
                        <div class="view-option masonry-view" title="瀑布流视图">
                            <i class="fas fa-columns"></i>
                        </div>
                    </div>
                    
                    <div class="size-controls">
                        <div class="size-option small-size" title="小图标">
                            <i class="fas fa-compress-alt"></i>
                        </div>
                        <div class="size-option medium-size active" title="中等图标">
                            <i class="fas fa-square"></i>
                        </div>
                        <div class="size-option large-size" title="大图标">
                            <i class="fas fa-expand-alt"></i>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($images)): ?>
                <!-- 空白状态 -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-images"></i>
                    </div>
                    <h3 class="empty-title">未找到图片</h3>
                    <p class="empty-text">没有符合当前筛选条件的图片，请尝试调整筛选条件。</p>
                    <a href="images.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> 查看全部图片
                    </a>
                </div>
                <?php else: ?>
                <!-- 图片网格 -->
                <form id="batchForm" action="" method="POST">
                    <input type="hidden" name="batch_action" id="batchAction" value="">
                    <input type="hidden" name="tag_name" id="batchTagName" value="">
                    
                    <div class="image-grid">
                        <?php foreach ($images as $image): ?>
                        <div class="image-card animate-fade-in">
                            <div class="image-wrapper">
                                <div class="drag-handle"><i class="fas fa-grip-lines"></i></div>
                                <img src="<?php echo htmlspecialchars($image['imageUrl']); ?>" alt="AI生成图片" loading="lazy" class="image-loading">
                                <div class="image-overlay">
                                    <div class="image-actions">
                                        <a href="#" class="image-action view-details" data-id="<?php echo htmlspecialchars($image['id']); ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($image['imageUrl']); ?>" target="_blank" class="image-action">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <a href="#" class="image-action delete-image" data-id="<?php echo htmlspecialchars($image['id']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="quick-view-overlay">
                                    <button class="quick-view-btn" data-id="<?php echo htmlspecialchars($image['id']); ?>" data-url="<?php echo htmlspecialchars($image['imageUrl']); ?>">
                                        <i class="fas fa-search-plus"></i> 快速查看
                                    </button>
                                </div>
                                <div class="image-card-actions">
                                    <input type="checkbox" class="image-select" name="selected_images[]" value="<?php echo htmlspecialchars($image['id']); ?>">
                                    <div class="favorite-action <?php echo isset($image['favorite']) && $image['favorite'] ? 'active' : ''; ?>" data-id="<?php echo htmlspecialchars($image['id']); ?>">
                                        <i class="fa<?php echo isset($image['favorite']) && $image['favorite'] ? 's' : 'r'; ?> fa-star"></i>
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
                                
                                <?php if (isset($image['tags']) && is_array($image['tags']) && !empty($image['tags'])): ?>
                                <div class="image-tags">
                                    <?php foreach ($image['tags'] as $tag): ?>
                                    <span class="image-tag"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
                
                <!-- 分页 -->
                <div class="pagination-controls">
                    <div class="per-page-control">
                        <span>每页显示:</span>
                        <select class="per-page-select" id="perPageSelect">
                            <option value="24" <?php echo $perPage === 24 ? 'selected' : ''; ?>>24张</option>
                            <option value="48" <?php echo $perPage === 48 ? 'selected' : ''; ?>>48张</option>
                            <option value="96" <?php echo $perPage === 96 ? 'selected' : ''; ?>>96张</option>
                        </select>
                    </div>
                    
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
                    <?php endif; ?>
                </div>
                
                <div class="page-info">
                    显示 <?php echo ($page - 1) * $perPage + 1; ?> - <?php echo min($page * $perPage, $totalImages); ?> 共 <?php echo $totalImages; ?> 张图片
                </div>
                <?php endif; ?>
                
                <!-- 标签添加模态框 -->
                <div class="modal" id="tagModal">
                    <div class="modal-content" style="max-width: 500px;">
                        <div class="modal-header">
                            <h3 class="modal-title">添加标签</h3>
                            <button class="modal-close" id="closeTagModal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>为选中的图片添加一个标签:</p>
                            <input type="text" class="form-control" id="tagNameInput" placeholder="输入标签名称" style="margin-top: 1rem;">
                            
                            <?php if (!empty($allTags)): ?>
                            <p style="margin-top: 1.5rem; margin-bottom: 0.5rem;">已有标签:</p>
                            <div class="badge-container">
                                <?php foreach($allTags as $tag): ?>
                                <span class="tag-badge tag-select" data-value="<?php echo htmlspecialchars($tag); ?>">
                                    <?php echo htmlspecialchars($tag); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" id="cancelTagAdd">取消</button>
                            <button type="button" class="btn btn-primary" id="confirmTagAdd">添加标签</button>
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
    
    <!-- 图片详情模态框 -->
    <div class="modal" id="imageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">图片详情</h3>
                <button class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-image">
                    <img src="" alt="图片详情" id="modalImage">
                </div>
                <div class="modal-details">
                    <div class="detail-item">
                        <div class="detail-label">AI模型</div>
                        <div class="detail-value" id="modalModel">
                            <span class="badge badge-primary"></span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">提示词</div>
                        <div class="detail-value prompt-text" id="modalPrompt"></div>
                    </div>
                    <div class="detail-item" id="modalRevisedPromptContainer">
                        <div class="detail-label">修改后的提示词</div>
                        <div class="detail-value prompt-text" id="modalRevisedPrompt"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">生成时间</div>
                        <div class="detail-value" id="modalDate"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">图片ID</div>
                        <div class="detail-value" id="modalId"></div>
                    </div>
                    <div class="detail-item" id="modalTagsContainer">
                        <div class="detail-label">标签</div>
                        <div class="detail-value">
                            <div class="badge-container" id="modalTags"></div>
                            <div class="add-tag-form">
                                <input type="text" class="tag-input" id="modalTagInput" placeholder="添加新标签">
                                <button class="btn btn-sm btn-primary" id="modalAddTag">添加</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="modalToggleFavorite">
                    <i class="far fa-star"></i> <span>收藏</span>
                </button>
                <a href="#" class="btn btn-light" id="modalDownload" download>
                    <i class="fas fa-download"></i> 下载图片
                </a>
                <a href="#" class="btn btn-danger" id="modalDelete">
                    <i class="fas fa-trash-alt"></i> 删除图片
                </a>
            </div>
        </div>
    </div>
    
    <!-- 全屏预览模态框 -->
    <div class="fullscreen-preview" id="fullscreenPreview">
        <div class="fullscreen-header">
            <h3 class="fullscreen-title">图片预览</h3>
            <button class="fullscreen-close" id="closeFullscreen">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="fullscreen-content">
            <img src="" alt="图片全屏预览" class="fullscreen-image" id="fullscreenImage">
        </div>
        <div class="fullscreen-nav">
            <button class="fullscreen-nav-btn" id="prevImage">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="fullscreen-nav-btn" id="nextImage">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <!-- 进度指示器 -->
    <div class="progress-container">
        <div class="progress-bar" id="progressBar"></div>
    </div>
    
    <!-- 图片比较工具栏 -->
    <div class="comparison-bar" id="comparisonBar">
        <div class="comparison-info">
            已选择 <span id="comparisonCount">0</span> 张图片进行比较
        </div>
        <div class="comparison-preview" id="comparisonPreview">
            <!-- 动态添加的比较项目 -->
        </div>
        <div class="comparison-actions">
            <button class="btn btn-light" id="cancelComparison">取消</button>
            <button class="btn btn-primary" id="startComparison">开始比较</button>
        </div>
    </div>
    
    <script>
        // 侧边栏折叠
        document.querySelector('.toggle-sidebar').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
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
        
        // 高级筛选切换
        document.getElementById('toggleAdvanced').addEventListener('click', function() {
            const advancedFilter = document.getElementById('advancedFilter');
            advancedFilter.classList.toggle('active');
            
            const icon = this.querySelector('i');
            if (advancedFilter.classList.contains('active')) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                this.textContent = ' 收起高级筛选 ';
            } else {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                this.textContent = ' 高级筛选 ';
            }
            this.appendChild(icon);
        });
        
        // 标签选择器
        const tagFilter = document.getElementById('tagFilter');
        const tagInput = document.getElementById('tagInput');
        const tagMenu = document.getElementById('tagMenu');
        const tagSearch = document.getElementById('tagSearch');
        
        if (tagFilter) {
            // 设置初始值
            if (tagInput.value) {
                tagFilter.value = tagInput.value;
            }
            
            // 点击标签输入框显示下拉菜单
            tagFilter.addEventListener('click', function(e) {
                e.stopPropagation();
                tagMenu.classList.toggle('active');
            });
            
            // 标签搜索
            tagSearch.addEventListener('input', function() {
                const searchValue = this.value.toLowerCase();
                const tagItems = tagMenu.querySelectorAll('.tag-menu-item');
                
                tagItems.forEach(item => {
                    const tagText = item.textContent.toLowerCase();
                    if (tagText.includes(searchValue)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
            
            // 选择标签
            const tagItems = tagMenu.querySelectorAll('.tag-menu-item');
            tagItems.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    tagFilter.value = value;
                    tagInput.value = value;
                    
                    // 更新选中状态
                    tagItems.forEach(tag => tag.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    tagMenu.classList.remove('active');
                });
            });
            
            // 点击其他地方关闭标签菜单
            document.addEventListener('click', function(e) {
                if (!tagMenu.contains(e.target) && e.target !== tagFilter) {
                    tagMenu.classList.remove('active');
                }
            });
        }
        
        // 视图切换
        document.querySelector('.grid-view').addEventListener('click', function() {
            document.body.classList.remove('view-list', 'view-masonry');
            document.body.classList.add('view-grid');
            document.querySelectorAll('.view-option').forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
        });
        
        document.querySelector('.list-view').addEventListener('click', function() {
            document.body.classList.remove('view-grid', 'view-masonry');
            document.body.classList.add('view-list');
            this.classList.add('active');
            document.querySelectorAll('.view-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.grid-view').classList.remove('active');
        });
        
        document.querySelector('.masonry-view').addEventListener('click', function() {
            document.body.classList.remove('view-grid', 'view-list');
            document.body.classList.add('view-masonry');
            this.classList.add('active');
            document.querySelectorAll('.view-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.grid-view').classList.remove('active');
        });
        
        // 批量操作功能
        const batchMenuToggle = document.getElementById('batchMenuToggle');
        const batchMenu = document.getElementById('batchMenu');
        const batchForm = document.getElementById('batchForm');
        const batchAction = document.getElementById('batchAction');
        const batchTagName = document.getElementById('batchTagName');
        const selectAllCheckbox = document.getElementById('selectAll');
        const selectionInfo = document.getElementById('selectionInfo');
        const selectedCount = document.getElementById('selectedCount');
        const cancelSelection = document.getElementById('cancelSelection');
        
        // 图片选择
        const imageCheckboxes = document.querySelectorAll('.image-select');
        function updateSelection() {
            const selectedCheckboxes = document.querySelectorAll('.image-select:checked');
            const count = selectedCheckboxes.length;
            
            selectedCount.textContent = count;
            
            if (count > 0) {
                selectionInfo.classList.add('active');
            } else {
                selectionInfo.classList.remove('active');
            }
            
            // 更新全选状态
            if (count === imageCheckboxes.length && count > 0) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (count === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        }
        
        // 添加图片选择框事件监听
        imageCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelection);
        });
        
        // 全选/取消全选
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            
            imageCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            
            updateSelection();
        });
        
        // 取消选择
        cancelSelection.addEventListener('click', function() {
            imageCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
            selectionInfo.classList.remove('active');
        });
        
        // 切换批量操作菜单
        batchMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            batchMenu.classList.toggle('active');
        });
        
        // 点击其他地方关闭批量操作菜单
        document.addEventListener('click', function(e) {
            if (!batchMenu.contains(e.target) && e.target !== batchMenuToggle) {
                batchMenu.classList.remove('active');
            }
        });
        
        // 批量操作菜单项点击
        const batchMenuItems = document.querySelectorAll('.batch-menu-item');
        batchMenuItems.forEach(item => {
            item.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                
                if (action === 'tag-add') {
                    // 打开标签添加模态框
                    document.getElementById('tagModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else if (action === 'download') {
                    // 批量下载图片
                    executeBatchDownload();
                } else {
                    // 其他批量操作
                    if (confirm('确定要对选中的图片执行此操作吗？')) {
                        batchAction.value = action;
                        batchForm.submit();
                    }
                }
                
                batchMenu.classList.remove('active');
            });
        });
        
        // 标签添加模态框
        const tagModal = document.getElementById('tagModal');
        const closeTagModal = document.getElementById('closeTagModal');
        const cancelTagAdd = document.getElementById('cancelTagAdd');
        const confirmTagAdd = document.getElementById('confirmTagAdd');
        const tagNameInput = document.getElementById('tagNameInput');
        
        // 关闭标签模态框
        function closeTagModalFunc() {
            tagModal.classList.remove('active');
            document.body.style.overflow = '';
            tagNameInput.value = '';
        }
        
        closeTagModal.addEventListener('click', closeTagModalFunc);
        cancelTagAdd.addEventListener('click', closeTagModalFunc);
        
        // 点击标签选择
        const tagSelects = document.querySelectorAll('.tag-select');
        tagSelects.forEach(tag => {
            tag.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                tagNameInput.value = value;
            });
        });
        
        // 确认添加标签
        confirmTagAdd.addEventListener('click', function() {
            const tagName = tagNameInput.value.trim();
            
            if (tagName) {
                batchAction.value = 'tag';
                batchTagName.value = tagName;
                batchForm.submit();
            } else {
                alert('请输入标签名称');
            }
        });
        
        // 收藏切换
        const favoriteActions = document.querySelectorAll('.favorite-action');
        favoriteActions.forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const id = this.getAttribute('data-id');
                const icon = this.querySelector('i');
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ 
                            action: 'toggle_favorite',
                            image_id: id 
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        if (result.is_favorite) {
                            this.classList.add('active');
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                        } else {
                            this.classList.remove('active');
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                        }
                    } else {
                        alert('操作失败：' + (result.message || '未知错误'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('请求错误，请稍后再试');
                }
            });
        });
        
        // 每页显示数量切换
        const perPageSelect = document.getElementById('perPageSelect');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', this.value);
                window.location.href = url.toString();
            });
        }
        
        // 批量下载功能
        async function executeBatchDownload() {
            const selectedCheckboxes = document.querySelectorAll('.image-select:checked');
            const selectedValues = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (selectedValues.length === 0) {
                alert('请至少选择一张图片');
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        batch_action: 'download',
                        selected_images: selectedValues
                    })
                });
                
                const result = await response.json();
                
                if (result.success && result.download_url) {
                    window.location.href = result.download_url;
                } else {
                    alert('下载准备失败：' + (result.message || '未知错误'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('请求错误，请稍后再试');
            }
        }
        
        // 图片详情模态框
        const modal = document.getElementById('imageModal');
        const modalClose = modal.querySelector('.modal-close');
        const modalImage = document.getElementById('modalImage');
        const modalModel = document.getElementById('modalModel').querySelector('.badge');
        const modalPrompt = document.getElementById('modalPrompt');
        const modalRevisedPrompt = document.getElementById('modalRevisedPrompt');
        const modalRevisedPromptContainer = document.getElementById('modalRevisedPromptContainer');
        const modalDate = document.getElementById('modalDate');
        const modalId = document.getElementById('modalId');
        const modalDownload = document.getElementById('modalDownload');
        const modalDelete = document.getElementById('modalDelete');
        const modalTags = document.getElementById('modalTags');
        const modalTagsContainer = document.getElementById('modalTagsContainer');
        const modalToggleFavorite = document.getElementById('modalToggleFavorite');
        const modalAddTag = document.getElementById('modalAddTag');
        const modalTagInput = document.getElementById('modalTagInput');
        
        // 打开模态框
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const card = this.closest('.image-card');
                const imageUrl = card.querySelector('img').src;
                const model = card.querySelector('.image-model').textContent;
                const prompt = card.querySelector('.image-title').textContent;
                const dateText = card.querySelector('.image-date').textContent.trim();
                
                // 填充模态框数据
                modalImage.src = imageUrl;
                modalModel.textContent = model;
                modalPrompt.textContent = prompt;
                modalDate.textContent = dateText;
                modalId.textContent = id;
                modalDownload.href = imageUrl;
                modalDelete.setAttribute('data-id', id);
                modalToggleFavorite.setAttribute('data-id', id);
                
                // 检查收藏状态
                const isFavorite = card.querySelector('.favorite-action').classList.contains('active');
                updateModalFavoriteButton(isFavorite);
                
                // 清空标签
                modalTags.innerHTML = '';
                
                // 尝试获取完整的图片信息
                try {
                    const historyFile = '../api/data/history.json';
                    const response = await fetch(historyFile);
                    const history = await response.json();
                    
                    const imageData = history.find(item => item.id === id);
                    if (imageData) {
                        if (imageData.revisedPrompt) {
                            modalRevisedPrompt.textContent = imageData.revisedPrompt;
                            modalRevisedPromptContainer.style.display = 'block';
                        } else {
                            modalRevisedPromptContainer.style.display = 'none';
                        }
                        
                        // 添加标签
                        if (imageData.tags && imageData.tags.length > 0) {
                            modalTagsContainer.style.display = 'block';
                            imageData.tags.forEach(tag => {
                                const tagElement = document.createElement('span');
                                tagElement.className = 'tag-badge';
                                tagElement.innerHTML = `${tag} <i class="fas fa-times remove-tag" data-tag="${tag}"></i>`;
                                modalTags.appendChild(tagElement);
                            });
                            
                            // 添加删除标签事件
                            modalTags.querySelectorAll('.remove-tag').forEach(removeBtn => {
                                removeBtn.addEventListener('click', function() {
                                    const tagName = this.getAttribute('data-tag');
                                    removeTag(id, tagName, this.closest('.tag-badge'));
                                });
                            });
                        } else {
                            modalTagsContainer.style.display = 'block';
                            modalTags.innerHTML = '<em style="color: var(--gray-500);">暂无标签</em>';
                        }
                    }
                } catch (error) {
                    console.error('Error fetching image details:', error);
                    modalRevisedPromptContainer.style.display = 'none';
                    modalTagsContainer.style.display = 'none';
                }
                
                // 显示模态框
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });
        
        // 更新模态框收藏按钮
        function updateModalFavoriteButton(isFavorite) {
            const icon = modalToggleFavorite.querySelector('i');
            const text = modalToggleFavorite.querySelector('span');
            
            if (isFavorite) {
                icon.className = 'fas fa-star';
                text.textContent = '取消收藏';
            } else {
                icon.className = 'far fa-star';
                text.textContent = '收藏';
            }
        }
        
        // 模态框内切换收藏状态
        modalToggleFavorite.addEventListener('click', async function() {
            const id = this.getAttribute('data-id');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ 
                        action: 'toggle_favorite',
                        image_id: id 
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    updateModalFavoriteButton(result.is_favorite);
                    
                    // 同时更新卡片上的收藏图标
                    const cardFavorite = document.querySelector(`.favorite-action[data-id="${id}"]`);
                    if (cardFavorite) {
                        const cardIcon = cardFavorite.querySelector('i');
                        if (result.is_favorite) {
                            cardFavorite.classList.add('active');
                            cardIcon.classList.remove('far');
                            cardIcon.classList.add('fas');
                        } else {
                            cardFavorite.classList.remove('active');
                            cardIcon.classList.remove('fas');
                            cardIcon.classList.add('far');
                        }
                    }
                } else {
                    alert('操作失败：' + (result.message || '未知错误'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('请求错误，请稍后再试');
            }
        });
        
        // 模态框内添加标签
        modalAddTag.addEventListener('click', function() {
            const tagName = modalTagInput.value.trim();
            const imageId = modalId.textContent;
            
            if (tagName) {
                addTagToImage(imageId, tagName);
            } else {
                alert('请输入标签名称');
            }
        });
        
        // 给图片添加标签
        async function addTagToImage(imageId, tagName) {
            try {
                const response = await fetch('../api/data/history.json');
                const history = await response.json();
                
                let success = false;
                for (let image of history) {
                    if (image.id === imageId) {
                        if (!image.tags) {
                            image.tags = [];
                        }
                        
                        if (!image.tags.includes(tagName)) {
                            image.tags.push(tagName);
                            success = true;
                        } else {
                            alert('该图片已有此标签');
                            return;
                        }
                        break;
                    }
                }
                
                if (success) {
                    // 保存更新后的历史记录
                    const saveResponse = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ 
                            action: 'update_history',
                            history: history
                        })
                    });
                    
                    // 添加标签到模态框
                    const tagElement = document.createElement('span');
                    tagElement.className = 'tag-badge';
                    tagElement.innerHTML = `${tagName} <i class="fas fa-times remove-tag" data-tag="${tagName}"></i>`;
                    modalTags.appendChild(tagElement);
                    
                    // 清空输入框
                    modalTagInput.value = '';
                    
                    // 如果是"暂无标签"提示，则移除
                    const emptyTag = modalTags.querySelector('em');
                    if (emptyTag) {
                        modalTags.removeChild(emptyTag);
                    }
                    
                    // 添加删除标签事件
                    tagElement.querySelector('.remove-tag').addEventListener('click', function() {
                        removeTag(imageId, tagName, tagElement);
                    });
                }
            } catch (error) {
                console.error('Error adding tag:', error);
                alert('添加标签失败，请稍后再试');
            }
        }
        
        // 从图片移除标签
        async function removeTag(imageId, tagName, tagElement) {
            try {
                const response = await fetch('../api/data/history.json');
                const history = await response.json();
                
                let success = false;
                for (let image of history) {
                    if (image.id === imageId && image.tags) {
                        const tagIndex = image.tags.indexOf(tagName);
                        if (tagIndex !== -1) {
                            image.tags.splice(tagIndex, 1);
                            success = true;
                        }
                        break;
                    }
                }
                
                if (success) {
                    // 保存更新后的历史记录
                    const saveResponse = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ 
                            action: 'update_history',
                            history: history
                        })
                    });
                    
                    // 从模态框移除标签
                    tagElement.remove();
                    
                    // 如果没有标签了，显示"暂无标签"提示
                    if (modalTags.children.length === 0) {
                        modalTags.innerHTML = '<em style="color: var(--gray-500);">暂无标签</em>';
                    }
                }
            } catch (error) {
                console.error('Error removing tag:', error);
                alert('移除标签失败，请稍后再试');
            }
        }
        
        // 关闭模态框
        modalClose.addEventListener('click', function() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // 点击模态框外部关闭
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // 删除图片功能
        document.querySelectorAll('.delete-image').forEach(button => {
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
        
        // 模态框内删除按钮
        modalDelete.addEventListener('click', async function(e) {
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
                        // 关闭模态框
                        modal.classList.remove('active');
                        document.body.style.overflow = '';
                        
                        // 刷新页面以更新图片列表
                        window.location.reload();
                    } else {
                        alert('删除失败：' + (result.error || '未知错误'));
                    }
                } catch (error) {
                    alert('请求错误：' + error.message);
                }
            }
        });
        
        // 高级筛选选项初始显示控制
        window.addEventListener('load', function() {
            const advancedFilter = document.getElementById('advancedFilter');
            const hasAdvancedFilter = <?php echo ($filterQuality || $filterAspectRatio || $filterFavorite || $filterTag) ? 'true' : 'false'; ?>;
            
            if (hasAdvancedFilter) {
                advancedFilter.classList.add('active');
                const toggleBtn = document.getElementById('toggleAdvanced');
                const icon = toggleBtn.querySelector('i');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                toggleBtn.textContent = ' 收起高级筛选 ';
                toggleBtn.appendChild(icon);
            }
        });
        
        // 复制提示词
        batchMenuItems.forEach(item => {
            if (item.getAttribute('data-action') === 'copy-prompts') {
                item.addEventListener('click', async function() {
                    const selectedCheckboxes = document.querySelectorAll('.image-select:checked');
                    if (selectedCheckboxes.length === 0) {
                        alert('请至少选择一张图片！');
                        return;
                    }
                    
                    try {
                        const response = await fetch('../api/data/history.json');
                        const allImages = await response.json();
                        
                        // 收集选中图片ID
                        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
                        
                        // 提取提示词
                        let promptText = '';
                        selectedIds.forEach(id => {
                            const img = allImages.find(image => image.id === id);
                            if (img && img.prompt) {
                                promptText += img.prompt + '\n\n';
                            }
                        });
                        
                        // 复制到剪贴板
                        await navigator.clipboard.writeText(promptText.trim());
                        alert('提示词已复制到剪贴板！');
                    } catch (error) {
                        console.error('复制提示词错误:', error);
                        alert('复制提示词失败，请稍后再试');
                    }
                });
            }
        });
    </script>
</body>
</html> 