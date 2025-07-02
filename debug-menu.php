<?php
/**
 * WordPress Menu Debug Script
 * Place this in the plugin directory and access via: /wp-content/plugins/employee-manager/debug-menu.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('WP_USE_THEMES', false);
    require_once('../../../wp-load.php');
}

// Allow admin and kunden users for debugging
if (!current_user_can('manage_options') && !current_user_can('read')) {
    wp_die('Access denied - you must be logged in');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WordPress Menu Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; background: #f9f9f9; }
        .debug-section h2 { margin-top: 0; color: #333; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
    </style>
</head>
<body>
    <h1>WordPress Menu Debug Analysis</h1>
    
    <div class="debug-section">
        <h2>1. Current User Information</h2>
        <?php
        $current_user = wp_get_current_user();
        echo "<p><strong>User ID:</strong> " . $current_user->ID . "</p>";
        echo "<p><strong>Username:</strong> " . $current_user->user_login . "</p>";
        echo "<p><strong>Email:</strong> " . $current_user->user_email . "</p>";
        echo "<p><strong>Roles:</strong> " . implode(', ', $current_user->roles) . "</p>";
        ?>
    </div>

    <div class="debug-section">
        <h2>2. WordPress Global Menu Variables</h2>
        <?php
        global $menu, $submenu, $admin_page_hooks, $_registered_pages;
        
        echo "<h3>Global \$menu array structure:</h3>";
        if (isset($menu) && is_array($menu)) {
            echo "<p class='success'>✓ \$menu is properly defined as array with " . count($menu) . " items</p>";
            
            // Check for problematic menu items
            $problematic_items = [];
            foreach ($menu as $index => $menu_item) {
                if (!is_array($menu_item)) {
                    $problematic_items[] = "Index $index: Not an array (" . gettype($menu_item) . ")";
                } elseif (count($menu_item) < 3) {
                    $problematic_items[] = "Index $index: Has " . count($menu_item) . " elements (expected at least 3)";
                } elseif (!isset($menu_item[2])) {
                    $problematic_items[] = "Index $index: Missing key 2 (callback/file)";
                }
            }
            
            if (empty($problematic_items)) {
                echo "<p class='success'>✓ All menu items properly structured</p>";
            } else {
                echo "<p class='error'>✗ Found problematic menu items:</p>";
                echo "<ul>";
                foreach ($problematic_items as $item) {
                    echo "<li class='error'>$item</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p class='error'>✗ \$menu is not properly defined</p>";
        }
        
        echo "<h3>Global \$submenu array structure:</h3>";
        if (isset($submenu) && is_array($submenu)) {
            echo "<p class='success'>✓ \$submenu is properly defined</p>";
            
            $submenu_problems = [];
            foreach ($submenu as $parent => $sub_items) {
                if (!is_array($sub_items)) {
                    $submenu_problems[] = "Parent '$parent': Not an array";
                    continue;
                }
                
                foreach ($sub_items as $sub_index => $sub_item) {
                    if (!is_array($sub_item)) {
                        $submenu_problems[] = "Parent '$parent', Index $sub_index: Not an array";
                    } elseif (count($sub_item) < 3) {
                        $submenu_problems[] = "Parent '$parent', Index $sub_index: Has " . count($sub_item) . " elements";
                    } elseif (!isset($sub_item[2])) {
                        $submenu_problems[] = "Parent '$parent', Index $sub_index: Missing key 2";
                    }
                }
            }
            
            if (empty($submenu_problems)) {
                echo "<p class='success'>✓ All submenu items properly structured</p>";
            } else {
                echo "<p class='error'>✗ Found problematic submenu items:</p>";
                echo "<ul>";
                foreach ($submenu_problems as $problem) {
                    echo "<li class='error'>$problem</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p class='error'>✗ \$submenu is not properly defined</p>";
        }
        ?>
    </div>

    <div class="debug-section">
        <h2>3. RT Employee Manager Menu Items</h2>
        <?php
        $found_rt_menu = false;
        $rt_menu_items = [];
        
        if (isset($menu) && is_array($menu)) {
            foreach ($menu as $index => $menu_item) {
                if (is_array($menu_item) && isset($menu_item[2]) && $menu_item[2] === 'rt-employee-manager') {
                    $found_rt_menu = true;
                    $rt_menu_items['main'] = $menu_item;
                    echo "<p class='success'>✓ Found RT Employee Manager main menu at index $index</p>";
                    echo "<pre>" . print_r($menu_item, true) . "</pre>";
                    break;
                }
            }
        }
        
        if (!$found_rt_menu) {
            echo "<p class='warning'>⚠ RT Employee Manager main menu not found</p>";
        }
        
        if (isset($submenu) && isset($submenu['rt-employee-manager'])) {
            echo "<p class='success'>✓ Found RT Employee Manager submenus</p>";
            echo "<pre>" . print_r($submenu['rt-employee-manager'], true) . "</pre>";
        } else {
            echo "<p class='warning'>⚠ RT Employee Manager submenus not found</p>";
        }
        ?>
    </div>

    <div class="debug-section">
        <h2>4. Active Plugins Analysis</h2>
        <?php
        $active_plugins = get_option('active_plugins', []);
        $problematic_plugins = [
            'admin-site-enhancements/admin-site-enhancements.php' => 'Known to modify admin menus',
            'members/members.php' => 'Role management plugin - can affect capabilities',
            'perfmatters/perfmatters.php' => 'Performance plugin - may modify admin',
        ];
        
        echo "<h3>Potentially Problematic Plugins:</h3>";
        foreach ($problematic_plugins as $plugin => $reason) {
            if (in_array($plugin, $active_plugins)) {
                echo "<p class='warning'>⚠ Active: $plugin - $reason</p>";
            } else {
                echo "<p class='success'>✓ Not active: $plugin</p>";
            }
        }
        
        echo "<h3>All Active Plugins:</h3>";
        echo "<ul>";
        foreach ($active_plugins as $plugin) {
            echo "<li>$plugin</li>";
        }
        echo "</ul>";
        ?>
    </div>

    <div class="debug-section">
        <h2>5. Hook Analysis</h2>
        <?php
        global $wp_filter;
        
        echo "<h3>admin_menu Hook Callbacks:</h3>";
        if (isset($wp_filter['admin_menu'])) {
            foreach ($wp_filter['admin_menu']->callbacks as $priority => $callbacks) {
                echo "<p><strong>Priority $priority:</strong></p>";
                echo "<ul>";
                foreach ($callbacks as $callback) {
                    $callback_name = 'Unknown';
                    if (is_array($callback['function'])) {
                        if (is_object($callback['function'][0])) {
                            $callback_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                        } else {
                            $callback_name = $callback['function'][0] . '::' . $callback['function'][1];
                        }
                    } elseif (is_string($callback['function'])) {
                        $callback_name = $callback['function'];
                    }
                    echo "<li>$callback_name</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p class='error'>✗ admin_menu hook not found</p>";
        }
        ?>
    </div>

    <div class="debug-section">
        <h2>6. WordPress Core Menu Processing</h2>
        <?php
        // Check if we can reproduce the error
        echo "<h3>Menu Array Key Test:</h3>";
        
        if (isset($menu) && is_array($menu)) {
            foreach ($menu as $index => $menu_item) {
                if (!is_array($menu_item)) {
                    echo "<p class='error'>✗ Menu item at index $index is not an array: " . gettype($menu_item) . "</p>";
                    continue;
                }
                
                // Check for the specific issue WordPress is complaining about
                if (!array_key_exists(2, $menu_item)) {
                    echo "<p class='error'>✗ Menu item at index $index missing key 2 (callback/file)</p>";
                    echo "<pre>Menu item: " . print_r($menu_item, true) . "</pre>";
                } elseif (is_null($menu_item[2])) {
                    echo "<p class='error'>✗ Menu item at index $index has null value at key 2</p>";
                    echo "<pre>Menu item: " . print_r($menu_item, true) . "</pre>";
                } elseif ($menu_item[2] === '') {
                    echo "<p class='error'>✗ Menu item at index $index has empty string at key 2</p>";
                    echo "<pre>Menu item: " . print_r($menu_item, true) . "</pre>";
                }
            }
        }
        ?>
    </div>

    <div class="debug-section">
        <h2>7. Error Log Analysis</h2>
        <?php
        $error_log_paths = [
            WP_CONTENT_DIR . '/debug.log',
            ini_get('error_log'),
            '/var/log/apache2/error.log',
            '/var/log/nginx/error.log'
        ];
        
        echo "<h3>Recent Menu-Related Errors:</h3>";
        $found_errors = false;
        
        foreach ($error_log_paths as $log_path) {
            if ($log_path && file_exists($log_path) && is_readable($log_path)) {
                $log_content = file_get_contents($log_path);
                $lines = explode("\n", $log_content);
                $recent_lines = array_slice($lines, -100); // Last 100 lines
                
                foreach ($recent_lines as $line) {
                    if (strpos($line, 'Undefined array key 2') !== false || 
                        strpos($line, 'menu.php') !== false ||
                        strpos($line, 'plugin.php') !== false) {
                        echo "<p class='error'>$line</p>";
                        $found_errors = true;
                    }
                }
            }
        }
        
        if (!$found_errors) {
            echo "<p class='success'>✓ No recent menu-related errors found in accessible logs</p>";
        }
        ?>
    </div>

    <p><a href="<?php echo admin_url('admin.php?page=rt-employee-manager'); ?>">← Back to RT Employee Manager</a></p>
</body>
</html>