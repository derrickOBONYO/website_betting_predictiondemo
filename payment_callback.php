<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/sms.php';

// Log the raw callback data for debugging
$callbackData = file_get_contents('php://input');
file_put_contents('../logs/mpesa_callback.log', date('Y-m-d H:i:s') . " - " . $callbackData . "\n\n", FILE_APPEND);

// Parse the JSON data
$data = json_decode($callbackData, true);

// Initialize response
$response = [
    'ResultCode' => 1, // Default to failure
    'ResultDesc' => 'Failed to process payment'
];

try {
    // Verify we have valid callback data
    if (isset($data['Body']['stkCallback'])) {
        $callback = $data['Body']['stkCallback'];
        $checkoutRequestID = $callback['CheckoutRequestID'];
        $resultCode = $callback['ResultCode'];
        
        // Find the transaction in our database
        $stmt = $pdo->prepare("
            SELECT t.*, u.user_id, u.phone, p.prediction_id, p.title, p.games 
            FROM transactions t
            JOIN users u ON t.user_id = u.user_id
            JOIN predictions p ON t.prediction_id = p.prediction_id
            WHERE t.mpesa_code = ? AND t.status = 'pending'
        ");
        $stmt->execute([$checkoutRequestID]);
        $transaction = $stmt->fetch();

        if ($transaction) {
            if ($resultCode == 0) {
                // Successful payment - get M-Pesa receipt number
                $receiptNumber = '';
                $amountPaid = 0;
                $phoneNumber = '';
                $transactionDate = '';
                
                // Extract callback metadata
                foreach ($callback['CallbackMetadata']['Item'] as $item) {
                    switch ($item['Name']) {
                        case 'MpesaReceiptNumber':
                            $receiptNumber = $item['Value'];
                            break;
                        case 'Amount':
                            $amountPaid = $item['Value'];
                            break;
                        case 'PhoneNumber':
                            $phoneNumber = $item['Value'];
                            break;
                        case 'TransactionDate':
                            $transactionDate = $item['Value'];
                            break;
                    }
                }
                
                // Verify the amount matches
                if ($amountPaid >= $transaction['amount']) {
                    // Start database transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // Update transaction status
                        $update = $pdo->prepare("
                            UPDATE transactions 
                            SET status = 'completed', 
                                mpesa_code = ?,
                                amount = ?,
                                phone_number = ?,
                                completed_at = NOW()
                            WHERE transaction_id = ?
                        ");
                        $update->execute([
                            $receiptNumber,
                            $amountPaid,
                            $phoneNumber,
                            $transaction['transaction_id']
                        ]);
                        
                        // Grant access to prediction
                        $access = $pdo->prepare("
                            INSERT INTO user_predictions 
                            (user_id, prediction_id, transaction_id) 
                            VALUES (?, ?, ?)
                        ");
                        $access->execute([
                            $transaction['user_id'],
                            $transaction['prediction_id'],
                            $transaction['transaction_id']
                        ]);
                        
                        // Send SMS with prediction
                        $sms = new SMS();
                        $games = json_decode($transaction['games'], true);
                        
                        $message = "Your prediction for " . $transaction['title'] . ":\n\n";
                        foreach ($games as $game) {
                            $message .= $game['home'] . " vs " . $game['away'] . ": " . $game['prediction'] . "\n";
                        }
                        $message .= "\nGood luck! - " . SITE_NAME;
                        
                        $smsResult = $sms->sendSMS($transaction['phone'], $message);
                        
                        // Update SMS status if sent successfully
                        if ($smsResult && isset($smsResult['SMSMessageData']['Recipients'][0]['status']) && 
                            $smsResult['SMSMessageData']['Recipients'][0]['status'] == 'Success') {
                            $update = $pdo->prepare("
                                UPDATE user_predictions 
                                SET sms_sent = TRUE 
                                WHERE user_id = ? AND prediction_id = ?
                            ");
                            $update->execute([
                                $transaction['user_id'],
                                $transaction['prediction_id']
                            ]);
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        // Update response
                        $response = [
                            'ResultCode' => 0,
                            'ResultDesc' => 'Payment processed successfully'
                        ];
                        
                        // Log success
                        file_put_contents('../logs/mpesa_success.log', 
                            date('Y-m-d H:i:s') . " - Transaction ID: {$transaction['transaction_id']}, " .
                            "Receipt: $receiptNumber, Amount: $amountPaid, Phone: $phoneNumber\n",
                            FILE_APPEND
                        );
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $response['ResultDesc'] = 'Error processing payment: ' . $e->getMessage();
                        error_log("Payment processing error: " . $e->getMessage());
                    }
                } else {
                    // Amount mismatch
                    $response['ResultDesc'] = 'Payment amount does not match';
                    
                    // Log the issue
                    file_put_contents('../logs/mpesa_errors.log', 
                        date('Y-m-d H:i:s') . " - Amount mismatch. Expected: {$transaction['amount']}, Received: $amountPaid\n",
                        FILE_APPEND
                    );
                }
            } else {
                // Failed transaction
                $update = $pdo->prepare("
                    UPDATE transactions 
                    SET status = 'failed',
                        completed_at = NOW()
                    WHERE mpesa_code = ?
                ");
                $update->execute([$checkoutRequestID]);
                
                $response = [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Payment failed or was cancelled by user'
                ];
            }
        } else {
            $response['ResultDesc'] = 'Transaction not found';
        }
    }
} catch (Exception $e) {
    $response['ResultDesc'] = 'Error processing callback: ' . $e->getMessage();
    error_log("Callback processing error: " . $e->getMessage());
}

// Send response back to M-Pesa
header('Content-Type: application/json');
echo json_encode($response);
?>