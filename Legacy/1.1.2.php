<?php
/**
 * Plugin Name: Custom Schema by BASEO
 * Plugin URI: http://thebaseo.com/plugins
 * Description: 🚀 Professional plugin to add custom schema to each URL of your website. Developed by BASEO to maximize your SEO.
 * Version: 1.1.2
 * Author: BASEO
 * Author URI: http://thebaseo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-schema-baseo
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package CustomSchemaBaseo
 * @author BASEO Team
 * @since 1.1.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit(__('Direct access not allowed!', 'custom-schema-baseo'));
}

// Define plugin constants
define('BASEO_SCHEMA_VERSION', '1.1.2');
define('BASEO_SCHEMA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BASEO_SCHEMA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BASEO_SCHEMA_PLUGIN_BASENAME', plugin_basename(__FILE__));

class CustomSchemaByBaseo {
    
    // Brand information
    private $brand_name = 'BASEO';
    private $plugin_name = 'Custom Schema by BASEO';
    private $plugin_url = 'http://thebaseo.com/plugins';
    private $author_url = 'http://thebaseo.com';
    private $support_url = 'http://thebaseo.com/support';
    private $docs_url = 'http://thebaseo.com/docs/custom-schema';
    private $brand_color = '#FF6B35'; // BASEO primary color
    private $brand_secondary = '#004E98'; // BASEO secondary color
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'create_table'));
        register_deactivation_hook(__FILE__, array($this, 'cleanup'));
        
        // Plugin information hooks
        add_filter('plugin_action_links_' . BASEO_SCHEMA_PLUGIN_BASENAME, array($this, 'add_plugin_links'));
        add_filter('plugin_row_meta', array($this, 'add_plugin_meta'), 10, 2);
        
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_head', array($this, 'inject_schema'));
        add_action('wp_ajax_baseo_save_schema', array($this, 'save_schema'));
        add_action('wp_ajax_baseo_delete_schema', array($this, 'delete_schema'));
        add_action('wp_ajax_baseo_get_schemas', array($this, 'get_schemas'));
        add_action('wp_ajax_baseo_toggle_schema', array($this, 'toggle_schema'));
        add_action('wp_ajax_baseo_get_single_schema', array($this, 'get_single_schema'));
        add_action('wp_ajax_baseo_update_schema', array($this, 'update_schema'));
        add_action('wp_ajax_baseo_search_urls', array($this, 'search_urls'));
        add_action('wp_ajax_baseo_save_bulk_schema', array($this, 'save_bulk_schema'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_footer', array($this, 'admin_footer_branding'));
        
        // NEW FUNCTIONALITIES FOR EDITING FROM URL
        add_action('add_meta_boxes', array($this, 'add_schema_meta_box'));
        add_action('save_post', array($this, 'save_schema_meta_box'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 100);
        add_action('wp_footer', array($this, 'add_frontend_editor'));
    }
    
    // Load text domain for translations
    public function load_textdomain() {
        load_plugin_textdomain('custom-schema-baseo', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    // Create table on plugin activation
    public function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            schema_name varchar(200) NOT NULL DEFAULT 'Schema without name',
            schema_data longtext NOT NULL,
            schema_type varchar(50) DEFAULT 'WebPage',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url_index (url(255)),
            KEY schema_name_index (schema_name),
            KEY active_schemas (is_active),
            KEY schema_type_index (schema_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Custom welcome message
        set_transient('baseo_schema_activation_notice', true, 30);
    }
    
    // Add custom links in plugins page
    public function add_plugin_links($links) {
        $custom_links = array(
            '<a href="' . admin_url('tools.php?page=baseo-custom-schema') . '" style="color: ' . $this->brand_color . '; font-weight: bold;">⚙️ ' . __('Configure', 'custom-schema-baseo') . '</a>',
            '<a href="' . $this->docs_url . '" target="_blank" style="color: ' . $this->brand_secondary . ';">📚 ' . __('Documentation', 'custom-schema-baseo') . '</a>'
        );
        return array_merge($custom_links, $links);
    }
    
    // Add custom metadata
    public function add_plugin_meta($plugin_meta, $plugin_file) {
        if ($plugin_file === BASEO_SCHEMA_PLUGIN_BASENAME) {
            $plugin_meta[] = '<a href="' . $this->support_url . '" target="_blank" style="color: ' . $this->brand_color . ';">🎯 ' . __('Premium Support', 'custom-schema-baseo') . '</a>';
            $plugin_meta[] = '<a href="' . $this->author_url . '" target="_blank" style="color: ' . $this->brand_secondary . ';">🌟 ' . __('More BASEO Plugins', 'custom-schema-baseo') . '</a>';
            $plugin_meta[] = '<span style="color: #666;">' . sprintf(__('Made with ❤️ by %s', 'custom-schema-baseo'), $this->brand_name) . '</span>';
        }
        return $plugin_meta;
    }
    
    // Add admin menu
    public function add_admin_menu() {
        $page = add_management_page(
            $this->plugin_name . ' - ' . __('Control Panel', 'custom-schema-baseo'),
            '🚀 Custom Schema BASEO',
            'manage_options',
            'baseo-custom-schema',
            array($this, 'admin_page')
        );
        
        // Add contextual help
        add_action('load-' . $page, array($this, 'add_help_tabs'));
    }
    
    // Add help tabs
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        $screen->add_help_tab(array(
            'id' => 'baseo-schema-overview',
            'title' => '📋 ' . __('Overview', 'custom-schema-baseo'),
            'content' => $this->get_help_content_overview()
        ));
        
        $screen->add_help_tab(array(
            'id' => 'baseo-schema-usage',
            'title' => '🎯 ' . __('How to Use', 'custom-schema-baseo'),
            'content' => $this->get_help_content_usage()
        ));
        
        $screen->set_help_sidebar($this->get_help_sidebar());
    }
    
    // Load admin scripts
    public function enqueue_admin_scripts($hook) {
        if ($hook != 'tools_page_baseo-custom-schema') {
            return;
        }
        
        // No need to enqueue external JS/CSS files that don't exist
        // Everything is inline in the admin_page() method
        
        // Pass variables to JavaScript
        wp_localize_script('jquery', 'baseo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('baseo_schema_nonce'),
            'brand_name' => $this->brand_name,
            'brand_color' => $this->brand_color,
            'plugin_url' => $this->plugin_url,
            'site_url' => site_url(),
            'i18n' => array(
                'confirm_delete' => __('Are you sure you want to delete this schema?', 'custom-schema-baseo'),
                'json_invalid' => __('Invalid JSON: Please check the syntax.', 'custom-schema-baseo'),
                'json_valid' => __('Valid JSON - Ready to save', 'custom-schema-baseo'),
                'script_tags_detected' => __('Script tags detected! Please remove <script> tags from your JSON-LD code. Only paste the JSON content.', 'custom-schema-baseo'),
                'url_domain_error' => __('URL must be from the same domain as your website', 'custom-schema-baseo'),
                'schema_saved' => __('Schema saved successfully!', 'custom-schema-baseo'),
                'schema_deleted' => __('Schema deleted successfully', 'custom-schema-baseo'),
                'schema_activated' => __('Schema activated', 'custom-schema-baseo'),
                'schema_deactivated' => __('Schema deactivated', 'custom-schema-baseo'),
                'save_schema' => __('Save Schema', 'custom-schema-baseo'),
                'update_schema' => __('Update Schema', 'custom-schema-baseo'),
                'cancel' => __('Cancel', 'custom-schema-baseo'),
                'editing' => __('Editing', 'custom-schema-baseo'),
                'edit_cancelled' => __('Edit cancelled', 'custom-schema-baseo'),
                'schema_updated' => __('updated successfully!', 'custom-schema-baseo'),
                'visit' => __('Visit', 'custom-schema-baseo'),
                'opening_url' => __('Opening URL in new tab', 'custom-schema-baseo'),
                'bulk_success' => __('Bulk schema applied successfully!', 'custom-schema-baseo'),
                'bulk_no_urls' => __('Please select at least one URL', 'custom-schema-baseo'),
                'bulk_confirm' => __('Apply schema to selected URLs?', 'custom-schema-baseo')
            )
        ));
    }
    
    // Admin page
    public function admin_page() {
        // Show activation notification
        if (get_transient('baseo_schema_activation_notice')) {
            echo '<div class="notice notice-success is-dismissible baseo-welcome-notice">';
            echo '<h3>🎉 ' . sprintf(__('Welcome to %s!', 'custom-schema-baseo'), $this->plugin_name) . '</h3>';
            echo '<p>' . sprintf(__('Thank you for choosing <strong>%s</strong>. Your plugin is ready to boost your SEO with custom schemas.', 'custom-schema-baseo'), $this->brand_name) . '</p>';
            echo '<p><a href="' . $this->docs_url . '" target="_blank" class="button button-primary">📚 ' . __('View Documentation', 'custom-schema-baseo') . '</a> ';
            echo '<a href="' . $this->support_url . '" target="_blank" class="button">🎯 ' . __('Premium Support', 'custom-schema-baseo') . '</a></p>';
            echo '</div>';
            delete_transient('baseo_schema_activation_notice');
        }
        ?>
        <div class="wrap baseo-schema-wrap">
            <!-- Premium Header with branding -->
            <div class="baseo-header">
                <div class="baseo-header-content">
                    <div class="baseo-logo-section">
                        <h1>
                            <span class="baseo-logo">🚀</span>
                            <span class="baseo-title"><?php _e('Custom Schema', 'custom-schema-baseo'); ?></span>
                            <span class="baseo-by"><?php _e('by', 'custom-schema-baseo'); ?></span>
                            <span class="baseo-brand"><?php echo $this->brand_name; ?></span>
                        </h1>
                        <p class="baseo-subtitle"><?php _e('Professional plugin to maximize your SEO with structured data', 'custom-schema-baseo'); ?></p>
                    </div>
                    
                    <div class="baseo-header-actions">
                        <a href="<?php echo $this->docs_url; ?>" target="_blank" class="baseo-btn baseo-btn-docs">📚 <?php _e('Docs', 'custom-schema-baseo'); ?></a>
                        <a href="<?php echo $this->support_url; ?>" target="_blank" class="baseo-btn baseo-btn-support">💬 <?php _e('Support', 'custom-schema-baseo'); ?></a>
                        <a href="<?php echo $this->author_url; ?>" target="_blank" class="baseo-btn baseo-btn-brand">🌟 <?php _e('More Plugins', 'custom-schema-baseo'); ?></a>
                    </div>
                </div>
            </div>
            
            <!-- Quick stats -->
            <?php $this->render_dashboard_stats(); ?>
            
            <!-- Tab Navigation -->
            <div class="baseo-tabs-navigation">
                <button class="baseo-tab-button active" data-tab="regular-schema">
                    📝 <?php _e('Regular Schema', 'custom-schema-baseo'); ?>
                </button>
                <button class="baseo-tab-button" data-tab="bulk-schema">
                    ⚡ <?php _e('Bulk Schema', 'custom-schema-baseo'); ?>
                </button>
            </div>
            
            <!-- Main content -->
            <div class="baseo-main-content">
                
                <!-- Regular Schema Tab -->
                <div id="regular-schema-tab" class="baseo-tab-content active">
                    <div class="baseo-content-grid-main">
                        <!-- Left panel: Add Schema -->
                        <div class="baseo-panel baseo-add-schema">
                            <div class="baseo-panel-header">
                                <h2>➕ <?php _e('Add New Schema', 'custom-schema-baseo'); ?></h2>
                                <p><?php _e('Create custom structured data to improve your search rankings', 'custom-schema-baseo'); ?></p>
                            </div>
                            
                            <form id="baseo-schema-form" class="baseo-form">
                                <div class="baseo-form-group">
                                    <label for="baseo-schema-name" class="baseo-label">
                                        ✏️ <?php _e('Schema Name', 'custom-schema-baseo'); ?>
                                        <span class="baseo-required">*</span>
                                    </label>
                                    <input type="text" 
                                           id="baseo-schema-name" 
                                           name="schema_name" 
                                           class="baseo-input" 
                                           placeholder="<?php _e('e.g. Main Schema, FAQ Schema, Product Info', 'custom-schema-baseo'); ?>" 
                                           required />
                                    <div class="baseo-help-text"><?php _e('Give it a descriptive name to identify it easily', 'custom-schema-baseo'); ?></div>
                                </div>
                                
                                <div class="baseo-form-group">
                                    <label for="baseo-url" class="baseo-label">
                                        🔗 <?php _e('Page URL', 'custom-schema-baseo'); ?>
                                        <span class="baseo-required">*</span>
                                    </label>
                                    <input type="text" 
                                           id="baseo-url" 
                                           name="url" 
                                           class="baseo-input" 
                                           placeholder="<?php echo site_url(); ?>/your-page" 
                                           required />
                                    <div class="baseo-help-text"><?php _e('Complete URL where you want to apply the schema', 'custom-schema-baseo'); ?></div>
                                </div>
                                
                                <div class="baseo-form-group">
                                    <label for="baseo-schema-type" class="baseo-label">📋 <?php _e('Schema Type', 'custom-schema-baseo'); ?></label>
                                    <select id="baseo-schema-type" name="schema_type" class="baseo-select">
                                        <option value="WebPage">📄 WebPage - <?php _e('General Web Page', 'custom-schema-baseo'); ?></option>
                                        <option value="Article">📝 Article - <?php _e('Blog Article', 'custom-schema-baseo'); ?></option>
                                        <option value="Product">🛍️ Product - <?php _e('Product', 'custom-schema-baseo'); ?></option>
                                        <option value="Organization">🏢 Organization - <?php _e('Organization', 'custom-schema-baseo'); ?></option>
                                        <option value="LocalBusiness">🏪 LocalBusiness - <?php _e('Local Business', 'custom-schema-baseo'); ?></option>
                                        <option value="Person">👤 Person - <?php _e('Person', 'custom-schema-baseo'); ?></option>
                                        <option value="Event">🎉 Event - <?php _e('Event', 'custom-schema-baseo'); ?></option>
                                        <option value="Recipe">🍳 Recipe - <?php _e('Recipe', 'custom-schema-baseo'); ?></option>
                                        <option value="Review">⭐ Review - <?php _e('Review', 'custom-schema-baseo'); ?></option>
                                        <option value="FAQ">❓ FAQ - <?php _e('Frequently Asked Questions', 'custom-schema-baseo'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="baseo-form-group">
                                    <label for="baseo-schema-data" class="baseo-label">
                                        📊 <?php _e('JSON-LD Code', 'custom-schema-baseo'); ?>
                                        <span class="baseo-required">*</span>
                                    </label>
                                    <div class="baseo-textarea-container">
                                        <textarea id="baseo-schema-data" 
                                                 name="schema_data" 
                                                 rows="12" 
                                                 class="baseo-textarea" 
                                                 placeholder='{"@context": "https://schema.org", "@type": "Organization", "name": "Your Company"}'
                                                 required></textarea>
                                        <div class="baseo-textarea-tools">
                                            <button type="button" id="baseo-validate-json" class="baseo-btn-small">✓ <?php _e('Validate', 'custom-schema-baseo'); ?></button>
                                            <button type="button" id="baseo-format-json" class="baseo-btn-small">🎨 <?php _e('Format', 'custom-schema-baseo'); ?></button>
                                            <button type="button" id="baseo-clear-json" class="baseo-btn-small">🗑️ <?php _e('Clear', 'custom-schema-baseo'); ?></button>
                                        </div>
                                    </div>
                                    <div class="baseo-help-text"><?php _e('Paste your JSON-LD code here (without the &lt;script&gt; tags)', 'custom-schema-baseo'); ?></div>
                                </div>
                                
                                <div class="baseo-form-actions">
                                    <button type="submit" class="baseo-btn baseo-btn-primary">
                                        💾 <?php _e('Save Schema', 'custom-schema-baseo'); ?>
                                    </button>
                                    <button type="button" id="baseo-test-schema" class="baseo-btn baseo-btn-secondary">
                                        🧪 <?php _e('Test with Google', 'custom-schema-baseo'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Right panel: Configured schemas -->
                        <div class="baseo-panel baseo-schemas-list">
                            <div class="baseo-panel-header">
                                <h2>📋 <?php _e('Configured Schemas', 'custom-schema-baseo'); ?></h2>
                                <div class="baseo-panel-actions">
                                    <input type="text" id="baseo-search" placeholder="🔍 <?php _e('Search by URL, schema name, or type...', 'custom-schema-baseo'); ?>" class="baseo-search" />
                                    <button id="baseo-refresh" class="baseo-btn-icon" title="<?php _e('Refresh schemas list', 'custom-schema-baseo'); ?>">🔄</button>
                                </div>
                            </div>
                            
                            <div id="baseo-schemas-container" class="baseo-schemas-container">
                                <div class="baseo-loading">
                                    <div class="baseo-spinner"></div>
                                    <p><?php _e('Loading your schemas...', 'custom-schema-baseo'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Schema Tab -->
                <div id="bulk-schema-tab" class="baseo-tab-content">
                    <div class="baseo-panel baseo-bulk-schema">
                        <div class="baseo-panel-header">
                            <h2>⚡ <?php _e('Bulk Schema', 'custom-schema-baseo'); ?></h2>
                            <p><?php _e('Apply the same schema to multiple URLs at once', 'custom-schema-baseo'); ?></p>
                        </div>
                        
                        <form id="baseo-bulk-form" class="baseo-form baseo-bulk-form">
                            <div class="baseo-bulk-grid">
                                <div class="baseo-bulk-left">
                                    <div class="baseo-form-group">
                                        <label for="baseo-bulk-schema-name" class="baseo-label">
                                            ✏️ <?php _e('Schema Name', 'custom-schema-baseo'); ?>
                                            <span class="baseo-required">*</span>
                                        </label>
                                        <input type="text" 
                                               id="baseo-bulk-schema-name" 
                                               name="bulk_schema_name" 
                                               class="baseo-input" 
                                               placeholder="<?php _e('e.g. Global Organization Schema', 'custom-schema-baseo'); ?>" 
                                               required />
                                    </div>
                                    
                                    <div class="baseo-form-group">
                                        <label for="baseo-bulk-schema-type" class="baseo-label">📋 <?php _e('Schema Type', 'custom-schema-baseo'); ?></label>
                                        <select id="baseo-bulk-schema-type" name="bulk_schema_type" class="baseo-select">
                                            <option value="WebPage">📄 WebPage</option>
                                            <option value="Article">📝 Article</option>
                                            <option value="Product">🛍️ Product</option>
                                            <option value="Organization">🏢 Organization</option>
                                            <option value="LocalBusiness">🏪 LocalBusiness</option>
                                            <option value="Person">👤 Person</option>
                                            <option value="Event">🎉 Event</option>
                                            <option value="Recipe">🍳 Recipe</option>
                                            <option value="Review">⭐ Review</option>
                                            <option value="FAQ">❓ FAQ</option>
                                        </select>
                                    </div>
                                    
                                    <div class="baseo-form-group">
                                        <label for="baseo-bulk-schema-data" class="baseo-label">
                                            📊 <?php _e('JSON-LD Code', 'custom-schema-baseo'); ?>
                                            <span class="baseo-required">*</span>
                                        </label>
                                        <div class="baseo-textarea-container">
                                            <textarea id="baseo-bulk-schema-data" 
                                                     name="bulk_schema_data" 
                                                     rows="6" 
                                                     class="baseo-textarea" 
                                                     placeholder='{"@context": "https://schema.org", "@type": "Organization", "name": "Your Company"}'
                                                     required></textarea>
                                            <div class="baseo-textarea-tools">
                                                <button type="button" id="baseo-validate-bulk-json" class="baseo-btn-small">✓ <?php _e('Validate', 'custom-schema-baseo'); ?></button>
                                                <button type="button" id="baseo-format-bulk-json" class="baseo-btn-small">🎨 <?php _e('Format', 'custom-schema-baseo'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="baseo-bulk-right">
                                    <div class="baseo-form-group">
                                        <label class="baseo-label">
                                            📂 <?php _e('Select URLs', 'custom-schema-baseo'); ?>
                                            <span class="baseo-selected-indicator">
                                                (<span id="baseo-header-selected-count">0</span> <?php _e('selected', 'custom-schema-baseo'); ?>)
                                            </span>
                                        </label>
                                        <div class="baseo-url-selector">
                                            <div class="baseo-search-urls-container">
                                                <input type="text" 
                                                       id="baseo-search-urls" 
                                                       placeholder="🔍 <?php _e('Search URLs by title, slug, or content...', 'custom-schema-baseo'); ?>" 
                                                       class="baseo-input baseo-search-urls" />
                                                <div class="baseo-url-filters">
                                                    <select id="baseo-url-post-type" class="baseo-select-small">
                                                        <option value="">📄 <?php _e('All Types', 'custom-schema-baseo'); ?></option>
                                                        <option value="page">📄 <?php _e('Pages', 'custom-schema-baseo'); ?></option>
                                                        <option value="post">📝 <?php _e('Posts', 'custom-schema-baseo'); ?></option>
                                                        <option value="product">🛍️ <?php _e('Products', 'custom-schema-baseo'); ?></option>
                                                    </select>
                                                    <button type="button" id="baseo-select-all-urls" class="baseo-btn-small">☑️ <?php _e('Select All', 'custom-schema-baseo'); ?></button>
                                                    <button type="button" id="baseo-unselect-all-urls" class="baseo-btn-small">☐ <?php _e('Unselect All', 'custom-schema-baseo'); ?></button>
                                                    <button type="button" id="baseo-load-more-urls" class="baseo-btn-small" style="display: none;">📥 <?php _e('Load More', 'custom-schema-baseo'); ?></button>
                                                </div>
                                            </div>
                                            
                                            <div id="baseo-urls-list" class="baseo-urls-list">
                                                <div class="baseo-loading-urls">
                                                    <div class="baseo-spinner-small"></div>
                                                    <p><?php _e('Loading URLs...', 'custom-schema-baseo'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="baseo-form-actions baseo-bulk-actions">
                                <button type="submit" class="baseo-btn baseo-btn-primary">
                                    ⚡ <?php _e('Apply Bulk Schema', 'custom-schema-baseo'); ?>
                                </button>
                                <div class="baseo-selected-count">
                                    <span id="baseo-selected-urls-count">0</span> <?php _e('URLs selected', 'custom-schema-baseo'); ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
            
            <!-- Premium Footer with branding -->
            <div class="baseo-footer">
                <div class="baseo-footer-content">
                    <div class="baseo-footer-brand">
                        <strong><?php echo $this->plugin_name; ?></strong> v<?php echo BASEO_SCHEMA_VERSION; ?>
                    </div>
                    <div class="baseo-footer-links">
                        <?php printf(__('Developed with ❤️ by %s', 'custom-schema-baseo'), '<a href="' . $this->author_url . '" target="_blank"><strong>' . $this->brand_name . '</strong></a>'); ?>
                        | <a href="<?php echo $this->plugin_url; ?>" target="_blank"><?php _e('More Plugins', 'custom-schema-baseo'); ?></a>
                        | <a href="<?php echo $this->support_url; ?>" target="_blank"><?php _e('Support', 'custom-schema-baseo'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Premium CSS Styles -->
        <style>
        :root {
            --baseo-primary: <?php echo $this->brand_color; ?>;
            --baseo-secondary: <?php echo $this->brand_secondary; ?>;
            --baseo-light: #f8f9ff;
            --baseo-border: #e1e5e9;
            --baseo-text: #2c3e50;
            --baseo-success: #27ae60;
            --baseo-warning: #f39c12;
            --baseo-error: #e74c3c;
            --baseo-gradient: linear-gradient(135deg, var(--baseo-primary) 0%, var(--baseo-secondary) 100%);
            --baseo-shadow: 0 4px 20px rgba(0,0,0,0.1);
            --baseo-shadow-hover: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .baseo-schema-wrap {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, sans-serif;
            max-width: none;
            margin: 0;
            background: #fafbfc;
        }
        
        .baseo-header {
            background: var(--baseo-gradient);
            color: white;
            padding: 40px 30px;
            margin: -10px -20px 0 -22px;
            box-shadow: var(--baseo-shadow);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .baseo-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .baseo-logo-section h1 {
            font-size: 2.8em;
            margin: 0 0 12px 0;
            font-weight: 200;
            letter-spacing: -0.5px;
        }
        
        .baseo-logo {
            font-size: 1.1em;
            margin-right: 12px;
            filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.3));
        }
        
        .baseo-title {
            font-weight: 300;
            color: white;
        }
        
        .baseo-by {
            font-size: 0.6em;
            opacity: 0.8;
            margin: 0 12px;
            font-weight: 300;
        }
        
        .baseo-brand {
            font-weight: 700;
            color: #FFD700;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.4);
            letter-spacing: 1px;
        }
        
        .baseo-subtitle {
            font-size: 1.2em;
            opacity: 0.9;
            margin: 0;
            font-weight: 300;
            line-height: 1.4;
        }
        
        .baseo-header-actions {
            display: flex;
            gap: 12px;
        }
        
        .baseo-btn {
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        
        .baseo-btn-docs {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 2px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
        }
        
        .baseo-btn-support {
            background: rgba(255,255,255,0.95);
            color: var(--baseo-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .baseo-btn-brand {
            background: #FFD700;
            color: var(--baseo-secondary);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
        }
        
        .baseo-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--baseo-shadow-hover);
        }
        
        /* Tab Navigation Styles */
        .baseo-tabs-navigation {
            display: flex;
            gap: 0;
            margin: 30px 20px 0;
            border-bottom: 1px solid var(--baseo-border);
            background: white;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .baseo-tab-button {
            padding: 20px 30px;
            border: none;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            position: relative;
            flex: 1;
            text-align: center;
        }
        
        .baseo-tab-button:hover {
            background: #e9ecef;
            color: var(--baseo-text);
        }
        
        .baseo-tab-button.active {
            background: white;
            color: var(--baseo-primary);
            border-bottom-color: var(--baseo-primary);
            box-shadow: inset 0 -3px 0 var(--baseo-primary);
        }
        
        .baseo-tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 1px;
            background: white;
        }
        
        /* Tab Content Styles */
        .baseo-tab-content {
            display: none;
        }
        
        .baseo-tab-content.active {
            display: block;
        }
        
        .baseo-main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
        }
        
        .baseo-content-grid-main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin: 40px 0;
            align-items: start;
        }
        
        .baseo-panel {
            background: white;
            border-radius: 16px;
            box-shadow: var(--baseo-shadow);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
        }
        
        .baseo-panel-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 30px;
            border-bottom: 1px solid var(--baseo-border);
        }
        
        .baseo-panel-header h2 {
            margin: 0 0 10px 0;
            color: var(--baseo-text);
            font-size: 1.6em;
            font-weight: 600;
            letter-spacing: -0.3px;
        }
        
        .baseo-panel-header p {
            margin: 0;
            color: #6c757d;
            font-size: 15px;
            line-height: 1.5;
        }
        
        .baseo-panel-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }
        
        .baseo-search {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid var(--baseo-border);
            border-radius: 8px;
            font-size: 13px;
            background: rgba(255,255,255,0.9);
        }
        
        .baseo-search:focus {
            outline: none;
            border-color: var(--baseo-primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }
        
        .baseo-btn-icon {
            padding: 8px;
            border: 1px solid var(--baseo-border);
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .baseo-btn-icon:hover {
            background: var(--baseo-primary);
            color: white;
            transform: translateY(-1px);
        }
        
        .baseo-form {
            padding: 35px;
        }
        
        .baseo-form-group {
            margin-bottom: 30px;
        }
        
        .baseo-label {
            display: block;
            font-weight: 600;
            color: var(--baseo-text);
            margin-bottom: 10px;
            font-size: 15px;
            letter-spacing: 0.2px;
        }
        
        .baseo-required {
            color: var(--baseo-error);
            font-weight: 700;
        }
        
        .baseo-input, .baseo-select, .baseo-textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--baseo-border);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
        }
        
        .baseo-input:focus, .baseo-select:focus, .baseo-textarea:focus {
            outline: none;
            border-color: var(--baseo-primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
            background: white;
        }
        
        .baseo-textarea-container {
            position: relative;
        }
        
        .baseo-textarea {
            font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.6;
            resize: vertical;
            min-height: 200px;
        }
        
        .baseo-textarea-tools {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            gap: 6px;
            z-index: 10;
        }
        
        .baseo-btn-small {
            padding: 6px 12px;
            font-size: 11px;
            background: rgba(255,255,255,0.95);
            border: 1px solid var(--baseo-border);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        
        .baseo-btn-small:hover {
            background: var(--baseo-primary);
            color: white;
            transform: translateY(-1px);
        }
        
        .baseo-help-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: 8px;
            line-height: 1.4;
        }
        
        .baseo-form-actions {
            display: flex;
            gap: 18px;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid #f0f2f5;
            flex-wrap: wrap;
        }
        
        .baseo-btn-primary {
            background: var(--baseo-gradient);
            color: white;
            font-size: 16px;
            font-weight: 600;
            padding: 16px 32px;
            box-shadow: var(--baseo-shadow);
        }
        
        .baseo-btn-secondary {
            background: var(--baseo-secondary);
            color: white;
            padding: 16px 28px;
            box-shadow: 0 4px 12px rgba(0, 78, 152, 0.2);
        }
        
        .baseo-btn-warning {
            background: linear-gradient(135deg, var(--baseo-warning) 0%, #e67e22 100%);
            color: white;
            font-size: 16px;
            font-weight: 600;
            padding: 16px 32px;
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }
        
        .baseo-btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(243, 156, 18, 0.4);
        }
        
        .baseo-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 1px solid var(--baseo-border);
            padding: 25px 20px;
            margin: 50px -20px -10px -22px;
            text-align: center;
        }
        
        .baseo-footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .baseo-footer a {
            color: var(--baseo-primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .baseo-welcome-notice {
            border-left: 4px solid var(--baseo-primary) !important;
            background: linear-gradient(135deg, #fff5f2 0%, #ffffff 100%);
            margin: 20px 20px 30px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            position: relative;
            z-index: 10;
        }
        
        .baseo-welcome-notice h3 {
            color: var(--baseo-primary);
            font-weight: 600;
            margin: 0 0 15px 0;
        }
        
        .baseo-welcome-notice p {
            margin: 0 0 15px 0;
            line-height: 1.5;
        }
        
        .baseo-welcome-notice p:last-child {
            margin-bottom: 0;
        }
        
        /* Premium list styles for schemas */
        .baseo-url-group {
            margin-bottom: 20px;
            border: 1px solid #e8ecef;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        
        .baseo-url-group:hover {
            box-shadow: var(--baseo-shadow);
            transform: translateY(-2px);
        }
        
        .baseo-url-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s ease;
        }
        
        .baseo-url-header:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
        }
        
        .baseo-url-header.expanded {
            background: linear-gradient(135deg, var(--baseo-light) 0%, #e3f2fd 100%);
        }
        
        .baseo-url-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .baseo-toggle-icon {
            font-size: 14px;
            color: var(--baseo-primary);
            transition: transform 0.3s ease;
            font-weight: 600;
        }
        
        .baseo-url-title {
            font-weight: 500;
            color: var(--baseo-text);
            font-size: 15px;
            word-break: break-all;
            font-family: 'SF Mono', 'Monaco', monospace;
            background: rgba(0,0,0,0.05);
            padding: 6px 12px;
            border-radius: 6px;
        }
        
        .baseo-url-stats {
            background: var(--baseo-gradient);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .baseo-url-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .baseo-btn-micro {
            padding: 8px 16px;
            font-size: 12px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
        }
        
        .baseo-add-to-url {
            background: var(--baseo-success);
            color: white;
            box-shadow: 0 2px 6px rgba(39, 174, 96, 0.2);
        }
        
        .baseo-add-to-url:hover {
            background: #219a52;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .baseo-visit-url {
            background: var(--baseo-secondary);
            color: white;
            box-shadow: 0 2px 6px rgba(0, 78, 152, 0.2);
        }
        
        .baseo-visit-url:hover {
            background: #003d7a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 78, 152, 0.3);
        }
        
        .baseo-url-content {
            padding: 0;
            background: #fafbfc;
        }
        
        .baseo-schema-item {
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s ease;
            background: white;
        }
        
        .baseo-schema-item:last-child {
            border-bottom: none;
        }
        
        .baseo-schema-item.baseo-active {
            border-left: 4px solid var(--baseo-success);
        }
        
        .baseo-schema-item.baseo-inactive {
            border-left: 4px solid #95a5a6;
            opacity: 0.7;
        }
        
        .baseo-schema-item:hover {
            background: #f8f9fa;
        }
        
        .baseo-schema-header {
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .baseo-schema-info {
            flex: 1;
        }
        
        .baseo-schema-name {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .baseo-schema-name:hover .baseo-name-text {
            color: var(--baseo-primary);
        }
        
        .baseo-status-icon {
            font-size: 18px;
        }
        
        .baseo-name-text {
            font-size: 16px;
            font-weight: 600;
            color: var(--baseo-text);
            transition: color 0.2s ease;
            flex: 1;
        }
        
        .baseo-schema-meta {
            display: flex;
            gap: 18px;
            font-size: 13px;
            color: #6c757d;
        }
        
        .baseo-schema-type {
            font-weight: 500;
            background: rgba(0,0,0,0.05);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
        }
        
        .baseo-schema-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .baseo-edit-schema {
            background: #3498db;
            color: white;
        }
        
        .baseo-toggle-schema {
            background: #f39c12;
            color: white;
        }
        
        .baseo-delete-schema {
            background: #e74c3c;
            color: white;
        }
        
        .baseo-btn-micro:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .baseo-schema-preview-container {
            padding: 0 25px 25px 25px;
            background: white;
        }
        
        .baseo-schema-preview {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            font-size: 12px;
            max-height: 250px;
            overflow-y: auto;
        }
        
        .baseo-schema-preview pre {
            margin: 0;
            font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
            line-height: 1.5;
            color: #2c3e50;
        }
        
        .baseo-preview-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
        }
        
        .baseo-test-schema {
            background: #9b59b6;
            color: white;
        }
        
        .baseo-copy-schema {
            background: #34495e;
            color: white;
        }
        
        .baseo-empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
        }
        
        .baseo-empty-state p {
            font-size: 18px;
            margin: 0;
            opacity: 0.8;
        }
        
        /* Notification styles */
        .baseo-notification {
            position: fixed;
            top: 60px;
            right: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--baseo-shadow-hover);
            padding: 18px 24px;
            z-index: 999999;
            transform: translateX(400px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 350px;
            border-left: 4px solid #ddd;
        }
        
        .baseo-notification.show {
            transform: translateX(0);
        }
        
        .baseo-notification.baseo-success {
            border-left-color: var(--baseo-success);
            background: linear-gradient(135deg, #d5f4e6 0%, #ffffff 100%);
        }
        
        .baseo-notification.baseo-error {
            border-left-color: var(--baseo-error);
            background: linear-gradient(135deg, #ffeaea 0%, #ffffff 100%);
        }
        
        .baseo-notification.baseo-warning {
            border-left-color: var(--baseo-warning);
            background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
        }
        
        .baseo-notification.baseo-info {
            border-left-color: #3498db;
            background: linear-gradient(135deg, #d6eaf8 0%, #ffffff 100%);
        }
        
        /* Loading spinner */
        .baseo-loading {
            text-align: center;
            padding: 60px 20px;
        }
        
        .baseo-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--baseo-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Search result styles */
        .baseo-search-stats {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 12px;
            font-size: 14px;
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }
        
        .baseo-search-stats a {
            color: #1565c0;
            text-decoration: none;
            font-weight: 600;
        }
        
        .baseo-search-stats a:hover {
            text-decoration: underline;
        }
        
        .baseo-search-no-results {
            color: #6c757d;
            padding: 40px 20px;
        }
        
        .baseo-search-no-results small {
            display: block;
            margin-top: 10px;
            opacity: 0.7;
        }
        
        /* Bulk Schema Styles */
        .baseo-bulk-form {
            padding: 30px;
        }
        
        .baseo-bulk-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .baseo-bulk-actions {
            border-top: 1px solid #f0f2f5;
            padding-top: 25px;
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .baseo-url-selector {
            border: 1px solid var(--baseo-border);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .baseo-search-urls-container {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid var(--baseo-border);
        }
        
        .baseo-search-urls {
            margin-bottom: 12px;
        }
        
        .baseo-url-filters {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .baseo-select-small {
            padding: 6px 12px;
            border: 1px solid var(--baseo-border);
            border-radius: 6px;
            font-size: 13px;
            background: white;
        }
        
        .baseo-urls-list {
            max-height: 300px;
            overflow-y: auto;
            background: white;
        }
        
        .baseo-loading-urls {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .baseo-spinner-small {
            width: 24px;
            height: 24px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--baseo-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        .baseo-url-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.2s ease;
        }
        
        .baseo-url-item:last-child {
            border-bottom: none;
        }
        
        .baseo-url-item:hover {
            background: #f8f9fa;
        }
        
        .baseo-url-checkbox {
            margin-right: 12px;
            transform: scale(1.2);
        }
        
        .baseo-url-item .baseo-url-info {
            flex: 1;
        }
        
        .baseo-url-item .baseo-url-title {
            font-weight: 500;
            font-size: 14px;
            color: var(--baseo-text);
            margin-bottom: 4px;
            background: none;
            padding: 0;
            font-family: inherit;
        }
        
        .baseo-url-path {
            font-size: 12px;
            color: #6c757d;
            font-family: 'SF Mono', Monaco, monospace;
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .baseo-url-meta {
            display: flex;
            gap: 10px;
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }
        
        .baseo-selected-count {
            font-size: 13px;
            color: var(--baseo-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .baseo-selected-indicator {
            color: var(--baseo-primary);
            font-weight: 600;
            font-size: 13px;
            margin-left: 10px;
            background: rgba(255, 107, 53, 0.1);
            padding: 4px 10px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .baseo-selected-indicator.has-selection {
            background: var(--baseo-primary);
            color: white;
            transform: scale(1.05);
        }
        
        #baseo-selected-urls-count {
            background: var(--baseo-primary);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }
        
        .baseo-url-item.has-schema {
            background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
            border-left: 3px solid var(--baseo-warning);
        }
        
        .baseo-url-item.has-schema .baseo-url-title:after {
            content: " ⚠️";
            font-size: 11px;
        }
        
        /* Bulk schema specific adjustments */
        .baseo-bulk-schema .baseo-textarea {
            min-height: 150px;
        }
        
        .baseo-bulk-left {
            padding-right: 20px;
        }
        
        .baseo-bulk-right {
            padding-left: 20px;
        }
        
        /* Pagination info styles */
        .baseo-pagination-info {
            padding: 15px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-top: 1px solid #e1e5e9;
            text-align: center;
            color: #1565c0;
            font-weight: 500;
        }
        
        .baseo-pagination-info small {
            font-size: 13px;
        }
        
        .baseo-pagination-info strong {
            color: var(--baseo-primary);
        }
        
        /* Responsive design - Mejorado */
        @media (max-width: 1200px) {
            /* Main grid switches to single column */
            .baseo-content-grid-main {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            // Append more URLs when loading additional pages
            function appendUrlsForBulk(data) {
                var container = $('#baseo-urls-list');
                var urls = data.urls || [];
                
                console.log('appendUrlsForBulk called with:', data);
                
                if (urls.length === 0) {
                    $('#baseo-load-more-urls').hide();
                    showNotification('📋 No more URLs to load', 'info');
                    
                    // Update pagination info to show completion
                    container.find('.baseo-pagination-info').remove();
                    var totalLoaded = container.find('.baseo-url-item').length;
                    var paginationInfo = '<div class="baseo-pagination-info">';
                    paginationInfo += '<small>✅ All ' + totalLoaded + ' URLs loaded</small>';
                    paginationInfo += '</div>';
                    container.append(paginationInfo);
                    return;
                }
                
                // Remove existing pagination info
                container.find('.baseo-pagination-info').remove();
                
                var html = '';
                urls.forEach(function(url) {
                    var hasSchemaClass = url.has_schema ? 'has-schema' : '';
                    html += '<div class="baseo-url-item ' + hasSchemaClass + '">';
                    html += '<input type="checkbox" class="baseo-url-checkbox" value="' + url.url + '" data-title="' + url.title + '">';
                    html += '<div class="baseo-url-info">';
                    html += '<div class="baseo-url-title">' + url.title + '</div>';
                    html += '<div class="baseo-url-path">' + url.path + '</div>';
                    html += '<div class="baseo-url-meta">';
                    html += '<span>' + url.post_type + '</span>';
                    if (url.has_schema) {
                        html += '<span>Has ' + url.schema_count + ' schema(s)</span>';
                    }
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
                
                container.append(html);
                
                // Add updated pagination info
                var totalLoaded = container.find('.baseo-url-item').length;
                var paginationInfo = '';
                
                if (data.has_more) {
                    paginationInfo = '<div class="baseo-pagination-info">';
                    paginationInfo += '<small>📊 Loaded ' + totalLoaded + ' of ' + data.total_posts + ' URLs - <strong>More available!</strong></small>';
                    paginationInfo += '</div>';
                    $('#baseo-load-more-urls').show();
                    currentUrlsPage = data.current_page || currentUrlsPage;
                    console.log('Updated currentUrlsPage to:', currentUrlsPage);
                } else {
                    paginationInfo = '<div class="baseo-pagination-info">';
                    paginationInfo += '<small>✅ All ' + (data.total_posts || totalLoaded) + ' URLs loaded</small>';
                    paginationInfo += '</div>';
                    $('#baseo-load-more-urls').hide();
                }
                
                container.append(paginationInfo);
                updateSelectedCount();
                
                // Show success notification
                showNotification('📥 Loaded ' + urls.length + ' more URLs (' + totalLoaded + ' total)', 'success');
            }
            
            /* Bulk grid also switches to single column */
            .baseo-bulk-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .baseo-bulk-left,
            .baseo-bulk-right {
                padding-left: 0;
                padding-right: 0;
            }
            
            /* Header adjustments */
            .baseo-header-content {
                flex-direction: column;
                text-align: center;
                gap: 25px;
            }
            
            .baseo-header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            /* Footer adjustments */
            .baseo-footer-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            /* URL header adjustments */
            .baseo-url-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .baseo-url-actions {
                align-self: stretch;
                justify-content: space-between;
            }
            
            /* Schema header adjustments */
            .baseo-schema-header {
                flex-direction: column;
                gap: 20px;
            }
            
            .baseo-schema-actions {
                align-self: stretch;
                justify-content: space-between;
            }
            
            /* Form actions adjustments */
            .baseo-form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .baseo-bulk-actions {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
        }
        
        @media (max-width: 768px) {
            /* Further mobile optimizations */
            .baseo-header {
                padding: 30px 20px;
            }
            
            .baseo-logo-section h1 {
                font-size: 2.2em;
            }
            
            .baseo-main-content {
                padding: 0 15px;
            }
            
            .baseo-panel-header {
                padding: 25px 20px;
            }
            
            .baseo-form {
                padding: 25px 20px;
            }
            
            .baseo-textarea-tools {
                position: static;
                margin-top: 10px;
                justify-content: flex-end;
            }
            
            .baseo-btn-small {
                margin-bottom: 5px;
            }
            
            .baseo-url-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .baseo-url-filters > * {
                margin-bottom: 5px;
            }
            
            .baseo-notification {
                left: 15px;
                right: 15px;
                max-width: none;
                transform: translateY(-100px);
            }
            
            .baseo-notification.show {
                transform: translateY(0);
            }

            /* Tab navigation mobile */
            .baseo-tabs-navigation {
                flex-direction: column;
                margin: 30px 15px 0;
            }

            .baseo-tab-button {
                padding: 15px 20px;
                border-bottom: 1px solid var(--baseo-border);
            }

            .baseo-tab-button:last-child {
                border-bottom: none;
            }
        }
        
        @media (max-width: 480px) {
            /* Extra small screens */
            .baseo-logo-section h1 {
                font-size: 1.8em;
            }
            
            .baseo-header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .baseo-btn {
                padding: 12px 20px;
                font-size: 13px;
            }
            
            .baseo-form-group {
                margin-bottom: 25px;
            }
            
            .baseo-input, .baseo-select, .baseo-textarea {
                padding: 14px 16px;
                font-size: 14px;
            }
            
            .baseo-schema-header {
                padding: 15px 20px;
            }
            
            .baseo-url-header {
                padding: 15px 20px;
            }
            
            .baseo-btn-micro {
                padding: 6px 12px;
                font-size: 11px;
            }

            .baseo-tab-button {
                padding: 12px 16px;
                font-size: 14px;
            }
        }
        </style>
        
        <!-- Premium JavaScript -->
        <script>
        // 🔍 SEARCH FUNCTIONALITY - GLOBAL SCOPE (accessible everywhere)
        console.log('🔍 Defining global search function...');
        
        // Global variables
        window.originalSchemas = [];
        window.originalPanelTitle = '';
        
        // 🌟 MAIN SEARCH FUNCTION - GLOBAL ACCESS
        window.baseoFilterSchemas = function(searchTerm) {
            console.log('🔍 baseoFilterSchemas called with term:', searchTerm);
            
            if (!searchTerm) {
                console.log('🔄 No search term, showing all schemas');
                jQuery('.baseo-url-group').show();
                jQuery('.baseo-schema-item').show();
                jQuery('.baseo-search-stats, .baseo-search-no-results').remove();
                
                // Reset panel title
                if (window.originalPanelTitle) {
                    jQuery('.baseo-schemas-list .baseo-panel-header h2').html(window.originalPanelTitle);
                }
                
                // Reset stats and collapse groups
                jQuery('.baseo-url-group').each(function() {
                    var $group = jQuery(this);
                    var activeCount = $group.find('.baseo-schema-item.baseo-active').length;
                    var totalCount = $group.find('.baseo-schema-item').length;
                    $group.find('.baseo-url-stats').text(activeCount + '/' + totalCount + ' active');
                    
                    var $content = $group.find('.baseo-url-content');
                    if ($content.is(':visible')) {
                        $content.slideUp(200);
                        $group.find('.baseo-toggle-icon').text('▶');
                        $group.find('.baseo-url-header').removeClass('expanded');
                    }
                });
                return;
            }
            
            var visibleGroups = 0;
            var visibleSchemas = 0;
            
            // Search through each URL group
            jQuery('.baseo-url-group').each(function() {
                var $group = jQuery(this);
                var url = $group.find('.baseo-url-title').text().toLowerCase();
                var hasMatch = false;
                var matchedInGroup = 0;
                
                // Check each schema in this group
                $group.find('.baseo-schema-item').each(function() {
                    var $schema = jQuery(this);
                    var name = $schema.find('.baseo-name-text').text().toLowerCase();
                    var type = $schema.find('.baseo-schema-type').text().toLowerCase();
                    
                    // Check if this schema matches the search
                    if (url.indexOf(searchTerm) !== -1 || 
                        name.indexOf(searchTerm) !== -1 || 
                        type.indexOf(searchTerm) !== -1) {
                        $schema.show();
                        hasMatch = true;
                        matchedInGroup++;
                        visibleSchemas++;
                    } else {
                        $schema.hide();
                    }
                });
                
                // Show or hide the entire group
                if (hasMatch) {
                    $group.show();
                    visibleGroups++;
                    
                    // Expand group to show results
                    var $content = $group.find('.baseo-url-content');
                    if (!$content.is(':visible')) {
                        $content.slideDown(300);
                        $group.find('.baseo-toggle-icon').text('▼');
                        $group.find('.baseo-url-header').addClass('expanded');
                    }
                    
                    $group.find('.baseo-url-stats').text(matchedInGroup + ' found');
                } else {
                    $group.hide();
                }
            });
            
            console.log('📊 Search results:', visibleSchemas, 'schemas in', visibleGroups, 'groups');
            
            // Update panel title
            jQuery('.baseo-schemas-list .baseo-panel-header h2').html('🔍 Search Results (' + visibleSchemas + ')');
            
            // Remove old results
            jQuery('.baseo-search-stats, .baseo-search-no-results').remove();
            
            // Add search stats
            var stats = '<div class="baseo-search-stats">';
            stats += '🔍 Found <strong>' + visibleSchemas + '</strong> schemas in <strong>' + visibleGroups + '</strong> URLs ';
            stats += '(searching for: "<em>' + searchTerm + '</em>") - ';
            stats += '<a href="#" class="baseo-clear-search">Clear search</a>';
            stats += '</div>';
            jQuery('#baseo-schemas-container').prepend(stats);
            
            // Show no results message if needed
            if (visibleGroups === 0) {
                var noResults = '<div class="baseo-empty-state baseo-search-no-results">';
                noResults += '<p>🔍 No schemas found for "<strong>' + searchTerm + '</strong>"</p>';
                noResults += '<small>💡 Try searching by URL, schema name, or schema type</small>';
                noResults += '</div>';
                jQuery('#baseo-schemas-container').append(noResults);
            }
        };
        
        // ✅ Verify function is available
        console.log('✅ baseoFilterSchemas function defined:', typeof window.baseoFilterSchemas);
        
        // 🚨 IMMEDIATE CLEANUP - Remove any existing handlers that might conflict
        jQuery(document).ready(function($) {
            console.log('🧹 IMMEDIATE cleanup of any existing search handlers...');
            
            // Nuclear cleanup of anything that might be listening
            $(document).off('input', '#baseo-search');
            $(document).off('keyup', '#baseo-search');
            $(document).off('click', '#baseo-refresh');
            
            // Debug: Check what events are attached
            setTimeout(function() {
                var searchElement = document.getElementById('baseo-search');
                if (searchElement) {
                    console.log('🔍 Search element found, checking attached events...');
                    console.log('Event listeners:', $._data(searchElement, 'events'));
                }
            }, 100);
        });
        
        jQuery(document).ready(function($) {
            
            // COMPLETE CLEANUP - Remove ALL old search functionality first
            $(document).off('input', '#baseo-search');
            $(document).off('keyup', '#baseo-search');  
            $(document).off('click', '#baseo-refresh');
            $(document).off('click', '.baseo-clear-search');
            
            // Initialize tab functionality
            initializeTabs();
            
            // Load schemas on start (only for Regular Schema tab)
            loadSchemas();
            currentUrlsPage = 1; // Initialize pagination
            loadUrlsForBulk('', '', 1, false);
            
            // Global keyboard shortcuts ONLY
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 75) {
                    e.preventDefault();
                    var $search = $('#baseo-search');
                    if ($search.length > 0) {
                        $search.focus();
                        showNotification('🔍 Search focused (ESC to clear)', 'info');
                    }
                }
                
                // Ctrl/Cmd + R to refresh (only on admin page)
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 82 && $('.baseo-schema-wrap').length) {
                    e.preventDefault();
                    var $refresh = $('#baseo-refresh');
                    if ($refresh.length > 0) {
                        $refresh.click();
                    }
                }
            });
            
            // Initialize tab functionality
            function initializeTabs() {
                // Tab button click handlers
                $('.baseo-tab-button').on('click', function() {
                    var targetTab = $(this).data('tab');
                    
                    // Remove active class from all tabs and buttons
                    $('.baseo-tab-button').removeClass('active');
                    $('.baseo-tab-content').removeClass('active');
                    
                    // Add active class to clicked button and corresponding tab
                    $(this).addClass('active');
                    $('#' + targetTab + '-tab').addClass('active');
                    
                    // Load content for the activated tab
                    if (targetTab === 'regular-schema') {
                        // Load schemas for regular schema tab if not already loaded
                        if ($('#baseo-schemas-container .baseo-empty-state').length && !$('#baseo-schemas-container .baseo-url-group').length) {
                            loadSchemas();
                        }
                    } else if (targetTab === 'bulk-schema') {
                        // Load URLs for bulk schema if not already loaded
                        if ($('#baseo-urls-list .baseo-loading-urls').length) {
                            currentUrlsPage = 1; // Reset pagination
                            loadUrlsForBulk('', '', 1, false);
                        }
                    }
                });
            }
            
            // Function to initialize search functionality
            function initializeSearchFunctionality() {
                // Simple search functionality - test if it works
                $(document).off('input', '#baseo-search').on('input', '#baseo-search', function() {
                    console.log('Search input detected:', $(this).val()); // Debug log
                    var searchTerm = $(this).val().toLowerCase().trim();
                    window.baseoFilterSchemas(searchTerm);
                });
                
                // Clear search when ESC is pressed
                $(document).off('keyup', '#baseo-search').on('keyup', '#baseo-search', function(e) {
                    if (e.keyCode === 27) { // ESC key
                        console.log('ESC pressed, clearing search'); // Debug log
                        $(this).val('');
                        window.baseoFilterSchemas('');
                        showNotification('🔍 Search cleared', 'info');
                    }
                });
                
                // Clear search link handler
                $(document).off('click', '.baseo-clear-search').on('click', '.baseo-clear-search', function(e) {
                    e.preventDefault();
                    $('#baseo-search').val('');
                    window.baseoFilterSchemas('');
                    showNotification('🔍 Search cleared', 'info');
                });
                
                // Refresh button functionality
                $(document).off('click', '#baseo-refresh').on('click', '#baseo-refresh', function() {
                    console.log('Refresh button clicked'); // Debug log
                    var $button = $(this);
                    var originalText = $button.html();
                    
                    $button.html('⳾').prop('disabled', true);
                    
                    // Clear search
                    $('#baseo-search').val('');
                    
                    // Reload schemas
                    loadSchemas();
                    
                    // Reset button after animation
                    setTimeout(function() {
                        $button.html(originalText).prop('disabled', false);
                        showNotification('🔄 Schemas refreshed successfully', 'success');
                    }, 1000);
                });
                
                // Test if elements exist
                console.log('Search element exists:', $('#baseo-search').length > 0);
                console.log('Refresh button exists:', $('#baseo-refresh').length > 0);
            }
            
            // Handle form submission
            $('#baseo-schema-form').on('submit', function(e) {
                e.preventDefault();
                
                var url = $('#baseo-url').val().trim();
                var schemaName = $('#baseo-schema-name').val().trim();
                var schemaData = $('#baseo-schema-data').val().trim();
                var schemaType = $('#baseo-schema-type').val();
                var editId = $(this).attr('data-edit-id');
                var isEdit = editId ? true : false;
                
                // Validate required fields
                if (!schemaName) {
                    showNotification('⌚ ' + baseo_ajax.i18n.json_invalid, 'error');
                    $('#baseo-schema-name').focus();
                    return;
                }
                
                // Validate domain
                if (!isValidDomain(url)) {
                    showNotification('🚨 ' + baseo_ajax.i18n.url_domain_error, 'error');
                    $('#baseo-url').focus();
                    return;
                }
                
                // Detect script tags
                if (hasScriptTags(schemaData)) {
                    showScriptTagsModal();
                    return;
                }
                
                // Validate JSON before sending
                try {
                    JSON.parse(schemaData);
                } catch (e) {
                    showNotification('⌚ ' + baseo_ajax.i18n.json_invalid, 'error');
                    return;
                }
                
                // Show loading
                var $submitBtn = $('button[type="submit"]');
                var originalText = $submitBtn.html();
                $submitBtn.html(isEdit ? '⳾ Updating...' : '⳾ Saving...').prop('disabled', true);
                
                var ajaxData = {
                    action: isEdit ? 'baseo_update_schema' : 'baseo_save_schema',
                    nonce: baseo_ajax.nonce,
                    url: url,
                    schema_name: schemaName,
                    schema_data: schemaData,
                    schema_type: schemaType
                };
                
                if (isEdit) {
                    ajaxData.id = editId;
                }
                
                $.ajax({
                    url: baseo_ajax.ajax_url,
                    type: 'POST',
                    data: ajaxData,
                    success: function(response) {
                        if (response.success) {
                            var message = isEdit ? 
                                '✅ "' + schemaName + '" ' + baseo_ajax.i18n.schema_updated : 
                                '✅ "' + schemaName + '" ' + baseo_ajax.i18n.schema_saved;
                            showNotification(message, 'success');
                            
                            if (isEdit) {
                                resetForm();
                            } else {
                                $('#baseo-schema-form')[0].reset();
                            }
                            loadSchemas();
                        } else {
                            showNotification('⌚ Error: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showNotification('⌚ Connection error. Please try again.', 'error');
                    },
                    complete: function() {
                        $submitBtn.html(originalText).prop('disabled', false);
                    }
                });
            });
            
            // Validate JSON
            $('#baseo-validate-json').on('click', function() {
                var schemaData = $('#baseo-schema-data').val();
                var $button = $(this);
                
                if (!schemaData.trim()) {
                    showNotification('⚠️ Schema field is empty', 'warning');
                    return;
                }
                
                // Check for script tags
                if (hasScriptTags(schemaData)) {
                    showScriptTagsModal();
                    return;
                }
                
                try {
                    var parsed = JSON.parse(schemaData);
                    $button.html('✅ Valid').css('background', 'var(--baseo-success)').css('color', 'white');
                    showNotification('✅ ' + baseo_ajax.i18n.json_valid, 'success');
                    setTimeout(function() {
                        $button.html('✓ Validate').css('background', '').css('color', '');
                    }, 2000);
                } catch (e) {
                    $button.html('⌚ Error').css('background', 'var(--baseo-error)').css('color', 'white');
                    showNotification('⌚ ' + baseo_ajax.i18n.json_invalid + ': ' + e.message, 'error');
                    setTimeout(function() {
                        $button.html('✓ Validate').css('background', '').css('color', '');
                    }, 3000);
                }
            });
            
            // Format JSON
            $('#baseo-format-json').on('click', function() {
                var schemaData = $('#baseo-schema-data').val();
                try {
                    var formatted = JSON.stringify(JSON.parse(schemaData), null, 2);
                    $('#baseo-schema-data').val(formatted);
                    showNotification('🎨 JSON formatted correctly', 'success');
                } catch (e) {
                    showNotification('⌚ Cannot format: Invalid JSON', 'error');
                }
            });
            
            // Clear textarea
            $('#baseo-clear-json').on('click', function() {
                if (confirm('Are you sure you want to clear the content?')) {
                    $('#baseo-schema-data').val('');
                    showNotification('🗑️ Content cleared', 'info');
                }
            });
            
            // Test with Google
            $('#baseo-test-schema').on('click', function() {
                var url = $('#baseo-url').val();
                if (!url) {
                    showNotification('⚠️ Please enter a URL to test first', 'warning');
                    return;
                }
                window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(url), '_blank');
            });
            
            // Delete schema
            $(document).on('click', '.baseo-delete-schema', function(e) {
                e.preventDefault();
                
                if (!confirm(baseo_ajax.i18n.confirm_delete)) {
                    return;
                }
                
                var schemaId = $(this).data('id');
                var $item = $(this).closest('.baseo-schema-item');
                
                $.ajax({
                    url: baseo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'baseo_delete_schema',
                        nonce: baseo_ajax.nonce,
                        id: schemaId
                    },
                    success: function(response) {
                        if (response.success) {
                            $item.fadeOut(300, function() {
                                $(this).remove();
                                loadSchemas();
                            });
                            showNotification('🗑️ ' + baseo_ajax.i18n.schema_deleted, 'success');
                        } else {
                            showNotification('⌚ Error deleting schema', 'error');
                        }
                    }
                });
            });
            
            // Function to load schemas
            function loadSchemas() {
                console.log('🔥 Loading schemas...');
                
                // CLEAN ALL search handlers before loading (prevent conflicts)
                $('#baseo-search').off();
                $('#baseo-refresh').off();
                $(document).off('input', '#baseo-search');
                $(document).off('keyup', '#baseo-search');  
                $(document).off('click', '#baseo-refresh');
                
                $('#baseo-schemas-container').html('<div class="baseo-loading"><div class="baseo-spinner"></div><p>Loading schemas...</p></div>');
                
                $.ajax({
                    url: baseo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'baseo_get_schemas',
                        nonce: baseo_ajax.nonce
                    },
                    success: function(response) {
                        console.log('📊 Schemas loaded successfully');
                        if (response.success) {
                            displaySchemas(response.data);
                            // CRITICAL: Initialize search functionality AFTER schemas are loaded
                            setTimeout(function() {
                                console.log('🔧 Initializing search after schemas loaded...');
                                console.log('🔍 Verifying function exists:', typeof window.baseoFilterSchemas);
                                initializeSearchFunctionality();
                            }, 500); // Longer delay to ensure everything is ready
                        }
                    },
                    error: function() {
                        console.error('⌚ Error loading schemas');
                        $('#baseo-schemas-container').html('<div class="baseo-empty-state"><p>⌚ Error loading schemas. Please refresh the page.</p></div>');
                    }
                });
            }
            
            // Function to display schemas organized by URL
            function displaySchemas(schemas) {
                var container = $('#baseo-schemas-container');
                
                // Save original panel title if not already saved
                if (!window.originalPanelTitle) {
                    window.originalPanelTitle = $('.baseo-schemas-list .baseo-panel-header h2').html();
                    console.log('🔍 Original panel title saved:', window.originalPanelTitle);
                }
                
                if (schemas.length === 0) {
                    container.html('<div class="baseo-empty-state"><p>🌟 Add your first schema to get started!</p></div>');
                    return;
                }
                
                // Organize schemas by URL
                var schemasByUrl = {};
                schemas.forEach(function(schema) {
                    if (!schemasByUrl[schema.url]) {
                        schemasByUrl[schema.url] = [];
                    }
                    schemasByUrl[schema.url].push(schema);
                });
                
                var html = '';
                
                // Generate HTML organized by URL
                Object.keys(schemasByUrl).forEach(function(url) {
                    var urlSchemas = schemasByUrl[url];
                    var activeCount = urlSchemas.filter(s => s.is_active == '1').length;
                    var totalCount = urlSchemas.length;
                    
                    // Clean URL display - remove trailing numbers
                    var cleanUrl = url.replace(/\/\d+\/\d+$/, '').replace(/\/$/, '');
                    
                    html += '<div class="baseo-url-group">';
                    html += '<div class="baseo-url-header" onclick="baseoToggleUrlGroup(this)">';
                    html += '<div class="baseo-url-info">';
                    html += '<span class="baseo-toggle-icon">▼</span>';
                    html += '<span class="baseo-url-title">' + cleanUrl + '</span>';
                    html += '<span class="baseo-url-stats">' + activeCount + '/' + totalCount + ' active</span>';
                    html += '</div>';
                    html += '<div class="baseo-url-actions">';
                    // FIXED: Removido el onclick inline que causaba el problema
                    html += '<button class="baseo-btn-micro baseo-visit-url" data-url="' + url + '">🔗 Visit</button>';
                    html += '<button class="baseo-btn-micro baseo-add-to-url" data-url="' + url + '">➕ Add Schema</button>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div class="baseo-url-content">';
                    
                    // Sort schemas by creation date
                    urlSchemas.sort(function(a, b) {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });
                    
                    urlSchemas.forEach(function(schema) {
                        var preview = JSON.stringify(JSON.parse(schema.schema_data), null, 2);
                        if (preview.length > 250) {
                            preview = preview.substring(0, 250) + '...';
                        }
                        
                        var statusClass = schema.is_active == '1' ? 'baseo-active' : 'baseo-inactive';
                        var statusIcon = schema.is_active == '1' ? '✅' : '⸏️';
                        
                        html += '<div class="baseo-schema-item ' + statusClass + '">';
                        html += '<div class="baseo-schema-header">';
                        html += '<div class="baseo-schema-info">';
                        html += '<div class="baseo-schema-name">';
                        html += '<span class="baseo-status-icon">' + statusIcon + '</span>';
                        html += '<span class="baseo-name-text">' + schema.schema_name + '</span>';
                        html += '</div>';
                        html += '<div class="baseo-schema-meta">';
                        html += '<span class="baseo-schema-type">' + getSchemaIcon(schema.schema_type) + ' ' + schema.schema_type + '</span>';
                        html += '<span class="baseo-date">Updated: ' + formatDate(schema.updated_at) + '</span>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="baseo-schema-actions">';
                        html += '<button class="baseo-btn-micro baseo-edit-schema" data-id="' + schema.id + '">✏️ Edit</button>';
                        html += '<button class="baseo-btn-micro baseo-toggle-schema" data-id="' + schema.id + '" data-active="' + schema.is_active + '">';
                        html += (schema.is_active == '1' ? '⸏️ Deactivate' : '▶️ Activate');
                        html += '</button>';
                        html += '<button class="baseo-btn-micro baseo-delete-schema" data-id="' + schema.id + '">🗑️ Delete</button>';
                        html += '</div>';
                        html += '</div>';
                        
                        html += '<div class="baseo-schema-preview-container" style="display: none;">';
                        html += '<div class="baseo-schema-preview"><pre>' + preview + '</pre></div>';
                        html += '<div class="baseo-preview-actions">';
                        html += '<button class="baseo-btn-micro baseo-test-schema" data-url="' + schema.url + '">🧪 Test on Google</button>';
                        html += '<button class="baseo-btn-micro baseo-copy-schema" data-schema=\'' + JSON.stringify(schema.schema_data) + '\'>📋 Copy JSON</button>';
                        html += '</div>';
                        html += '</div>';
                        
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    html += '</div>';
                });
                
                container.html(html);
            }
            
            // Function to get schema icons
            function getSchemaIcon(type) {
                var icons = {
                    'WebPage': '📄',
                    'Article': '📝',
                    'Product': '🛍️',
                    'Organization': '🏢',
                    'LocalBusiness': '🏪',
                    'Person': '👤',
                    'Event': '🎉',
                    'Recipe': '🍳',
                    'Review': '⭐',
                    'FAQ': '❓'
                };
                return icons[type] || '📋';
            }
            
            // Function to format date
            function formatDate(dateString) {
                var date = new Date(dateString);
                return date.toLocaleDateString(undefined, {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // Function to show notifications
            function showNotification(message, type) {
                var className = 'baseo-notification baseo-' + type;
                var $notification = $('<div class="' + className + '">' + message + '</div>');
                
                $('body').append($notification);
                
                setTimeout(function() {
                    $notification.addClass('show');
                }, 100);
                
                setTimeout(function() {
                    $notification.removeClass('show');
                    setTimeout(function() {
                        $notification.remove();
                    }, 300);
                }, 4000);
            }
            
            // Function to validate domain
            function isValidDomain(url) {
                try {
                    var urlObj = new URL(url);
                    var siteUrlObj = new URL(baseo_ajax.site_url);
                    return urlObj.hostname === siteUrlObj.hostname;
                } catch (e) {
                    return false;
                }
            }
            
            // Function to detect script tags
            function hasScriptTags(text) {
                return /<script[\s\S]*?>[\s\S]*?<\/script>/i.test(text) || /<script[\s\S]*?>/i.test(text);
            }
            
            // Function to show script tags modal
            function showScriptTagsModal() {
                var modal = $('<div class="baseo-script-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center;">' +
                    '<div style="background: white; padding: 40px; border-radius: 16px; max-width: 500px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">' +
                    '<h3 style="color: #e74c3c; margin-bottom: 20px;">🚨 Script Tags Detected</h3>' +
                    '<p style="margin-bottom: 30px; line-height: 1.6;">' + baseo_ajax.i18n.script_tags_detected + '</p>' +
                    '<button class="baseo-btn baseo-btn-primary" onclick="$(\'.baseo-script-modal\').remove()">Got it!</button>' +
                    '</div>' +
                    '</div>');
                
                $('body').append(modal);
                
                // Remove modal when clicking outside
                modal.on('click', function(e) {
                    if (e.target === this) {
                        $(this).remove();
                    }
                });
            }
            
            // Global function for URL group toggle
            window.baseoToggleUrlGroup = function(header) {
                var $header = $(header);
                var $content = $header.next('.baseo-url-content');
                var $icon = $header.find('.baseo-toggle-icon');
                
                if ($content.is(':visible')) {
                    $content.slideUp(300);
                    $icon.text('▶');
                    $header.removeClass('expanded');
                } else {
                    $content.slideDown(300);
                    $icon.text('▼');
                    $header.addClass('expanded');
                }
            };
            
            // Additional events - FIXED: Event handlers para los botones Visit y Add
            $(document).on('click', '.baseo-add-to-url', function(e) {
                e.stopPropagation(); // Prevenir que se active el toggle del grupo
                var url = $(this).data('url');
                $('#baseo-url').val(url);
                $('#baseo-schema-name').focus();
                showNotification('🔍 URL pre-filled, now add the schema name', 'info');
            });
            
            // FIXED: Event handler corregido para el botón Visit
            $(document).on('click', '.baseo-visit-url', function(e) {
                e.stopPropagation(); // Prevenir que se active el toggle del grupo
                var url = $(this).data('url');
                
                // Validar que la URL esté definida y no esté vacía
                if (!url) {
                    showNotification('⚠️ Invalid URL', 'warning');
                    return;
                }
                
                // Abrir la URL en nueva pestaña
                try {
                    window.open(url, '_blank');
                    showNotification('🔗 ' + baseo_ajax.i18n.opening_url, 'info');
                } catch (error) {
                    showNotification('⌚ Error opening URL: ' + error.message, 'error');
                    console.error('Error opening URL:', error);
                }
            });
            
            $(document).on('click', '.baseo-toggle-schema', function() {
                var schemaId = $(this).data('id');
                var isActive = $(this).data('active') == '1';
                var newStatus = isActive ? '0' : '1';
                
                $.ajax({
                    url: baseo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'baseo_toggle_schema',
                        nonce: baseo_ajax.nonce,
                        id: schemaId,
                        active: newStatus
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification(isActive ? '⸏️ ' + baseo_ajax.i18n.schema_deactivated : '▶️ ' + baseo_ajax.i18n.schema_activated, 'success');
                            loadSchemas();
                        }
                    }
                });
            });
            
            $(document).on('click', '.baseo-schema-name', function() {
                $(this).closest('.baseo-schema-item').find('.baseo-schema-preview-container').slideToggle(300);
            });
            
            $(document).on('click', '.baseo-test-schema', function() {
                var url = $(this).data('url');
                window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(url), '_blank');
            });
            
            $(document).on('click', '.baseo-copy-schema', function() {
                var schemaData = $(this).data('schema');
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(schemaData).then(function() {
                        showNotification('📋 Schema copied to clipboard', 'success');
                    });
                } else {
                    // Fallback for browsers that don't support clipboard API
                    var textArea = document.createElement('textarea');
                    textArea.value = schemaData;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showNotification('📋 Schema copied to clipboard', 'success');
                }
            });
            
            // Edit schema functionality
            $(document).on('click', '.baseo-edit-schema', function() {
                var schemaId = $(this).data('id');
                var $schemaItem = $(this).closest('.baseo-schema-item');
                
                // Get schema data from the item
                var schemaName = $schemaItem.find('.baseo-name-text').text();
                var schemaTypeText = $schemaItem.find('.baseo-schema-type-badge').text().trim();
                var schemaType = schemaTypeText.split(' ')[1]; // Get type after emoji
                var url = $schemaItem.closest('.baseo-url-group').find('.baseo-url-title').text();
                
                // Get full schema data via AJAX
                $.ajax({
                    url: baseo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'baseo_get_single_schema',
                        nonce: baseo_ajax.nonce,
                        id: schemaId
                    },
                    success: function(response) {
                        if (response.success) {
                            var schema = response.data;
                            
                            // Fill form with schema data
                            $('#baseo-schema-name').val(schema.schema_name);
                            $('#baseo-url').val(schema.url);
                            $('#baseo-schema-type').val(schema.schema_type);
                            $('#baseo-schema-data').val(schema.schema_data);
                            
                            // Change form to edit mode
                            $('#baseo-schema-form').attr('data-edit-id', schemaId);
                            $('#baseo-schema-form button[type="submit"]').html('🔄 ' + baseo_ajax.i18n.update_schema).removeClass('baseo-btn-primary').addClass('baseo-btn-warning');
                            
                            // Add cancel button
                            if (!$('#baseo-cancel-edit').length) {
                                $('#baseo-schema-form .baseo-form-actions').append('<button type="button" id="baseo-cancel-edit" class="baseo-btn baseo-btn-secondary">⌚ ' + baseo_ajax.i18n.cancel + '</button>');
                            }
                            
                            // Scroll to form
                            $('html, body').animate({
                                scrollTop: $('#baseo-schema-form').offset().top - 100
                            }, 500);
                            
                            // Focus on name field
                            $('#baseo-schema-name').focus();
                            
                            showNotification('✏️ ' + baseo_ajax.i18n.editing + ' "' + schema.schema_name + '"', 'info');
                        } else {
                            showNotification('⌚ Error loading schema data', 'error');
                        }
                    },
                    error: function() {
                        showNotification('⌚ Connection error', 'error');
                    }
                });
            });
            
            // Cancel edit functionality
            $(document).on('click', '#baseo-cancel-edit', function() {
                resetForm();
                showNotification('⌚ ' + baseo_ajax.i18n.edit_cancelled, 'info');
            });
            
            // Reset form function
            function resetForm() {
                $('#baseo-schema-form')[0].reset();
                $('#baseo-schema-form').removeAttr('data-edit-id');
                $('#baseo-schema-form button[type="submit"]').html('💾 ' + baseo_ajax.i18n.save_schema).removeClass('baseo-btn-warning').addClass('baseo-btn-primary');
                $('#baseo-cancel-edit').remove();
            }
            
            // ⚡ BULK SCHEMA FUNCTIONALITY
            
            // Global variables for pagination
            var currentUrlsPage = 1;
            var currentSearchTerm = '';
            var currentPostType = '';
            var isLoadingUrls = false;
            
            // Load URLs for bulk selection
            function loadUrlsForBulk(searchTerm = '', postType = '', page = 1, append = false) {
                if (isLoadingUrls) return; // Prevent multiple simultaneous requests
                
                isLoadingUrls = true;
                currentSearchTerm = searchTerm;
                currentPostType = postType;
                
                if (!append) {
                    currentUrlsPage = 1;
                    $('#baseo-urls-list').html('<div class="baseo-loading-urls"><div class="baseo-spinner-small"></div><p>Loading URLs...</p></div>');
                    $('#baseo-load-more-urls').hide();
                } else {
                    $('#baseo-load-more-urls').html('⳾ Loading...').prop('disabled', true);
                }
                
                console.log('Loading URLs - Page:', page, 'Search:', searchTerm, 'Type:', postType, 'Append:', append);
                
                $.ajax({
                    url: baseo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'baseo_search_urls',
                        nonce: baseo_ajax.nonce,
                        search: searchTerm,
                        post_type: postType,
                        page: page
                    },
                    success: function(response) {
                        console.log('AJAX Response:', response);
                        
                        if (response.success && response.data) {
                            if (append) {
                                console.log('Appending URLs. Has more:', response.data.has_more, 'URLs count:', response.data.urls ? response.data.urls.length : 0);
                                appendUrlsForBulk(response.data);
                            } else {
                                displayUrlsForBulk(response.data);
                            }
                        } else {
                            console.error('Invalid response:', response);
                            if (!append) {
                                $('#baseo-urls-list').html('<div class="baseo-empty-state"><p>⌚ Error loading URLs</p></div>');
                            } else {
                                showNotification('⌚ Error loading more URLs', 'error');
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error, xhr);
                        if (!append) {
                            $('#baseo-urls-list').html('<div class="baseo-empty-state"><p>⌚ Connection error: ' + error + '</p></div>');
                        } else {
                            showNotification('⌚ Connection error loading more URLs: ' + error, 'error');
                        }
                    },
                    complete: function() {
                        console.log('AJAX Complete. Loading state:', isLoadingUrls);
                        isLoadingUrls = false;
                        $('#baseo-load-more-urls').html('📥 Load More').prop('disabled', false);
                    }
                });
            }
            
            // Display URLs for bulk selection
            function displayUrlsForBulk(data) {
                var container = $('#baseo-urls-list');
                var urls = data.urls || [];
                
                if (urls.length === 0) {
                    container.html('<div class="baseo-empty-state"><p>🔍 No URLs found</p><small>💡 Try adjusting your search terms or filters</small></div>');
                    $('#baseo-load-more-urls').hide();
                    updateSelectedCount();
                    return;
                }
                
                var html = '';
                urls.forEach(function(url) {
                    var hasSchemaClass = url.has_schema ? 'has-schema' : '';
                    html += '<div class="baseo-url-item ' + hasSchemaClass + '">';
                    html += '<input type="checkbox" class="baseo-url-checkbox" value="' + url.url + '" data-title="' + url.title + '">';
                    html += '<div class="baseo-url-info">';
                    html += '<div class="baseo-url-title">' + url.title + '</div>';
                    html += '<div class="baseo-url-path">' + url.path + '</div>';
                    html += '<div class="baseo-url-meta">';
                    html += '<span>' + url.post_type + '</span>';
                    if (url.has_schema) {
                        html += '<span>Has ' + url.schema_count + ' schema(s)</span>';
                    }
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
                
                // Add pagination info
                var totalInfo = '';
                if (data.total_posts > data.per_page) {
                    var showing = Math.min(data.per_page, urls.length);
                    totalInfo = '<div class="baseo-pagination-info">';
                    totalInfo += '<small>📊 Showing ' + showing + ' of ' + data.total_posts + ' total URLs';
                    if (data.has_more) {
                        totalInfo += ' - <strong>Load more to see all</strong>';
                    }
                    totalInfo += '</small>';
                    totalInfo += '</div>';
                }
                
                container.html(html + totalInfo);
                
                // Show/hide load more button
                if (data.has_more) {
                    $('#baseo-load-more-urls').show();
                    currentUrlsPage = data.current_page;
                } else {
                    $('#baseo-load-more-urls').hide();
                }
                
                updateSelectedCount();
            }
            
            // Update selected URLs count
            function updateSelectedCount() {
                var selectedCount = $('.baseo-url-checkbox:checked').length;
                $('#baseo-selected-urls-count').text(selectedCount);
                $('#baseo-header-selected-count').text(selectedCount);
                
                // Update indicator styling based on selection
                var $indicator = $('.baseo-selected-indicator');
                if (selectedCount > 0) {
                    $indicator.addClass('has-selection');
                } else {
                    $indicator.removeClass('has-selection');
                }
            }
            
            // Load more URLs
            $('#baseo-load-more-urls').on('click', function() {
                if (isLoadingUrls) return; // Prevent double clicks
                
                var nextPage = currentUrlsPage + 1;
                console.log('Loading page:', nextPage, 'Current search:', currentSearchTerm, 'Current type:', currentPostType);
                loadUrlsForBulk(currentSearchTerm, currentPostType, nextPage, true);
            });
            
            // Search URLs with debounce
            var searchUrlsTimeout;
            $('#baseo-search-urls').on('input', function() {
                var searchTerm = $(this).val();
                var postType = $('#baseo-url-post-type').val();
                
                clearTimeout(searchUrlsTimeout);
                searchUrlsTimeout = setTimeout(function() {
                    currentUrlsPage = 1; // Reset pagination on new search
                    currentSearchTerm = searchTerm; // Update global state
                    currentPostType = postType; // Update global state
                    loadUrlsForBulk(searchTerm, postType, 1, false);
                }, 500);
            });
            
            // Filter by post type
            $('#baseo-url-post-type').on('change', function() {
                var postType = $(this).val();
                var searchTerm = $('#baseo-search-urls').val();
                currentUrlsPage = 1; // Reset pagination on filter change
                currentSearchTerm = searchTerm; // Update global state
                currentPostType = postType; // Update global state
                loadUrlsForBulk(searchTerm, postType, 1, false);
            });
            
            // Select all URLs (only visible ones)
            $('#baseo-select-all-urls').on('click', function() {
                var visibleCheckboxes = $('.baseo-url-checkbox:visible');
                visibleCheckboxes.prop('checked', true);
                updateSelectedCount();
                var count = visibleCheckboxes.length;
                var totalLoaded = $('.baseo-url-item').length;
                
                if (count < totalLoaded) {
                    showNotification('☑️ ' + count + ' visible URLs selected', 'info');
                } else {
                    showNotification('☑️ All ' + count + ' loaded URLs selected', 'success');
                }
                
                // Show hint if there are more URLs to load
                if ($('#baseo-load-more-urls').is(':visible')) {
                    setTimeout(function() {
                        showNotification('💡 Use "Load More" to select additional URLs', 'info');
                    }, 1500);
                }
            });
            
            // Unselect all URLs
            $('#baseo-unselect-all-urls').on('click', function() {
                $('.baseo-url-checkbox').prop('checked', false);
                updateSelectedCount();
                showNotification('☐ All URLs unselected', 'info');
            });
            
            // Update count when checkboxes change (delegated event for dynamic content)
            $(document).on('change', '.baseo-url-checkbox', function() {
                updateSelectedCount();
                
                // Show feedback when selecting/deselecting
                if (this.checked) {
                    console.log('URL selected:', $(this).data('title'));
                } else {
                    console.log('URL deselected:', $(this).data('title'));
                }
            });
            
            // Validate bulk JSON
            $('#baseo-validate-bulk-json').on('click', function() {
                var schemaData = $('#baseo-bulk-schema-data').val();
                var $button = $(this);
                
                if (!schemaData.trim()) {
                    showNotification('⚠️ Schema field is empty', 'warning');
                    return;
                }
                
                // Check for script tags
                if (hasScriptTags(schemaData)) {
                    showScriptTagsModal();
                    return;
                }
                
                try {
                    JSON.parse(schemaData);
                    $button.html('✅ Valid').css('background', 'var(--baseo-success)').css('color', 'white');
                    showNotification('✅ ' + baseo_ajax.i18n.json_valid, 'success');
                    setTimeout(function() {
                        $button.html('✓ Validate').css('background', '').css('color', '');
                    }, 2000);
                } catch (e) {
                    $button.html('⌚ Error').css('background', 'var(--baseo-error)').css('color', 'white');
                    showNotification('⌚ ' + baseo_ajax.i18n.json_invalid + ': ' + e.message, 'error');
                    setTimeout(function() {
                        $button.html('✓ Validate').css('background', '').css('color', '');
                    }, 3000);
                }
            });
            
            // Format bulk JSON
            $('#baseo-format-bulk-json').on('click', function() {
                var schemaData = $('#baseo-bulk-schema-data').val();
                try {
                    var formatted = JSON.stringify(JSON.parse(schemaData), null, 2);
                    $('#baseo-bulk-schema-data').val(formatted);
                    showNotification('🎨 JSON formatted correctly', 'success');
                } catch (e) {
                    showNotification('⌚ Cannot format: Invalid JSON', 'error');
                }
            });
            
            // Handle bulk form submission
            $('#baseo-bulk-form').on('submit', function(e) {
                e.preventDefault();
                
                var schemaName = $('#baseo-bulk-schema-name').val().trim();
                var schemaData = $('#baseo-bulk-schema-data').val().trim();
                var schemaType = $('#baseo-bulk-schema-type').val();
                var selectedUrls = [];
                
                // Get selected URLs
                $('.baseo-url-checkbox:checked').each(function() {
                    selectedUrls.push({
                        url: $(this).val(),
                        title: $(this).data('title')
                    });
                });
                
                // Validate required fields
                if (!schemaName) {
                    showNotification('⌚ Please enter a schema name', 'error');
                    $('#baseo-bulk-schema-name').focus();
                    return;
                }
                
                if (!schemaData) {
                    showNotification('⌚ Please enter JSON-LD code', 'error');
                    $('#baseo-bulk-schema-data').focus();
                    return;
                }
                
                if (selectedUrls.length === 0) {
                    showNotification('⌚ Please select at least one URL', 'error');
                    return;
                }
                
                // Check for script tags
                if (hasScriptTags(schemaData)) {
                    showScriptTagsModal();
                    return;
                }
                
                // Validate JSON
                try {
                    JSON.parse(schemaData);
                } catch (e) {
                    showNotification('⌚ Invalid JSON: ' + e.message, 'error');
                    return;
                }
                
                // Confirm action
                if (!confirm('Apply "' + schemaName + '" schema to ' + selectedUrls.length + ' selected URLs?')) {
                    return;
                }
                
                // Show loading
                var $submitBtn = $('#baseo-bulk-form button[type="submit"]');
                var originalText = $submitBtn.html();
                $submitBtn.html('⳾ Applying to ' + selectedUrls.length + ' URLs...').prop('disabled', true);
                
                // AJAX request
                $.ajax({
                    url: baseo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'baseo_save_bulk_schema',
                        nonce: baseo_ajax.nonce,
                        schema_name: schemaName,
                        schema_data: schemaData,
                        schema_type: schemaType,
                        urls: selectedUrls
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('✅ Bulk schema applied successfully to ' + response.data.success_count + ' URLs!', 'success');
                            
                            if (response.data.failed_count > 0) {
                                showNotification('⚠️ ' + response.data.failed_count + ' URLs failed to process', 'warning');
                            }
                            
                            // Reset form and clear selections
                            $('#baseo-bulk-form')[0].reset();
                            $('.baseo-url-checkbox').prop('checked', false);
                            updateSelectedCount();
                            loadSchemas(); // Refresh main schemas list
                            
                            // Reload URLs to show updated schema counts
                            currentUrlsPage = 1;
                            loadUrlsForBulk(currentSearchTerm, currentPostType, 1, false);
                            
                            // Show success message with details
                            setTimeout(function() {
                                showNotification('📋 Form reset - Ready for next bulk operation', 'info');
                            }, 2000);
                        } else {
                            showNotification('⌚ Error: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showNotification('⌚ Connection error. Please try again.', 'error');
                    },
                    complete: function() {
                        $submitBtn.html(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // Render dashboard statistics
    private function render_dashboard_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
        $urls_count = $wpdb->get_var("SELECT COUNT(DISTINCT url) FROM $table_name");
        $types = $wpdb->get_results("SELECT schema_type, COUNT(*) as count FROM $table_name GROUP BY schema_type ORDER BY count DESC LIMIT 2");
        ?>
        <div class="baseo-dashboard-stats">
            <div class="baseo-stat-card">
                <div class="baseo-stat-number"><?php echo $total; ?></div>
                <div class="baseo-stat-label">📊 <?php _e('Total Schemas', 'custom-schema-baseo'); ?></div>
            </div>
            <div class="baseo-stat-card">
                <div class="baseo-stat-number"><?php echo $active; ?></div>
                <div class="baseo-stat-label">✅ <?php _e('Active Schemas', 'custom-schema-baseo'); ?></div>
            </div>
            <div class="baseo-stat-card">
                <div class="baseo-stat-number"><?php echo $urls_count; ?></div>
                <div class="baseo-stat-label">🔗 <?php _e('Configured URLs', 'custom-schema-baseo'); ?></div>
            </div>
            <?php if ($types): foreach ($types as $type): ?>
            <div class="baseo-stat-card">
                <div class="baseo-stat-number"><?php echo $type->count; ?></div>
                <div class="baseo-stat-label"><?php echo $this->get_schema_icon($type->schema_type) . ' ' . $type->schema_type; ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        
        <style>
        .baseo-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin: 30px 0 40px;
            padding: 0 20px;
        }
        .baseo-stat-card {
            background: white;
            padding: 30px 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid var(--baseo-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .baseo-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .baseo-stat-number {
            font-size: 3.2em;
            font-weight: 700;
            color: var(--baseo-primary);
            line-height: 1;
            margin-bottom: 12px;
            background: var(--baseo-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .baseo-stat-label {
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .baseo-dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 20px;
                padding: 0 15px;
            }
            .baseo-stat-card {
                padding: 25px 20px;
            }
            .baseo-stat-number {
                font-size: 2.8em;
            }
        }
        
        @media (max-width: 480px) {
            .baseo-dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            .baseo-stat-card {
                padding: 20px 15px;
            }
            .baseo-stat-number {
                font-size: 2.4em;
            }
            .baseo-stat-label {
                font-size: 12px;
            }
        }
        </style>
        <?php
    }
    
    // NEW FUNCTIONALITIES FOR EDITING FROM URL
    
    // Add Meta Box in post/page editor
    public function add_schema_meta_box() {
        $screens = array('post', 'page', 'product'); // Add more types if needed
        
        foreach ($screens as $screen) {
            add_meta_box(
                'baseo-schema-meta-box',
                '🚀 ' . $this->brand_name . ' - Custom Schema',
                array($this, 'render_schema_meta_box'),
                $screen,
                'side',
                'high'
            );
        }
    }
    
    // Render Meta Box
    public function render_schema_meta_box($post) {
        // Get existing schemas for this URL
        $current_url = get_permalink($post->ID);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $existing_schemas = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE url = %s ORDER BY created_at DESC",
            $current_url
        ), ARRAY_A);
        
        wp_nonce_field('baseo_schema_meta_box', 'baseo_schema_meta_box_nonce');
        ?>
        <div class="baseo-meta-box">
            <p><strong>🔍 URL:</strong><br>
            <code><?php echo esc_html($current_url); ?></code></p>
            
            <?php if ($existing_schemas): ?>
            <div class="baseo-existing-schemas">
                <h4>📋 <?php printf(__('Existing Schemas (%d):', 'custom-schema-baseo'), count($existing_schemas)); ?></h4>
                <?php foreach ($existing_schemas as $schema): ?>
                <div class="baseo-mini-schema" style="background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 4px; border-left: 3px solid <?php echo $schema['is_active'] ? '#27ae60' : '#95a5a6'; ?>;">
                    <div style="font-weight: 600; font-size: 12px;">
                        <?php echo $schema['is_active'] ? '✅' : '⸏️'; ?> <?php echo esc_html($schema['schema_name']); ?>
                    </div>
                    <div style="font-size: 11px; color: #666;">
                        <?php echo $this->get_schema_icon($schema['schema_type']); ?> <?php echo $schema['schema_type']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <p style="font-size: 11px; color: #666; margin: 10px 0;">
                    💡 <?php printf(__('Manage all schemas from %s', 'custom-schema-baseo'), '<a href="' . admin_url('tools.php?page=baseo-custom-schema') . '">Custom Schema BASEO</a>'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <hr style="margin: 15px 0;">
            
            <h4>➕ <?php _e('Add New Schema:', 'custom-schema-baseo'); ?></h4>
            
            <p><label for="baseo_meta_schema_name"><strong>✏️ <?php _e('Schema Name:', 'custom-schema-baseo'); ?></strong></label><br>
            <input type="text" name="baseo_meta_schema_name" id="baseo_meta_schema_name" 
                   style="width: 100%;" placeholder="<?php _e('e.g. Main Schema, FAQ, Product', 'custom-schema-baseo'); ?>"/>
            </p>
            
            <p><label for="baseo_meta_schema_type"><strong>📋 <?php _e('Schema Type:', 'custom-schema-baseo'); ?></strong></label><br>
            <select name="baseo_meta_schema_type" id="baseo_meta_schema_type" style="width: 100%;">
                <option value=""><?php _e('Select type...', 'custom-schema-baseo'); ?></option>
                <option value="WebPage">📄 WebPage</option>
                <option value="Article">📝 Article</option>
                <option value="Product">🛍️ Product</option>
                <option value="Organization">🏢 Organization</option>
                <option value="LocalBusiness">🏪 LocalBusiness</option>
                <option value="Person">👤 Person</option>
                <option value="Event">🎉 Event</option>
                <option value="Recipe">🍳 Recipe</option>
            </select></p>
            
            <p><label for="baseo_meta_schema_data"><strong>📊 <?php _e('Schema JSON-LD:', 'custom-schema-baseo'); ?></strong></label><br>
            <textarea name="baseo_meta_schema_data" 
                     id="baseo_meta_schema_data" 
                     rows="6" 
                     style="width: 100%; font-family: monospace; font-size: 11px;"
                     placeholder='{"@context": "https://schema.org", "@type": "Article", "headline": "Article title"}'></textarea></p>
            
            <p style="margin: 10px 0;">
                <button type="button" id="baseo-validate-meta-json" class="button button-small">✓ <?php _e('Validate JSON', 'custom-schema-baseo'); ?></button>
                <button type="button" id="baseo-format-meta-json" class="button button-small">🎨 <?php _e('Format', 'custom-schema-baseo'); ?></button>
            </p>
            
            <?php 
            // Show error/success messages
            $error = get_transient('baseo_schema_error_' . $post->ID);
            $success = get_transient('baseo_schema_success_' . $post->ID);
            
            if ($error): 
                delete_transient('baseo_schema_error_' . $post->ID);
            ?>
            <div class="notice notice-error" style="padding: 8px; margin: 10px 0;">
                <p style="margin: 0; font-size: 12px;">⌚ <?php echo esc_html($error); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($success): 
                delete_transient('baseo_schema_success_' . $post->ID);
            ?>
            <div class="notice notice-success" style="padding: 8px; margin: 10px 0;">
                <p style="margin: 0; font-size: 12px;">✅ <?php echo esc_html($success); ?></p>
            </div>
            <?php endif; ?>
            
            <p><small>💡 <strong><?php _e('Tip:', 'custom-schema-baseo'); ?></strong> <?php _e('Leave fields empty if you don\'t want to add a new schema.', 'custom-schema-baseo'); ?></small></p>
        </div>
        
        <style>
        .baseo-meta-box code {
            background: #f1f1f1;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 11px;
            word-break: break-all;
        }
        .baseo-meta-box textarea {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
        }
        .baseo-meta-box .button-small {
            font-size: 11px;
            height: auto;
            padding: 4px 8px;
            line-height: 1.2;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Validate JSON in meta box
            $('#baseo-validate-meta-json').on('click', function() {
                var schemaData = $('#baseo_meta_schema_data').val();
                var $button = $(this);
                
                if (!schemaData.trim()) {
                    alert('⚠️ Schema field is empty');
                    return;
                }
                
                try {
                    JSON.parse(schemaData);
                    $button.text('✅ Valid').css('color', '#27ae60');
                    setTimeout(function() {
                        $button.text('✓ Validate JSON').css('color', '');
                    }, 2000);
                } catch (e) {
                    alert('⌚ Invalid JSON: ' + e.message);
                    $button.text('⌚ Error').css('color', '#e74c3c');
                    setTimeout(function() {
                        $button.text('✓ Validate JSON').css('color', '');
                    }, 3000);
                }
            });
            
            // Format JSON in meta box
            $('#baseo-format-meta-json').on('click', function() {
                var schemaData = $('#baseo_meta_schema_data').val();
                try {
                    var formatted = JSON.stringify(JSON.parse(schemaData), null, 2);
                    $('#baseo_meta_schema_data').val(formatted);
                } catch (e) {
                    alert('⌚ Cannot format: Invalid JSON');
                }
            });
        });
        </script>
        <?php
    }
    
    // Save Meta Box
    public function save_schema_meta_box($post_id) {
        // Verify nonce
        if (!isset($_POST['baseo_schema_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['baseo_schema_meta_box_nonce'], 'baseo_schema_meta_box')) {
            return;
        }
        
        // Verify permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $url = get_permalink($post_id);
        $schema_name = sanitize_text_field($_POST['baseo_meta_schema_name'] ?? '');
        $schema_type = sanitize_text_field($_POST['baseo_meta_schema_type'] ?? '');
        $schema_data = wp_unslash($_POST['baseo_meta_schema_data'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        // If no data to add, do nothing
        if (empty($schema_name) || empty($schema_type) || empty($schema_data)) {
            return;
        }
        
        // Validate JSON
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Show error on next load
            set_transient('baseo_schema_error_' . $post_id, sprintf(__('Invalid JSON in schema "%s"', 'custom-schema-baseo'), $schema_name), 30);
            return;
        }
        
        // Save new schema
        $result = $wpdb->insert(
            $table_name,
            array(
                'url' => $url,
                'schema_name' => $schema_name,
                'schema_data' => $schema_data,
                'schema_type' => $schema_type,
                'is_active' => 1
            ),
            array('%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result !== false) {
            // Success message
            set_transient('baseo_schema_success_' . $post_id, sprintf(__('Schema "%s" saved successfully', 'custom-schema-baseo'), $schema_name), 30);
        } else {
            set_transient('baseo_schema_error_' . $post_id, sprintf(__('Error saving schema "%s"', 'custom-schema-baseo'), $schema_name), 30);
        }
    }
    
    // Add button in admin bar (frontend)
    public function add_admin_bar_button($wp_admin_bar) {
        if (!is_admin() && current_user_can('manage_options')) {
            $current_url = $this->get_current_url();
            
            // Check if schemas exist for this URL
            global $wpdb;
            $table_name = $wpdb->prefix . 'baseo_custom_schemas';
            
            $schema_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE url = %s",
                $current_url
            ));
            
            $button_text = $schema_count > 0 ? '✏️ ' . __('Manage Schemas', 'custom-schema-baseo') . ' (' . $schema_count . ')' : '➕ ' . __('Add Schema', 'custom-schema-baseo');
            $button_class = $schema_count > 0 ? 'baseo-edit-schema' : 'baseo-add-schema';
            
            $wp_admin_bar->add_node(array(
                'id'    => 'baseo-schema-editor',
                'title' => $button_text,
                'href'  => '#',
                'meta'  => array(
                    'class' => $button_class,
                    'onclick' => 'baseoToggleSchemaEditor(); return false;'
                )
            ));
        }
    }
    
    // Frontend editor for administrators
    public function add_frontend_editor() {
        if (!is_admin() && current_user_can('manage_options')) {
            $current_url = $this->get_current_url();
            
            // Get existing schemas
            global $wpdb;
            $table_name = $wpdb->prefix . 'baseo_custom_schemas';
            
            $existing_schemas = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE url = %s ORDER BY created_at DESC",
                $current_url
            ), ARRAY_A);
            ?>
            
            <!-- Premium Frontend Editor -->
            <div id="baseo-frontend-editor" class="baseo-frontend-editor" style="display: none;">
                <div class="baseo-editor-overlay"></div>
                <div class="baseo-editor-modal">
                    <div class="baseo-editor-header">
                        <h3>🚀 <?php echo $this->brand_name; ?> - <?php _e('Schema Editor', 'custom-schema-baseo'); ?></h3>
                        <button type="button" id="baseo-close-editor" class="baseo-close-btn">✕</button>
                    </div>
                    
                    <div class="baseo-editor-content">
                        <div class="baseo-editor-info">
                            <p><strong>🔍 <?php _e('Current URL:', 'custom-schema-baseo'); ?></strong><br>
                            <code><?php echo esc_html($current_url); ?></code></p>
                            
                            <?php if ($existing_schemas): ?>
                            <div class="baseo-existing-list">
                                <h4>📋 <?php printf(__('Existing Schemas (%d):', 'custom-schema-baseo'), count($existing_schemas)); ?></h4>
                                <?php foreach ($existing_schemas as $schema): ?>
                                <div class="baseo-mini-item" style="background: #f8f9fa; padding: 12px 15px; margin: 6px 0; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid <?php echo $schema['is_active'] ? '#27ae60' : '#95a5a6'; ?>;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">
                                            <?php echo $schema['is_active'] ? '✅' : '⸏️'; ?> <?php echo esc_html($schema['schema_name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #666;">
                                            <?php echo $this->get_schema_icon($schema['schema_type']); ?> <?php echo $schema['schema_type']; ?>
                                            <span style="margin-left: 10px; color: #999;">ID: <?php echo $schema['id']; ?></span>
                                        </div>
                                    </div>
                                    <div class="baseo-mini-actions" style="display: flex; gap: 4px;">
                                        <button class="baseo-btn-tiny baseo-edit-existing" 
                                                data-id="<?php echo $schema['id']; ?>"
                                                data-name="<?php echo esc_attr($schema['schema_name']); ?>"
                                                data-type="<?php echo esc_attr($schema['schema_type']); ?>"
                                                style="background: #3498db; color: white; border: none; padding: 3px 8px; border-radius: 3px; font-size: 10px; cursor: pointer; margin-right: 2px;">
                                            ✏️ Edit
                                        </button>
                                        <button class="baseo-btn-tiny baseo-toggle-existing" 
                                                data-id="<?php echo $schema['id']; ?>"
                                                data-active="<?php echo $schema['is_active']; ?>"
                                                style="background: <?php echo $schema['is_active'] ? '#f39c12' : '#27ae60'; ?>; color: white; border: none; padding: 3px 8px; border-radius: 3px; font-size: 10px; cursor: pointer; margin-right: 2px;">
                                            <?php echo $schema['is_active'] ? '⸏️' : '▶️'; ?>
                                        </button>
                                        <button class="baseo-btn-tiny baseo-delete-existing" 
                                                data-id="<?php echo $schema['id']; ?>" 
                                                style="background: #e74c3c; color: white; border: none; padding: 3px 8px; border-radius: 3px; font-size: 10px; cursor: pointer;">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <hr style="margin: 20px 0;">
                        
                        <div class="baseo-form-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 id="baseo-form-title">➕ <?php _e('Add New Schema:', 'custom-schema-baseo'); ?></h4>
                            <button type="button" id="baseo-cancel-frontend-edit" class="baseo-btn-cancel" style="display: none; background: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; cursor: pointer;">
                                ⌚ <?php _e('Cancel Edit', 'custom-schema-baseo'); ?>
                            </button>
                        </div>
                        
                        <form id="baseo-frontend-form">
                            <input type="hidden" id="frontend-edit-id" name="edit_id" value="">
                            
                            <div class="baseo-field">
                                <label for="frontend-schema-name">✏️ <?php _e('Schema Name:', 'custom-schema-baseo'); ?></label>
                                <input type="text" name="schema_name" id="frontend-schema-name" placeholder="<?php _e('e.g. Main Schema, FAQ, Product Info', 'custom-schema-baseo'); ?>" required />
                            </div>
                            
                            <div class="baseo-field">
                                <label for="frontend-schema-type">📋 <?php _e('Schema Type:', 'custom-schema-baseo'); ?></label>
                                <select name="schema_type" id="frontend-schema-type" required>
                                    <option value=""><?php _e('Select type...', 'custom-schema-baseo'); ?></option>
                                    <option value="WebPage">📄 WebPage</option>
                                    <option value="Article">📝 Article</option>
                                    <option value="Product">🛍️ Product</option>
                                    <option value="Organization">🏢 Organization</option>
                                    <option value="LocalBusiness">🏪 LocalBusiness</option>
                                    <option value="Person">👤 Person</option>
                                    <option value="Event">🎉 Event</option>
                                    <option value="Recipe">🍳 Recipe</option>
                                </select>
                            </div>
                            
                            <div class="baseo-field">
                                <label for="frontend-schema-data">📊 <?php _e('Schema JSON-LD:', 'custom-schema-baseo'); ?></label>
                                <textarea name="schema_data" 
                                         id="frontend-schema-data" 
                                         rows="8" 
                                         placeholder='{"@context": "https://schema.org", "@type": "WebPage", "name": "Page title"}'
                                         required></textarea>
                            </div>
                            
                            <div class="baseo-editor-actions">
                                <button type="button" id="validate-frontend-json" class="baseo-btn-secondary">✓ <?php _e('Validate', 'custom-schema-baseo'); ?></button>
                                <button type="button" id="format-frontend-json" class="baseo-btn-secondary">🎨 <?php _e('Format', 'custom-schema-baseo'); ?></button>
                                <button type="submit" id="baseo-frontend-submit" class="baseo-btn-primary">💾 <?php _e('Save Schema', 'custom-schema-baseo'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <style>
            .baseo-frontend-editor {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            }
            
            .baseo-editor-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.75);
                backdrop-filter: blur(10px);
            }
            
            .baseo-editor-modal {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                width: 90%;
                max-width: 700px;
                max-height: 85vh;
                overflow: hidden;
            }
            
            .baseo-editor-header {
                background: linear-gradient(135deg, <?php echo $this->brand_color; ?> 0%, <?php echo $this->brand_secondary; ?> 100%);
                color: white;
                padding: 25px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .baseo-editor-header h3 {
                margin: 0;
                font-size: 1.4em;
                font-weight: 600;
            }
            
            .baseo-close-btn {
                background: rgba(255,255,255,0.15);
                border: none;
                color: white;
                width: 35px;
                height: 35px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 18px;
                transition: all 0.2s ease;
                backdrop-filter: blur(10px);
            }
            
            .baseo-close-btn:hover {
                background: rgba(255,255,255,0.25);
                transform: scale(1.1);
            }
            
            .baseo-editor-content {
                padding: 30px;
                max-height: 60vh;
                overflow-y: auto;
            }
            
            .baseo-editor-info {
                margin-bottom: 25px;
                padding: 20px;
                background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                border-radius: 10px;
                border: 1px solid #e9ecef;
            }
            
            .baseo-editor-info code {
                background: #e9ecef;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                word-break: break-all;
            }
            
            .baseo-existing-list {
                margin-top: 15px;
            }
            
            .baseo-existing-list h4 {
                margin: 10px 0;
                font-size: 14px;
                color: #495057;
            }
            
            .baseo-field {
                margin-bottom: 25px;
            }
            
            .baseo-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #2c3e50;
                font-size: 14px;
            }
            
            .baseo-field input,
            .baseo-field select,
            .baseo-field textarea {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            
            .baseo-field input:focus,
            .baseo-field select:focus,
            .baseo-field textarea:focus {
                outline: none;
                border-color: <?php echo $this->brand_color; ?>;
                box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
            }
            
            .baseo-field textarea {
                font-family: 'SF Mono', Monaco, Consolas, monospace;
                font-size: 12px;
                line-height: 1.5;
                resize: vertical;
            }
            
            .baseo-editor-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 30px;
                padding-top: 25px;
                border-top: 1px solid #e1e5e9;
            }
            
            .baseo-btn-primary {
                background: linear-gradient(135deg, <?php echo $this->brand_color; ?> 0%, <?php echo $this->brand_secondary; ?> 100%);
                color: white;
                border: none;
                padding: 14px 28px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 14px;
            }
            
            .baseo-btn-secondary {
                background: #6c757d;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 12px;
                transition: all 0.2s ease;
            }
            
            .baseo-btn-primary:hover,
            .baseo-btn-secondary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .baseo-btn-cancel {
                transition: all 0.2s ease;
            }
            
            .baseo-btn-cancel:hover {
                background: #5a6268 !important;
                transform: translateY(-1px);
            }
            
            .baseo-form-header {
                border-bottom: 1px solid #e1e5e9;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            
            .baseo-form-header h4 {
                margin: 0;
                color: #2c3e50;
                font-weight: 600;
            }
            
            .baseo-mini-actions button {
                transition: all 0.2s ease;
            }
            
            .baseo-mini-actions button:hover {
                transform: translateY(-1px);
                opacity: 0.9;
            }
            
            #baseo-form-title.editing {
                color: <?php echo $this->brand_color; ?>;
            }
            
            @media (max-width: 768px) {
                .baseo-editor-modal {
                    width: 95%;
                    max-height: 90vh;
                }
                
                .baseo-editor-content {
                    padding: 20px;
                }
                
                .baseo-editor-actions {
                    flex-direction: column;
                }
            }
            </style>
            
            <script>
            // Global variables
            var baseoEditorOpen = false;
            
            // Function to show/hide editor
            function baseoToggleSchemaEditor() {
                var editor = document.getElementById('baseo-frontend-editor');
                if (baseoEditorOpen) {
                    editor.style.display = 'none';
                    baseoEditorOpen = false;
                } else {
                    editor.style.display = 'block';
                    baseoEditorOpen = true;
                }
            }
            
            // Close editor
            document.getElementById('baseo-close-editor').addEventListener('click', function() {
                baseoToggleSchemaEditor();
            });
            
            // Close when clicking overlay
            document.querySelector('.baseo-editor-overlay').addEventListener('click', function() {
                baseoToggleSchemaEditor();
            });
            
            // Validate JSON frontend
            document.getElementById('validate-frontend-json').addEventListener('click', function() {
                var schemaData = document.getElementById('frontend-schema-data').value;
                var button = this;
                
                if (!schemaData.trim()) {
                    alert('⚠️ Schema field is empty');
                    return;
                }
                
                // Check for script tags
                if (/<script[\s\S]*?>[\s\S]*?<\/script>/i.test(schemaData) || /<script[\s\S]*?>/i.test(schemaData)) {
                    alert('🚨 Script tags detected! Please remove <script> tags from your JSON-LD code.');
                    return;
                }
                
                try {
                    JSON.parse(schemaData);
                    button.textContent = '✅ Valid';
                    button.style.background = '#27ae60';
                    setTimeout(function() {
                        button.textContent = '✓ Validate';
                        button.style.background = '#6c757d';
                    }, 2000);
                } catch (e) {
                    alert('⌚ Invalid JSON: ' + e.message);
                    button.textContent = '⌚ Error';
                    button.style.background = '#e74c3c';
                    setTimeout(function() {
                        button.textContent = '✓ Validate';
                        button.style.background = '#6c757d';
                    }, 3000);
                }
            });
            
            // Format JSON frontend
            document.getElementById('format-frontend-json').addEventListener('click', function() {
                var schemaData = document.getElementById('frontend-schema-data').value;
                try {
                    var formatted = JSON.stringify(JSON.parse(schemaData), null, 2);
                    document.getElementById('frontend-schema-data').value = formatted;
                } catch (e) {
                    alert('⌚ Cannot format: Invalid JSON');
                }
            });
            
            // Save schema frontend
            document.getElementById('baseo-frontend-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                var url = '<?php echo $current_url; ?>';
                var schemaName = document.getElementById('frontend-schema-name').value;
                var schemaType = document.getElementById('frontend-schema-type').value;
                var schemaData = document.getElementById('frontend-schema-data').value;
                var editId = document.getElementById('frontend-edit-id').value;
                var isEdit = editId ? true : false;
                
                // Validate required fields
                if (!schemaName.trim() || !schemaType || !schemaData.trim()) {
                    alert('⌚ Please fill all required fields');
                    return;
                }
                
                // Validate domain
                try {
                    var urlObj = new URL(url);
                    var siteUrlObj = new URL('<?php echo site_url(); ?>');
                    if (urlObj.hostname !== siteUrlObj.hostname) {
                        alert('🚨 URL must be from the same domain as your website');
                        return;
                    }
                } catch (e) {
                    alert('⌚ Invalid URL format');
                    return;
                }
                
                // Check for script tags
                if (/<script[\s\S]*?>[\s\S]*?<\/script>/i.test(schemaData) || /<script[\s\S]*?>/i.test(schemaData)) {
                    alert('🚨 Script tags detected! Please remove <script> tags from your JSON-LD code.');
                    return;
                }
                
                // Validate JSON
                try {
                    JSON.parse(schemaData);
                } catch (e) {
                    alert('⌚ Invalid JSON: ' + e.message);
                    return;
                }
                
                // Show loading state
                var submitBtn = document.getElementById('baseo-frontend-submit');
                var originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = isEdit ? '⳾ Updating...' : '⳾ Saving...';
                submitBtn.disabled = true;
                
                // AJAX request
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    var message = isEdit ? 
                                        '✅ Schema "' + schemaName + '" updated successfully!' :
                                        '✅ Schema "' + schemaName + '" saved successfully!';
                                    alert(message);
                                    
                                    // Reset form and reload page
                                    resetFrontendForm();
                                    location.reload();
                                } else {
                                    alert('⌚ Error: ' + response.data);
                                }
                            } catch (e) {
                                alert('⌚ Server response error');
                            }
                        } else {
                            alert('⌚ Connection error');
                        }
                    }
                };
                
                var action = isEdit ? 'baseo_update_schema' : 'baseo_save_schema';
                var data = 'action=' + action + '&nonce=<?php echo wp_create_nonce('baseo_schema_nonce'); ?>&url=' + encodeURIComponent(url) + '&schema_name=' + encodeURIComponent(schemaName) + '&schema_data=' + encodeURIComponent(schemaData) + '&schema_type=' + encodeURIComponent(schemaType);
                
                if (isEdit) {
                    data += '&id=' + editId;
                }
                
                xhr.send(data);
            });
            
            // Function to reset frontend form
            function resetFrontendForm() {
                document.getElementById('baseo-frontend-form').reset();
                document.getElementById('frontend-edit-id').value = '';
                document.getElementById('baseo-form-title').innerHTML = '➕ <?php _e("Add New Schema:", "custom-schema-baseo"); ?>';
                document.getElementById('baseo-form-title').classList.remove('editing');
                document.getElementById('baseo-frontend-submit').innerHTML = '💾 <?php _e("Save Schema", "custom-schema-baseo"); ?>';
                document.getElementById('baseo-cancel-frontend-edit').style.display = 'none';
            }
            
            // Function to set form to edit mode
            function setFrontendEditMode(schemaData) {
                document.getElementById('frontend-edit-id').value = schemaData.id;
                document.getElementById('frontend-schema-name').value = schemaData.schema_name;
                document.getElementById('frontend-schema-type').value = schemaData.schema_type;
                document.getElementById('frontend-schema-data').value = schemaData.schema_data;
                
                document.getElementById('baseo-form-title').innerHTML = '✏️ <?php _e("Edit Schema:", "custom-schema-baseo"); ?> "' + schemaData.schema_name + '"';
                document.getElementById('baseo-form-title').classList.add('editing');
                document.getElementById('baseo-frontend-submit').innerHTML = '🔄 <?php _e("Update Schema", "custom-schema-baseo"); ?>';
                document.getElementById('baseo-cancel-frontend-edit').style.display = 'inline-block';
                
                // Focus on name field
                document.getElementById('frontend-schema-name').focus();
                document.getElementById('frontend-schema-name').select();
            }
            
            // Cancel edit functionality
            document.getElementById('baseo-cancel-frontend-edit').addEventListener('click', function() {
                resetFrontendForm();
                alert('⌚ Edit cancelled');
            });
            
            // Edit existing schema
            <?php if ($existing_schemas): ?>
            document.querySelectorAll('.baseo-edit-existing').forEach(function(button) {
                button.addEventListener('click', function() {
                    var schemaId = this.getAttribute('data-id');
                    
                    // Show loading
                    this.innerHTML = '⳾';
                    this.disabled = true;
                    
                    // Get full schema data via AJAX
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    var self = this;
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            self.innerHTML = '✏️ Edit';
                            self.disabled = false;
                            
                            if (xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        setFrontendEditMode(response.data);
                                        
                                        // Scroll to form
                                        document.getElementById('baseo-frontend-form').scrollIntoView({
                                            behavior: 'smooth',
                                            block: 'start'
                                        });
                                    } else {
                                        alert('⌚ Error loading schema data: ' + response.data);
                                    }
                                } catch (e) {
                                    alert('⌚ Server response error');
                                }
                            } else {
                                alert('⌚ Connection error');
                            }
                        }
                    };
                    
                    var data = 'action=baseo_get_single_schema&nonce=<?php echo wp_create_nonce('baseo_schema_nonce'); ?>&id=' + schemaId;
                    xhr.send(data);
                });
            });
            
            // Toggle existing schema
            document.querySelectorAll('.baseo-toggle-existing').forEach(function(button) {
                button.addEventListener('click', function() {
                    var schemaId = this.getAttribute('data-id');
                    var isActive = this.getAttribute('data-active') == '1';
                    var newStatus = isActive ? '0' : '1';
                    
                    // Show loading
                    var originalText = this.innerHTML;
                    this.innerHTML = '⳾';
                    this.disabled = true;
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    var self = this;
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        alert(isActive ? '⸏️ Schema deactivated' : '▶️ Schema activated');
                                        location.reload();
                                    } else {
                                        alert('⌚ Error updating status');
                                        self.innerHTML = originalText;
                                        self.disabled = false;
                                    }
                                } catch (e) {
                                    alert('⌚ Server response error');
                                    self.innerHTML = originalText;
                                    self.disabled = false;
                                }
                            } else {
                                alert('⌚ Connection error');
                                self.innerHTML = originalText;
                                self.disabled = false;
                            }
                        }
                    };
                    
                    var data = 'action=baseo_toggle_schema&nonce=<?php echo wp_create_nonce('baseo_schema_nonce'); ?>&id=' + schemaId + '&active=' + newStatus;
                    xhr.send(data);
                });
            });
            
            // Delete existing schema
            document.querySelectorAll('.baseo-delete-existing').forEach(function(button) {
                button.addEventListener('click', function() {
                    if (!confirm('Are you sure you want to delete this schema?')) {
                        return;
                    }
                    
                    var schemaId = this.getAttribute('data-id');
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            alert('🗑️ Schema deleted successfully');
                            location.reload();
                        }
                    };
                    
                    var data = 'action=baseo_delete_schema&nonce=<?php echo wp_create_nonce('baseo_schema_nonce'); ?>&id=' + schemaId;
                    xhr.send(data);
                });
            });
            <?php endif; ?>
            </script>
            <?php
        }
    }
    
    // Get schema icon
    private function get_schema_icon($type) {
        $icons = array(
            'WebPage' => '📄',
            'Article' => '📝',
            'Product' => '🛍️',
            'Organization' => '🏢',
            'LocalBusiness' => '🏪',
            'Person' => '👤',
            'Event' => '🎉',
            'Recipe' => '🍳',
            'Review' => '⭐',
            'FAQ' => '❓'
        );
        return isset($icons[$type]) ? $icons[$type] : '📋';
    }
    
    // Save schema via AJAX
    public function save_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo') . ' - ' . $this->brand_name);
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo') . ' - ' . __('Contact', 'custom-schema-baseo') . ' ' . $this->brand_name);
        }
        
        $url = sanitize_text_field($_POST['url']);
        $schema_name = sanitize_text_field($_POST['schema_name'] ?? __('Schema without name', 'custom-schema-baseo'));
        $schema_data = wp_unslash($_POST['schema_data']);
        $schema_type = sanitize_text_field($_POST['schema_type'] ?? 'WebPage');
        $is_active = 1; // New schema active by default
        
        // Validate domain
        $site_url = parse_url(site_url());
        $input_url = parse_url($url);
        
        if (!$input_url || $input_url['host'] !== $site_url['host']) {
            wp_send_json_error(__('URL must be from the same domain as your website', 'custom-schema-baseo'));
            return;
        }
        
        // Validate JSON
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON - Verify syntax with', 'custom-schema-baseo') . ' ' . $this->brand_name);
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'url' => $url,
                'schema_name' => $schema_name,
                'schema_data' => $schema_data,
                'schema_type' => $schema_type,
                'is_active' => $is_active
            ),
            array('%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(sprintf(__('Schema "%s" saved successfully by %s', 'custom-schema-baseo'), $schema_name, $this->brand_name));
        } else {
            wp_send_json_error(__('Error saving - Contact support', 'custom-schema-baseo') . ' ' . $this->brand_name);
        }
    }
    
    // Delete schema via AJAX
    public function delete_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo'));
        }
        
        $id = intval($_POST['id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        wp_send_json_success();
    }
    
    // Toggle schema via AJAX
    public function toggle_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo'));
        }
        
        $id = intval($_POST['id']);
        $is_active = intval($_POST['active']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $result = $wpdb->update(
            $table_name,
            array('is_active' => $is_active),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Error updating status', 'custom-schema-baseo'));
        }
    }
    
    // Get schemas via AJAX
    public function get_schemas() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $schemas = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC", ARRAY_A);
        
        wp_send_json_success($schemas);
    }
    
    // Get single schema via AJAX
    public function get_single_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo'));
        }
        
        $id = intval($_POST['id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $schema = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($schema) {
            wp_send_json_success($schema);
        } else {
            wp_send_json_error(__('Schema not found', 'custom-schema-baseo'));
        }
    }
    
    // Update schema via AJAX
    public function update_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo') . ' - ' . $this->brand_name);
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo') . ' - ' . __('Contact', 'custom-schema-baseo') . ' ' . $this->brand_name);
        }
        
        $id = intval($_POST['id']);
        $url = sanitize_text_field($_POST['url']);
        $schema_name = sanitize_text_field($_POST['schema_name'] ?? __('Schema without name', 'custom-schema-baseo'));
        $schema_data = wp_unslash($_POST['schema_data']);
        $schema_type = sanitize_text_field($_POST['schema_type'] ?? 'WebPage');
        
        // Validate domain
        $site_url = parse_url(site_url());
        $input_url = parse_url($url);
        
        if (!$input_url || $input_url['host'] !== $site_url['host']) {
            wp_send_json_error(__('URL must be from the same domain as your website', 'custom-schema-baseo'));
            return;
        }
        
        // Validate JSON
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON - Verify syntax with', 'custom-schema-baseo') . ' ' . $this->brand_name);
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'url' => $url,
                'schema_name' => $schema_name,
                'schema_data' => $schema_data,
                'schema_type' => $schema_type,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(sprintf(__('Schema "%s" updated successfully by %s', 'custom-schema-baseo'), $schema_name, $this->brand_name));
        } else {
            wp_send_json_error(__('Error updating - Contact support', 'custom-schema-baseo') . ' ' . $this->brand_name);
        }
    }
    
    // Search URLs for bulk schema
    public function search_urls() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo'));
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = 50; // URLs per page
        
        global $wpdb;
        $schema_table = $wpdb->prefix . 'baseo_custom_schemas';
        
        // Build query
        $args = array(
            'post_type' => !empty($post_type) ? $post_type : array('page', 'post', 'product'),
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $posts_query = new WP_Query($args);
        $posts = $posts_query->posts;
        
        // Check if there are more pages
        $has_more = $posts_query->max_num_pages > $page;
        $total_posts = $posts_query->found_posts;
        
        // Clean up memory
        wp_reset_postdata();
        
        $urls = array();
        foreach ($posts as $post) {
            $url = get_permalink($post->ID);
            
            // Check if this URL already has schemas
            $schema_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $schema_table WHERE url = %s",
                $url
            ));
            
            $urls[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => $url,
                'path' => str_replace(home_url(), '', $url),
                'post_type' => ucfirst($post->post_type),
                'has_schema' => $schema_count > 0,
                'schema_count' => (int)$schema_count
            );
        }
        
        wp_send_json_success(array(
            'urls' => $urls,
            'has_more' => $has_more,
            'current_page' => $page,
            'total_posts' => $total_posts,
            'per_page' => $per_page
        ));
    }
    
    // Save bulk schema
    public function save_bulk_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_die(__('Security error', 'custom-schema-baseo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'custom-schema-baseo'));
        }
        
        $schema_name = sanitize_text_field($_POST['schema_name']);
        $schema_data = wp_unslash($_POST['schema_data']);
        $schema_type = sanitize_text_field($_POST['schema_type']);
        $urls = $_POST['urls']; // Array of URL objects
        
        // Validate JSON
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON', 'custom-schema-baseo'));
            return;
        }
        
        if (empty($urls) || !is_array($urls)) {
            wp_send_json_error(__('No URLs selected', 'custom-schema-baseo'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($urls as $url_data) {
            $url = sanitize_text_field($url_data['url']);
            $title = sanitize_text_field($url_data['title']);
            
            // Validate domain
            $site_url = parse_url(site_url());
            $input_url = parse_url($url);
            
            if (!$input_url || $input_url['host'] !== $site_url['host']) {
                $failed_count++;
                continue;
            }
            
            // Create unique schema name for this URL
            $unique_schema_name = $schema_name . ' - ' . $title;
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'url' => $url,
                    'schema_name' => $unique_schema_name,
                    'schema_data' => $schema_data,
                    'schema_type' => $schema_type,
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result !== false) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }
        
        wp_send_json_success(array(
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'total_count' => count($urls)
        ));
    }
    
    // Inject schema in head
    public function inject_schema() {
        $current_url = $this->get_current_url();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        // Get all active schemas for this URL, ordered by creation date
        $schemas = $wpdb->get_results($wpdb->prepare(
            "SELECT schema_name, schema_data FROM $table_name 
             WHERE url = %s AND is_active = 1 
             ORDER BY created_at ASC",
            $current_url
        ));
        
        if ($schemas) {
            echo '<!-- Custom Schema by ' . $this->brand_name . ' -->' . "\n";
            
            foreach ($schemas as $schema) {
                echo '<!-- Schema: ' . esc_html($schema->schema_name) . ' -->' . "\n";
                echo '<script type="application/ld+json">' . "\n";
                echo $schema->schema_data . "\n";
                echo '</script>' . "\n";
            }
            
            echo '<!-- End Custom Schema by ' . $this->brand_name . ' -->' . "\n";
        }
    }
    
    // Get current URL
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    // Footer with branding
    public function admin_footer_branding() {
        $screen = get_current_screen();
        if ($screen->id === 'tools_page_baseo-custom-schema') {
            echo '<script>console.log("🚀 Custom Schema by ' . $this->brand_name . ' v' . BASEO_SCHEMA_VERSION . ' - Boosting your SEO!");</script>';
        }
    }
    
    // Help content
    private function get_help_content_overview() {
        return '<h4>🎯 ' . __('What does this plugin do?', 'custom-schema-baseo') . '</h4>
                <p><strong>' . $this->plugin_name . '</strong> ' . __('allows you to add custom structured data (Schema.org) to any page of your website to improve your search engine rankings.', 'custom-schema-baseo') . '</p>
                <h4>✨ ' . __('Benefits:', 'custom-schema-baseo') . '</h4>
                <ul>
                    <li>🚀 <strong>' . __('Better SEO:', 'custom-schema-baseo') . '</strong> ' . __('Rich snippets in Google', 'custom-schema-baseo') . '</li>
                    <li>🎨 <strong>' . __('Easy to use:', 'custom-schema-baseo') . '</strong> ' . __('Intuitive interface', 'custom-schema-baseo') . '</li>
                    <li>⚡ <strong>' . __('Fast:', 'custom-schema-baseo') . '</strong> ' . __('Optimized for performance', 'custom-schema-baseo') . '</li>
                    <li>🔒 <strong>' . __('Secure:', 'custom-schema-baseo') . '</strong> ' . __('Integrated JSON validation', 'custom-schema-baseo') . '</li>
                </ul>';
    }
    
    private function get_help_content_usage() {
        return '<h4>📋 ' . __('Steps to add a schema:', 'custom-schema-baseo') . '</h4>
                <ol>
                    <li>' . __('Enter the <strong>complete URL</strong> of the page', 'custom-schema-baseo') . '</li>
                    <li>' . __('Select the appropriate <strong>schema type</strong>', 'custom-schema-baseo') . '</li>
                    <li>' . __('Paste your <strong>JSON-LD</strong> code (without script tags)', 'custom-schema-baseo') . '</li>
                    <li>' . __('Click <strong>"Validate"</strong> to verify syntax', 'custom-schema-baseo') . '</li>
                    <li>' . __('Save and <strong>you\'re done!</strong>', 'custom-schema-baseo') . '</li>
                </ol>
                <h4>💡 ' . __('Useful resources:', 'custom-schema-baseo') . '</h4>
                <p><a href="https://schema.org/" target="_blank">Schema.org</a> | 
                   <a href="https://search.google.com/test/rich-results" target="_blank">Google Rich Results Test</a></p>';
    }
    
    private function get_help_sidebar() {
        return '<p><strong>🎯 ' . __('Premium Support', 'custom-schema-baseo') . '</strong></p>
                <p>' . __('Need personalized help?', 'custom-schema-baseo') . '</p>
                <p><a href="' . $this->support_url . '" target="_blank" class="button button-primary">' . sprintf(__('Contact %s', 'custom-schema-baseo'), $this->brand_name) . '</a></p>
                <hr>
                <p><strong>📚 ' . __('Documentation', 'custom-schema-baseo') . '</strong></p>
                <p><a href="' . $this->docs_url . '" target="_blank">' . __('View complete guides', 'custom-schema-baseo') . '</a></p>
                <hr>
                <p><strong>🌟 ' . __('More Plugins', 'custom-schema-baseo') . '</strong></p>
                <p><a href="' . $this->author_url . '" target="_blank">' . sprintf(__('Discover more %s tools', 'custom-schema-baseo'), $this->brand_name) . '</a></p>';
    }
    
    // Clean on deactivation
    public function cleanup() {
        // Keep data in case they reactivate the plugin
        delete_transient('baseo_schema_activation_notice');
    }
}

// 🚀 Initialize Custom Schema by BASEO
new CustomSchemaByBaseo();