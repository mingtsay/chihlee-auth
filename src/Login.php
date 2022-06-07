<?php

namespace MingTsay\ChihleeAuth;

class Login
{
    private const ENDPOINT = 'https://cip1.chihlee.edu.tw';
    private const LOGIN_FAILED = '登入失敗！';
    private const LOGIN_FAILED_MSG = [
        'AUTH_CODE' => '畫面閒置過久或「驗證碼」輸入錯誤，請重新輸入。',
        'USERNAME' => '您的帳號失效，無法登入系統。',
        'PASSWORD' => '帳號或密碼錯誤，請重新輸入。',
    ];

    private function getSessionId(): ?string
    {
        // request
        $ch = curl_init(self::ENDPOINT . '/index.do');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        $result = curl_exec($ch);
        curl_close($ch);

        // get cookie
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
        $cookies = [];
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }

        if (!isset($cookies['JSESSIONID'])) return null;
        return $cookies['JSESSIONID'];
    }

    private function getAuthCodeImage(string $session_id): string
    {
        // request
        $ch = curl_init(self::ENDPOINT . '/authImage.do');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, 'JSESSIONID=' . $session_id);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    private function decodeAuthCodeSingle($im, int $offsetX): string
    {
        $auth_code_pos = [
            [10, 18],
            [12, 6],
            [5, 19],
            [13, 17],
            [8, 11],
            [11, 7],
            [4, 20],
            [14, 15],
            [14, 20],
        ];
        $auth_code_table = [
            '000100000' => 'A',
            '101111000' => 'B',
            '110000000' => 'C',
            '111100010' => 'D',
            '111010000' => 'E',
            '011000000' => 'F',
            '110000010' => 'G',
            '001010010' => 'H',
            '001000000' => 'I',
            '001000100' => 'J',
            '011111000' => 'K',
            '101000000' => 'L',
            '101010000' => 'M',
            '001100010' => 'N',
            '110100000' => 'O',
            '011001000' => 'P',
            '110100001' => 'Q',
            '011101000' => 'R',
            '101011000' => 'S',
            '010010000' => 'T',
            '100100010' => 'U',
            '100000000' => 'V',
            '010001010' => 'W',
            '000110000' => 'X',
            '010011000' => 'Y',
            '111000000' => 'Z',
        ];

        $pattern = '';
        foreach ($auth_code_pos as $pos)
            $pattern .= ImageColorAt($im, $offsetX + $pos[0], $pos[1]) === 16777215 ? '1' : '0';

        return $auth_code_table[$pattern];
    }

    private function decodeAuthCode(string $image): string
    {
        $im = ImageCreateFromString($image);
        $auth_code = '';
        for ($offsetX = 0; $offsetX < 80; $offsetX += 20)
            $auth_code .= $this->decodeAuthCodeSingle($im, $offsetX);
        ImageDestroy($im);

        return $auth_code;
    }

    private function login(string $session_id, string $username, string $password, string $auth_code): array
    {
        $body = http_build_query([
            'muid' => $username,
            'mpassword' => $password,
            'authcode' => $auth_code,
        ]);

        // request
        $ch = curl_init(self::ENDPOINT . '/login.do');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, 'JSESSIONID=' . $session_id);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: ' . self::ENDPOINT . '/index.do',
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        $login_result = [];
        $login_result['failure'] = strpos($result, self::LOGIN_FAILED) !== false;
        if ($login_result['failure']) {
            foreach (self::LOGIN_FAILED_MSG as $failure_type => $keyword) {
                if (strpos($result, $keyword) !== false) {
                    $login_result['failure_type'] = $failure_type;
                    break;
                }
            }
            if (!isset($login_result['failure_type']))
                $login_result['failure_type'] = 'UNKNOWN';
        }

        return $login_result;
    }

    public function auth(string $username, string $password): array
    {
        $session_id = $this->getSessionId();
        $auth_code = $this->decodeAuthCode($this->getAuthCodeImage($session_id));
        return $this->login($session_id, $username, $password, $auth_code);
    }
}
