<?php
/**
 * Override WordPress Plugin Upgrader for Bundled Plugins
 * 
 * This file is intentionally minimal to avoid conflicts.
 * The actual installation bypass is handled by force_install_omnixep.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// This file is kept for compatibility but does not override anything
// Use force_install_omnixep.php for manual installation instead
