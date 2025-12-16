<?php
// Функция для чтения метаданных файла
function getFileMetadata($filePath) {
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $baseName = pathinfo($filePath, PATHINFO_FILENAME);
    $metaFilePath = dirname($filePath) . '/' . $baseName . '.txt';
    
    if (file_exists($metaFilePath) && is_readable($metaFilePath)) {
        $metaContent = @file_get_contents($metaFilePath);
        if ($metaContent !== false) {
            $metaData = @json_decode($metaContent, true);
            if ($metaData && isset($metaData['original_name'])) {
                return $metaData;
            }
        }
    }
    
    return null;
}

// Функция для получения всех файлов, отсортированных по дате
function getFilesByDate() {
    $filesByDate = [];
    $uploadDir = 'uploads/';
    
    if (!is_dir($uploadDir)) {
        return $filesByDate;
    }
    
    $dates = array_filter(glob($uploadDir . '*'), 'is_dir');
    
    foreach ($dates as $dateDir) {
        $date = basename($dateDir);
        $files = array_filter(glob($dateDir . '/*'), 'is_file');
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            // Пропускаем текстовые файлы с метаданными
            if ($extension === 'txt') {
                continue;
            }
            
            $metadata = getFileMetadata($file);
            $originalName = $metadata ? $metadata['original_name'] : basename($file);
            $uploadDateTime = $metadata && isset($metadata['upload_date']) ? $metadata['upload_date'] : null;
            
            // Получаем размер файла
            $fileSize = @filesize($file);
            $formattedSize = formatFileSize($fileSize);
            
            $filesByDate[$date][] = [
                'path' => $file,
                'name' => basename($file),
                'original_name' => $originalName,
                'date' => $date,
                'upload_datetime' => $uploadDateTime,
                'size' => $formattedSize,
                'url' => str_replace('\\', '/', $file),
                'type' => getFileType($file)
            ];
        }
    }
    
    // Сортировка по дате (от новых к старым)
    krsort($filesByDate);
    
    return $filesByDate;
}

// Определение типа файла
function getFileType($file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    $videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
    
    if (in_array($extension, $imageExtensions)) {
        return 'image';
    } elseif (in_array($extension, $videoExtensions)) {
        return 'video';
    }
    return 'unknown';
}

// Форматирование размера файла
function formatFileSize($bytes) {
    if ($bytes === false || $bytes < 0) {
        return '0 B';
    }
    
    if ($bytes < 1024) {
        return number_format($bytes, 0, '.', '') . ' B';
    } elseif ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 2, '.', '') . ' KB';
    } else {
        return number_format($bytes / (1024 * 1024), 2, '.', '') . ' MB';
    }
}

$filesByDate = getFilesByDate();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Share</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Image Share</h1>
        
        <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
            <div class="form-group">
                <label for="file">Выберите изображение или видео:</label>
                <input type="file" id="file" name="file" accept="image/*,video/*" required>
            </div>
            <button type="submit" class="btn" id="upload-btn" disabled>Загрузить</button>
        </form>

        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] === 'delete'): ?>
                <div class="message success">Файл успешно удален!</div>
            <?php else: ?>
                <div class="message success">Файл успешно загружен!</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="message error">Ошибка: <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- Панель выбранных файлов -->
        <div class="selected-panel">
            <div class="selected-actions">
                <button type="button" class="btn" id="copy-selected" disabled>Копировать выбранные</button>
                <div class="separator-controls">
                    <label for="separator-type">Разделитель:</label>
                    <select id="separator-type" class="separator-select">
                        <option value="space">Пробел</option>
                        <option value="comma">Запятая</option>
                        <option value="semicolon">Точка с запятой</option>
                        <option value="custom">Кастомный</option>
                    </select>
                    <input type="text" id="custom-separator" class="custom-separator" placeholder="Введите разделитель" style="display: none;">
                </div>
            </div>
            <div id="selected-list" class="selected-list"></div>
        </div>

        <div class="files-container">
            <?php if (empty($filesByDate)): ?>
                <p class="empty-message">Нет загруженных файлов</p>
            <?php else: ?>
                <?php foreach ($filesByDate as $date => $files): ?>
                    <div class="date-section">
                        <h2 class="date-header"><?php echo htmlspecialchars($date); ?></h2>
                        <div class="cards-grid">
                            <?php foreach ($files as $file): ?>
                                <div class="card">
                                    <div class="preview-container">
                                        <?php if ($file['type'] === 'image'): ?>
                                            <img src="<?php echo htmlspecialchars($file['url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($file['name']); ?>"
                                                 class="preview"
                                                 data-url="<?php echo htmlspecialchars($file['url']); ?>"
                                                 data-type="image">
                                        <?php elseif ($file['type'] === 'video'): ?>
                                            <video class="preview" 
                                                   data-url="<?php echo htmlspecialchars($file['url']); ?>"
                                                   data-type="video">
                                                <source src="<?php echo htmlspecialchars($file['url']); ?>" type="video/mp4">
                                            </video>
                                            <div class="video-play-icon">▶</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-info">
                                        <label class="select-label">
                                            <input type="checkbox"
                                                   class="select-checkbox"
                                                   data-url="<?php echo htmlspecialchars($file['url']); ?>"
                                                   data-name="<?php echo htmlspecialchars($file['name']); ?>">
                                            <span>Выбрать</span>
                                        </label>
                                        <div class="card-date">
                                            <?php 
                                            if (isset($file['upload_datetime']) && $file['upload_datetime']) {
                                                // Форматируем дату и время из метаданных
                                                $datetime = new DateTime($file['upload_datetime']);
                                                echo htmlspecialchars($datetime->format('Y-m-d H:i'));
                                            } else {
                                                // Если метаданных нет, показываем только дату
                                                echo htmlspecialchars($date);
                                            }
                                            ?>
                                        </div>
                                        <?php if (isset($file['size'])): ?>
                                            <div class="card-size"><?php echo htmlspecialchars($file['size']); ?></div>
                                        <?php endif; ?>
                                        <?php if (isset($file['original_name']) && $file['original_name'] !== $file['name']): ?>
                                            <div class="original-name"><?php echo htmlspecialchars($file['original_name']); ?></div>
                                        <?php endif; ?>
                                        <a href="<?php echo htmlspecialchars($file['url']); ?>" 
                                           target="_blank" 
                                           class="file-link"
                                           data-url="<?php echo htmlspecialchars($file['url']); ?>">
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </a>
                                        <div class="card-buttons">
                                            <button class="btn-copy" 
                                                    data-url="<?php echo htmlspecialchars($file['url']); ?>">
                                                Копировать
                                            </button>
                                            <button class="btn-delete" 
                                                    data-path="<?php echo htmlspecialchars($file['path']); ?>"
                                                    data-name="<?php echo htmlspecialchars($file['name']); ?>">
                                                Удалить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно -->
    <div id="modal" class="modal">
        <span class="modal-close">&times;</span>
        <div class="modal-content">
            <img id="modal-image" src="" alt="" style="display: none;">
            <video id="modal-video" controls style="display: none;"></video>
        </div>
    </div>

    <script src="script.js"></script>
    
    <footer class="footer">
        <p>&copy; Alexandr Sidorenkov 2025-2030</p>
    </footer>
</body>
</html>

