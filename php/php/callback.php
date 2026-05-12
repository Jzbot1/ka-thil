<?php
// API endpoint URL
$url = "https://cash.free.jzstore.in/api/check-order-status";

// POST data
$postData = array(
    "user_token" => "2048f66bef68633fa3262d7a398ab577",
    "order_id" => "8052313697"
);

// Initialize cURL session
$ch = curl_init($url);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

// Execute cURL session and get the response
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
    exit;
}

// Close cURL session
curl_close($ch);

// Decode the JSON response
$responseData = json_decode($response, true);

// Check if the API call was successful
if ($responseData["status"] === "COMPLETED") {
    // API call was successful
    // Access the response data as needed
    $txnStatus = $responseData["result"]["txnStatus"];
    $orderId = $responseData["result"]["orderId"];
    $status = $responseData["result"]["status"];
    $amount = $responseData["result"]["amount"];
    $date = $responseData["result"]["date"];
    $utr = $responseData["result"]["utr"];

    echo "Transaction Status: $txnStatus<br>";
    echo "Order ID: $orderId<br>";
    echo "Status: $status<br>";
    echo "Amount: $amount<br>";
    echo "Date: $date<br>";
    echo "UTR: $utr<br>";
} else {
    // API call failed
    $errorMessage = $responseData["message"];
    echo "API Error: $errorMessage";
}
?>
