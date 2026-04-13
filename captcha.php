<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $answer = isset($data['answer']) ? intval($data['answer']) : null;

    if ($answer === null) {
        echo json_encode(['valid' => false, 'error' => 'No answer provided']);
        exit;
    }

    $valid = isset($_SESSION['captcha_answer']) && $answer === (int)$_SESSION['captcha_answer'];
    echo json_encode(['valid' => $valid]);
    exit;
}

// GET – generate new captcha
$a = rand(1, 12);
$b = rand(1, 12);
$_SESSION['captcha_answer'] = $a + $b;

echo json_encode(['question' => "What is {$a} + {$b}?"]);
