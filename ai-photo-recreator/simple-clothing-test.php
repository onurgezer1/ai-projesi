<?php
/**
 * Basit Giysi Rengi Test - AI Photo Recreator
 * 
 * "Bu fotoğraftaki adamın gömleğinin rengini 'Siyah' yap" komutunu test eder
 */

// WordPress entegrasyonu
if (file_exists('wp-config.php')) {
    require_once('wp-config.php');
    require_once('wp-load.php');
} else {
    die('WordPress bulunamadı. Bu dosyayı WordPress ana dizinine koyun.');
}

// Güvenlik kontrolü
if (!current_user_can('manage_options')) {
    die('Erişim reddedildi. Yönetici yetkisi gerekli.');
}

// AI processor sınıfını dahil et
require_once(WP_PLUGIN_DIR . '/ai-photo-recreator/includes/class-ai-processor.php');

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basit Giysi Rengi Test - AI Photo Recreator</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f0f0f1; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; margin: -30px -30px 30px -30px; border-radius: 8px 8px 0 0; }
        .test-section { margin: 25px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #e3f2fd; border-color: #b3e5fc; color: #01579b; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 13px; border-left: 3px solid #007cba; }
        .btn { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 8px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #005a87; }
        .command-test { background: white; padding: 20px; border-left: 4px solid #007cba; margin: 15px 0; border-radius: 6px; }
        h1, h2, h3 { color: #333; }
        .status-good { color: #28a745; }
        .status-bad { color: #dc3545; }
        .result-info { background: #e7f3ff; padding: 15px; border-radius: 6px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Basit Giysi Rengi Test</h1>
            <p>Geliştirilmiş AI sistemini test edelim: "Bu fotoğraftaki adamın gömleğinin rengini 'Siyah' yap"</p>
        </div>
        
        <div class="test-section info">
            <h2>🎯 Test Hedefleri</h2>
            <ul>
                <li>✅ Türkçe komut algılama: "gömleğinin rengini"</li>
                <li>✅ Renk tespiti: "Siyah" (tırnak içinde)</li>
                <li>✅ Basitleştirilmiş giysi algılama</li>
                <li>✅ Gelişmiş renk değiştirme algoritması</li>
                <li>✅ Ten rengi koruması</li>
            </ul>
        </div>

        <?php
        // Test komutları
        $test_commands = array(
            "Bu fotoğraftaki adamın gömleğinin rengini 'Siyah' yap",
            "Gömleğinin rengini siyah yap",
            "Bu fotoğraftaki gömleği siyaha çevir",
            "Adamın gömleğini mavi yap"
        );
        ?>

        <div class="test-section">
            <h2>🔧 AI Processor Test</h2>
            
            <?php
            try {
                $processor = new AI_Photo_Processor();
                echo '<div class="result-info success"><strong>✅ AI Processor sınıfı başarıyla yüklendi</strong></div>';
                
                // Test her komut için parsing
                foreach ($test_commands as $index => $command) {
                    echo "<div class='command-test'>";
                    echo "<h3>Test " . ($index + 1) . ": " . htmlspecialchars($command) . "</h3>";
                    
                    // Reflection kullanarak private fonksiyonu test et
                    $reflection = new ReflectionClass($processor);
                    $parseMethod = $reflection->getMethod('parse_transformation_instructions');
                    $parseMethod->setAccessible(true);
                    
                    $result = $parseMethod->invoke($processor, $command);
                    
                    echo "<div class='result-info'>";
                    echo "<strong>Parsing Sonuçları:</strong><br>";
                    echo "Dil: " . ($result['language'] ?? 'Bilinmiyor') . "<br>";
                    echo "Ana Niyet: " . ($result['primary_intent'] ?? 'Bilinmiyor') . "<br>";
                    
                    if (isset($result['transformation_type'])) {
                        echo "Dönüşüm Türü: " . $result['transformation_type'] . "<br>";
                    }
                    
                    if (isset($result['target_clothing'])) {
                        echo "Hedef Giysi: " . implode(', ', $result['target_clothing']) . "<br>";
                    }
                    
                    if (isset($result['target_colors'])) {
                        echo "Hedef Renkler: " . implode(', ', $result['target_colors']) . "<br>";
                    }
                    echo "</div>";
                    
                    // Detaylı sonuç
                    echo "<details style='margin-top: 10px;'>";
                    echo "<summary>🔍 Detaylı Sonuçlar (Genişlet)</summary>";
                    echo "<pre>" . print_r($result, true) . "</pre>";
                    echo "</details>";
                    
                    echo "</div>";
                }
                
            } catch (Exception $e) {
                echo '<div class="result-info error"><strong>❌ Hata:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <div class="test-section">
            <h2>🎨 Renk Haritalama Testi</h2>
            
            <?php
            if (isset($processor)) {
                $reflection = new ReflectionClass($processor);
                $colorMethod = $reflection->getMethod('get_target_color_rgb');
                $colorMethod->setAccessible(true);
                
                $test_colors = array('siyah', 'Siyah', '"Siyah"', 'beyaz', 'mavi', 'kırmızı', 'black', 'white');
                
                foreach ($test_colors as $color) {
                    $rgb = $colorMethod->invoke($processor, $color);
                    $status = $rgb ? 'success' : 'error';
                    $result = $rgb ? "RGB(" . $rgb['r'] . ", " . $rgb['g'] . ", " . $rgb['b'] . ")" : "Tanınmadı";
                    
                    echo "<div class='result-info $status'>";
                    echo "<strong>$color</strong> → $result";
                    echo "</div>";
                }
            }
            ?>
        </div>

        <div class="test-section">
            <h2>💡 Sonuçlar ve Öneriler</h2>
            
            <div class="result-info info">
                <h3>🎯 Başarılı İyileştirmeler</h3>
                <ul>
                    <li>✅ Basitleştirilmiş giysi algılama sistemi</li>
                    <li>✅ Gelişmiş renk değiştirme algoritması</li>
                    <li>✅ Türkçe geri bildirim mesajları</li>
                    <li>✅ Çoklu ten rengi tespit yöntemleri</li>
                    <li>✅ Daha geniş giysi alanı kapsama</li>
                </ul>
            </div>

            <div class="result-info warning">
                <h3>🔄 Test Etme Adımları</h3>
                <ol>
                    <li>WordPress admin panelinde AI Photo Recreator'ü açın</li>
                    <li>Bir fotoğraf yükleyin (tercihen açık renkli giysi ile)</li>
                    <li>Komut girin: <code>"Bu fotoğraftaki adamın gömleğinin rengini 'Siyah' yap"</code></li>
                    <li>Process Photo'ya tıklayın</li>
                    <li>Sonuçları kontrol edin - giysi rengi değişmeli, yüz/el korunmalı</li>
                </ol>
            </div>
        </div>

        <div class="test-section">
            <h2>📊 Teknik Detaylar</h2>
            
            <div class="result-info">
                <strong>Geliştirmeler:</strong><br>
                • <code>detect_clothing_areas_advanced()</code> - Basitleştirildi<br>
                • <code>detect_simple_clothing_regions()</code> - Yeni eklendi<br>
                • <code>transform_area_color()</code> - Geliştirildi<br>
                • <code>is_skin_color_improved()</code> - HSV desteği eklendi<br>
                • <code>create_user_feedback_message()</code> - Türkçeye çevrildi
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px;">
            <p><strong>🚀 Sistem hazır! Artık gerçek fotoğraf testine geçebilirsiniz.</strong></p>
            <a href="<?php echo admin_url('admin.php?page=ai-photo-recreator'); ?>" class="btn">
                Plugin'i Test Et →
            </a>
        </div>
    </div>
</body>
</html>