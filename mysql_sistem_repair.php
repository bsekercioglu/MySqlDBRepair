<?php
<?php
// MySQL Sistem Tabloları Onarım Scripti

header('Content-Type: text/html; charset=utf-8');

// Veritabanı bağlantı bilgileri
$host = 'localhost';
$username = 'root'; // MySQL root kullanıcısı
$password = ''; // MySQL root şifresi

echo '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySQL Sistem Tabloları Onarım</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px;
        }
        .warning-box h2 {
            color: #856404;
            margin-bottom: 10px;
        }
        .warning-box ul {
            margin-left: 20px;
            color: #856404;
        }
        .content {
            padding: 30px;
        }
        .result-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        .result-box.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .result-box.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .result-box.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .command {
            background: #2d3748;
            color: #68d391;
            padding: 15px;
            border-radius: 5px;
            font-family: "Courier New", monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚠️ MySQL Sistem Tabloları Onarım</h1>
        <p>mysql.user ve diğer sistem tabloları için onarım scripti</p>
    </div>
    <div class="content">
        <div class="warning-box">
            <h2>⚠️ ÖNEMLİ UYARI!</h2>
            <p><strong>Bu script MySQL sistem veritabanına müdahale eder!</strong></p>
            <ul>
                <li>Bu işlemi yapmadan önce MySQL veritabanını durdurmanız önerilir</li>
                <li>İşlem sırasında MySQL servisine erişim kesilebilir</li>
                <li>Önemli verilerinizin yedeğini alın</li>
                <li>Bu işlem sadece sistem yöneticileri tarafından yapılmalıdır</li>
            </ul>
        </div>';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
    
    echo '<div class="result-box success">
        <h3>✓ MySQL Bağlantısı Başarılı</h3>
        <p>Sunucu: <strong>' . htmlspecialchars($host) . '</strong></p>
    </div>';
    
    $pdo->exec("USE mysql");
    
    echo '<div class="result-box">
        <h3>MySQL Sistem Veritabanına Bağlanıldı</h3>
    </div>';
    
    echo '<div class="result-box">
        <h3>1. mysql.user Tablosunu Kontrol Ediliyor...</h3>';
    
    try {
        $stmt = $pdo->query("CHECK TABLE user");
        $results = $stmt->fetchAll();
        
        $userTableStatus = '';
        foreach ($results as $result) {
            if (isset($result['Msg_text'])) {
                $userTableStatus = $result['Msg_text'];
                echo '<p>Durum: <strong>' . htmlspecialchars($userTableStatus) . '</strong></p>';
            }
        }
        
        if (stripos($userTableStatus, 'OK') === false && stripos($userTableStatus, 'status') === false) {
            echo '<div class="result-box warning">
                <h3>⚠️ mysql.user Tablosunda Sorun Tespit Edildi!</h3>
                <p>Tablo onarımı gerekli görünüyor.</p>
            </div>';
            
            echo '<div class="result-box">
                <h3>2. Onarım Komutu</h3>
                <p>Aşağıdaki komutu MySQL komut satırından çalıştırın:</p>
                <div class="command">
                    mysqlcheck -u root -p --repair mysql user
                </div>
                <p>Veya MySQL komut satırında:</p>
                <div class="command">
                    USE mysql;<br>
                    REPAIR TABLE user;
                </div>
            </div>';
        } else {
            echo '<div class="result-box success">
                <h3>✓ mysql.user Tablosu Sağlıklı Görünüyor</h3>
            </div>';
        }
    } catch (Exception $e) {
        echo '<div class="result-box error">
            <h3>✗ HATA: ' . htmlspecialchars($e->getMessage()) . '</h3>
        </div>';
    }
    
    echo '</div>';
    
    $systemTables = ['db', 'tables_priv', 'columns_priv', 'procs_priv', 'func', 'host'];
    
    echo '<div class="result-box">
        <h3>3. Diğer Sistem Tablolarını Kontrol Ediliyor...</h3>';
    
    foreach ($systemTables as $table) {
        try {
            $stmt = $pdo->query("CHECK TABLE `$table`");
            $results = $stmt->fetchAll();
            
            $status = 'OK';
            foreach ($results as $result) {
                if (isset($result['Msg_text'])) {
                    $status = $result['Msg_text'];
                    break;
                }
            }
            
            if (stripos($status, 'OK') !== false || stripos($status, 'status') !== false) {
                echo '<p>✓ <strong>mysql.' . htmlspecialchars($table) . '</strong> - OK</p>';
            } else {
                echo '<p>⚠ <strong>mysql.' . htmlspecialchars($table) . '</strong> - ' . htmlspecialchars($status) . '</p>';
            }
        } catch (Exception $e) {
            echo '<p>✗ <strong>mysql.' . htmlspecialchars($table) . '</strong> - HATA: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    
    echo '</div>';
    
    // Manuel onarım talimatları
    echo '<div class="result-box warning">
        <h3>📋 Manuel Onarım Talimatları</h3>
        <p><strong>MySQL servisini durdurup başlatmanız gerekebilir:</strong></p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li>MySQL servisini durdurun (Windows: Services > MySQL > Stop)</li>
            <li>Komut satırından şu komutu çalıştırın:</li>
        </ol>
        <div class="command">
            mysqlcheck -u root -p --repair --all-databases
        </div>
        <p><strong>VEYA</strong> sadece mysql veritabanı için:</p>
        <div class="command">
            mysqlcheck -u root -p --repair mysql
        </div>
        <p><strong>VEYA</strong> MySQL komut satırında:</p>
        <div class="command">
            USE mysql;<br>
            REPAIR TABLE user;<br>
            REPAIR TABLE db;<br>
            REPAIR TABLE tables_priv;<br>
            REPAIR TABLE columns_priv;
        </div>
    </div>';
    
} catch (PDOException $e) {
    echo '<div class="result-box error">
        <h3>✗ VERİTABANI BAĞLANTI HATASI</h3>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <p>MySQL servisinin çalıştığından emin olun.</p>
    </div>';
} catch (Exception $e) {
    echo '<div class="result-box error">
        <h3>✗ GENEL HATA</h3>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
    </div>';
}

echo '    </div>
    <div style="padding: 20px; text-align: center; background: #f8f9fa; border-top: 1px solid #dee2e6;">
        <p style="color: #666;">MySQL Sistem Onarım Scripti | ' . date('Y-m-d H:i:s') . '</p>
    </div>
</div>
</body>
</html>';
?>
