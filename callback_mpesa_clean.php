<?php

/**
 * M-Pesa Daraja Callback Handler
 * Developed by Watsons Developers (watsonsdevelopers.com)
 * Handles STK Push payment confirmations
 */

// Set JSON response header
header('Content-Type: application/json');

// Allow CORS for Safaricom
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Include system initialization
    require_once 'init.php';
    
    // Log incoming request
    $raw_input = file_get_contents('php://input');
    _log('M-Pesa Callback Raw Input: ' . $raw_input, 'M-Pesa', 0);
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }
    
    // Parse JSON input
    $callback_data = json_decode($raw_input, true);
    
    if (!$callback_data) {
        _log('M-Pesa Callback: Invalid JSON received', 'M-Pesa', 0);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit();
    }
    
    // Validate callback structure
    if (!isset($callback_data['Body']['stkCallback'])) {
        _log('M-Pesa Callback: Invalid callback structure', 'M-Pesa', 0);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid callback structure']);
        exit();
    }
    
    $stk_callback = $callback_data['Body']['stkCallback'];
    $checkout_request_id = $stk_callback['CheckoutRequestID'] ?? '';
    $result_code = $stk_callback['ResultCode'] ?? '';
    $result_desc = $stk_callback['ResultDesc'] ?? '';
    
    _log("M-Pesa Callback - CheckoutID: {$checkout_request_id}, ResultCode: {$result_code}, ResultDesc: {$result_desc}", 'M-Pesa', 0);
    
    if (empty($checkout_request_id)) {
        _log('M-Pesa Callback: Missing CheckoutRequestID', 'M-Pesa', 0);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing CheckoutRequestID']);
        exit();
    }
    
    // Find payment record
    $payment = ORM::for_table('tbl_payment_gateway')
        ->where('checkout_request_id', $checkout_request_id)
        ->find_one();
    
    if (!$payment) {
        _log("M-Pesa Callback: Payment record not found for CheckoutID: {$checkout_request_id}", 'M-Pesa', 0);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment record not found']);
        exit();
    }
    
    // Update payment record with callback data
    $payment->pg_paid_response = $raw_input;
    
    if ($result_code == '0') {
        // Payment successful
        _log("M-Pesa Payment SUCCESS for CheckoutID: {$checkout_request_id}", 'M-Pesa', 0);
        
        $callback_metadata = $stk_callback['CallbackMetadata']['Item'] ?? [];
        $mpesa_receipt = '';
        $phone_number = '';
        $amount = 0;
        $transaction_date = '';
        
        // Extract callback metadata
        foreach ($callback_metadata as $item) {
            switch ($item['Name']) {
                case 'MpesaReceiptNumber':
                    $mpesa_receipt = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $phone_number = $item['Value'];
                    break;
                case 'Amount':
                    $amount = $item['Value'];
                    break;
                case 'TransactionDate':
                    $transaction_date = $item['Value'];
                    break;
            }
        }
        
        // Update payment record
        $payment->status = 2; // Paid
        $payment->paid_date = date('Y-m-d H:i:s');
        $payment->mpesa_receipt_number = $mpesa_receipt;
        $payment->mpesa_phone_number = $phone_number;
        $payment->mpesa_amount = $amount;
        $payment->save();
        
        _log("M-Pesa Payment Details - Receipt: {$mpesa_receipt}, Phone: {$phone_number}, Amount: {$amount}", 'M-Pesa', 0);
        
        // Activate service
        $activation_result = activate_service_after_payment($payment);
        
        if ($activation_result) {
            _log("Service activated successfully for user: {$payment->username}", 'M-Pesa', 0);
        } else {
            _log("Service activation failed for user: {$payment->username}", 'M-Pesa', 0);
        }
        
    } else {
        // Payment failed or cancelled
        _log("M-Pesa Payment FAILED for CheckoutID: {$checkout_request_id}, ResultCode: {$result_code}, ResultDesc: {$result_desc}", 'M-Pesa', 0);
        
        $payment->status = 3; // Failed
        $payment->save();
    }
    
    // Respond to Safaricom
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Success'
    ]);
    
} catch (Exception $e) {
    _log('M-Pesa Callback Exception: ' . $e->getMessage(), 'M-Pesa', 0);
    
    http_response_code(500);
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Internal server error'
    ]);
}

