<?php
declare(strict_types=1);

/**
 * MOBILE STORE POS — cPanel Root Fallback Forwarder
 *
 * If Apache document root is set to the project root instead of /public,
 * this file forwards execution directly into the public front controller.
 */

require_once __DIR__ . '/public/index.php';
