<?php
require dirname(__DIR__) . '/config.php';
json_response(['ok'=>true,'app'=>app_display_name(),'time'=>now_iso()]);
