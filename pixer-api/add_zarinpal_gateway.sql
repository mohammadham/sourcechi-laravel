-- SQL Script to Add ZarinPal Payment Gateway to Pixer Settings
-- Usage: Import this file or run these queries manually in your database

-- Update settings table to add ZarinPal to paymentGateway array
-- This will add ZarinPal to all existing language settings

-- For MySQL 5.7+ with JSON support
UPDATE settings 
SET options = JSON_ARRAY_APPEND(
    options, 
    '$.paymentGateway', 
    JSON_OBJECT(
        'name', 'ZARINPAL',
        'title', 'زرین‌پال'
    )
)
WHERE JSON_SEARCH(options, 'one', 'ZARINPAL', NULL, '$.paymentGateway[*].name') IS NULL;

-- Verify the update
SELECT 
    id,
    language,
    JSON_EXTRACT(options, '$.paymentGateway') as payment_gateways
FROM settings;

-- Note: If you're using PostgreSQL or older MySQL versions without JSON functions,
-- you may need to manually edit the options field through your admin panel
