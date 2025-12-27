# MySQL Veritabanı Bakım ve Yedekleme Sistemi

Bu proje, MySQL veritabanlarınızın bakımını yapmak ve otomatik yedeklerini almak için geliştirilmiş bir PHP uygulamasıdır. Özellikle `kurs` veritabanı için tasarlanmış olsa da, kolayca başka veritabanları için de kullanılabilir.

## Projenin Amacı

Zamanla MySQL veritabanlarında biriken fragmantasyon, istatistik kayıpları ve potansiyel bütünlük sorunları veritabanı performansını düşürebilir. Bu script, bu sorunları otomatik olarak tespit edip çözerek veritabanınızın sağlıklı ve performanslı kalmasını sağlar. Ayrıca bakım işlemleri sonrasında otomatik yedek alma özelliği sayesinde verilerinizin güvenliğini garanti altına alır.

## Özellikler ve İşlevler

### Bakım İşlemleri

Her tablo için aşağıdaki bakım işlemleri sırasıyla gerçekleştirilir:

1. **CHECK TABLE**: Tablo bütünlüğü kontrolü yapılır. Bozuk index'ler, eksik veriler veya dosya sistemi sorunları tespit edilir.

2. **ANALYZE TABLE**: MySQL'in sorgu optimizer'ı için gerekli olan tablo istatistikleri güncellenir. Bu işlem, sorgu performansının artmasını sağlar.

3. **OPTIMIZE TABLE**: Tablo içindeki fragmantasyon giderilir, veriler yeniden organize edilir ve tablo boyutu optimize edilir. Bu özellikle sık sık INSERT/DELETE/UPDATE işlemi yapılan tablolarda önemlidir.

4. **REPAIR TABLE**: MyISAM motorunu kullanan tablolar için, eğer gerekiyorsa otomatik onarım işlemi yapılır. InnoDB tabloları için bu işlem genellikle gerekmez.

### Raporlama

- Her bakım işlemi için detaylı HTML raporu oluşturulur
- Başarılı ve başarısız işlemler ayrı ayrı gösterilir
- Tüm işlemler için log dosyası kaydedilir
- İstatistiksel özet bilgiler sunulur

### Otomatik Yedekleme

Bakım işlemleri tamamlandıktan sonra otomatik olarak veritabanı yedeği alınır:

- Öncelikle sisteminizdeki `mysqldump` komutu kullanılır
- Eğer mysqldump erişilebilir değilse, PHP ile manuel yedek oluşturulur
- Yedek dosyası SQL formatında kaydedilir
- Tek tıkla indirme imkanı sunulur

## Kurulum ve Gereksinimler

### Sistem Gereksinimleri

- PHP 7.0 veya üzeri (PDO extension ile)
- MySQL 5.5 veya üzeri / MariaDB 10.0 veya üzeri
- Web sunucusu (Apache, Nginx vb.) veya PHP CLI erişimi
- Veritabanına bağlanma yetkisi (SELECT, SHOW, CHECK, ANALYZE, OPTIMIZE, REPAIR izinleri)

### Kurulum Adımları

1. Proje dosyalarını web sunucunuzun çalışma dizinine kopyalayın

2. `db_bakim.php` veya `config.php` dosyasında veritabanı bağlantı bilgilerinizi güncelleyin:
   ```php
   $host = 'localhost';
   $dbname = 'kurs';
   $username = 'root';
   $password = 'şifreniz';
   ```

3. Tarayıcınızdan scripti çalıştırın veya komut satırından:
   ```bash
   php db_bakim.php
   ```

## Kullanım

### Yöntem 1: Tek Dosya ile Çalıştırma

`db_bakim.php` dosyasını kullanarak doğrudan çalıştırabilirsiniz:

1. Dosyayı bir metin editörü ile açın
2. Bağlantı bilgilerini düzenleyin (satır 92-95)
3. Web tarayıcısından dosyayı açın veya komut satırından çalıştırın

### Yöntem 2: Config Dosyası ile Çalıştırma

Daha düzenli bir yapı için config dosyası kullanabilirsiniz:

1. `config.php` dosyasını düzenleyip veritabanı bilgilerinizi girin
2. `db_bakim_config.php` dosyasını çalıştırın

Bu yöntem, özellikle birden fazla veritabanı için script kullanacaksanız daha pratik olacaktır.

## Çıktı Formatları

### HTML Raporu

Script çalıştırıldığında tarayıcıda görsel bir rapor oluşturulur:
- Renkli durum göstergeleri (yeşil: başarılı, kırmızı: hata, sarı: uyarı)
- Her tablo için detaylı işlem sonuçları
- Özet istatistikler (toplam tablo, başarılı işlem sayısı, hata sayısı)
- Yedek dosyası indirme butonu

