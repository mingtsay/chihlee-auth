# Chihlee Auth

## Usage

```php
$login = new \MingTsay\ChihleeAuth\Login();
$result = $login->auth('學號/教職員帳號', '密碼');

if ($result['failure']) {
    echo('Login failed. Reason: ' . $result['failure_type']);
} else {
    // if no failure, failure_type won't be set.
    echo('Login ok.');
}
```

## List of `failure_type`

- `AUTH_CODE`: 驗證碼錯誤
- `USERNAME`: 帳號失效/不存在
- `PASSWORD`: 密碼錯誤

## Note

若於 5 分鐘內，連續失敗達 5 次，帳號將自動鎖定 5 分鐘。
