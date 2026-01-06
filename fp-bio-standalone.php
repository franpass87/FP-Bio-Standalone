<?php
/**
 * Plugin Name: FP Bio Standalone
 * Plugin URI: https://github.com/FranPass87/FP-Bio-Standalone
 * Description: Renders /bio page as a beautiful standalone landing page, bypassing WordPress theme completely. Perfect for Instagram "Link in Bio".
 * Version: 1.0.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fp-bio-standalone
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * 
 * GitHub Plugin URI: FranPass87/FP-Bio-Standalone
 * GitHub Branch: main
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FP_BIO_STANDALONE_VERSION', '1.0.0');
define('FP_BIO_STANDALONE_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Activation hook - flush rewrite rules
 */
function fp_bio_standalone_activate() {
    fp_bio_standalone_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'fp_bio_standalone_activate');

/**
 * Deactivation hook - cleanup
 */
function fp_bio_standalone_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'fp_bio_standalone_deactivate');

/**
 * Add rewrite rules for /bio
 */
function fp_bio_standalone_add_rewrite_rules() {
    add_rewrite_rule('^bio/?$', 'index.php?fp_bio_standalone=1', 'top');
}
add_action('init', 'fp_bio_standalone_add_rewrite_rules');

/**
 * Register query var
 */
function fp_bio_standalone_query_vars($vars) {
    $vars[] = 'fp_bio_standalone';
    return $vars;
}
add_filter('query_vars', 'fp_bio_standalone_query_vars');

/**
 * Handle the /bio request - render standalone page
 */
function fp_bio_standalone_template_redirect() {
    // Check if this is the bio page
    if (!get_query_var('fp_bio_standalone') && !is_page('bio')) {
        return;
    }

    // If it's a page with slug 'bio', we also handle it
    if (is_page('bio') || get_query_var('fp_bio_standalone')) {
        fp_bio_standalone_render_page();
        exit;
    }
}
add_action('template_redirect', 'fp_bio_standalone_template_redirect', 1);

/**
 * Get bio links from FP Publisher tables (if available)
 * Falls back to page content if FP Publisher not installed
 */
function fp_bio_standalone_get_links() {
    global $wpdb;
    
    $links = [];
    
    // Try to get links from FP Publisher bio_links table
    $table = $wpdb->prefix . 'fp_pub_bio_links';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    
    if ($table_exists) {
        // Get the remote site ID for this site
        $sites_table = $wpdb->prefix . 'fp_pub_remote_sites';
        $sites_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$sites_table}'") === $sites_table;
        
        $remote_site_id = null;
        if ($sites_table_exists) {
            // Find the remote site that matches this site URL
            $current_url = home_url();
            $remote_site_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$sites_table} WHERE site_url LIKE %s LIMIT 1",
                '%' . $wpdb->esc_like(parse_url($current_url, PHP_URL_HOST)) . '%'
            ));
        }
        
        // Get active links
        $query = "SELECT * FROM {$table} WHERE is_active = 1";
        if ($remote_site_id) {
            $query .= $wpdb->prepare(" AND remote_site_id = %d", $remote_site_id);
        }
        $query .= " AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY sort_order ASC, id ASC";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($results) {
            foreach ($results as $row) {
                $links[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'url' => $row['url'],
                    'icon' => $row['icon'] ?? '🔗',
                ];
            }
        }
    }
    
    // If no links from FP Publisher, try to parse from bio page content
    if (empty($links)) {
        $bio_page = get_page_by_path('bio');
        if ($bio_page) {
            // Parse content for links (basic extraction)
            $content = $bio_page->post_content;
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $url = $match[1];
                $title = strip_tags($match[2]);
                
                // Skip internal WordPress links
                if (strpos($url, '#') === 0 || strpos($url, 'wp-admin') !== false) {
                    continue;
                }
                
                $links[] = [
                    'id' => md5($url),
                    'title' => $title,
                    'url' => $url,
                    'icon' => '🔗',
                ];
            }
        }
    }
    
    return $links;
}

/**
 * Track click (if FP Publisher table exists)
 */
function fp_bio_standalone_track_click() {
    if (!isset($_GET['fp_bio_click'])) {
        return;
    }
    
    global $wpdb;
    $link_id = absint($_GET['fp_bio_click']);
    
    if ($link_id > 0) {
        $table = $wpdb->prefix . 'fp_pub_bio_links';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        
        if ($table_exists) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET click_count = click_count + 1 WHERE id = %d",
                $link_id
            ));
        }
        
        // Redirect to the actual URL
        $url = $wpdb->get_var($wpdb->prepare(
            "SELECT url FROM {$table} WHERE id = %d",
            $link_id
        ));
        
        if ($url) {
            wp_redirect($url);
            exit;
        }
    }
}
add_action('init', 'fp_bio_standalone_track_click');

