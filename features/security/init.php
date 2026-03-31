<?php
/**
 * Security hardening bootstrap.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/security-hardening.php';

YZRH_Security_Hardening::init();
