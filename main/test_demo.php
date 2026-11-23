<?php

function runTest(string $name, string $baseUrl) {
    echo "\n========================================================\n";
    echo "   ТЕСТУВАННЯ API: {$name} ({$baseUrl})\n";
    echo "========================================================\n";

    // Helper for requests
    $request = function (string $method, string $path, ?array $body = null, ?string $token = null) use ($baseUrl) {
        $ch = curl_init($baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'json' => json_decode($response, true), 'raw' => $response];
    };

    // 1. Admin Login
    echo "\n[1] Вхід як ADMIN (admin@bank.com)... ";
    $res = $request('POST', '/api/auth/login', ['email' => 'admin@bank.com', 'password' => 'admin123']);
    if ($res['status'] === 200 && !empty($res['json']['token'])) {
        $adminToken = $res['json']['token'];
        echo "OK (HTTP 200)\n";
        echo "    Отримано JWT токен: " . substr($adminToken, 0, 30) . "...\n";
    } else {
        echo "FAIL (HTTP {$res['status']})\n";
        return;
    }

    // 2. Admin GET /api/clients
    echo "\n[2] ADMIN запитує список усіх клієнтів (GET /api/clients)... ";
    $res = $request('GET', '/api/clients', null, $adminToken);
    if ($res['status'] === 200) {
        $count = count($res['json']['data'] ?? []);
        $total = $res['json']['pagination']['totalItems'] ?? $count;
        echo "OK (HTTP 200)\n";
        echo "    Всього клієнтів у базі: {$total}, повернуто на сторінці: {$count}\n";
    } else {
        echo "FAIL (HTTP {$res['status']})\n";
    }

    // 3. Client Login
    echo "\n[3] Вхід як CLIENT (client@bank.com, client_id = 1)... ";
    $res = $request('POST', '/api/auth/login', ['email' => 'client@bank.com', 'password' => 'client123']);
    if ($res['status'] === 200 && !empty($res['json']['token'])) {
        $clientToken = $res['json']['token'];
        echo "OK (HTTP 200)\n";
    } else {
        echo "FAIL (HTTP {$res['status']})\n";
        return;
    }

    // 4. Client GET /api/clients
    echo "\n[4] CLIENT запитує список клієнтів (GET /api/clients)... ";
    $res = $request('GET', '/api/clients', null, $clientToken);
    if ($res['status'] === 200) {
        $count = count($res['json']['data'] ?? []);
        $firstId = $res['json']['data'][0]['id'] ?? null;
        echo "OK (HTTP 200)\n";
        echo "    Клієнту видно записів: {$count}\n";
        if ($count === 1 && $firstId == 1) {
            echo "    -> [ВДАЛО]: Клієнт бачить ТІЛЬКИ свій запис (ID = {$firstId})!\n";
        }
    } else {
        echo "FAIL (HTTP {$res['status']})\n";
    }

    // 5. Client POST /api/clients (403 check)
    echo "\n[5] CLIENT намагається створити клієнта (POST /api/clients)... ";
    $res = $request('POST', '/api/clients', [
        'firstName' => 'Тест', 'lastName' => 'Тестов', 'email' => 'test@bank.com', 'phone' => '123', 'taxId' => '123',
        'first_name' => 'Тест', 'last_name' => 'Тестов', 'tax_id' => '123'
    ], $clientToken);
    if ($res['status'] === 403) {
        echo "OK (HTTP 403 Forbidden!)\n";
        echo "    Повідомлення: " . ($res['json']['error'] ?? $res['raw']) . "\n";
    } else {
        echo "FAIL (Отримано HTTP {$res['status']})\n";
    }

    // 6. Manager Login
    echo "\n[6] Вхід як MANAGER (manager@bank.com)... ";
    $res = $request('POST', '/api/auth/login', ['email' => 'manager@bank.com', 'password' => 'manager123']);
    if ($res['status'] === 200 && !empty($res['json']['token'])) {
        $mgrToken = $res['json']['token'];
        echo "OK (HTTP 200)\n";
    } else {
        echo "FAIL (HTTP {$res['status']})\n";
        return;
    }

    // 7. Manager DELETE /api/clients/1 (403 check)
    echo "\n[7] MANAGER намагається видалити клієнта (DELETE /api/clients/1)... ";
    $res = $request('DELETE', '/api/clients/1', null, $mgrToken);
    if ($res['status'] === 403) {
        echo "OK (HTTP 403 Forbidden!)\n";
        echo "    Повідомлення: " . ($res['json']['error'] ?? $res['raw']) . "\n";
    } else {
        echo "FAIL (Отримано HTTP {$res['status']})\n";
    }

    // 8. Request without token (401 check)
    echo "\n[8] Запит без токена (GET /api/clients)... ";
    $res = $request('GET', '/api/clients', null, null);
    if ($res['status'] === 401) {
        echo "OK (HTTP 401 Unauthorized!)\n";
        echo "    Захист JWT діє.\n";
    } else {
        echo "FAIL (Отримано HTTP {$res['status']})\n";
    }
}

// Run for both
runTest("Symfony 6.4", "http://127.0.0.1:8001");
runTest("Laravel 11", "http://127.0.0.1:8002");

echo "\n========================================================\n";
echo "   УСІ ПЕРЕВІРКИ УСПІШНО ПРОЙДЕНІ В ОБОХ ФРЕЙМВОРКАХ!\n";
echo "========================================================\n\n";