### Log Dosyası

Her çalıştırmada `db_bakim_log_YYYY-MM-DD_HH-MM-SS.txt` formatında bir log dosyası oluşturulur. Bu dosya:
- Tüm tablolar için işlem sonuçlarını içerir
- Hata mesajlarını detaylı şekilde kaydeder
- Daha sonra inceleme yapmak için saklanabilir

### Yedek Dosyası

Bakım sonrası `backup_kurs_YYYY-MM-DD_HH-MM-SS.sql` formatında bir SQL dump dosyası oluşturulur. Bu dosya:
- Tüm tablo yapılarını içerir
- Tüm verileri INSERT komutları olarak içerir
- Standart MySQL dump formatındadır
- phpMyAdmin veya MySQL komut satırı ile geri yüklenebilir

## Performans ve Öneriler

### Büyük Veritabanları İçin

Eğer veritabanınız çok büyükse (100+ tablo veya GB boyutunda veriler):

- Scripti düşük trafik saatlerinde çalıştırın
- OPTIMIZE işlemi uzun sürebilir, sabırlı olun
- Yedek alma işlemi disk alanı gerektirir, yeterli alan olduğundan emin olun
- Script çalışırken veritabanı erişimi kısıtlanabilir

### Güvenlik Önerileri

- `config.php` dosyasını `.gitignore` içine ekleyin
- Veritabanı kullanıcısının yalnızca gerekli yetkilere sahip olduğundan emin olun
- Script dosyalarını doğrudan web'den erişilebilir olmayacak şekilde yapılandırın (örn: `.htaccess` ile IP kısıtlaması)
- Yedek dosyalarının düzenli olarak temizlenmesini sağlayın

### Zamanlama

Düzenli bakım için cron job veya Windows Task Scheduler kullanabilirsiniz:

**Linux/Mac (Cron):**
```bash
0 2 * * 0 /usr/bin/php /path/to/db_bakim.php
```

**Windows (Task Scheduler):**
- Program: `php.exe`
- Argument: `C:\path\to\db_bakim.php`
- Schedule: Haftalık, Pazar gecesi 02:00

## Sorun Giderme

### "Veritabanı bulunamadı" Hatası

- Veritabanı adının doğru yazıldığından emin olun
- MySQL kullanıcısının veritabanına erişim yetkisi olduğunu kontrol edin

### "PDO Exception" Hatası

- MySQL servisinin çalıştığından emin olun
- Bağlantı bilgilerinin (host, kullanıcı adı, şifre) doğru olduğunu kontrol edin
- Firewall ayarlarını kontrol edin

### Yedek Dosyası Oluşturulamıyor

- Disk alanının yeterli olduğundan emin olun
- Dosya yazma izinlerini kontrol edin
- `mysqldump` komutunun PATH'te olduğunu kontrol edin

### OPTIMIZE İşlemi Çok Uzun Sürüyor

- Bu normaldir, özellikle büyük tablolarda
- İşlemi kesmeyin, tamamlanmasını bekleyin
- Gerekirse scripti küçük gruplar halinde tablolar için çalıştırabilirsiniz

## Dosya Yapısı

```
PhpDBBakim/
├── db_bakim.php              # Ana bakım scripti (tek dosya)
├── db_bakim_config.php       # Config dosyası kullanan versiyon
├── config.php                # Veritabanı bağlantı ayarları
├── mysql_sistem_repair.php   # MySQL sistem tabloları onarım scripti
├── README.md                 # Bu dosya
├── backup_*.sql              # Oluşturulan yedek dosyaları
└── db_bakim_log_*.txt       # Log dosyaları
```

## Teknik Detaylar

### Kullanılan Teknolojiler

- PHP PDO (PHP Data Objects) - Veritabanı bağlantısı için
- MySQL CHECK/ANALYZE/OPTIMIZE/REPAIR komutları
- mysqldump - Yedekleme için
- HTML5/CSS3 - Rapor arayüzü için

### Kod Yapısı

Script, modüler bir yapıda değildir ancak kolayca genişletilebilir:
- `formatBytes()`: Dosya boyutu formatlaması
- `createBackupManually()`: PHP ile manuel yedek oluşturma
- Ana döngü: Her tablo için bakım işlemleri
- Rapor oluşturma: HTML ve text formatında

## İletişim ve Destek

Bu projeyle ilgili sorularınız veya önerileriniz için issue açabilir veya pull request gönderebilirsiniz.

## Lisans

Bu proje açık kaynak kodludur ve özgürce kullanılabilir.

---

**Not**: Bu script'i production ortamında kullanmadan önce test ortamında denemeniz önerilir. Özellikle OPTIMIZE ve REPAIR işlemleri veritabanını kilitleyebilir.
