<?php

// Fallback for hosts that open index.php from the project root:
// the real front controller lives in public/ (see .htaccess and README).
require __DIR__ . '/public/index.php';
