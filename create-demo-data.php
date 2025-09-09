<?php
/**
 * Demo Data Creator for RT Employee Manager
 * 
 * Usage:
 * - Create sample data: php create-demo-data.php create
 * - Fix existing data: php create-demo-data.php fix
 * - Clear all data: php create-demo-data.php clear
 */

// Security check - only run if WordPress is loaded
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = array(
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../../../wp-load.php'
    );
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("Error: Could not find WordPress installation.\n");
    }
}

// Get command line argument
$action = isset($argv[1]) ? $argv[1] : 'create';
$user_id = isset($argv[2]) ? intval($argv[2]) : 11; // Default to user ID 11

switch ($action) {
    case 'create':
        echo "Creating sample employee data for user ID $user_id...\n";
        
        // Clear existing first
        $existing_employees = get_posts(array(
            'post_type' => 'angestellte',
            'numberposts' => -1,
            'meta_key' => 'employer_id',
            'meta_value' => $user_id,
            'post_status' => 'any'
        ));
        
        foreach ($existing_employees as $post) {
            wp_delete_post($post->ID, true);
        }
        echo "Cleared " . count($existing_employees) . " existing employees.\n";
        
        // Create new sample data
        if (function_exists('rt_create_sample_employees')) {
            rt_create_sample_employees($user_id);
            echo "✅ Sample data created successfully!\n";
        } else {
            echo "❌ Error: rt_create_sample_employees function not found.\n";
        }
        break;
        
    case 'fix':
        echo "Fixing existing employee data...\n";
        if (function_exists('rt_fix_existing_employees')) {
            $fixed_count = rt_fix_existing_employees();
            echo "✅ Fixed $fixed_count employee posts.\n";
        } else {
            echo "❌ Error: rt_fix_existing_employees function not found.\n";
        }
        break;
        
    case 'clear':
        echo "Clearing all employee data for user ID $user_id...\n";
        $employees = get_posts(array(
            'post_type' => 'angestellte',
            'numberposts' => -1,
            'meta_key' => 'employer_id',
            'meta_value' => $user_id,
            'post_status' => 'any'
        ));
        
        foreach ($employees as $post) {
            wp_delete_post($post->ID, true);
        }
        echo "✅ Cleared " . count($employees) . " employees.\n";
        break;
        
    default:
        echo "RT Employee Manager Demo Data Creator\n";
        echo "=====================================\n";
        echo "Usage: php create-demo-data.php [action] [user_id]\n\n";
        echo "Actions:\n";
        echo "  create  - Create sample employee data (default)\n";
        echo "  fix     - Fix existing employee data\n";
        echo "  clear   - Clear all employee data\n\n";
        echo "Examples:\n";
        echo "  php create-demo-data.php create 11\n";
        echo "  php create-demo-data.php fix\n";
        echo "  php create-demo-data.php clear 11\n";
        break;
}

// Clear caches
wp_cache_flush();
echo "Cache cleared.\n";
?>