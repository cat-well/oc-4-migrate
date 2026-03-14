<?php
/**
 * Liqpay for OpenCart 4
 * 
 * @package     OpenCartBot
 * @author      OpenCartBot
 * @copyright   (c) OpenCartBot
 * @license     Commercial License - This software is not open-source and cannot be redistributed, modified, or used without a valid license from the author.
 * @link        https://opencartbot.com
 * @support     support@opencartbot.com
 */

$_['heading_title'] = 'Liqpay';
$_['heading_title_payments'] = 'Liqpay Payments';

$_['text_extension'] = 'Extension';
$_['text_edit'] = 'Edit Liqpay';
$_['text_liqpay'] = '<a href="https://opencartbot.com/en/" target="_blank"><kbd>opencartbot</kbd></a>';
$_['text_pay'] = 'Direct Payment';
$_['text_hold'] = 'Hold';
$_['text_all_zones'] = 'All Zones';
$_['text_list'] = 'Transaction List';
$_['text_no_results'] = 'No transactions found.';
$_['text_capture_title'] = 'Capture Funds';
$_['text_capture_confirm'] = 'Are you sure you want to capture the funds?';
$_['text_capture_success'] = 'Funds successfully captured!';
$_['text_refund_title'] = 'Refund Funds';
$_['text_refund_confirm'] = 'Enter the amount to refund:';
$_['text_refund_success'] = 'Funds successfully refunded!';
$_['text_success'] = 'Success';
$_['text_pending'] = 'Pending';
$_['text_verification'] = 'Under Verification';
$_['text_canceled'] = 'Canceled';
$_['text_failed'] = 'Failed';
$_['text_refunded'] = 'Refunded';
$_['text_captured'] = 'Captured';
$_['text_payments'] = 'Payments';
$_['text_session_success'] = 'Success';
$_['text_developed'] = 'Module developed by';
$_['text_copyright'] = '<p><a target="_blank" href="https://opencartbot.com/en/liqpay-opencart-4">Extension page</a></p>
<p><a target="_blank" href="https://opencartbot.com/support/">Technical support</a></p><br>
<p>Author: <strong>opencartbot</strong></p>
<p>Website: <a target="_blank" href="https://opencartbot.com/">opencartbot.com</a></p>';

$_['tab_general'] = 'General';
$_['tab_order_status'] = 'Order Status';
$_['tab_license'] = 'License';

$_['entry_public_key'] = 'Public Key';
$_['entry_private_key'] = 'Private Key';
$_['entry_action_type'] = 'Payment Type';
$_['entry_title'] = 'Payment Method Title';
$_['entry_description'] = 'Payment Description';
$_['entry_total'] = 'Minimum Amount';
$_['entry_order_status'] = 'Order Status (Success)';
$_['entry_pending_status'] = 'Order Status (Pending)';
$_['entry_hold_status'] = 'Order Status (Hold)';
$_['entry_failed_status'] = 'Order Status (Failed)';
$_['entry_refund_status'] = 'Order Status (Refund)';
$_['entry_geo_zone'] = 'Geo Zone';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort Order';
$_['entry_refund_amount'] = 'Refund Amount';
$_['entry_session'] = 'SameSite Session';
$_['entry_license'] = 'License Key';

$_['help_action_type'] = 'Select payment type: Direct Payment or Hold for later capture';
$_['help_total'] = 'Minimum order amount to activate this payment method';
$_['help_description'] = 'Payment description text. Use {order_id} to insert order number';
$_['help_order_status'] = 'Order status after successful payment';
$_['help_pending_status'] = 'Order status for pending payments';
$_['help_hold_status'] = 'Order status for held payments awaiting capture';
$_['help_failed_status'] = 'Status for failed order payments';
$_['help_refund_status'] = 'Order status after manual payment refund';
$_['help_session'] = 'Use <strong>None</strong> for proper payment method operation (to prevent customers from being logged out after payment)';

$_['column_transaction_id'] = 'Transaction ID';
$_['column_order_id'] = 'Order ID';
$_['column_liqpay_order_id'] = 'Liqpay Order ID';
$_['column_amount'] = 'Amount';
$_['column_status'] = 'Status';
$_['column_action_type'] = 'Type';
$_['column_date_added'] = 'Date Added';
$_['column_action'] = 'Action';

$_['button_save'] = 'Save';
$_['button_cancel'] = 'Cancel';
$_['button_back'] = 'Back';
$_['button_payments'] = 'View Payments';
$_['button_capture'] = 'Capture';
$_['button_refund'] = 'Refund';

$_['error_permission'] = 'Warning: You do not have permission to modify Liqpay payment module!';
$_['error_public_key'] = 'Public Key is required!';
$_['error_private_key'] = 'Private Key is required!';
$_['error_transaction'] = 'Transaction not found!';
$_['error_refund_amount'] = 'Please enter a valid refund amount!';
$_['error_transaction_not_found'] = 'Transaction not found!';
$_['error_not_hold_payment'] = 'Transaction is not a hold payment!';
$_['error_cannot_capture'] = 'Cannot capture funds from this transaction!';
$_['error_cannot_refund'] = 'Cannot refund funds from this transaction!';
$_['error_refund_amount_exceeds'] = 'Refund amount exceeds available amount!';
$_['error_capture_failed'] = 'Failed to capture funds!';
$_['error_refund_failed'] = 'Failed to refund funds!';
$_['error_refund_status_update'] = 'Refund completed, but failed to update order status in store!';
$_['error_capture_status_update'] = 'Funds captured, but failed to update order status in store!';
$_['error_license1'] = 'Error 1: Invalid license key. Enter your domain key!';
$_['error_license2'] = 'Error 2: Invalid license key. Enter your domain key!';
$_['error_license3'] = 'Error 3: Invalid license key. Enter your domain key!';

$_['success_captured'] = 'Funds successfully captured!';
$_['success_refunded'] = 'Funds successfully refunded!';