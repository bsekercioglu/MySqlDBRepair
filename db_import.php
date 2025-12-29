<?php
// Veritabanı Import Scripti
// SQL backup dosyasından veritabanı geri yükleme işlemi

header('Content-Type: text/html; charset=utf-8');

// Config dosyası varsa onu kullan, yoksa varsayılan değerler
if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
    $host = $config['host'];
    $dbname = $config['dbname'];
    $username = $config['username'];
    $password = $config['password'];
} else {
    $host = 'localhost';
    $dbname = 'kurs';
    $username = 'root';
    $password = '';
}

// Dosya yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Dosya yükleme hatası!']);
        exit;
    }
    
    $uploadedFile = $_FILES['sql_file']['tmp_name'];
    $fileName = $_FILES['sql_file']['name'];
    
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        $pdo->exec("USE `$dbname`");
        
        // Mevcut tabloları tespit et ve yedekle
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $backupData = [];
        if (!empty($existingTables)) {
            $backupFile = 'backup_before_import_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = __DIR__ . '/' . $backupFile;
            
            foreach ($existingTables as $table) {
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $createTable = $stmt->fetch();
                if (isset($createTable['Create Table'])) {
                    $backupData[] = "-- Tablo: $table\n";
                    $backupData[] = "DROP TABLE IF EXISTS `$table`;\n";
                    $backupData[] = $createTable['Create Table'] . ";\n\n";
                    
                    $stmt = $pdo->query("SELECT * FROM `$table`");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        foreach ($rows as $row) {
                            $values = [];
                            foreach ($row as $value) {
                                $values[] = $value === null ? 'NULL' : $pdo->quote($value);
                            }
                            $backupData[] = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $backupData[] = "\n";
                    }
                }
            }
            
            file_put_contents($backupPath, implode('', $backupData));
        }
        
        // Mevcut tabloları drop et
        foreach ($existingTables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        
        // SQL dosyasını oku
        $sqlContent = file_get_contents($uploadedFile);
        
        // BOM karakterini temizle
        $sqlContent = preg_replace('/^\xEF\xBB\xBF/', '', $sqlContent);
        
        // Yorumları temizle
        $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
        $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
        
        // SQL komutlarını güvenli şekilde böl
        $commands = [];
        $currentCommand = '';
        $inQuotes = false;
        $quoteChar = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        
        $length = strlen($sqlContent);
        for ($i = 0; $i < $length; $i++) {
            $char = $sqlContent[$i];
            $prevChar = ($i > 0) ? $sqlContent[$i - 1] : '';
            $nextChar = ($i < $length - 1) ? $sqlContent[$i + 1] : '';
            
            // Escape karakteri kontrolü
            if ($char === '\\' && ($inSingleQuote || $inDoubleQuote)) {
                $currentCommand .= $char;
                if ($i < $length - 1) {
                    $currentCommand .= $nextChar;
                    $i++;
                }
                continue;
            }
            
            // Tek tırnak
            if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
                $inSingleQuote = !$inSingleQuote;
                $currentCommand .= $char;
                continue;
            }
            
            // Çift tırnak
            if ($char === '"' && !$inSingleQuote && !$inBacktick) {
                $inDoubleQuote = !$inDoubleQuote;
                $currentCommand .= $char;
                continue;
            }
            
            // Backtick
            if ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
                $currentCommand .= $char;
                continue;
            }
            
            $currentCommand .= $char;
            
            // Noktalı virgül bulundu ve quote içinde değiliz
            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $cmd = trim($currentCommand);
                $currentCommand = '';
                
                if (!empty($cmd)) {
                    // Gereksiz komutları filtrele
                    $skipCommands = ['SET', 'USE', '/*', '--', 'DELIMITER'];
                    $shouldSkip = false;
                    
                    foreach ($skipCommands as $skip) {
                        if (stripos($cmd, $skip) === 0) {
                            $shouldSkip = true;
                            break;
                        }
                    }
                    
                    if (!$shouldSkip) {
                        $commands[] = $cmd;
                    }
                }
            }
        }
        
        // Son komutu ekle (eğer noktalı virgül ile bitmediyse)
        if (!empty(trim($currentCommand))) {
            $cmd = trim($currentCommand);
            if (!empty($cmd) && stripos($cmd, 'SET') !== 0 && stripos($cmd, 'USE') !== 0) {
                $commands[] = $cmd;
            }
        }
        
        // Tablo isimlerini ve INSERT sayılarını tespit et
        $tableStats = [];
        foreach ($commands as $cmd) {
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i', $cmd, $matches)) {
                $tableName = $matches[1];
                if (!isset($tableStats[$tableName])) {
                    $tableStats[$tableName] = ['creates' => 0, 'inserts' => 0];
                }
                $tableStats[$tableName]['creates']++;
            } elseif (preg_match('/INSERT\s+(?:INTO\s+)?[`"]?(\w+)[`"]?/i', $cmd, $matches)) {
                $tableName = $matches[1];
                if (!isset($tableStats[$tableName])) {
                    $tableStats[$tableName] = ['creates' => 0, 'inserts' => 0];
                }
                $tableStats[$tableName]['inserts']++;
            }
        }
        
        $totalCommands = count($commands);
        $processedCommands = 0;
        $results = [];
        $currentTableProgress = [];
        
        // SQL komutlarını çalıştır
        foreach ($commands as $index => $cmd) {
            $cmd = trim($cmd);
            if (empty($cmd)) continue;
            
            try {
                $pdo->exec($cmd);
                $processedCommands++;
                
                // Hangi tablo üzerinde işlem yapıldığını tespit et
                $currentTable = null;
                $operation = 'SQL komutu';
                
                if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i', $cmd, $matches)) {
                    $currentTable = $matches[1];
                    $operation = 'Tablo oluşturuluyor';
                    $currentTableProgress[$currentTable] = ['total' => 0, 'done' => 0];
                } elseif (preg_match('/INSERT\s+(?:INTO\s+)?[`"]?(\w+)[`"]?/i', $cmd, $matches)) {
                    $currentTable = $matches[1];
                    $operation = 'Veri yükleniyor';
                    
                    if (!isset($currentTableProgress[$currentTable])) {
                        $currentTableProgress[$currentTable] = ['total' => 0, 'done' => 0];
                    }
                    
                    if (isset($tableStats[$currentTable])) {
                        $currentTableProgress[$currentTable]['total'] = $tableStats[$currentTable]['inserts'];
                    }
                    $currentTableProgress[$currentTable]['done']++;
                } elseif (preg_match('/DROP\s+TABLE/i', $cmd)) {
                    $operation = 'Tablo siliniyor';
                }
                
                $progress = ($processedCommands / $totalCommands) * 100;
                $message = '';
                
                if ($currentTable) {
                    $tableProgress = $currentTableProgress[$currentTable];
                    if ($tableProgress['total'] > 0) {
                        $tablePercent = ($tableProgress['done'] / $tableProgress['total']) * 100;
                        $message = "$currentTable - $operation (%" . round($tablePercent, 1) . ")";
                    } else {
                        $message = "$currentTable - $operation";
                    }
                } else {
                    $message = $operation . '...';
                }
                
                $results[] = [
                    'progress' => round($progress, 2),
                    'table' => $currentTable,
                    'message' => $message
                ];
                
                // Her 10 komutta bir ilerleme gönder (performans için)
                if ($processedCommands % 10 == 0 || $index == count($commands) - 1) {
                    flush();
                }
                
            } catch (PDOException $e) {
                // Bazı hataları yok say
                $errorMsg = $e->getMessage();
                $ignoreErrors = ['already exists', 'Unknown database', 'Duplicate entry'];
                $shouldIgnore = false;
                
                foreach ($ignoreErrors as $ignore) {
                    if (stripos($errorMsg, $ignore) !== false) {
                        $shouldIgnore = true;
                        break;
                    }
                }
                
                if (!$shouldIgnore) {
                    $results[] = [
                        'progress' => round(($processedCommands / $totalCommands) * 100, 2),
                        'error' => true,
                        'message' => 'Hata: ' . substr($errorMsg, 0, 100)
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Import işlemi tamamlandı!',
            'backup_file' => isset($backupFile) ? $backupFile : null,
            'results' => $results,
            'total_tables' => count($tableNames),
            'processed_commands' => $processedCommands
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Hata: ' . $e->getMessage()
        ]);
    }
    exit;
}