/**
 * Activate service after successful payment
 */
function activate_service_after_payment($payment)
{
    try {
        // Get plan details
        $plan = ORM::for_table('tbl_plans')->find_one($payment->plan_id);
        if (!$plan) {
            _log("Plan not found: {$payment->plan_id}", 'M-Pesa', 0);
            return false;
        }
        
        // Get customer
        $customer = ORM::for_table('tbl_customers')->where('username', $payment->username)->find_one();
        if (!$customer) {
            _log("Customer not found: {$payment->username}", 'M-Pesa', 0);
            return false;
        }
        
        // Create transaction record
        $transaction = ORM::for_table('tbl_transactions')->create();
        $transaction->invoice = 'MPESA' . $payment->id;
        $transaction->username = $payment->username;
        $transaction->plan_name = $payment->plan_name;
        $transaction->price = $payment->price;
        $transaction->recharged_on = date('Y-m-d');
        $transaction->recharged_time = date('H:i:s');
        $transaction->method = 'M-Pesa STK Push';
        $transaction->routers = $payment->routers;
        $transaction->type = $plan->type;
        $transaction->save();
        
        // Create user recharge record
        $recharge = ORM::for_table('tbl_user_recharges')->create();
        $recharge->customer_id = $customer->id;
        $recharge->username = $payment->username;
        $recharge->plan_id = $payment->plan_id;
        $recharge->namebp = $payment->plan_name;
        $recharge->recharged_on = date('Y-m-d');
        $recharge->recharged_time = date('H:i:s');
        
        // Calculate expiration based on plan
        if ($plan->typebp == 'Limited' && $plan->limit_type == 'Time_Limit') {
            $time_unit = $plan->time_unit;
            $time_limit = $plan->time_limit;
            
            if ($time_unit == 'Hrs') {
                $expiration = date('Y-m-d H:i:s', strtotime('+' . $time_limit . ' hours'));
            } else {
                $expiration = date('Y-m-d H:i:s', strtotime('+' . $time_limit . ' minutes'));
            }
            
            $recharge->expiration = date('Y-m-d', strtotime($expiration));
            $recharge->time = date('H:i:s', strtotime($expiration));
        } else {
            // Default 30 days for unlimited plans
            $recharge->expiration = date('Y-m-d', strtotime('+30 days'));
            $recharge->time = '23:59:59';
        }
        
        $recharge->status = 'on';
        $recharge->method = 'M-Pesa STK Push';
        $recharge->routers = $payment->routers;
        $recharge->type = $plan->type;
        $recharge->save();
        
        // Activate based on service type
        if ($plan->type == 'Hotspot') {
            $activation_result = activate_hotspot_service($customer, $plan, $recharge);
            _log("Hotspot activation result: " . ($activation_result ? 'SUCCESS' : 'FAILED'), 'M-Pesa', $customer->id);
            return $activation_result;
        } elseif ($plan->type == 'PPPOE') {
            $activation_result = activate_pppoe_service($customer, $plan, $recharge);
            _log("PPPoE activation result: " . ($activation_result ? 'SUCCESS' : 'FAILED'), 'M-Pesa', $customer->id);
            return $activation_result;
        }
        
        // Default activation for other plan types
        _log("Plan type '{$plan->type}' - marking as activated without specific service activation", 'M-Pesa', $customer->id);
        return true;
        
    } catch (Exception $e) {
        _log('Service activation error: ' . $e->getMessage(), 'M-Pesa', 0);
        return false;
    }
}

/**
 * Activate Hotspot service (RADIUS) - Enhanced with MAC address authentication
 */
