<?php

declare(strict_types=1);

http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['error' => 'Legacy bid endpoint removed. Use /api/rooms/{code}/bid instead.']);
exit;
