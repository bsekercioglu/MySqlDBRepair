<?php
// MySQL Veritabanı Bakım ve Yedekleme Scripti
// kurs veritabanı için tablo bakımı ve otomatik yedekleme

header('Content-Type: text/html; charset=utf-8');

// Dosya boyutunu okunabilir formata çevir
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// mysqldump çalışmazsa PHP ile yedek oluştur
function createBackupManually($pdo, $dbname, $backupPath) {
    try {
        $pdo->exec("USE `$dbname`");
        
        $backup = "-- MySQL Dump\n";
        $backup .= "-- Veritabanı: $dbname\n";
        $backup .= "-- Tarih: " . date('Y-m-d H:i:s') . "\n\n";
        $backup .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup .= "SET time_zone = \"+00:00\";\n\n";
        $backup .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
        $backup .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
        $backup .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
        $backup .= "/*!40101 SET NAMES utf8mb4 */;\n\n";
        $backup .= "DROP DATABASE IF EXISTS `$dbname`;\n";
        $backup .= "CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
        $backup .= "USE `$dbname`;\n\n";
        
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $createTable = $stmt->fetch();
                if (isset($createTable['Create Table'])) {
                    $backup .= "\n-- Tablo yapısı: $table\n";
                    $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                    $backup .= $createTable['Create Table'] . ";\n\n";
                    
                    $stmt = $pdo->query("SELECT * FROM `$table`");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $backup .= "-- Tablo verileri: $table\n";
                    $columns = array_keys($rows[0]);
                    
                    foreach ($rows as $row) {
                        $values = array();
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = $pdo->quote($value);
                            }
                        }
                        $backup .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }
        }
        
        $backup .= "\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
        $backup .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
        $backup .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
        
        file_put_contents($backupPath, $backup);
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

// Veritabanı bağlantı bilgileri
$host = 'localhost';
$dbname = 'kurs';
$username = 'root'; // Kullanıcı adınızı buraya yazın
$password = ''; // Şifrenizi buraya yazın

