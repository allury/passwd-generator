<?php

declare(strict_types=1);

$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($_SERVER['HTTP_X_REQUESTED_WITH']);

ob_start();
require dirname(__DIR__) . '/passwd.php';
ob_end_clean();

function fail_test(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail_test($message);
    }
}

$cases = [
    [16, true, true, true, true],
    [50, false, false, true, false],
    [24, false, false, false, true],
];

foreach ($cases as [$length, $lowercase, $uppercase, $numbers, $symbols]) {
    for ($iteration = 0; $iteration < 100; $iteration++) {
        $password = generate_secure_password($length, $uppercase, $lowercase, $numbers, $symbols);

        expect_true(strlen($password) === $length, 'Generated password has an unexpected length.');
        expect_true(!preg_match('/(.)\1/', $password), 'Generated password contains adjacent duplicate characters.');
        expect_true(!preg_match('/[0O1lI]/', $password), 'Generated password contains an ambiguous character.');

        if ($lowercase) {
            expect_true((bool)preg_match('/[a-z]/', $password), 'Lowercase character is missing.');
        }
        if ($uppercase) {
            expect_true((bool)preg_match('/[A-Z]/', $password), 'Uppercase character is missing.');
        }
        if ($numbers) {
            expect_true((bool)preg_match('/[2-9]/', $password), 'Number is missing.');
        }
        if ($symbols) {
            expect_true((bool)preg_match('/[^a-zA-Z0-9]/', $password), 'Symbol is missing.');
        }
    }
}

expect_true(
    str_starts_with(generate_secure_password(8, false, false, false, false), '错误：'),
    'Empty character selection should return an error.'
);

expect_true(normalize_password_length([]) === 16, 'Array length input should use the default length.');
expect_true(normalize_password_length(100) === 50, 'Length input should be capped at 50.');
expect_true(normalize_password_length(4) === 8, 'Length input should be raised to the minimum of 8.');

$_POST['lowercase'] = 'on';
expect_true(post_checkbox_enabled('lowercase'), 'Checkbox value "on" should be enabled.');
$_POST['lowercase'] = 'false';
expect_true(!post_checkbox_enabled('lowercase'), 'Checkbox value "false" should be disabled.');
$_POST['lowercase'] = [];
expect_true(!post_checkbox_enabled('lowercase'), 'Array checkbox input should be disabled.');

echo "Password generation tests passed." . PHP_EOL;
