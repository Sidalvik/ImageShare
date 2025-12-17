<?php
// Функция для подсчета общего размера всех файлов в директории
function getTotalUploadSize($dir) {
    $totalSize = 0;
    if (!is_dir($dir) || !is_readable($dir)) {
        return $totalSize;
    }
    
    // Используем рекурсивную функцию для обхода директорий
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->isReadable()) {
                $fileSize = @filesize($file->getRealPath());
                if ($fileSize !== false && $fileSize > 0) {
                    $totalSize += $fileSize;
                }
            }
        }
    } catch (Exception $e) {
        // Если итератор не работает, возвращаем 0 (разрешаем загрузку)
        // Это может произойти при проблемах с правами доступа
        return 0;
    }
    
    return $totalSize;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    header('Location: index.php?error=' . urlencode('Нет файла для загрузки'));
    exit;
}

$file = $_FILES['file'];
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'];
$allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'];
$allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);

// Проверка типа файла
if (!in_array($file['type'], $allowedTypes)) {
    header('Location: index.php?error=' . urlencode('Недопустимый тип файла'));
    exit;
}

// Лимит общего размера всех загруженных файлов (90MB)
$maxTotalSize = 90 * 1024 * 1024; // 90MB

// Проверка размера одного файла (не должен превышать лимит)
if ($file['size'] > $maxTotalSize) {
    header('Location: index.php?error=' . urlencode('Файл слишком большой (максимум 90MB)'));
    exit;
}

// Проверка общего размера всех загруженных файлов
$baseUploadDir = 'uploads/';

// Создание базовой директории uploads, если её нет
if (!is_dir($baseUploadDir)) {
    if (!mkdir($baseUploadDir, 0755, true)) {
        header('Location: index.php?error=' . urlencode('Не удалось создать базовую директорию uploads'));
        exit;
    }
}

$currentTotalSize = getTotalUploadSize($baseUploadDir);

if ($currentTotalSize + $file['size'] > $maxTotalSize) {
    $currentSizeMB = round($currentTotalSize / (1024 * 1024), 2);
    header('Location: index.php?error=' . urlencode('Превышен лимит хранилища (90MB). Текущий размер: ' . $currentSizeMB . 'MB'));
    exit;
}

// Создание директории по дате в формате YYYYMMDD
$dateDir = date('Ymd');
$uploadDir = $baseUploadDir . $dateDir . '/';

// Создание директории с датой, если её нет
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        header('Location: index.php?error=' . urlencode('Не удалось создать директорию с датой'));
        exit;
    }
}

// Генерация уникального имени файла в формате YYYY-MM-DD--HH-mm-ss_NNNNNN
$originalName = $file['name'];
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
if (empty($extension)) {
    // Если расширение не определено, пытаемся определить по MIME типу
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/svg+xml' => 'svg',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogg',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi'
    ];
    $extension = isset($mimeToExt[$file['type']]) ? $mimeToExt[$file['type']] : 'bin';
}

// Формат даты и времени: YYYYMMDDHHmmss
$dateTime = date('YmdHis');

// Определение порядкового номера (NNNNNN - 6 цифр)
$sequenceNumber = 1;
$pattern = $uploadDir . $dateTime . '*.*';
$filesInDir = glob($pattern);
if (!empty($filesInDir)) {
    // Находим максимальный номер среди существующих файлов
    $maxNumber = 0;
    foreach ($filesInDir as $existingFile) {
        $basename = basename($existingFile);
        // Ищем файлы с форматом YYYYMMDDHHmmssNNNNNN.extension
        if (preg_match('/' . preg_quote($dateTime, '/') . '(\d{6})\./', $basename, $matches)) {
            $num = (int)$matches[1];
            if ($num > $maxNumber) {
                $maxNumber = $num;
            }
        }
    }
    $sequenceNumber = $maxNumber + 1;
}

// Форматируем номер с ведущими нулями (6 цифр)
$sequenceNumberFormatted = str_pad($sequenceNumber, 6, '0', STR_PAD_LEFT);

// Формируем имя файла: YYYYMMDDHHmmssNNNNNN.extension
$fileName = $dateTime . $sequenceNumberFormatted . '.' . $extension;
$targetPath = $uploadDir . $fileName;

// Перемещение файла
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Сохранение метаданных в текстовый файл
    $metaFileName = $dateTime . $sequenceNumberFormatted . '.txt';
    $metaFilePath = $uploadDir . $metaFileName;
    
    $uploadDateTime = date('Y-m-d H:i:s');
    $originalFileName = $file['name'];
    
    $metaData = [
        'upload_date' => $uploadDateTime,
        'original_name' => $originalFileName
    ];
    
    // Сохраняем метаданные в JSON формате
    if (file_put_contents($metaFilePath, json_encode($metaData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
        // Если не удалось сохранить метаданные, удаляем загруженный файл
        @unlink($targetPath);
        header('Location: index.php?error=' . urlencode('Ошибка при сохранении метаданных'));
        exit;
    }
    
    header('Location: index.php?success=1');
} else {
    header('Location: index.php?error=' . urlencode('Ошибка при загрузке файла'));
}
exit;