// HTML Sayfası
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veritabanı Import - SQL Geri Yükleme</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .content {
            padding: 40px;
        }
        .upload-area {
            border: 3px dashed #667eea;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            background: #e9ecef;
            border-color: #5568d3;
        }
        .upload-area.dragover {
            background: #d4edda;
            border-color: #28a745;
        }
        .upload-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        .file-input {
            display: none;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .file-info {
            margin-top: 20px;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 5px;
            display: none;
        }
        .file-info.show {
            display: block;
        }
        .progress-container {
            margin-top: 30px;
            display: none;
        }
        .progress-container.show {
            display: block;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .progress-details {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }
        .progress-item {
            padding: 8px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .progress-item.error {
            border-left-color: #dc3545;
            color: #721c24;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .warning-box h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        .warning-box ul {
            margin-left: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Veritabanı Import İşlemi</h1>
            <p>SQL Backup Dosyasından Geri Yükleme</p>
        </div>
        <div class="content">
            <div class="warning-box">
                <h3>⚠️ Önemli Uyarılar</h3>
                <ul>
                    <li>Bu işlem mevcut veritabanındaki <strong>TÜM TABLOLARI SİLECEKTİR</strong></li>
                    <li>İşlem öncesi otomatik olarak yedek alınacaktır</li>
                    <li>Yüklenecek dosyadan tablolar geri yüklenecektir</li>
                    <li>İşlem geri alınamaz, lütfen emin olun!</li>
                </ul>
            </div>
            
            <form id="importForm" enctype="multipart/form-data">
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📁</div>
                    <h3>SQL Dosyası Seçin</h3>
                    <p>Dosyayı buraya sürükleyip bırakın veya tıklayarak seçin</p>
                    <input type="file" id="sqlFile" name="sql_file" class="file-input" accept=".sql,.txt">
                    <button type="button" class="btn" onclick="document.getElementById('sqlFile').click()">
                        Dosya Seç
                    </button>
                </div>
                
                <div class="file-info" id="fileInfo">
                    <strong>Seçilen Dosya:</strong> <span id="fileName"></span><br>
                    <strong>Boyut:</strong> <span id="fileSize"></span>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-danger" id="importBtn" disabled>
                        Import İşlemini Başlat
                    </button>
                </div>
            </form>
            
            <div class="progress-container" id="progressContainer">
                <h3 style="margin-bottom: 15px;">İşlem İlerlemesi</h3>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill">0%</div>
                </div>
                <div class="progress-details" id="progressDetails"></div>
            </div>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('sqlFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const importBtn = document.getElementById('importBtn');
        const importForm = document.getElementById('importForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressDetails = document.getElementById('progressDetails');
        
        // Drag & Drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatBytes(file.size);
            fileInfo.classList.add('show');
            importBtn.disabled = false;
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        importForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // SweetAlert onay iste
            const result = await Swal.fire({
                title: '⚠️ DİKKAT!',
                html: `
                    <p><strong>Bu işlem mevcut veritabanındaki TÜM TABLOLARI SİLECEKTİR!</strong></p>
                    <p>İşlem öncesi otomatik yedek alınacaktır.</p>
                    <p>Yüklenecek dosya: <strong>${fileName.textContent}</strong></p>
                    <hr>
                    <p style="color: #dc3545;">İşlem geri alınamaz. Devam etmek istediğinize emin misiniz?</p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Import İşlemini Başlat',
                cancelButtonText: 'İptal',
                reverseButtons: true
            });
            
            if (!result.isConfirmed) {
                return;
            }
            
            // Form verilerini hazırla
            const formData = new FormData();
            formData.append('sql_file', fileInput.files[0]);
            formData.append('action', 'import');
            
            // UI hazırla
            importBtn.disabled = true;
            progressContainer.classList.add('show');
            progressFill.style.width = '0%';
            progressFill.textContent = '0%';
            progressDetails.innerHTML = '<div class="progress-item">İşlem başlatılıyor...</div>';
            
            // AJAX ile gönder
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    // Dosya yükleme ilerlemesi
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // İlerleme güncellemeleri
                            let currentProgress = 0;
                            const results = response.results || [];
                            
                            results.forEach((item, index) => {
                                setTimeout(() => {
                                    currentProgress = item.progress;
                                    progressFill.style.width = currentProgress + '%';
                                    progressFill.textContent = currentProgress.toFixed(1) + '%';
                                    
                                    const itemDiv = document.createElement('div');
                                    itemDiv.className = 'progress-item' + (item.error ? ' error' : '');
                                    itemDiv.textContent = item.message || `İşleniyor... ${currentProgress.toFixed(1)}%`;
                                    progressDetails.appendChild(itemDiv);
                                    progressDetails.scrollTop = progressDetails.scrollHeight;
                                }, index * 50);
                            });
                            
                            // İşlem tamamlandı
                            setTimeout(() => {
                                Swal.fire({
                                    title: '✅ Başarılı!',
                                    html: `
                                        <p>Import işlemi başarıyla tamamlandı!</p>
                                        <p><strong>Toplam Tablo:</strong> ${response.total_tables || 0}</p>
                                        <p><strong>İşlenen Komut:</strong> ${response.processed_commands || 0}</p>
                                        ${response.backup_file ? `<p><strong>Yedek Dosyası:</strong> ${response.backup_file}</p>` : ''}
                                    `,
                                    icon: 'success',
                                    confirmButtonText: 'Tamam'
                                }).then(() => {
                                    location.reload();
                                });
                            }, results.length * 50 + 500);
                            
                        } else {
                            Swal.fire({
                                title: '❌ Hata!',
                                text: response.message || 'Import işlemi başarısız oldu!',
                                icon: 'error',
                                confirmButtonText: 'Tamam'
                            });
                            importBtn.disabled = false;
                        }
                    } catch (e) {
                        Swal.fire({
                            title: '❌ Hata!',
                            text: 'Yanıt işlenirken bir hata oluştu!',
                            icon: 'error',
                            confirmButtonText: 'Tamam'
                        });
                        importBtn.disabled = false;
                    }
                } else {
                    Swal.fire({
                        title: '❌ Hata!',
                        text: 'Sunucu hatası oluştu!',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                    importBtn.disabled = false;
                }
            });
            
            xhr.addEventListener('error', () => {
                Swal.fire({
                    title: '❌ Hata!',
                    text: 'Bağlantı hatası oluştu!',
                    icon: 'error',
                    confirmButtonText: 'Tamam'
                });
                importBtn.disabled = false;
            });
            
            xhr.open('POST', 'db_import.php');
            xhr.send(formData);
        });
    </script>
</body>
</html>
