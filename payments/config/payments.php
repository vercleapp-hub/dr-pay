<?php
require_once __DIR__.'/db.php';


function post($k){ return trim($_POST[$k] ?? ''); }
function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }