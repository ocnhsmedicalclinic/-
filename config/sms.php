<?php
// SMS Configuration (Default: Semaphore for Philippines)
// Get your API Key at https://semaphore.co/
define('SMS_API_KEY', '042be725f43c7633ce52ab57125d892a013874b53e8570b5');
define('SMS_SENDER_NAME', 'medicalclinic'); // Optional, specific to your account

function sendSMS($number, $message)
{
    // Basic validation for PH numbers (09xxxxxxxxx -> +639xxxxxxxxx if needed, but Semaphore handles 09)

    $parameters = array(
        'apikey' => SMS_API_KEY,
        'number' => $number,
        'message' => $message,
        'sendername' => SMS_SENDER_NAME
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Disable SSL check for local development if needed
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
}
?>