/**
 * Get settings from options or FP Publisher
 */
function fp_bio_standalone_get_settings() {
    $defaults = [
        'primary_color' => '#3b82f6',
        'theme' => 'auto', // auto, light, dark
        'logo_url' => '',
        'description' => '',
    ];
    
    // Try to get from FP Publisher settings
    global $wpdb;
    $settings_table = $wpdb->prefix . 'fp_pub_bio_settings';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$settings_table}'") === $settings_table) {
        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM {$settings_table}", ARRAY_A);
        foreach ($results as $row) {
            if (isset($defaults[$row['setting_key']])) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    
    // Fallback to WordPress options
    $saved = get_option('fp_bio_standalone_settings', []);
    
    return array_merge($defaults, $saved);
}

/**
 * Render the standalone bio page
 */
function fp_bio_standalone_render_page() {
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');
    $links = fp_bio_standalone_get_links();
    $settings = fp_bio_standalone_get_settings();
    
    // Get logo
    $logo_url = $settings['logo_url'];
    if (empty($logo_url)) {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'medium');
        }
    }
    
    // Primary color
    $primary = $settings['primary_color'] ?: '#3b82f6';
    
    // Theme
    $theme = $settings['theme'] ?: 'auto';
    
    // Description
    $description = $settings['description'] ?: $site_description;
    
    // Generate tracking URLs
    $tracked_links = [];
    foreach ($links as $link) {
        $tracked_links[] = [
            'title' => $link['title'],
            'url' => is_numeric($link['id']) 
                ? add_query_arg('fp_bio_click', $link['id'], home_url('/bio'))
                : $link['url'],
            'icon' => $link['icon'],
        ];
    }
    
    ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>"<?php echo $theme === 'dark' ? ' class="dark"' : ($theme === 'light' ? ' class="light"' : ''); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?php echo esc_attr($primary); ?>">
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:title" content="<?php echo esc_attr($site_name); ?> - Link">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo esc_url(home_url('/bio')); ?>">
    <?php if ($logo_url): ?>
    <meta property="og:image" content="<?php echo esc_url($logo_url); ?>">
    <?php endif; ?>
    <title><?php echo esc_html($site_name); ?> - Link</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        :root {
            --primary: <?php echo esc_attr($primary); ?>;
            --primary-light: <?php echo esc_attr($primary); ?>22;
            --bg: #fafafa;
            --surface: #ffffff;
            --text: #1a1a1a;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --radius: 14px;
            --shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        @media (prefers-color-scheme: dark) {
            :root:not(.light) {
                --bg: #0a0a0a;
                --surface: #141414;
                --text: #fafafa;
                --text-muted: #9ca3af;
                --border: #262626;
                --shadow: 0 2px 8px rgba(0,0,0,0.3);
            }
        }

        html.dark {
            --bg: #0a0a0a;
            --surface: #141414;
            --text: #fafafa;
            --text-muted: #9ca3af;
            --border: #262626;
            --shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        html.light {
            --bg: #fafafa;
            --surface: #ffffff;
            --text: #1a1a1a;
            --text-muted: #6b7280;
            --border: #e5e7eb;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 48px 20px 100px;
        }

        .bio-container {
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .bio-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .bio-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 16px;
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .bio-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .bio-description {
            font-size: 0.9rem;
            color: var(--text-muted);
            max-width: 280px;
            line-height: 1.5;
        }

        .bio-links {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .bio-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 56px;
            padding: 14px 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .bio-link:hover, .bio-link:focus {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .bio-link:active {
            transform: translateY(0);
        }

        .bio-link-icon {
            font-size: 1.2em;
            flex-shrink: 0;
        }

        .bio-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            background: linear-gradient(transparent, var(--bg) 50%);
        }

        .bio-footer a {
            color: inherit;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .bio-footer a:hover {
            opacity: 1;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .bio-header {
            animation: fadeInUp 0.5s ease forwards;
        }

        .bio-link {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }

        <?php for ($i = 0; $i < min(count($links), 10); $i++): ?>
        .bio-link:nth-child(<?php echo $i + 1; ?>) {
            animation-delay: <?php echo 0.1 + ($i * 0.05); ?>s;
        }
        <?php endfor; ?>

        @media (prefers-reduced-motion: reduce) {
            .bio-header, .bio-link {
                animation: none;
                opacity: 1;
            }
        }

        /* Empty state */
        .bio-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .bio-empty-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="bio-container">
        <header class="bio-header">
            <?php if ($logo_url): ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" class="bio-logo">
            <?php endif; ?>
            <h1 class="bio-name"><?php echo esc_html($site_name); ?></h1>
            <?php if ($description): ?>
            <p class="bio-description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </header>

        <nav class="bio-links" aria-label="Link utili">
            <?php if (!empty($tracked_links)): ?>
                <?php foreach ($tracked_links as $link): ?>
                <a href="<?php echo esc_url($link['url']); ?>" class="bio-link" target="_blank" rel="noopener">
                    <?php if (!empty($link['icon'])): ?>
                    <span class="bio-link-icon"><?php echo esc_html($link['icon']); ?></span>
                    <?php endif; ?>
                    <span><?php echo esc_html($link['title']); ?></span>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bio-empty">
                    <div class="bio-empty-icon">🔗</div>
                    <p>Nessun link disponibile</p>
                </div>
            <?php endif; ?>
        </nav>
    </div>

    <footer class="bio-footer">
        <p>Powered by <a href="https://francescopasseri.com" target="_blank" rel="noopener">FP Publisher</a></p>
    </footer>
</body>
</html>
    <?php
}

/**
 * Admin settings page
 */
function fp_bio_standalone_admin_menu() {
    add_options_page(
        __('FP Bio Settings', 'fp-bio-standalone'),
        __('FP Bio', 'fp-bio-standalone'),
        'manage_options',
        'fp-bio-standalone',
        'fp_bio_standalone_settings_page'
    );
}
add_action('admin_menu', 'fp_bio_standalone_admin_menu');

/**
 * Register settings
 */
function fp_bio_standalone_register_settings() {
    register_setting('fp_bio_standalone', 'fp_bio_standalone_settings');
}
add_action('admin_init', 'fp_bio_standalone_register_settings');

/**
 * Settings page content
 */
function fp_bio_standalone_settings_page() {
    $settings = fp_bio_standalone_get_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('FP Bio Standalone Settings', 'fp-bio-standalone'); ?></h1>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin: 20px 0; max-width: 600px;">
            <h2 style="margin-top: 0;">📱 Anteprima</h2>
            <p>
                La tua pagina Bio è disponibile su:<br>
                <a href="<?php echo esc_url(home_url('/bio')); ?>" target="_blank" style="font-size: 16px; font-weight: bold;">
                    <?php echo esc_html(home_url('/bio')); ?> ↗
                </a>
            </p>
        </div>

        <form method="post" action="options.php" style="max-width: 600px;">
            <?php settings_fields('fp_bio_standalone'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="primary_color"><?php esc_html_e('Colore Primario', 'fp-bio-standalone'); ?></label>
                    </th>
                    <td>
                        <input type="color" id="primary_color" name="fp_bio_standalone_settings[primary_color]" 
                               value="<?php echo esc_attr($settings['primary_color']); ?>">
                        <p class="description"><?php esc_html_e('Colore usato per bordi e hover', 'fp-bio-standalone'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="theme"><?php esc_html_e('Tema', 'fp-bio-standalone'); ?></label>
                    </th>
                    <td>
                        <select id="theme" name="fp_bio_standalone_settings[theme]">
                            <option value="auto" <?php selected($settings['theme'], 'auto'); ?>><?php esc_html_e('Automatico (segue preferenze sistema)', 'fp-bio-standalone'); ?></option>
                            <option value="light" <?php selected($settings['theme'], 'light'); ?>><?php esc_html_e('Chiaro', 'fp-bio-standalone'); ?></option>
                            <option value="dark" <?php selected($settings['theme'], 'dark'); ?>><?php esc_html_e('Scuro', 'fp-bio-standalone'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="logo_url"><?php esc_html_e('Logo URL', 'fp-bio-standalone'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="logo_url" name="fp_bio_standalone_settings[logo_url]" 
                               value="<?php echo esc_attr($settings['logo_url']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Lascia vuoto per usare il logo del sito', 'fp-bio-standalone'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="description"><?php esc_html_e('Descrizione', 'fp-bio-standalone'); ?></label>
                    </th>
                    <td>
                        <textarea id="description" name="fp_bio_standalone_settings[description]" 
                                  rows="3" class="large-text"><?php echo esc_textarea($settings['description']); ?></textarea>
                        <p class="description"><?php esc_html_e('Breve descrizione mostrata sotto il nome', 'fp-bio-standalone'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-top: 30px; max-width: 600px;">
            <h3 style="margin-top: 0;">ℹ️ Come Funziona</h3>
            <p>Questo plugin renderizza la pagina <code>/bio</code> come una landing page standalone, bypassando completamente il tema WordPress.</p>
            <p><strong>I link vengono presi da:</strong></p>
            <ol>
                <li>Tabella FP Publisher (se disponibile)</li>
                <li>Contenuto della pagina con slug "bio" (fallback)</li>
            </ol>
            <p>Per gestire i link, usa FP Publisher sul sito manager, oppure crea una pagina "Bio" e inserisci i link manualmente.</p>
        </div>
    </div>
    <?php
}
