<?php
/**
 * Advanced Server Management System v2.0
 * نظام متطور لإدارة الخوادم - بدون تسجيل دخول
 */

class AdvancedServerManager {
    private $config;
    
    public function __construct() {
        $this->initConfig();
        session_start();
    }
    
    private function initConfig() {
        $this->config = [
            'allowed_commands' => [
                'system' => ['uname -a', 'uptime', 'date', 'whoami', 'free -m', 'df -h', 'ps aux', 'top -bn1'],
                'files' => ['ls -la', 'find . -name "*.php"', 'du -sh', 'stat', 'file', 'wc -l'],
                'network' => ['netstat -tulpn', 'ping -c 3 google.com', 'curl -I localhost', 'wget --spider google.com'],
                'security' => ['lastlog', 'who', 'id', 'groups'],
                'php' => ['php -v', 'php -m', 'php -i | grep "PHP Version"'],
                'mysql' => ['mysql --version', 'which mysql']
            ],
            'upload_path' => './uploads/',
            'max_upload_size' => 50 * 1024 * 1024, // 50MB
            'session_timeout' => 3600 // 1 hour
        ];
    }
    
    public function executeCommand($category, $command) {
        if (!isset($this->config['allowed_commands'][$category])) {
            return ["error" => "فئة الأوامر غير مسموحة"];
        }
        
        if (!in_array($command, $this->config['allowed_commands'][$category])) {
            return ["error" => "الأمر غير مسموح به"];
        }
        
        $output = [];
        $returnCode = 0;
        exec(escapeshellcmd($command) . " 2>&1", $output, $returnCode);
        
        return [
            "command" => $command,
            "output" => $output,
            "return_code" => $returnCode,
            "timestamp" => date('Y-m-d H:i:s')
        ];
    }
    
    public function getSystemInfo() {
        return [
            'server' => [
                'hostname' => php_uname('n'),
                'os' => php_uname('s') . ' ' . php_uname('r'),
                'architecture' => php_uname('m'),
                'kernel' => php_uname('v')
            ],
            'php' => [
                'version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ],
            'resources' => [
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                'disk_free' => $this->formatBytes(disk_free_space("/")),
                'disk_total' => $this->formatBytes(disk_total_space("/")),
                'load_average' => sys_getloadavg()
            ],
            'web_server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'user' => get_current_user(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
        ];
    }
    
    public function fileManager($action, $path = '', $file = '') {
        $basePath = realpath('./') . '/';
        $targetPath = $basePath . ltrim($path, '/');
        
        // Security check
        if (strpos(realpath($targetPath), $basePath) !== 0) {
            return ["error" => "مسار غير مسموح"];
        }
        
        switch($action) {
            case 'list':
                return $this->listFiles($targetPath);
            case 'view':
                return $this->viewFile($targetPath . $file);
            case 'download':
                return $this->downloadFile($targetPath . $file);
            case 'delete':
                return $this->deleteFile($targetPath . $file);
            case 'create_folder':
                return $this->createFolder($targetPath . $file);
            default:
                return ["error" => "إجراء غير معروف"];
        }
    }
    
    private function listFiles($path) {
        if (!is_dir($path)) {
            return ["error" => "المسار غير موجود"];
        }
        
        $files = [];
        $items = scandir($path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $path . '/' . $item;
            $files[] = [
                'name' => $item,
                'path' => $fullPath,
                'size' => is_dir($fullPath) ? null : $this->formatBytes(filesize($fullPath)),
                'type' => is_dir($fullPath) ? 'directory' : 'file',
                'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4),
                'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                'is_writable' => is_writable($fullPath)
            ];
        }
        
        // Sort: directories first, then files
        usort($files, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['type'] === 'directory' ? -1 : 1;
        });
        
        return $files;
    }
    
    private function viewFile($filePath) {
        if (!file_exists($filePath) || is_dir($filePath)) {
            return ["error" => "الملف غير موجود"];
        }
        
        if (filesize($filePath) > 5 * 1024 * 1024) { // 5MB limit
            return ["error" => "الملف كبير جداً للعرض"];
        }
        
        $content = file_get_contents($filePath);
        return [
            "content" => $content,
            "size" => $this->formatBytes(filesize($filePath)),
            "lines" => substr_count($content, "\n") + 1
        ];
    }
    
