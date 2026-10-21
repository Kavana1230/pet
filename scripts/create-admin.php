<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$options = getopt('', ['name:', 'email:', 'password:']);

$name = trim((string) ($options['name'] ?? ''));
$email = strtolower(trim((string) ($options['email'] ?? '')));
$password = (string) ($options['password'] ?? '');

if ($name === '' || $email === '' || $password === '') {
    echo "Usage: php scripts/create-admin.php --name=\"Admin Name\" --email=\"admin@pawnet.local\" --password=\"YourStrongPassword\"\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: Invalid email format.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "Error: Admin password must be at least 8 characters long.\n";
    exit(1);
}

$config = require __DIR__ . '/../config/database.php';
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$hash = password_hash($password, PASSWORD_DEFAULT);
$id = bin2hex(random_bytes(16));

$stmt = $pdo->prepare('
    INSERT INTO users (id, name, email, role, status, password_hash, created_at)
    VALUES (:id, :name, :email, "admin", "active", :hash, NOW(6))
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        role = "admin",
        status = "active",
        password_hash = VALUES(password_hash),
        updated_at = NOW(6)
');

$stmt->execute([
    'id' => $id,
    'name' => $name,
    'email' => $email,
    'hash' => $hash,
]);

// Also sync with data/accounts.php if needed for backward compatibility
$accountsFile = __DIR__ . '/../data/accounts.php';
if (file_exists($accountsFile)) {
    $accounts = (array) include $accountsFile;
    $found = false;
    foreach ($accounts as &$account) {
        if (strtolower($account['email'] ?? '') === $email) {
            $account['role'] = 'admin';
            $account['name'] = $name;
            $account['passwordHash'] = $hash;
            $found = true;
            break;
        }
    }
    unset($account);
    if (!$found) {
        $accounts[] = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => '',
            'address' => '',
            'role' => 'admin',
            'passwordHash' => $hash,
            'createdAt' => date(DATE_ATOM),
        ];
    }
    $php = "<?php\nreturn " . var_export($accounts, true) . ";\n";
    file_put_contents($accountsFile, $php, LOCK_EX);
}

echo "Success: Admin account '{$email}' created / updated successfully!\n";
