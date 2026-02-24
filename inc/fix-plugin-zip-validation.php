<?php
/**
 * Fix Plugin Zip Validation for Bundled Plugins
 * 
 * This file is intentionally minimal to avoid conflicts.
 * The actual installation bypass is handled by force_install_omnixep.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// This file is kept for compatibility but does not add filters
// Use force_install_omnixep.php for manual installation instead
