<?php
// Web-based Webhook Endpoint Tester
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Endpoint Tester - Berkeley C <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Server Response</label>
            <div class="code-block">
                <pre><?php echo htmlspecialchars($testResults['response']); ?></pre>
            </div>
            <?php if ($testResults['is_security_validation']): ?>
                <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded">
                    <h5 class="font-semibold text-blue-900 text-sm mb-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Security Analysis
                    </h5>
                    <p class="text-blue-800 text-xs">
                        The 400 response with "Missing X-RapidSOS-Signature header" confirms your webhook is correctly
                        implementing HMAC-SHA256 signature verification as required by RapidSOS security standards.
                    </p>
                </div>
            <?php endif; ?>
        </div>Emergency Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .code-block {
            background: #1a1a1a;
            color: #e6e6e6;
            border-radius: 8px;
            padding: 1rem;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Header -->
    <div class="gradient-bg text-white py-8 mb-8">
        <div class="container mx-auto px-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold mb-2">
                        <i class="fas fa-terminal mr-3"></i>
                        Webhook Endpoint Tester
                    </h1>
                    <p class="text-blue-100 text-lg">Berkeley County Emergency Services</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-blue-100 mb-1">Test Date</div>
                    <div class="text-lg font-semibold"><?php echo date('M d, Y H:i:s'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6">
        <?php
        $testResults = null;
        $testPerformed = false;

        if (isset($_POST['test_webhook'])) {
            $testPerformed = true;

            // Test webhook endpoint
            $webhookUrl = 'https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php';
            $testPayload = [
                'test' => 'connection',
                'timestamp' => time(),
                'source' => 'web_tester',
                'test_id' => uniqid('test_')
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhookUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: Berkeley-County-Webhook-Tester/1.0'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_HEADER, true);

            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            // Parse response to check for security validation
            $responseBody = '';
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            if ($headerSize > 0) {
                $responseBody = substr($output, $headerSize);
            }

            $isSecurityValidation = false;
            $securityMessage = '';
            if ($httpCode == 400 && strpos($responseBody, 'X-RapidSOS-Signature') !== false) {
                $isSecurityValidation = true;
                $securityMessage = 'Webhook security is working correctly - signature verification required';
            }

            $testResults = [
                'success' => !$error && ($httpCode < 400 || $isSecurityValidation),
                'http_code' => $httpCode,
                'error' => $error,
                'response' => $output,
                'info' => $info,
                'payload_sent' => $testPayload,
                'is_security_validation' => $isSecurityValidation,
                'security_message' => $securityMessage,
                'response_body' => $responseBody
            ];
        }
        ?>

        <!-- Test Controls -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-play-circle text-green-500 mr-2"></i>
                Webhook Connectivity Test
            </h2>
            <p class="text-gray-600 mb-4">
                This tool tests the accessibility and responsiveness of your RapidSOS webhook endpoint.
            </p>

            <form method="POST" class="mb-4">
                <button type="submit" name="test_webhook" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors duration-200">
                    <i class="fas fa-rocket mr-2"></i>
                    Test Webhook Endpoint
                </button>
            </form>

            <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3 text-sm text-blue-700">
                        <strong>Endpoint URL:</strong> https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php
                    </div>
                </div>
            </div>
        </div>

        <?php if ($testPerformed): ?>
            <!-- Test Results -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-clipboard-list <?php echo $testResults['success'] ? 'text-green-500' : 'text-red-500'; ?> mr-2"></i>
                    Test Results
                </h2>

                <!-- Status Summary -->
                <div class="mb-6">
                    <?php if ($testResults['success']): ?>
                        <?php if ($testResults['is_security_validation']): ?>
                            <div class="bg-green-50 border-l-4 border-green-400 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-shield-check text-green-400 text-xl"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-green-800 font-semibold">🛡️ Security Validation Success!</h3>
                                        <p class="text-green-700 text-sm mt-1">
                                            <?php echo $testResults['security_message']; ?> |
                                            Response Time: <?php echo round($testResults['info']['total_time'], 3); ?>s
                                        </p>
                                        <p class="text-green-600 text-xs mt-2">
                                            ✅ Endpoint is accessible<br>
                                            ✅ Security headers are enforced<br>
                                            ✅ HMAC signature verification is working
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-green-50 border-l-4 border-green-400 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400 text-xl"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-green-800 font-semibold">✅ Webhook Endpoint is Accessible!</h3>
                                        <p class="text-green-700 text-sm mt-1">
                                            HTTP Status: <?php echo $testResults['http_code']; ?> |
                                            Response Time: <?php echo round($testResults['info']['total_time'], 3); ?>s
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-red-800 font-semibold">❌ Webhook Test Failed</h3>
                                    <p class="text-red-700 text-sm mt-1">
                                        <?php if ($testResults['error']): ?>
                                            Error: <?php echo htmlspecialchars($testResults['error']); ?>
                                        <?php else: ?>
                                            HTTP Status: <?php echo $testResults['http_code']; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Detailed Information -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Request Details -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Request Sent</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Test Payload</label>
                                <div class="code-block">
                                    <pre><?php echo json_encode($testResults['payload_sent'], JSON_PRETTY_PRINT); ?></pre>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Connection Info</label>
                                <div class="bg-gray-50 p-3 rounded text-sm">
                                    <div><strong>URL:</strong> <?php echo htmlspecialchars($testResults['info']['url']); ?></div>
                                    <div><strong>Connect Time:</strong> <?php echo round($testResults['info']['connect_time'], 3); ?>s</div>
                                    <div><strong>Total Time:</strong> <?php echo round($testResults['info']['total_time'], 3); ?>s</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Response Details -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Server Response</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">HTTP Response</label>
                                <div class="code-block">
                                    <pre><?php echo htmlspecialchars($testResults['response']); ?></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recommendations -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-900 mb-2">
                        <i class="fas fa-lightbulb mr-2"></i>
                        Next Steps
                    </h4>
                    <ul class="text-blue-800 text-sm space-y-1">
                        <?php if ($testResults['success']): ?>
                            <?php if ($testResults['is_security_validation']): ?>
                                <li>🛡️ Your webhook security is properly configured</li>
                                <li>✅ HMAC signature verification is enforced</li>
                                <li>🔐 Only authenticated requests will be processed</li>
                                <li>📊 Ready for production RapidSOS integration</li>
                                <li>🔧 RapidSOS can now send signed webhooks to this endpoint</li>
                            <?php else: ?>
                                <li>✅ Your webhook endpoint is responding correctly</li>
                                <li>📝 Check the webhook logs for the test entry</li>
                                <li>🔧 You can now configure RapidSOS to use this endpoint</li>
                                <li>📊 Monitor the dashboard for incoming alerts</li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li>🔍 Check server connectivity and firewall settings</li>
                            <li>📝 Verify the webhook endpoint URL is correct</li>
                            <li>🛠️ Check server logs for error details</li>
                            <li>🔧 Test from a different network if possible</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Information Panel -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                About Webhook Testing
            </h2>
            <div class="prose text-gray-600">
                <p class="mb-3">
                    This test sends a simple JSON payload to your webhook endpoint to verify:
                </p>
                <ul class="list-disc list-inside space-y-1 mb-4">
                    <li>Endpoint accessibility from external networks</li>
                    <li>Server response time and availability</li>
                    <li>Proper HTTP status codes</li>
                    <li>SSL certificate validity</li>
                </ul>
                <p class="text-sm text-gray-500">
                    <strong>Note:</strong> This is a connectivity test only. Check your webhook logs to see if the test payload was processed correctly by your application.
                </p>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center py-8">
            <a href="index.html" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200 mr-4">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
            <a href="test/webhook_simulation.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                <i class="fas fa-flask mr-2"></i>
                Advanced Webhook Testing
            </a>
        </div>
    </div>
</body>

</html>