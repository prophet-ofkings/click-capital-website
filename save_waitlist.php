<?php
/**
 * Click Capital - Waitlist Form Handler (Simple CSV Version)
 * Saves form submissions to CSV file that Excel can open
 * File location: media/waitlist.csv
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Log the request
error_log("=== Waitlist Form Submission ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request Headers: " . json_encode(getallheaders()));

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    error_log("OPTIONS preflight request handled");
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    error_log("Error: Method not allowed. Was: " . $_SERVER['REQUEST_METHOD']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
error_log("Raw input received: " . $input);

if (empty($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No data received']);
    error_log("Error: No input data received");
    exit();
}

$data = json_decode($input, true);

// Check if JSON decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    error_log("Error: JSON decode failed: " . json_last_error_msg());
    exit();
}

error_log("Decoded data: " . json_encode($data));

// Validate required fields
$required = ['fullName', 'email', 'phone'];
$missing = [];
foreach ($required as $field) {
    if (empty($data[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
    error_log("Error: Missing fields: " . implode(', ', $missing));
    exit();
}

// Validate email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format: ' . $data['email']]);
    error_log("Error: Invalid email format: " . $data['email']);
    exit();
}

// Define file path
$csvFile = __DIR__ . '/media/waitlist.csv';
error_log("CSV file path: " . $csvFile);

// Create media directory if it doesn't exist
$mediaDir = __DIR__ . '/media';
if (!is_dir($mediaDir)) {
    error_log("Media directory doesn't exist, creating: " . $mediaDir);
    if (!mkdir($mediaDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create media directory']);
        error_log("Error: Failed to create media directory");
        exit();
    }
    error_log("Media directory created successfully");
}

try {
    // Check if file exists, if not create with headers
    $isNewFile = !file_exists($csvFile);
    error_log("Is new file: " . ($isNewFile ? 'Yes' : 'No'));
    
    // Check file permissions
    if (file_exists($csvFile) && !is_writable($csvFile)) {
        error_log("File exists but is not writable. Permissions: " . decoct(fileperms($csvFile)));
        throw new Exception('CSV file is not writable');
    }
    
    // Check directory permissions
    if (!is_writable($mediaDir)) {
        error_log("Media directory is not writable. Permissions: " . decoct(fileperms($mediaDir)));
        throw new Exception('Media directory is not writable');
    }
    
    // Open file for appending
    $handle = fopen($csvFile, 'a');
    
    if ($handle === false) {
        error_log("Failed to open file for writing");
        throw new Exception('Could not open file for writing. Check permissions.');
    }
    
    error_log("File opened successfully");
    
    // Write headers if new file
    if ($isNewFile) {
        $headers = [
            'Full Name',
            'Email',
            'Country Code',
            'Phone Number',
            'Country',
            'Interests',
            'Timestamp',
            'IP Address'
        ];
        if (fputcsv($handle, $headers) === false) {
            fclose($handle);
            error_log("Failed to write headers");
            throw new Exception('Failed to write CSV headers');
        }
        error_log("Headers written");
    }
    
    // Prepare data row with default values
    $rowData = [
        $data['fullName'] ?? '',
        $data['email'] ?? '',
        $data['countryCode'] ?? '',
        $data['phone'] ?? '',
        $data['country'] ?? '',
        $data['interests'] ?? '',
        $data['timestamp'] ?? date('Y-m-d H:i:s'),
        $data['ipAddress'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'Unknown')
    ];
    
    error_log("Row data to write: " . json_encode($rowData));
    
    // Write data
    if (fputcsv($handle, $rowData) === false) {
        fclose($handle);
        error_log("Failed to write row data");
        throw new Exception('Failed to write CSV data');
    }
    
    error_log("Data written successfully");
    
    // Close file
    fclose($handle);
    
    // Log success
    error_log("Waitlist entry saved for: " . $data['email']);
    
    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! You have been added to our waitlist.',
        'file' => 'media/waitlist.csv',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving data: ' . $e->getMessage(),
        'error_details' => 'Check server error logs for more information'
    ]);
}
?>