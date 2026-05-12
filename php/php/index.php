<?php
// Set the API endpoint URL
$api_url = 'https://cash.free.jzstore.in/api/create-order';

// Define the payload data
$data = array(
    'customer_mobile' => '8145344963',
    'user_token' => 'e8d2a2f1ac98d41d3b7422fd11ab98fa',
    'amount' => '1',
    'order_id' => '8787772321800',   //use unique order id
    'redirect_url' => 'https://cash.free.jzstore.in',
    'remark1' => 'testremark',
    'remark2' => 'testremark2',
);

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Encode the data as form-urlencoded

// Execute the cURL request
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    // Parse the JSON response
    $result = json_decode($response, true);

    // Check if the status is true or false
    if ($result && isset($result['status'])) {
        if ($result['status'] === true) {
            // Order was created successfully
            echo 'Order Created Successfully<br>';
            echo 'Order ID: ' . $result['result']['orderId'] . '<br>';
            echo 'Payment URL: ' . $result['result']['payment_url'];
        } else {
            // Plan expired
            echo 'Status: ' . $result['status'] . '<br>';
            echo 'Message: ' . $result['message'];
        }
    } else {
        // Invalid response
        echo 'Invalid API response';
    }
}

// Close cURL session
curl_close($ch);
?>
