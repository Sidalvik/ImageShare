<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['file_path'])) {
    header('Location: index.php?error=' . urlencode('Не указан файл для удаления'));
    exit;
}

$filePath = $_POST['file_path'];

// Проверка безопасности: файл должен находиться в директории uploads
$realPath = realpath($filePath);
$uploadsDir = realpath('uploads/');

if (!$realPath || !$uploadsDir || strpos($realPath, $uploadsDir) !== 0) {
    header('Location: index.php?error=' . urlencode('Недопустимый путь к файлу'));
    exit;
}

// Проверка существования файла
if (!file_exists($filePath) || !is_file($filePath)) {
    header('Location: index.php?error=' . urlencode('Файл не найден'));
    exit;
}

// Удаление файла
if (@unlink($filePath)) {
    // Удаление связанного файла с метаданными
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $baseName = pathinfo($filePath, PATHINFO_FILENAME);
    $metaFilePath = dirname($filePath) . '/' . $baseName . '.txt';
    
    if (file_exists($metaFilePath)) {
        @unlink($metaFilePath);
    }
    
    // Проверяем, пуста ли директория даты, и удаляем её, если пуста
    $dateDir = dirname($filePath);
    if (is_dir($dateDir)) {
        $filesInDir = glob($dateDir . '/*');
        if (empty($filesInDir)) {
            @rmdir($dateDir);
        }
    }
    
    header('Location: index.php?success=delete');
} else {
    header('Location: index.php?error=' . urlencode('Ошибка при удалении файла'));
}
exit;

