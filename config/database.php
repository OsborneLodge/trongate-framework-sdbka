<?php
//Database settings
define('HOST', 'localhost');
define('USER', 'root');
define('PASSWORD', '');
define('DATABASE', '');

// the following define directs the code in Api.php to follow a different sql dialect for sqlite
// When SQLITE == false then the dialect is compatible with mysql and maria databases.

define('SQLITE', false);

// the following define would be typical for an sqlite database
// define('DSN', 'sqlite:' . APPPATH . DATABASE);
// leave as empty string for a mysql or maria database

define('DSN', '');

// this is introduced to avoid having to modify engine/Model.php
define('DEBUG', false);

// the following defines facilitate DEBUG information to be out put to a simple text file
define('DEBUG_TO_FILE', false);
define('DEBUG_FILE_NAME', APPPATH . DATABASE . '_DEBUG.txt');
