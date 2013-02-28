<?php

// database credentials
define('DB_HOST',  'localhost');
define('DB_USER',  'root');
define('DB_PASS',  'root');
define('DB_NAME',  'demo');
define('DB_TABLE', 'revision');

// remember to add a trailing slash
define('PATH_TO_DELTAS', __DIR__ . '/deltas/');

// pretty colors
define('COLOR_WHITE', "\033[1;37m");
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_RED',   "\033[0;31m");
define('COLOR_RESET', "\033[0;37m");
define('COLOR_COMMENT', "\033[1;30m");