function activate_hotspot_service($customer, $plan, $recharge)
{
    global $config;
    
    try {
        // Get customer's MAC address from portal session or use customer username as MAC
        $mac_address = $customer->username; // In captive portal, username is the MAC address
        
        _log("Starting hotspot activation for MAC: {$mac_address}, Customer: {$customer->id}", 'M-Pesa', $customer->id);
        
        // Create RADIUS user with MAC address authentication
        $radius_username = str_replace(':', '', strtolower($mac_address)); // Remove colons for username
        $radius_password = substr(md5($mac_address . time()), 0, 8); // Generate password
        
        // Calculate expiration timestamp
        $expiration_timestamp = strtotime($recharge->expiration . ' ' . $recharge->time);
        
        // Insert into radcheck for authentication
        $radcheck = ORM::for_table('radcheck')->create();
        $radcheck->username = $radius_username;
        $radcheck->attribute = 'Cleartext-Password';
        $radcheck->op = ':=';
        $radcheck->value = $radius_password;
        $radcheck->save();
        
        // Set Session-Timeout if plan has time limit
        if ($plan->typebp == 'Limited' && $plan->limit_type == 'Time_Limit') {
            $session_timeout = ($plan->time_unit == 'Hrs') ? ($plan->time_limit * 3600) : ($plan->time_limit * 60);
            
            $radcheck_timeout = ORM::for_table('radcheck')->create();
            $radcheck_timeout->username = $radius_username;
            $radcheck_timeout->attribute = 'Session-Timeout';
            $radcheck_timeout->op = ':=';
            $radcheck_timeout->value = $session_timeout;
            $radcheck_timeout->save();
        }
        
        // Set Expiration attribute
        $radcheck_expiry = ORM::for_table('radcheck')->create();
        $radcheck_expiry->username = $radius_username;
        $radcheck_expiry->attribute = 'Expiration';
        $radcheck_expiry->op = ':=';
        $radcheck_expiry->value = date('M d Y H:i:s', $expiration_timestamp);
        $radcheck_expiry->save();
        
        // Set bandwidth limits if specified
        if ($plan->typebp == 'Limited' && $plan->limit_type == 'Data_Limit') {
            $data_limit_bytes = $plan->data_limit * 1024 * 1024; // Convert MB to bytes
            
            $radcheck_data = ORM::for_table('radcheck')->create();
            $radcheck_data->username = $radius_username;
            $radcheck_data->attribute = 'Max-Octets';
            $radcheck_data->op = ':=';
            $radcheck_data->value = $data_limit_bytes;
            $radcheck_data->save();
        }
        
        // Create radreply for bandwidth control
        if (!empty($plan->shared_rate)) {
            $bandwidth_parts = explode('/', $plan->shared_rate);
            if (count($bandwidth_parts) == 2) {
                $download_rate = trim($bandwidth_parts[0]) . 'k';
                $upload_rate = trim($bandwidth_parts[1]) . 'k';
                
                $radreply_down = ORM::for_table('radreply')->create();
                $radreply_down->username = $radius_username;
                $radreply_down->attribute = 'WISPr-Bandwidth-Max-Down';
                $radreply_down->op = ':=';
                $radreply_down->value = $download_rate;
                $radreply_down->save();
                
                $radreply_up = ORM::for_table('radreply')->create();
                $radreply_up->username = $radius_username;
                $radreply_up->attribute = 'WISPr-Bandwidth-Max-Up';
                $radreply_up->op = ':=';
                $radreply_up->value = $upload_rate;
                $radreply_up->save();
            }
        }
        
        _log("RADIUS user created successfully - Username: {$radius_username}, Password: {$radius_password}, Expires: " . date('Y-m-d H:i:s', $expiration_timestamp), 'M-Pesa', $customer->id);
        
        // Update customer record with RADIUS credentials
        $customer->password = $radius_password;
        $customer->service_type = 'Hotspot';
        $customer->status = 'Active';
        $customer->save();
        
        return true;
        
    } catch (Exception $e) {
        _log('RADIUS activation failed: ' . $e->getMessage(), 'M-Pesa', $customer->id);
        return false;
    }
}

/**
 * Activate PPPoE service
 */
function activate_pppoe_service($customer, $plan, $recharge)
{
    try {
        // Update customer status for PPPoE
        $customer->status = 'Active';
        $customer->service_type = 'PPPoE';
        
        // Generate PPPoE credentials if not exist
        if (empty($customer->pppoe_username)) {
            $customer->pppoe_username = $customer->username . '_pppoe';
        }
        if (empty($customer->pppoe_password)) {
            $customer->pppoe_password = substr(md5(time() . $customer->username), 0, 10);
        }
        
        $customer->save();
        
        _log("PPPoE service activated for user: {$customer->username}, PPPoE User: {$customer->pppoe_username}", 'M-Pesa', $customer->id);
        
        return true;
        
    } catch (Exception $e) {
        _log('PPPoE activation failed: ' . $e->getMessage(), 'M-Pesa', $customer->id);
        return false;
    }
}