    public function uploadFile($file, $targetPath = '') {
        $uploadPath = $this->config['upload_path'];
        if ($targetPath) {
            $uploadPath = $targetPath;
        }
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        if ($file['size'] > $this->config['max_upload_size']) {
            return ["error" => "حجم الملف يتجاوز الحد المسموح (50MB)"];
        }
        
        $fileName = basename($file['name']);
        $targetFile = $uploadPath . $fileName;
        
        // Security: check file extension
        $allowedExtensions = ['php', 'html', 'css', 'js', 'txt', 'json', 'xml', 'jpg', 'png', 'gif', 'zip', 'pdf'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            return ["error" => "نوع الملف غير مسموح به"];
        }
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return ["success" => "تم رفع الملف بنجاح", "file" => $fileName, "path" => $targetFile];
        } else {
            return ["error" => "فشل في رفع الملف"];
        }
    }
    
    private function createFolder($path) {
        if (mkdir($path, 0755, true)) {
            return ["success" => "تم إنشاء المجلد بنجاح"];
        } else {
            return ["error" => "فشل في إنشاء المجلد"];
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public function getFileContent($filePath) {
        if (!file_exists($filePath)) {
            return ["error" => "الملف غير موجود"];
        }
        
        return [
            "content" => htmlspecialchars(file_get_contents($filePath)),
            "size" => filesize($filePath),
            "modified" => date('Y-m-d H:i:s', filemtime($filePath))
        ];
    }
    
    public function saveFileContent($filePath, $content) {
        if (file_put_contents($filePath, $content) !== false) {
            return ["success" => "تم حفظ الملف بنجاح"];
        } else {
            return ["error" => "فشل في حفظ الملف"];
        }
    }
}

// واجهة المستخدم المتطورة
class ModernAdminPanel {
    private $manager;
    
    public function __construct() {
        $this->manager = new AdvancedServerManager();
    }
    
    public function renderDashboard() {
        $currentTab = $_GET['tab'] ?? 'dashboard';
        ?>
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>نظام إدارة الخادم المتطور</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                :root {
                    --primary: #667eea;
                    --secondary: #764ba2;
                    --success: #10b981;
                    --danger: #ef4444;
                    --warning: #f59e0b;
                    --info: #3b82f6;
                    --dark: #1f2937;
                    --light: #f8fafc;
                }
                
                * { margin: 0; padding: 0; box-sizing: border-box; }
                
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: var(--light);
                    color: var(--dark);
                }
                
                .navbar {
                    background: linear-gradient(135deg, var(--primary), var(--secondary));
                    color: white;
                    padding: 1rem 2rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .nav-brand { 
                    font-size: 1.5rem; 
                    font-weight: bold;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .nav-info {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    font-size: 0.9rem;
                }
                
                .container {
                    display: flex;
                    min-height: calc(100vh - 70px);
                }
                
                .sidebar {
                    width: 250px;
                    background: white;
                    border-left: 1px solid #e5e7eb;
                    padding: 1rem 0;
                }
                
                .sidebar-item {
                    padding: 0.75rem 1.5rem;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    color: var(--dark);
                    text-decoration: none;
                    transition: all 0.3s;
                    border-right: 3px solid transparent;
                }
                
                .sidebar-item:hover, .sidebar-item.active {
                    background: #f1f5f9;
                    border-right-color: var(--primary);
                    color: var(--primary);
                }
                
                .main-content {
                    flex: 1;
                    padding: 2rem;
                    background: #f8fafc;
                }
                
                .card {
                    background: white;
                    border-radius: 10px;
                    padding: 1.5rem;
                    margin-bottom: 1.5rem;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    border: 1px solid #e5e7eb;
                }
                
                .card-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1rem;
                    padding-bottom: 0.75rem;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 1rem;
                    margin-bottom: 2rem;
                }
                
                .stat-card {
                    background: white;
                    padding: 1.5rem;
                    border-radius: 10px;
                    border-left: 4px solid var(--primary);
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                }
                
                .stat-value {
                    font-size: 2rem;
                    font-weight: bold;
                    color: var(--primary);
                }
                
                .stat-label {
                    color: #6b7280;
                    font-size: 0.875rem;
                }
                
                .btn {
                    padding: 0.5rem 1rem;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 500;
                    transition: all 0.3s;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .btn-primary { background: var(--primary); color: white; }
                .btn-danger { background: var(--danger); color: white; }
                .btn-success { background: var(--success); color: white; }
                .btn-warning { background: var(--warning); color: white; }
                .btn-info { background: var(--info); color: white; }
                
                .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
                
                .command-output {
                    background: #1a1a1a;
                    color: #00ff00;
                    padding: 1rem;
                    border-radius: 6px;
                    font-family: 'Courier New', monospace;
                    max-height: 400px;
                    overflow-y: auto;
                    white-space: pre-wrap;
                    font-size: 0.9rem;
                }
                
                .file-list { margin-top: 1rem; }
                .file-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.75rem;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    margin-bottom: 0.5rem;
                    background: white;
                }
                
                .file-item:hover {
                    background: #f8f9fa;
                }
                
                .file-info {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    flex: 1;
                }
                
                .file-actions {
                    display: flex;
                    gap: 0.5rem;
                }
                
                .tab-content { display: none; }
                .tab-content.active { display: block; }
                
                .form-grid {
                    display: grid;
                    grid-template-columns: 1fr 2fr 1fr;
                    gap: 1rem;
                    margin-bottom: 1rem;
                }
                
                select, input, textarea {
                    padding: 0.5rem;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    font-size: 1rem;
                    font-family: inherit;
                }
                
                textarea {
                    min-height: 200px;
                    resize: vertical;
                }
                
                .alert {
                    padding: 1rem;
                    border-radius: 6px;
                    margin-bottom: 1rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .alert-warning {
                    background: #fff3cd;
                    color: #856404;
                    border: 1px solid #ffeaa7;
                }
                
                .alert-success {
                    background: #d1fae5;
                    color: #065f46;
                    border: 1px solid #a7f3d0;
                }
                
                .alert-error {
                    background: #fee2e2;
                    color: #991b1b;
                    border: 1px solid #fecaca;
                }
                
                .alert-info {
                    background: #dbeafe;
                    color: #1e40af;
                    border: 1px solid #93c5fd;
                }
                
                .quick-commands {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 0.5rem;
                    margin: 1rem 0;
                }
                
                .file-editor {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 6px;
                    padding: 1rem;
                }
                
                .breadcrumb {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    margin-bottom: 1rem;
                    padding: 0.5rem;
                    background: white;
                    border-radius: 6px;
                    border: 1px solid #e5e7eb;
                }
                
                .breadcrumb a {
                    color: var(--primary);
                    text-decoration: none;
                }
                
                .breadcrumb a:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <!-- شريط التنقل -->
            <nav class="navbar">
                <div class="nav-brand">
                    <i class="fas fa-server"></i> نظام إدارة الخادم
                </div>
                <div class="nav-info">
                    <span>مرحباً! النظام يعمل بدون تسجيل دخول</span>
                    <span>|</span>
                    <span>المستخدم: <?php echo get_current_user(); ?></span>
                </div>
            </nav>
            
            <div class="container">
                <!-- القائمة الجانبية -->
                <div class="sidebar">
                    <a href="?tab=dashboard" class="sidebar-item <?php echo $currentTab == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                    </a>
                    <a href="?tab=commands" class="sidebar-item <?php echo $currentTab == 'commands' ? 'active' : ''; ?>">
                        <i class="fas fa-terminal"></i> الأوامر
                    </a>
                    <a href="?tab=files" class="sidebar-item <?php echo $currentTab == 'files' ? 'active' : ''; ?>">
                        <i class="fas fa-folder"></i> إدارة الملفات
                    </a>
                    <a href="?tab=editor" class="sidebar-item <?php echo $currentTab == 'editor' ? 'active' : ''; ?>">
                        <i class="fas fa-edit"></i> محرر الملفات
                    </a>
                    <a href="?tab=upload" class="sidebar-item <?php echo $currentTab == 'upload' ? 'active' : ''; ?>">
                        <i class="fas fa-upload"></i> رفع الملفات
                    </a>
                    <a href="?tab=system" class="sidebar-item <?php echo $currentTab == 'system' ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i> معلومات النظام
                    </a>
                </div>
                
                <!-- المحتوى الرئيسي -->
                <div class="main-content">
                    <?php $this->renderTabContent($currentTab); ?>
                </div>
            </div>
            
            <script>
                function executeCommand(category, command) {
                    const outputElement = document.getElementById('command-output');
                    outputElement.innerHTML = '<div style="color: #666;">جاري التنفيذ...</div>';
                    
                    fetch('?ajax=command&category=' + category + '&command=' + encodeURIComponent(command))
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                outputElement.innerHTML = '<div style="color: red">❌ ' + data.error + '</div>';
                            } else {
                                outputElement.innerHTML = data.output.join('\n');
                            }
                        })
                        .catch(error => {
                            outputElement.innerHTML = '<div style="color: red">❌ خطأ: ' + error + '</div>';
                        });
                }
                
                function refreshSystemInfo() {
                    fetch('?ajax=system_info')
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('memory-usage').textContent = data.resources.memory_usage;
                            document.getElementById('disk-free').textContent = data.resources.disk_free;
                            document.getElementById('load-average').textContent = data.resources.load_average.join(', ');
                        });
                }
                
                function viewFile(filePath) {
                    fetch('?ajax=view_file&file=' + encodeURIComponent(filePath))
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                alert('خطأ: ' + data.error);
                            } else {
                                document.getElementById('editor-content').value = data.content;
                                document.getElementById('editor-file').value = filePath;
                                // Switch to editor tab
                                window.location.href = '?tab=editor';
                            }
                        });
                }
                
                function saveFile() {
                    const filePath = document.getElementById('editor-file').value;
                    const content = document.getElementById('editor-content').value;
                    
                    fetch('?ajax=save_file', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'file=' + encodeURIComponent(filePath) + '&content=' + encodeURIComponent(content)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ ' + data.success);
                        } else {
                            alert('❌ ' + data.error);
                        }
                    });
                }
                
                // تحديث المعلومات كل 30 ثانية
                setInterval(refreshSystemInfo, 30000);
                
                // تحمين الأوامر حسب الفئة
                const commands = <?php echo json_encode((new AdvancedServerManager())->config['allowed_commands']); ?>;
                
                document.addEventListener('DOMContentLoaded', function() {
                    const categorySelect = document.querySelector('[name="category"]');
                    const commandSelect = document.querySelector('[name="command"]');
                    
                    if (categorySelect && commandSelect) {
                        categorySelect.addEventListener('change', function() {
                            const category = this.value;
                            commandSelect.innerHTML = '<option value="">اختر الأمر</option>';
                            
                            if (category && commands[category]) {
                                commands[category].forEach(cmd => {
                                    const option = document.createElement('option');
                                    option.value = cmd;
                                    option.textContent = cmd;
                                    commandSelect.appendChild(option);
                                });
                            }
                        });
                    }
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    private function renderTabContent($tab) {
        switch($tab) {
            case 'dashboard':
                $this->renderDashboardTab();
                break;
            case 'commands':
                $this->renderCommandsTab();
                break;
            case 'files':
                $this->renderFilesTab();
                break;
            case 'editor':
                $this->renderEditorTab();
                break;
            case 'upload':
                $this->renderUploadTab();
                break;
            case 'system':
                $this->renderSystemTab();
                break;
        }
    }
    
    private function renderDashboardTab() {
        $systemInfo = $this->manager->getSystemInfo();
        ?>
        <div class="tab-content active">
            <h1>🚀 لوحة التحكم الرئيسية</h1>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>مرحباً!</strong> النظام يعمل بدون تسجيل دخول - يمكنك البدء فوراً في إدارة الخادم
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="memory-usage"><?php echo $systemInfo['resources']['memory_usage']; ?></div>
                    <div class="stat-label">استخدام الذاكرة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="disk-free"><?php echo $systemInfo['resources']['disk_free']; ?></div>
                    <div class="stat-label">مساحة القرص المتاحة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="load-average"><?php echo implode(', ', $systemInfo['resources']['load_average']); ?></div>
                    <div class="stat-label">متوسط الحمل</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $systemInfo['php']['version']; ?></div>
                    <div class="stat-label">إصدار PHP</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> معلومات النظام التفصيلية</h2>
                    <button onclick="refreshSystemInfo()" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> تحديث
                    </button>
                </div>
                <pre><?php print_r($systemInfo); ?></pre>
            </div>
        </div>
        <?php
    }
    
    private function renderCommandsTab() {
        $commands = $this->manager->config['allowed_commands'];
        $result = null;
        
        if ($_POST['execute'] ?? false) {
            $result = $this->manager->executeCommand($_POST['category'], $_POST['command']);
        }
        ?>
        <div class="tab-content active">
            <h1>💻 نافذة الأوامر</h1>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-terminal"></i> تنفيذ الأوامر</h2>
                </div>
                
                <form method="post">
                    <div class="form-grid">
                        <select name="category" required>
                            <option value="">اختر الفئة</option>
                            <?php foreach ($commands as $category => $cmds): ?>
                                <option value="<?php echo $category; ?>"><?php echo ucfirst($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="command" required>
                            <option value="">اختر الأمر</option>
                        </select>
                        
                        <button type="submit" name="execute" class="btn btn-primary">
                            <i class="fas fa-play"></i> تنفيذ الأمر
                        </button>
                    </div>
                </form>
                
                <div class="quick-commands">
                    <?php foreach ($commands as $category => $cmds): ?>
                        <?php foreach (array_slice($cmds, 0, 2) as $cmd): ?>
                            <button onclick="executeCommand('<?php echo $category; ?>', '<?php echo $cmd; ?>')" 
                                    class="btn btn-success">
                                <i class="fas fa-bolt"></i> <?php echo substr($cmd, 0, 25) . (strlen($cmd) > 25 ? '...' : ''); ?>
                            </button>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="command-output" id="command-output" style="min-height: 200px;">
                    <?php if ($result): ?>
                        <?php 
                        if (isset($result['error'])) {
                            echo '<div style="color: red">❌ ' . $result['error'] . '</div>';
                        } else {
                            echo implode("\n", $result['output']);
                        }
                        ?>
                    <?php else: ?>
                        <div style="color: #666; text-align: center; padding: 2rem;">
                            <i class="fas fa-terminal fa-2x" style="margin-bottom: 1rem;"></i>
                            <p>سيظهر ناتج الأمر هنا بعد التنفيذ</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function renderFilesTab() {
        $path = $_GET['path'] ?? '';
        $files = $this->manager->fileManager('list', $path);
        ?>
        <div class="tab-content active">
            <h1>📁 إدارة الملفات</h1>
            
            <div class="breadcrumb">
                <a href="?tab=files">Root</a>
                <?php if ($path): ?>
                    <i class="fas fa-chevron-left"></i>
                    <span><?php echo $path; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-folder-open"></i> متصفح الملفات</h2>
                    <a href="?tab=upload" class="btn btn-primary">
                        <i class="fas fa-upload"></i> رفع ملف
                    </a>
                </div>
                
                <div class="file-list">
                    <?php if (isset($files['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $files['error']; ?>
                        </div>
                    <?php elseif (empty($files)): ?>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-folder-open fa-3x" style="margin-bottom: 1rem;"></i>
                            <p>المجلد فارغ</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($files as $file): ?>
                            <div class="file-item">
                                <div class="file-info">
                                    <i class="fas fa-<?php echo $file['type'] == 'directory' ? 'folder text-warning' : 'file text-primary'; ?>"></i>
                                    <span>
                                        <?php if ($file['type'] == 'directory'): ?>
                                            <a href="?tab=files&path=<?php echo urlencode($file['name']); ?>" style="color: inherit; text-decoration: none;">
                                                <?php echo $file['name']; ?>/
                                            </a>
                                        <?php else: ?>
                                            <?php echo $file['name']; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="file-actions">
                                    <span class="file-size"><?php echo $file['size'] ?? '-'; ?></span>
                                    <span class="file-perms"><?php echo $file['permissions']; ?></span>
                                    <?php if ($file['type'] == 'file'): ?>
                                        <button onclick="viewFile('<?php echo $file['path']; ?>')" class="btn btn-info btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function renderEditorTab() {
        $filePath = $_POST['file'] ?? '';
        $content = '';
        
        if ($filePath) {
            $fileContent = $this->manager->getFileContent($filePath);
            if (!isset($fileContent['error'])) {
                $content = $fileContent['content'];
            }
        }
        ?>
        <div class="tab-content active">
            <h1>📝 محرر الملفات</h1>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-edit"></i> محرر النصوص</h2>
                    <div>
                        <input type="text" id="editor-file" value="<?php echo htmlspecialchars($filePath); ?>" 
                               placeholder="مسار الملف" style="width: 300px;">
                        <button onclick="saveFile()" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ
                        </button>
                    </div>
                </div>
                
                <textarea id="editor-content" style="width: 100%; height: 500px; font-family: 'Courier New', monospace;"><?php echo $content; ?></textarea>
            </div>
        </div>
        <?php
    }
    
    private function renderUploadTab() {
        if ($_FILES['file'] ?? false) {
            $result = $this->manager->uploadFile($_FILES['file'], $_POST['upload_path'] ?? '');
        }
        ?>
        <div class="tab-content active">
            <h1>📤 رفع الملفات</h1>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-upload"></i> رفع ملف جديد</h2>
                </div>
                
                <?php if (isset($result)): ?>
                    <div class="<?php echo isset($result['error']) ? 'alert alert-error' : 'alert alert-success'; ?>">
                        <i class="fas <?php echo isset($result['error']) ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                        <?php echo $result['error'] ?? $result['success']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data">
                    <div style="margin-bottom: 1rem;">
                        <label>مسار الرفع (اختياري):</label>
                        <input type="text" name="upload_path" placeholder="./uploads/" 
                               style="width: 100%; padding: 0.5rem; margin-top: 0.5rem;">
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <input type="file" name="file" required 
                               style="width: 100%; padding: 1rem; border: 2px dashed #d1d5db; border-radius: 8px;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-upload"></i> رفع الملف
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    private function renderSystemTab() {
        $systemInfo = $this->manager->getSystemInfo();
        ?>
        <div class="tab-content active">
            <h1>ℹ️ معلومات النظام</h1>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-server"></i> معلومات الخادم</h2>
                    <button onclick="refreshSystemInfo()" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> تحديث
                    </button>
                </div>
                <pre><?php print_r($systemInfo); ?></pre>
            </div>
        </div>
        <?php
    }
}

// معالجة طلبات AJAX
if ($_GET['ajax'] ?? false) {
    header('Content-Type: application/json');
    $manager = new AdvancedServerManager();
    
    switch($_GET['ajax']) {
        case 'command':
            $result = $manager->executeCommand($_GET['category'], $_GET['command']);
            echo json_encode($result);
            break;
        case 'system_info':
            echo json_encode($manager->getSystemInfo());
            break;
        case 'view_file':
            $result = $manager->getFileContent($_GET['file']);
            echo json_encode($result);
            break;
    }
    exit;
}

// معالجة حفظ الملف
if ($_POST['ajax'] == 'save_file' ?? false) {
    header('Content-Type: application/json');
    $manager = new AdvancedServerManager();
    $result = $manager->saveFileContent($_POST['file'], $_POST['content']);
    echo json_encode($result);
    exit;
}

// عرض لوحة التحكم
$panel = new ModernAdminPanel();
$panel->renderDashboard();
?>