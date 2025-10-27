-- Add Persian (Farsi) language to the languages table
-- This script can be run directly on the database

INSERT INTO languages (language_code, language_name, flag, created_at, updated_at)
SELECT 'fa', 'فارسی', '{"thumbnail":"","original":"https://flagcdn.com/w320/ir.png","id":null}', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM languages WHERE language_code = 'fa'
);

-- Verify the insertion
SELECT * FROM languages WHERE language_code = 'fa';
