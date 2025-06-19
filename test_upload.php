<?php
// Simple test script to check file upload functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>File Upload Test</h2>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings:</h3>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";

// Check upload directory
echo "<h3>Upload Directory:</h3>";
$upload_dir = 'upload/';
echo "Upload directory: $upload_dir<br>";
echo "Directory exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "<br>";
echo "Directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "<br>";
echo "Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";

// Test form
echo "<h3>Test File Upload:</h3>";
echo "<form method='POST' enctype='multipart/form-data'>";
echo "<input type='file' name='test_file' required><br><br>";
echo "<input type='submit' value='Upload Test File'>";
echo "</form>";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h3>Upload Results:</h3>";
    
    $file = $_FILES['test_file'];
    echo "File name: " . $file['name'] . "<br>";
    echo "File size: " . $file['size'] . " bytes<br>";
    echo "File type: " . $file['type'] . "<br>";
    echo "Upload error: " . $file['error'] . "<br>";
    echo "Temporary file: " . $file['tmp_name'] . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $target_path = $upload_dir . time() . '_' . $file['name'];
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            echo "<strong style='color: green;'>File uploaded successfully to: $target_path</strong><br>";
        } else {
            echo "<strong style='color: red;'>Failed to move uploaded file</strong><br>";
            echo "Error: " . error_get_last()['message'] . "<br>";
        }
    } else {
        echo "<strong style='color: red;'>Upload error occurred</strong><br>";
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                echo "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                echo "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                echo "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                echo "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                echo "A PHP extension stopped the file upload";
                break;
            default:
                echo "Unknown upload error";
        }
    }
}
?> 