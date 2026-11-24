<?php
if (!defined('PAWFEE_ADMIN_REQUEST')) { http_response_code(403); exit('Forbidden'); }
// Shared order management, with fulfillment-specific navigation and filtering.
require __DIR__ . '/orders.php';