// HTML çıktı bufferı başlat
ob_start();

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
    
    // HTML başlangıcı
    echo '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySQL Veritabanı Bakım Raporu</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
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
        .header .info {
            font-size: 1.1em;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        .summary h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-box .label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }
        .stat-box .value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-box.success .value { color: #28a745; }
        .stat-box.error .value { color: #dc3545; }
        .table-section {
            margin-top: 30px;
        }
        .table-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        .table-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .table-card.error {
            border-left-color: #dc3545;
        }
        .table-card.success {
            border-left-color: #28a745;
        }
        .table-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        .process-list {
            list-style: none;
            margin: 10px 0;
        }
        .process-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .process-item .label {
            font-weight: 500;
            color: #555;
        }
        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .status.ok {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status.na {
            background: #e2e3e5;
            color: #383d41;
        }
        .error-list {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        .error-list .error-item {
            color: #721c24;
            margin: 5px 0;
            padding-left: 20px;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            border-top: 1px solid #dee2e6;
        }
        .backup-section {
            background: #e7f3ff;
            border: 2px solid #2196F3;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        .backup-section h2 {
            color: #1976D2;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .btn-download {
            display: inline-block;
            padding: 15px 30px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1em;
            margin: 10px 5px;
            transition: background 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-download:hover {
            background: #218838;
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }
        .backup-info {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #2196F3;
        }
        .backup-info p {
            margin: 5px 0;
            color: #333;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>MySQL Veritabanı Bakım Raporu</h1>
        <div class="info">
            Veritabanı: <strong>' . htmlspecialchars($dbname) . '</strong> | 
            Sunucu: <strong>' . htmlspecialchars($host) . '</strong>
        </div>
    </div>
    <div class="content">';
    
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    if ($stmt->rowCount() == 0) {
        echo '<div class="error-list"><strong>HATA:</strong> \'' . htmlspecialchars($dbname) . '\' veritabanı bulunamadı!</div></div></div></body></html>';
        exit;
    }
    
    $pdo->exec("USE `$dbname`");
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo '<div class="error-list"><strong>UYARI:</strong> Veritabanında tablo bulunamadı!</div></div></div></body></html>';
        exit;
    }
    
    echo '<div class="summary">
        <h2>Bakım Özeti</h2>
        <p><strong>Toplam ' . count($tables) . ' tablo bulundu.</strong></p>
    </div>
    <div class="table-section">
        <h2>Tablo Detayları</h2>';
    
    $results = [
        'toplam_tablo' => count($tables),
        'basarili' => 0,
        'hata' => 0,
        'detaylar' => []
    ];
    
    foreach ($tables as $table) {
        $tableResult = [
            'tablo' => $table,
            'check' => null,
            'analyze' => null,
            'optimize' => null,
            'repair' => null,
            'hata' => []
        ];
        
        // Tablo bütünlüğü kontrolü
        try {
            $stmt = $pdo->query("CHECK TABLE `$table`");
            $checkResults = $stmt->fetchAll();
            
            if (!empty($checkResults)) {
                $checkResult = $checkResults[0];
                if (isset($checkResult['Msg_text'])) {
                    $status = $checkResult['Msg_text'];
                    if (stripos($status, 'OK') !== false || stripos($status, 'status') !== false) {
                        $tableResult['check'] = 'OK';
                    } else {
                        $tableResult['check'] = $status;
                    }
                }
            }
        } catch (Exception $e) {
            $tableResult['check'] = 'HATA';
            $tableResult['hata'][] = "CHECK: " . $e->getMessage();
        }
        
        // İstatistikleri güncelle
        try {
            $stmt = $pdo->query("ANALYZE TABLE `$table`");
            $stmt->fetchAll();
            $tableResult['analyze'] = 'OK';
        } catch (Exception $e) {
            $tableResult['analyze'] = 'HATA';
            $tableResult['hata'][] = "ANALYZE: " . $e->getMessage();
        }
        
        // Tabloyu optimize et
        try {
            $stmt = $pdo->query("OPTIMIZE TABLE `$table`");
            $optimizeResults = $stmt->fetchAll();
            
            if (!empty($optimizeResults)) {
                $optimizeResult = $optimizeResults[0];
                if (isset($optimizeResult['Msg_text'])) {
                    $status = $optimizeResult['Msg_text'];
                    if (stripos($status, 'OK') !== false || stripos($status, 'status') !== false) {
                        $tableResult['optimize'] = 'OK';
                    } else {
                        $tableResult['optimize'] = $status;
                    }
                } else {
                    $tableResult['optimize'] = 'OK';
                }
            } else {
                $tableResult['optimize'] = 'OK';
            }
        } catch (Exception $e) {
            $tableResult['optimize'] = 'HATA';
            $tableResult['hata'][] = "OPTIMIZE: " . $e->getMessage();
        }
        
        // MyISAM tabloları için onarım kontrolü
        try {
            $stmt = $pdo->query("SHOW TABLE STATUS LIKE '$table'");
            $tableStatuses = $stmt->fetchAll();
            
            if (!empty($tableStatuses)) {
                $tableStatus = $tableStatuses[0];
                
                if (isset($tableStatus['Engine']) && strtoupper($tableStatus['Engine']) == 'MYISAM') {
                    $stmt = $pdo->query("REPAIR TABLE `$table`");
                    $repairResults = $stmt->fetchAll();
                    
                    if (!empty($repairResults)) {
                        $repairResult = $repairResults[0];
                        if (isset($repairResult['Msg_text'])) {
                            $status = $repairResult['Msg_text'];
                            if (stripos($status, 'OK') !== false) {
                                $tableResult['repair'] = 'OK';
                            } else {
                                $tableResult['repair'] = $status;
                            }
                        } else {
                            $tableResult['repair'] = 'OK';
                        }
                    } else {
                        $tableResult['repair'] = 'OK';
                    }
                } else {
                    $tableResult['repair'] = 'N/A';
                }
            } else {
                $tableResult['repair'] = 'ATLANDI';
            }
        } catch (Exception $e) {
            $tableResult['repair'] = 'ATLANDI';
        }
        
        $cardClass = empty($tableResult['hata']) ? 'success' : 'error';
        if (empty($tableResult['hata'])) {
            $results['basarili']++;
        } else {
            $results['hata']++;
        }
        
        $getStatusClass = function($status) {
            if ($status === 'OK' || stripos($status, 'OK') !== false) return 'ok';
            if ($status === 'HATA' || stripos($status, 'HATA') !== false) return 'error';
            if ($status === 'N/A' || $status === 'ATLANDI') return 'na';
            return 'warning';
        };
        
        echo '<div class="table-card ' . $cardClass . '">
            <div class="table-name">' . htmlspecialchars($table) . '</div>
            <ul class="process-list">';
        
        echo '<li class="process-item">
            <span class="label">[1/4] Tablo Bütünlüğü Kontrolü</span>
            <span class="status ' . $getStatusClass($tableResult['check'] ?? 'N/A') . '">' . htmlspecialchars($tableResult['check'] ?? 'N/A') . '</span>
        </li>';
        
        echo '<li class="process-item">
            <span class="label">[2/4] İstatistik Güncelleme</span>
            <span class="status ' . $getStatusClass($tableResult['analyze'] ?? 'N/A') . '">' . htmlspecialchars($tableResult['analyze'] ?? 'N/A') . '</span>
        </li>';
        
        echo '<li class="process-item">
            <span class="label">[3/4] Tablo Optimizasyonu</span>
            <span class="status ' . $getStatusClass($tableResult['optimize'] ?? 'N/A') . '">' . htmlspecialchars($tableResult['optimize'] ?? 'N/A') . '</span>
        </li>';
        
        echo '<li class="process-item">
            <span class="label">[4/4] Tablo Onarım Kontrolü</span>
            <span class="status ' . $getStatusClass($tableResult['repair'] ?? 'N/A') . '">' . htmlspecialchars($tableResult['repair'] ?? 'N/A') . '</span>
        </li>';
        
        echo '</ul>';
        
        if (!empty($tableResult['hata'])) {
            echo '<div class="error-list">';
            foreach ($tableResult['hata'] as $hata) {
                echo '<div class="error-item">' . htmlspecialchars($hata) . '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
        
        $results['detaylar'][] = $tableResult;
    }
    
    $basariOrani = $results['toplam_tablo'] > 0 ? round(($results['basarili'] / $results['toplam_tablo']) * 100, 2) : 0;
    
    echo '</div>';
    
    echo '<div class="summary">
        <h2>Bakım Özeti</h2>
        <div class="stats">
            <div class="stat-box">
                <div class="label">Toplam Tablo</div>
                <div class="value">' . $results['toplam_tablo'] . '</div>
            </div>
            <div class="stat-box success">
                <div class="label">Başarılı</div>
                <div class="value">' . $results['basarili'] . '</div>
            </div>
            <div class="stat-box error">
                <div class="label">Hata</div>
                <div class="value">' . $results['hata'] . '</div>
            </div>
            <div class="stat-box">
                <div class="label">Tamamlanma Oranı</div>
                <div class="value">' . $basariOrani . '%</div>
            </div>
        </div>
    </div>';
    
    echo '<div class="backup-section">
        <h2>💾 Veritabanı Yedeği</h2>
        <div class="backup-info">
            <p><strong>Bakım işlemi tamamlandı. Şimdi veritabanı yedeğini alabilirsiniz.</strong></p>
            <p>Yedek dosyası SQL formatında oluşturulacak ve indirilebilir olacaktır.</p>
        </div>';
    
    $backupFile = 'backup_' . $dbname . '_' . date('Y-m-d_H-i-s') . '.sql';
    $backupPath = __DIR__ . '/' . $backupFile;
    $backupSuccess = false;
    $backupError = '';
    
    try {
        // Önce mysqldump dene
        $command = sprintf(
            'mysqldump -h %s -u %s %s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($username),
            $password ? '-p' . escapeshellarg($password) : '',
            escapeshellarg($dbname),
            escapeshellarg($backupPath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($backupPath) && filesize($backupPath) > 0) {
            $backupSuccess = true;
        } else {
            // mysqldump çalışmadıysa PHP ile yedek oluştur
            $backupSuccess = createBackupManually($pdo, $dbname, $backupPath);
            if (!$backupSuccess) {
                $backupError = 'Yedek alma işlemi başarısız oldu.';
            }
        }
        
        if ($backupSuccess && file_exists($backupPath)) {
            $fileSize = filesize($backupPath);
            $fileSizeFormatted = formatBytes($fileSize);
            
            echo '<div class="backup-info" style="background: #d4edda; border-left-color: #28a745;">
                <p>✓ <strong>Yedek başarıyla oluşturuldu!</strong></p>
                <p>Dosya adı: <strong>' . htmlspecialchars($backupFile) . '</strong></p>
                <p>Dosya boyutu: <strong>' . $fileSizeFormatted . '</strong></p>
                <p style="margin-top: 15px;">
                    <a href="' . htmlspecialchars($backupFile) . '" class="btn-download" download>📥 Yedeği İndir</a>
                </p>
            </div>';
        } else {
            echo '<div class="backup-info" style="background: #f8d7da; border-left-color: #dc3545;">
                <p>✗ <strong>Yedek alma başarısız oldu!</strong></p>
                <p>Hata: ' . htmlspecialchars($backupError ?: 'Bilinmeyen hata') . '</p>
                <p><small>mysqldump komutuna erişilemiyor olabilir. PHP ile manuel yedek alma deneniyor...</small></p>
            </div>';
        }
    } catch (Exception $e) {
        echo '<div class="backup-info" style="background: #f8d7da; border-left-color: #dc3545;">
            <p>✗ <strong>Yedek alma sırasında hata oluştu!</strong></p>
            <p>Hata: ' . htmlspecialchars($e->getMessage()) . '</p>
        </div>';
    }
    
    echo '</div>';
    
    echo '</div>';
    echo '<div class="footer">
        Bakım işlemi tamamlandı! | ' . date('Y-m-d H:i:s') . '
    </div>';
    
    // Log dosyası oluştur
    $logFile = 'db_bakim_log_' . date('Y-m-d_H-i-s') . '.txt';
    $logContent = "Veritabanı Bakım Raporu\n";
    $logContent .= "Tarih: " . date('Y-m-d H:i:s') . "\n";
    $logContent .= "Veritabanı: $dbname\n";
    $logContent .= str_repeat("=", 70) . "\n\n";
    
    foreach ($results['detaylar'] as $detail) {
        $logContent .= "Tablo: " . $detail['tablo'] . "\n";
        $logContent .= "  CHECK: " . ($detail['check'] ?? 'N/A') . "\n";
        $logContent .= "  ANALYZE: " . ($detail['analyze'] ?? 'N/A') . "\n";
        $logContent .= "  OPTIMIZE: " . ($detail['optimize'] ?? 'N/A') . "\n";
        $logContent .= "  REPAIR: " . ($detail['repair'] ?? 'N/A') . "\n";
        if (!empty($detail['hata'])) {
            $logContent .= "  Hatalar:\n";
            foreach ($detail['hata'] as $hata) {
                $logContent .= "    - $hata\n";
            }
        }
        $logContent .= "\n";
    }
    
    file_put_contents($logFile, $logContent);
    echo '<div style="text-align: center; padding: 20px; background: #e9ecef; margin-top: 20px; border-radius: 5px;">
        <strong>Detaylı rapor kaydedildi:</strong> ' . htmlspecialchars($logFile) . '
    </div>';
    
    echo '</div>';
    echo '</body></html>';
    
} catch (PDOException $e) {
    echo '<div class="container">
        <div class="header">
            <h1>Veritabanı Bağlantı Hatası</h1>
        </div>
        <div class="content">
            <div class="error-list">
                <h2>HATA: ' . htmlspecialchars($e->getMessage()) . '</h2>
                <p><strong>Lütfen bağlantı bilgilerinizi kontrol edin:</strong></p>
                <ul style="margin-top: 15px; padding-left: 30px;">
                    <li>Host: ' . htmlspecialchars($host) . '</li>
                    <li>Veritabanı: ' . htmlspecialchars($dbname) . '</li>
                    <li>Kullanıcı: ' . htmlspecialchars($username) . '</li>
                </ul>
            </div>
        </div>
    </div>
    </body></html>';
    exit(1);
} catch (Exception $e) {
    echo '<div class="container">
        <div class="header">
            <h1>Genel Hata</h1>
        </div>
        <div class="content">
            <div class="error-list">
                <h2>HATA: ' . htmlspecialchars($e->getMessage()) . '</h2>
            </div>
        </div>
    </div>
    </body></html>';
    exit(1);
}
?>
