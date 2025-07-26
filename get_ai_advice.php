<?php
// get_ai_advice.php
// This script handles the server-side communication with the OpenAI API for budget suggestions.

session_start();
include 'openai_key.php'; // Include your OpenAI API key

// Set the content type to JSON for the response
header('Content-Type: application/json');

// 1. Basic Security Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

// 2. Retrieve data sent from the client-side JavaScript (index.php)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$chartData = $data['chartData'] ?? []; // Expense data for insights
$username = $data['username'] ?? 'User'; // Current username
$selectedMonth = $data['selectedMonth'] ?? ''; // Month selected for chart/filter

// 3. Construct the prompt for the OpenAI AI model
$prompt = "Hello {$username}! Provide personalized, actionable budget advice and tips to help manage finances better. Focus on practical steps and potential areas for savings. Keep the advice concise and easy to understand, formatted as a bulleted list.";

if (!empty($selectedMonth)) {
    $prompt .= "\n\nYour expenses for " . date("F Y", strtotime($selectedMonth)) . " are:\n";
} else {
    $prompt .= "\n\nYour overall expenses are:\n";
}

if (!empty($chartData)) {
    foreach ($chartData as $item) {
        // Ensure category and total are safely escaped for the prompt
        $category = htmlspecialchars($item['category']);
        $total = number_format($item['total'], 2);
        $prompt .= "- {$category}: ₹{$total}\n";
    }
} else {
    $prompt .= "No expenses recorded yet. ";
}
$prompt .= "\n\nBased on this information (or lack thereof), what are your best budgeting suggestions?";


// 4. OpenAI API Configuration
// Use a suitable OpenAI model. 'gpt-3.5-turbo' is cost-effective and good.
// 'gpt-4o' or 'gpt-4-turbo' can be used for higher quality, but are more expensive.
$model_name = "gpt-3.5-turbo";
$openai_api_url = "https://api.openai.com/v1/chat/completions"; // OpenAI Chat Completions API endpoint

// Ensure your OpenAI API key is available
if (empty($openai_api_key)) {
    echo json_encode(['success' => false, 'message' => 'OpenAI API key is not configured.']);
    exit();
}

// 5. Initialize cURL session for OpenAI API request
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $openai_api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $openai_api_key, // OpenAI API authentication
    "Content-Type: application/json"
]);

// OpenAI uses a 'messages' array for chat completions
$messages = [
    ["role" => "system", "content" => "You are a helpful financial advisor. Provide concise, actionable budget advice and tips for personal finance management, focusing on practical steps and potential savings. Format your advice as a bulleted list."],
    ["role" => "user", "content" => $prompt] // $prompt is already constructed above
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "model" => $model_name,
    "messages" => $messages,
    "max_tokens" => 250, // Limit response length
    "temperature" => 0.7, // Control creativity (0.0-1.0)
]));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Set a generous timeout (e.g., 60 seconds)

// 6. Execute the cURL request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
$curl_error = curl_error($ch); // Get cURL error message if any
curl_close($ch);

// 7. Handle cURL errors
if ($curl_error) {
    error_log("cURL Error for OpenAI API: " . $curl_error); // Log the error
    echo json_encode(['success' => false, 'message' => 'Network/cURL Error: ' . $curl_error]);
    exit();
}

// 8. Decode the API response
$api_response_data = json_decode($response, true);

// 9. Handle HTTP errors from the OpenAI API
if ($http_code !== 200) {
    // OpenAI API errors typically have an 'error' key with 'message' inside it
    $error_message = $api_response_data['error']['message'] ?? 'Unknown OpenAI API error.';
    error_log("OpenAI API HTTP Error ({$http_code}): " . $error_message . " Response: " . $response); // Log API error
    echo json_encode(['success' => false, 'message' => 'OpenAI API Error (' . $http_code . '): ' . $error_message, 'details' => $api_response_data]);
    exit();
}

// 10. Extract the generated text from the successful response
// For OpenAI Chat Completions API, the text is in choices[0]->message->content
if (isset($api_response_data['choices'][0]['message']['content'])) {
    $ai_advice = $api_response_data['choices'][0]['message']['content'];

    // OpenAI models typically don't echo the prompt back, so no need for prompt removal
    echo json_encode(['success' => true, 'advice' => trim($ai_advice)]);
} else {
    // Unexpected response format from OpenAI
    error_log("Unexpected OpenAI response structure: " . $response); // Log unexpected response
    echo json_encode(['success' => false, 'message' => 'Unexpected OpenAI response structure. Please check model output.', 'details' => $api_response_data]);
}
?>