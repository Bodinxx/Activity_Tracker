<?php
session_start();
header('Content-Type: application/json');

define('DATA_DIR', __DIR__ . '/../data/');

/* ── Auth guard ── */
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$sessionUser = $_SESSION['user'];

/* ── JSON helpers ── */
function readJsonFile(string $path): array {
    if (!file_exists($path)) return [];
    $fp = fopen($path, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function writeJsonFile(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fp = fopen($path, 'c');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

/* ── ISO week helper ── */
function getIsoWeekKey(string $date = ''): string {
    $ts = $date ? strtotime($date) : time();
    return date('o-W', $ts);
}

function getWeekDates(string $weekKey): array {
    [$year, $week] = explode('-', $weekKey);
    $dates = [];
    // Find Monday of that ISO week
    $dto = new DateTime();
    $dto->setISODate((int)$year, (int)$week, 1);
    for ($i = 0; $i < 7; $i++) {
        $dates[] = $dto->format('Y-m-d');
        $dto->modify('+1 day');
    }
    return $dates;
}

$logsFile = DATA_DIR . 'activity_logs.json';
$method   = $_SERVER['REQUEST_METHOD'];

/* ── GET ── */
if ($method === 'GET') {
    $weekKey = $_GET['week'] ?? getIsoWeekKey();
    $user    = $_GET['user'] ?? $sessionUser;

    // Only admin may request other users
    if ($user !== $sessionUser && ($_SESSION['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $logs      = readJsonFile($logsFile);
    $weekDates = getWeekDates($weekKey);
    $result    = [];

    foreach ($weekDates as $d) {
        $result[$d] = $logs[$d][$user] ?? null;
    }

    echo json_encode(['week' => $weekKey, 'days' => $result]);
    exit;
}

/* ── POST ── */
if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    $action = $input['action'] ?? '';

    switch ($action) {

        case 'save_day': {
            $date = trim($input['date'] ?? '');
            $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$date || !$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD.']);
                exit;
            }

            $dayData = [
                'water'      => floatval($input['water'] ?? 0),
                'sleep'      => floatval($input['sleep'] ?? 0),
                'meals'      => intval($input['meals'] ?? 0),
                'steps'      => intval($input['steps'] ?? 0),
                'activities' => is_array($input['activities'] ?? null) ? $input['activities'] : [],
                'saved_at'   => date('Y-m-d H:i:s'),
            ];

            $logs = readJsonFile($logsFile);
            if (!isset($logs[$date])) $logs[$date] = [];
            $logs[$date][$sessionUser] = $dayData;

            if (!writeJsonFile($logsFile, $logs)) {
                http_response_code(500);
                echo json_encode(['error' => 'Could not save log']);
                exit;
            }

            echo json_encode(['success' => true, 'date' => $date]);
            break;
        }

        case 'get_week': {
            $weekKey   = $input['week'] ?? getIsoWeekKey();
            $logs      = readJsonFile($logsFile);
            $weekDates = getWeekDates($weekKey);
            $result    = [];

            foreach ($weekDates as $d) {
                $result[$d] = $logs[$d][$sessionUser] ?? null;
            }

            echo json_encode(['week' => $weekKey, 'days' => $result]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
