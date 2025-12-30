<?php
/**
 * Plugin Name: Custom Schema by BASEO
 * Plugin URI: http://thebaseo.com/plugins
 * Description: 🚀 Professional plugin to add custom schema to each URL of your website. Developed by BASEO to maximize your SEO.
 * Version: 1.0.1
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
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit(__('Direct access not allowed!', 'custom-schema-baseo'));
}

// Define plugin constants
define('BASEO_SCHEMA_VERSION', '1.0.1');
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
    private $brand_color = '#FF6B35';
    private $brand_secondary = '#004E98';
    
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
    
    // Register REST API routes
    public function register_rest_routes() {
        $namespace = 'baseo/v1';
        
        // Listar y crear schemas
        register_rest_route($namespace, '/schemas', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'rest_get_schemas'),
                'permission_callback' => array($this, 'rest_permission_check')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'rest_create_schema'),
                'permission_callback' => array($this, 'rest_permission_check')
            )
        ));
        
        // Leer, actualizar y eliminar schema específico
        register_rest_route($namespace, '/schemas/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'rest_get_schema'),
                'permission_callback' => array($this, 'rest_permission_check')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'rest_update_schema'),
                'permission_callback' => array($this, 'rest_permission_check')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'rest_delete_schema'),
                'permission_callback' => array($this, 'rest_permission_check')
            )
        ));
        
        // Toggle estado activo/inactivo
        register_rest_route($namespace, '/schemas/(?P<id>\d+)/toggle', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_toggle_schema'),
            'permission_callback' => array($this, 'rest_permission_check')
        ));
    }
    
    // REST API permission check
    public function rest_permission_check($request) {
        // Mapear capacidad personalizada a 'manage_options' por defecto
        return current_user_can(apply_filters('baseo_schema_capability', 'manage_options'));
    }
    
    // REST API: Get all schemas with pagination and filters
    public function rest_get_schemas($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        // 1. Leer y sanear query params
        $page = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?? 20)));
        $url_filter = $request->get_param('url');
        $type_filter = $request->get_param('schema_type');
        
        // Validar schema_type si viene
        if ($type_filter !== null) {
            $allowed_types = array('WebPage', 'Article', 'Product', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Recipe', 'Review', 'FAQ');
            if (!in_array($type_filter, $allowed_types, true)) {
                return new WP_Error('invalid_type', __('server_invalid_schema_type', 'custom-schema-baseo'), array('status' => 400));
            }
            $type_filter = sanitize_text_field($type_filter);
        }
        
        // Sanitizar URL si viene
        if ($url_filter !== null) {
            $url_filter = esc_url_raw($url_filter);
        }
        
        // 2. Construir WHERE dinámicamente
        $where_clauses = array();
        $where_params = array();
        
        if ($url_filter) {
            $where_clauses[] = 'url = %s';
            $where_params[] = $url_filter;
        }
        
        if ($type_filter) {
            $where_clauses[] = 'schema_type = %s';
            $where_params[] = $type_filter;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // 3. COUNT filtrado
        $count_query = "SELECT COUNT(*) FROM $table_name $where_sql";
        if (!empty($where_params)) {
            $count_query = $wpdb->prepare($count_query, ...$where_params);
        }
        $total_items = intval($wpdb->get_var($count_query));
        
        // Calcular total de páginas
        $total_pages = ceil($total_items / $per_page);
        
        // Forzar página 1 si no hay resultados o ajustar si excede
        if ($total_pages < 1) {
            $page = 1;
            $offset = 0;
            $total_pages = 1;
        } elseif ($page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $per_page;
        } else {
            $offset = ($page - 1) * $per_page;
        }
        
        // 4. SELECT paginado con filtros
        $select_query = "SELECT * FROM $table_name $where_sql ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($where_params, array($per_page, $offset));
        
        if (empty($where_params)) {
            $schemas = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ), ARRAY_A);
        } else {
            $schemas = $wpdb->get_results($wpdb->prepare(
                $select_query,
                ...$query_params
            ), ARRAY_A);
        }
        
        // 5. Crear response con headers de paginación
        $response = new WP_REST_Response($schemas);
        
        $response->header('X-WP-Total', $total_items);
        $response->header('X-WP-TotalPages', $total_pages);
        $response->header('X-WP-Page', $page);
        $response->header('X-WP-Per-Page', $per_page);
        
        // Construir cabeceras Link
        $links = array();
        
        // Link a página actual (self)
        $self_params = array_filter(array(
            'page' => $page,
            'per_page' => $per_page,
            'schema_type' => $type_filter,
            'url' => $url_filter
        ));
        $self_url = untrailingslashit(rest_url('baseo/v1/schemas')) . '?' . http_build_query($self_params, '', '&', PHP_QUERY_RFC3986);
        $links[] = '<' . $self_url . '>; rel="self"';
        
        // Link a primera página
        if ($page > 1) {
            $first_params = array_filter(array(
                'page' => 1,
                'per_page' => $per_page,
                'schema_type' => $type_filter,
                'url' => $url_filter
            ));
            $first_url = untrailingslashit(rest_url('baseo/v1/schemas')) . '?' . http_build_query($first_params, '', '&', PHP_QUERY_RFC3986);
            $links[] = '<' . $first_url . '>; rel="first"';
        }
        
        // Link a página anterior
        if ($page > 1) {
            $prev_params = array_filter(array(
                'page' => $page - 1,
                'per_page' => $per_page,
                'schema_type' => $type_filter,
                'url' => $url_filter
            ));
            $prev_url = untrailingslashit(rest_url('baseo/v1/schemas')) . '?' . http_build_query($prev_params, '', '&', PHP_QUERY_RFC3986);
            $links[] = '<' . $prev_url . '>; rel="prev"';
        }
        
        // Link a página siguiente
        if ($page < $total_pages) {
            $next_params = array_filter(array(
                'page' => $page + 1,
                'per_page' => $per_page,
                'schema_type' => $type_filter,
                'url' => $url_filter
            ));
            $next_url = untrailingslashit(rest_url('baseo/v1/schemas')) . '?' . http_build_query($next_params, '', '&', PHP_QUERY_RFC3986);
            $links[] = '<' . $next_url . '>; rel="next"';
        }
        
        // Link a última página
        if ($page < $total_pages && $total_pages > 1) {
            $last_params = array_filter(array(
                'page' => $total_pages,
                'per_page' => $per_page,
                'schema_type' => $type_filter,
                'url' => $url_filter
            ));
            $last_url = untrailingslashit(rest_url('baseo/v1/schemas')) . '?' . http_build_query($last_params, '', '&', PHP_QUERY_RFC3986);
            $links[] = '<' . $last_url . '>; rel="last"';
        }
        
        // Setear cabecera Link si hay links
        if (!empty($links)) {
            $response->header('Link', implode(', ', $links));
        }
        
        return $response;
    }
    
    // REST API: Get single schema
    public function rest_get_schema($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $schema = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $request['id']
        ), ARRAY_A);
        
        if (!$schema) {
            return new WP_Error('not_found', __('Schema not found', 'custom-schema-baseo'), array('status' => 404));
        }
        
        return rest_ensure_response($schema);
    }
    
    // REST API: Create schema
    public function rest_create_schema($request) {
        $params = $request->get_json_params();
        
        // Validación de URL
        $url = esc_url_raw($params['url'] ?? '');
        if (!$this->validate_url($url)) {
            return new WP_Error('invalid_url', __('server_invalid_url', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Canonización a HTTPS
        $url = $this->canonize_url_https($url);
        
        // Validación de schema_type
        $allowed_types = array('WebPage', 'Article', 'Product', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Recipe', 'Review', 'FAQ');
        $schema_type = sanitize_text_field($params['schema_type'] ?? 'WebPage');
        if (!in_array($schema_type, $allowed_types, true)) {
            return new WP_Error('invalid_type', __('server_invalid_schema_type', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Validación de JSON y script tags
        $schema_data = wp_unslash($params['schema_data'] ?? '');
        if (preg_match('/<\s*\/?\s*script\b/i', $schema_data)) {
            return new WP_Error('script_tags', __('server_contains_script_tags', 'custom-schema-baseo'), array('status' => 400));
        }
        
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('server_invalid_json', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Sanitizar meta tags
        $meta_title = isset($params['meta_title']) ? sanitize_text_field($params['meta_title']) : '';
        $meta_description = isset($params['meta_description']) ? sanitize_textarea_field($params['meta_description']) : '';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'url' => $url,
                'schema_name' => sanitize_text_field($params['schema_name'] ?? __('Schema without name', 'custom-schema-baseo')),
                'schema_data' => $schema_data,
                'schema_type' => $schema_type,
                'meta_title' => $meta_title,
                'meta_description' => $meta_description,
                'is_active' => 1
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Error saving schema', 'custom-schema-baseo'), array('status' => 500));
        }
        
        return rest_ensure_response(array('id' => $wpdb->insert_id, 'success' => true));
    }
    
    // REST API: Update schema
    public function rest_update_schema($request) {
        $params = $request->get_json_params();
        $id = intval($request['id']);
        
        // Verificar que el schema existe
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE id = %d",
            $id
        ));
        
        if (!$exists) {
            return new WP_Error('not_found', __('Schema not found', 'custom-schema-baseo'), array('status' => 404));
        }
        
        // Validación de URL
        $url = esc_url_raw($params['url'] ?? '');
        if (!$this->validate_url($url)) {
            return new WP_Error('invalid_url', __('server_invalid_url', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Canonización a HTTPS
        $url = $this->canonize_url_https($url);
        
        // Validación de schema_type con whitelist
        $allowed_types = array('WebPage', 'Article', 'Product', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Recipe', 'Review', 'FAQ');
        $schema_type = sanitize_text_field($params['schema_type'] ?? 'WebPage');
        if (!in_array($schema_type, $allowed_types, true)) {
            return new WP_Error('invalid_type', __('server_invalid_schema_type', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Validación de script tags
        $schema_data = wp_unslash($params['schema_data'] ?? '');
        if (preg_match('/<\s*\/?\s*script\b/i', $schema_data)) {
            return new WP_Error('script_tags', __('server_contains_script_tags', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Validación de JSON
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('server_invalid_json', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Sanitizar meta tags
        $meta_title = isset($params['meta_title']) ? sanitize_text_field($params['meta_title']) : '';
        $meta_description = isset($params['meta_description']) ? sanitize_textarea_field($params['meta_description']) : '';
        
        $schema_name = sanitize_text_field($params['schema_name'] ?? __('Schema without name', 'custom-schema-baseo'));
        
        $result = $wpdb->update(
            $table_name,
            array(
                'url' => $url,
                'schema_name' => $schema_name,
                'schema_data' => $schema_data,
                'schema_type' => $schema_type,
                'meta_title' => $meta_title,
                'meta_description' => $meta_description,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Error updating schema', 'custom-schema-baseo'), array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    // REST API: Delete schema
    public function rest_delete_schema($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        $id = intval($request['id']);
        
        // Verificar que existe antes de eliminar
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE id = %d",
            $id
        ));
        
        if (!$exists) {
            return new WP_Error('not_found', __('Schema not found', 'custom-schema-baseo'), array('status' => 404));
        }
        
        $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
        
        if ($result === false) {
            return new WP_Error('db_error', __('Error deleting schema', 'custom-schema-baseo'), array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    // REST API: Toggle schema status
    public function rest_toggle_schema($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $id = intval($request['id']);
        
        // Obtener estado actual
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM $table_name WHERE id = %d",
            $id
        ));
        
        if ($current === null) {
            return new WP_Error('not_found', __('Schema not found', 'custom-schema-baseo'), array('status' => 404));
        }
        
        $new_status = $current ? 0 : 1;
        
        $result = $wpdb->update(
            $table_name,
            array('is_active' => $new_status),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Error updating status', 'custom-schema-baseo'), array('status' => 500));
        }
        
        return rest_ensure_response(array('is_active' => $new_status));
    }
    
    // Helper method: Validate URL
    private function validate_url($url) {
        if (empty($url)) return false;
        
        $site_parsed = wp_parse_url(home_url());
        $input_parsed = wp_parse_url($url);
        
        // Verificar que wp_parse_url no falló y que tiene host
        if ($input_parsed === false || !isset($input_parsed['host'])) {
            return false;
        }
        
        if (!isset($input_parsed['scheme']) || !in_array($input_parsed['scheme'], array('http', 'https'))) {
            return false;
        }
        
        $site_host = strtolower($site_parsed['host']);
        $input_host = strtolower($input_parsed['host']);
        
        $is_same_host = ($input_host === $site_host);
        $is_subdomain = (substr_compare($input_host, '.' . $site_host, -strlen('.' . $site_host)) === 0);
        
        return ($is_same_host || $is_subdomain);
    }
    
    // Helper method: Canonize URL to HTTPS
    private function canonize_url_https($url) {
        $site_parsed = wp_parse_url(home_url());
        $input_parsed = wp_parse_url($url);
        
        // Detectar si home_url() usa HTTPS
        $site_is_https = ($site_parsed['scheme'] === 'https');
        
        // Detectar si es entorno de desarrollo
        $input_host = strtolower($input_parsed['host']);
        $is_dev_host = (
            $input_host === 'localhost' ||
            $input_host === '127.0.0.1' ||
            (substr_compare($input_host, '.test', -5) === 0) ||
            (substr_compare($input_host, '.local', -6) === 0)
        );
        
        // Canonizar a HTTPS si el sitio es HTTPS y NO es desarrollo
        if ($site_is_https && !$is_dev_host && $input_parsed['scheme'] === 'http') {
            // Reconstruir URL con HTTPS manteniendo host/path/query/fragment
            $url = 'https://' . $input_parsed['host'];
            if (isset($input_parsed['path'])) $url .= $input_parsed['path'];
            if (isset($input_parsed['query'])) $url .= '?' . $input_parsed['query'];
            if (isset($input_parsed['fragment'])) $url .= '#' . $input_parsed['fragment'];
        }
        
        return $url;
    }
    
    // Database migration to add new columns
    private function migrate_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        // Check if columns exist
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        if (!in_array('meta_title', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN meta_title VARCHAR(200) DEFAULT '' AFTER schema_type");
        }
        
        if (!in_array('meta_description', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN meta_description TEXT AFTER meta_title");
        }
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_head', array($this, 'inject_meta_tags'), 1);
        add_action('wp_head', array($this, 'inject_schema'), 5);
        
        // AJAX handlers
        add_action('wp_ajax_baseo_save_schema', array($this, 'save_schema'));
        add_action('wp_ajax_baseo_delete_schema', array($this, 'delete_schema'));
        add_action('wp_ajax_baseo_get_schemas', array($this, 'get_schemas'));
        add_action('wp_ajax_baseo_toggle_schema', array($this, 'toggle_schema'));
        add_action('wp_ajax_baseo_get_single_schema', array($this, 'get_single_schema'));
        add_action('wp_ajax_baseo_update_schema', array($this, 'update_schema'));
        
        // REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Meta boxes and admin bar
        add_action('add_meta_boxes', array($this, 'add_schema_meta_box'));
        add_action('save_post', array($this, 'save_schema_meta_box'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 100);
        
        // Run migration
        $this->migrate_database();
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
            meta_title varchar(200) DEFAULT '',
            meta_description text,
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
        
        // Run migration for existing installations
        $this->migrate_database();
        
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
    
    // Load admin scripts and styles
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin page
        if ($hook != 'tools_page_baseo-custom-schema') {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'baseo-schema-admin', 
            BASEO_SCHEMA_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            BASEO_SCHEMA_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'baseo-schema-admin', 
            BASEO_SCHEMA_PLUGIN_URL . 'assets/js/admin.js', 
            array('jquery'), 
            BASEO_SCHEMA_VERSION, 
            true
        );
        
        // Pass variables to JavaScript
        wp_localize_script('baseo-schema-admin', 'baseo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('baseo_schema_nonce'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'rest_base' => rest_url('baseo/v1'),
            'brand_name' => $this->brand_name,
            'brand_color' => $this->brand_color,
            'brand_secondary' => $this->brand_secondary,
            'plugin_url' => $this->plugin_url,
            'site_url' => site_url(),
            'version' => BASEO_SCHEMA_VERSION,
            'is_plugin_page' => true,
            'i18n' => array(
                // Existing translations...
                'error_prefix' => __('❌ Error: ', 'custom-schema-baseo'),
                'connection_error' => __('❌ Connection error. Please try again.', 'custom-schema-baseo'),
                'schema_field_empty' => __('⚠️ Schema field is empty', 'custom-schema-baseo'),
                'json_valid' => __('✅ JSON valid - Ready to save', 'custom-schema-baseo'),
                'json_invalid' => __('❌ Invalid JSON: Please check the syntax.', 'custom-schema-baseo'),
                'json_formatted' => __('🎨 JSON formatted correctly', 'custom-schema-baseo'),
                'json_cannot_format' => __('❌ Cannot format: Invalid JSON', 'custom-schema-baseo'),
                'clear_confirm' => __('Are you sure you want to clear the content?', 'custom-schema-baseo'),
                'content_cleared' => __('🗑️ Content cleared', 'custom-schema-baseo'),
                'enter_url_first' => __('⚠️ Please enter a URL to test first', 'custom-schema-baseo'),
                'url_domain_error' => __('🚨 URL must be from the same domain as your website', 'custom-schema-baseo'),
                'invalid_url' => __('⚠️ Invalid URL', 'custom-schema-baseo'),
                'script_tags_title' => __('🚨 Script Tags Detected', 'custom-schema-baseo'),
                'script_tags_detected' => __('🚨 Script tags detected! Please remove <script> tags from your JSON-LD code. Only paste the JSON content.', 'custom-schema-baseo'),
                'got_it' => __('Got it!', 'custom-schema-baseo'),
                'server_invalid_url' => __('Invalid URL', 'custom-schema-baseo'),
                'server_invalid_schema_type' => __('Invalid schema type', 'custom-schema-baseo'),
                'server_invalid_json' => __('Invalid JSON', 'custom-schema-baseo'),
                'server_contains_script_tags' => __('Script tags are not allowed', 'custom-schema-baseo'),
                'server_permission_denied' => __('Permission denied', 'custom-schema-baseo'),
                'server_nonce_invalid' => __('Security check failed', 'custom-schema-baseo'),
                'saving' => __('⏱ Saving...', 'custom-schema-baseo'),
                'updating' => __('⏱ Updating...', 'custom-schema-baseo'),
                'schema_saved' => __('Schema saved successfully!', 'custom-schema-baseo'),
                'schema_updated' => __('Schema updated successfully!', 'custom-schema-baseo'),
                'schema_deleted' => __('🗑️ Schema deleted successfully', 'custom-schema-baseo'),
                'schema_copied' => __('📋 Schema copied to clipboard', 'custom-schema-baseo'),
                'error_deleting' => __('❌ Error deleting schema', 'custom-schema-baseo'),
                'error_loading' => __('❌ Error loading schema data', 'custom-schema-baseo'),
                'edit_cancelled' => __('❌ Edit cancelled', 'custom-schema-baseo'),
                'server_response_error' => __('❌ Server response error', 'custom-schema-baseo'),
                'loading_schemas' => __('Loading schemas...', 'custom-schema-baseo'),
                'empty_state' => __('🌟 Add your first schema to get started!', 'custom-schema-baseo'),
                'active_short' => __('active', 'custom-schema-baseo'),
                'updated_label' => __('Updated: ', 'custom-schema-baseo'),
                'visit' => __('🔗 Visit', 'custom-schema-baseo'),
                'add_schema' => __('➕ Add Schema', 'custom-schema-baseo'),
                'edit' => __('✏️ Edit', 'custom-schema-baseo'),
                'delete' => __('🗑️ Delete', 'custom-schema-baseo'),
                'activate' => __('▶️ Activate', 'custom-schema-baseo'),
                'deactivate' => __('⏸ Deactivate', 'custom-schema-baseo'),
                'test_on_google' => __('🧪 Test on Google', 'custom-schema-baseo'),
                'copy_json' => __('📋 Copy JSON', 'custom-schema-baseo'),
                'update_schema' => __('🔄 Update Schema', 'custom-schema-baseo'),
                'save_schema' => __('💾 Save Schema', 'custom-schema-baseo'),
                'cancel' => __('❌ Cancel', 'custom-schema-baseo'),
                'editing' => __('✏️ Editing', 'custom-schema-baseo'),
                'schema_activated' => __('▶️ Schema activated', 'custom-schema-baseo'),
                'schema_deactivated' => __('⏸ Schema deactivated', 'custom-schema-baseo'),
                'opening_url' => __('🔗 Opening URL in new tab', 'custom-schema-baseo'),
                'url_prefilled' => __('📝 URL pre-filled, now add the schema name', 'custom-schema-baseo'),
                'confirm_delete' => __('Are you sure you want to delete this schema?', 'custom-schema-baseo'),
                'validate' => __('✓ Validate', 'custom-schema-baseo'),
                'validate_json' => __('✓ Validate JSON', 'custom-schema-baseo'),
                'valid' => __('✅ Valid', 'custom-schema-baseo'),
                'error' => __('❌ Error', 'custom-schema-baseo'),
                'invalid_json_prefix' => __('❌ Invalid JSON: ', 'custom-schema-baseo'),
                'frontend_fill_required' => __('❌ Please fill all required fields', 'custom-schema-baseo'),
                'frontend_same_domain' => __('🚨 URL must be from the same domain as your website', 'custom-schema-baseo'),
                'frontend_invalid_url' => __('❌ Invalid URL format', 'custom-schema-baseo'),
                'frontend_saved_ok' => __('✅ Schema saved successfully!', 'custom-schema-baseo'),
                'add_new_schema' => __('➕ Add New Schema', 'custom-schema-baseo'),
                'schema_name' => __('Schema Name', 'custom-schema-baseo'),
                'page_url' => __('Page URL', 'custom-schema-baseo'),
                'schema_type' => __('Schema Type', 'custom-schema-baseo'),
                'json_ld_code' => __('JSON-LD Code', 'custom-schema-baseo'),
                'format' => __('🎨 Format', 'custom-schema-baseo'),
                'clear' => __('🗑️ Clear', 'custom-schema-baseo'),
                'test_with_google' => __('🧪 Test with Google', 'custom-schema-baseo'),
                'configured_schemas' => __('📋 Configured Schemas', 'custom-schema-baseo'),
                'filter_by_type' => __('Filter by type:', 'custom-schema-baseo'),
                'all_types' => __('All', 'custom-schema-baseo'),
                'items_per_page' => __('Items per page:', 'custom-schema-baseo'),
                'items_per_page_short' => __('showing', 'custom-schema-baseo'),
                'prev' => __('← Previous', 'custom-schema-baseo'),
                'next' => __('Next →', 'custom-schema-baseo'),
                'page_label' => __('Page', 'custom-schema-baseo'),
                'of_label' => __('of', 'custom-schema-baseo'),
                'items_label' => __('items', 'custom-schema-baseo'),
                
                // New meta tags translations
                'meta_title' => __('Meta Title', 'custom-schema-baseo'),
                'meta_description' => __('Meta Description', 'custom-schema-baseo'),
                'chars_left' => __('characters left', 'custom-schema-baseo'),
                'optimal' => __('Optimal', 'custom-schema-baseo'),
                'too_long' => __('Too long', 'custom-schema-baseo'),
                'insert_variables' => __('📝 Insert Variables', 'custom-schema-baseo'),
                'variable_copied' => __('✅ Variable copied!', 'custom-schema-baseo'),
                'click_to_copy' => __('Click to copy', 'custom-schema-baseo')
            )
        ));
    }
    
    // Admin page - Minimal container
    public function admin_page() {
        echo '<div id="baseo-schema-app"></div>';
    }
    
    // Save schema via AJAX
    public function save_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_send_json_error(__('server_nonce_invalid', 'custom-schema-baseo'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('server_permission_denied', 'custom-schema-baseo'));
            return;
        }
        
        // Sanitizar URL
        $url = esc_url_raw($_POST['url']);
        
        // Validar URL y dominio
        if (empty($url)) {
            wp_send_json_error(__('server_invalid_url', 'custom-schema-baseo'));
            return;
        }
        
        $site_parsed = wp_parse_url(home_url());
        $input_parsed = wp_parse_url($url);
        
        if (!isset($input_parsed['scheme']) || !in_array($input_parsed['scheme'], array('http', 'https'))) {
            wp_send_json_error(__('server_invalid_url', 'custom-schema-baseo'));
            return;
        }
        
        $site_host = strtolower($site_parsed['host']);
        $input_host = strtolower($input_parsed['host']);
        
        $is_same_host = ($input_host === $site_host);
        $is_subdomain = (substr_compare($input_host, '.' . $site_host, -strlen('.' . $site_host)) === 0);
        
        if (!$is_same_host && !$is_subdomain) {
            wp_send_json_error(__('server_invalid_url', 'custom-schema-baseo'));
            return;
        }
        
        $site_is_https = ($site_parsed['scheme'] === 'https');
        $is_dev_host = (
            $input_host === 'localhost' ||
            $input_host === '127.0.0.1' ||
            (substr_compare($input_host, '.test', -5) === 0) ||
            (substr_compare($input_host, '.local', -6) === 0)
        );
        
        if ($site_is_https && !$is_dev_host && $input_parsed['scheme'] === 'http') {
            $url = 'https://' . $input_parsed['host'];
            if (isset($input_parsed['path'])) $url .= $input_parsed['path'];
            if (isset($input_parsed['query'])) $url .= '?' . $input_parsed['query'];
            if (isset($input_parsed['fragment'])) $url .= '#' . $input_parsed['fragment'];
        }
        
        $schema_name = sanitize_text_field($_POST['schema_name'] ?? __('Schema without name', 'custom-schema-baseo'));
        $schema_data = wp_unslash($_POST['schema_data']);
        $schema_type = sanitize_text_field($_POST['schema_type'] ?? 'WebPage');
        $meta_title = isset($_POST['meta_title']) ? sanitize_text_field($_POST['meta_title']) : '';
        $meta_description = isset($_POST['meta_description']) ? sanitize_textarea_field($_POST['meta_description']) : '';
        
        $allowed_types = array('WebPage', 'Article', 'Product', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Recipe', 'Review', 'FAQ');
        if (!in_array($schema_type, $allowed_types, true)) {
            wp_send_json_error(__('server_invalid_schema_type', 'custom-schema-baseo'));
            return;
        }
        
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('server_invalid_json', 'custom-schema-baseo'));
            return;
        }
        
        if (preg_match('/<\s*\/?\s*script\b/i', $schema_data)) {
            wp_send_json_error(__('server_contains_script_tags', 'custom-schema-baseo'));
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
                'meta_title' => $meta_title,
                'meta_description' => $meta_description,
                'is_active' => 1
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(sprintf(__('Schema "%s" saved successfully', 'custom-schema-baseo'), $schema_name));
        } else {
            wp_send_json_error(__('Error saving schema', 'custom-schema-baseo'));
        }
    }
    
    // Delete schema via AJAX
    public function delete_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_send_json_error(__('server_nonce_invalid', 'custom-schema-baseo'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('server_permission_denied', 'custom-schema-baseo'));
            return;
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
            wp_send_json_error(__('server_nonce_invalid', 'custom-schema-baseo'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('server_permission_denied', 'custom-schema-baseo'));
            return;
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
            wp_send_json_error(__('server_nonce_invalid', 'custom-schema-baseo'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('server_permission_denied', 'custom-schema-baseo'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $schemas = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC", ARRAY_A);
        
        wp_send_json_success($schemas);
    }
    
    // Get single schema via AJAX
    public function get_single_schema() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_send_json_error(__('server_nonce_invalid', 'custom-schema-baseo'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('server_permission_denied', 'custom-schema-baseo'));
            return;
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
            wp_send_json_error(__('server_nonce_invalid', 'custom-schema-baseo'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('server_permission_denied', 'custom-schema-baseo'));
            return;
        }
        
        $id = intval($_POST['id']);
        $url = esc_url_raw($_POST['url']);
        
        if (empty($url)) {
            wp_send_json_error(__('server_invalid_url', 'custom-schema-baseo'));
            return;
        }
        
        $site_parsed = wp_parse_url(home_url());
        $input_parsed = wp_parse_url($url);
        
        if (!isset($input_parsed['scheme']) || !in_array($input_parsed['scheme'], array('http', 'https'))) {
            wp_send_json_error(__('server_invalid_url', 'custom-schema-baseo'));
            return;
        }
        
        $site_host = strtolower($site_parsed['host']);
        $input_host = strtolower($input_parsed['host']);
        
        $is_same_host = ($input_host === $site_host);
        $is_subdomain = (substr_compare($input_host, '.' . $site_host, -strlen('.' . $site_host)) === 0);
        
        if (!$is_same_host && !$is_subdomain) {
            wp_send_json_error(__('server_invalid_url', 'custom-schema-baseo'));
            return;
        }
        
        $site_is_https = ($site_parsed['scheme'] === 'https');
        $is_dev_host = (
            $input_host === 'localhost' ||
            $input_host === '127.0.0.1' ||
            (substr_compare($input_host, '.test', -5) === 0) ||
            (substr_compare($input_host, '.local', -6) === 0)
        );
        
        if ($site_is_https && !$is_dev_host && $input_parsed['scheme'] === 'http') {
            $url = 'https://' . $input_parsed['host'];
            if (isset($input_parsed['path'])) $url .= $input_parsed['path'];
            if (isset($input_parsed['query'])) $url .= '?' . $input_parsed['query'];
            if (isset($input_parsed['fragment'])) $url .= '#' . $input_parsed['fragment'];
        }
        
        $schema_name = sanitize_text_field($_POST['schema_name'] ?? __('Schema without name', 'custom-schema-baseo'));
        $schema_data = wp_unslash($_POST['schema_data']);
        $schema_type = sanitize_text_field($_POST['schema_type'] ?? 'WebPage');
        $meta_title = isset($_POST['meta_title']) ? sanitize_text_field($_POST['meta_title']) : '';
        $meta_description = isset($_POST['meta_description']) ? sanitize_textarea_field($_POST['meta_description']) : '';
        
        $allowed_types = array('WebPage', 'Article', 'Product', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Recipe', 'Review', 'FAQ');
        if (!in_array($schema_type, $allowed_types, true)) {
            wp_send_json_error(__('server_invalid_schema_type', 'custom-schema-baseo'));
            return;
        }
        
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('server_invalid_json', 'custom-schema-baseo'));
            return;
        }
        
        if (preg_match('/<\s*\/?\s*script\b/i', $schema_data)) {
            wp_send_json_error(__('server_contains_script_tags', 'custom-schema-baseo'));
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
                'meta_title' => $meta_title,
                'meta_description' => $meta_description,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(sprintf(__('Schema "%s" updated successfully', 'custom-schema-baseo'), $schema_name));
        } else {
            wp_send_json_error(__('Error updating schema', 'custom-schema-baseo'));
        }
    }
    
    // Add Meta Box in post/page editor
    public function add_schema_meta_box() {
        $screens = array('post', 'page', 'product');
        
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
            <p><strong>🔗 URL:</strong><br>
            <code><?php echo esc_html($current_url); ?></code></p>
            
            <?php if ($existing_schemas): ?>
            <div class="baseo-existing-schemas">
                <h4>📋 <?php printf(__('Existing Schemas (%d):', 'custom-schema-baseo'), count($existing_schemas)); ?></h4>
                <?php foreach ($existing_schemas as $schema): ?>
                <div style="background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 4px;">
                    <div style="font-weight: 600; font-size: 12px;">
                        <?php echo $schema['is_active'] ? '✅' : '⏸'; ?> <?php echo esc_html($schema['schema_name']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <hr style="margin: 15px 0;">
            
            <p style="text-align: center;">
                <a href="<?php echo admin_url('tools.php?page=baseo-custom-schema&url=' . urlencode($current_url)); ?>" 
                   class="button button-primary" target="_blank">
                    ⚙️ <?php _e('Manage Schemas', 'custom-schema-baseo'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    // Save Meta Box
    public function save_schema_meta_box($post_id) {
        return;
    }
    
    // Add button in admin bar (frontend)
    public function add_admin_bar_button($wp_admin_bar) {
        if (!is_admin() && current_user_can('manage_options')) {
            $current_url = $this->get_current_url();
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'baseo_custom_schemas';
            
            $schema_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE url = %s",
                $current_url
            ));
            
            $button_text = $schema_count > 0 ? 
                '✏️ ' . __('Manage Schemas', 'custom-schema-baseo') . ' (' . $schema_count . ')' : 
                '➕ ' . __('Add Schema', 'custom-schema-baseo');
            
            $wp_admin_bar->add_node(array(
                'id'    => 'baseo-schema-editor',
                'title' => $button_text,
                'href'  => admin_url('tools.php?page=baseo-custom-schema&url=' . urlencode($current_url)),
                'meta'  => array(
                    'target' => '_blank'
                )
            ));
        }
    }
    
    // Inject meta tags in head
    public function inject_meta_tags() {
        $current_url = $this->get_current_url();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        // Get the most recent active schema for this URL
        $schema = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_title, meta_description FROM $table_name 
             WHERE url = %s AND is_active = 1 
             ORDER BY updated_at DESC LIMIT 1",
            $current_url
        ));
        
        if ($schema && (!empty($schema->meta_title) || !empty($schema->meta_description))) {
            echo '<!-- Custom Meta Tags by ' . $this->brand_name . ' -->' . "\n";
            
            if (!empty($schema->meta_title)) {
                echo '<meta name="title" content="' . esc_attr($schema->meta_title) . '">' . "\n";
                echo '<meta property="og:title" content="' . esc_attr($schema->meta_title) . '">' . "\n";
                echo '<meta name="twitter:title" content="' . esc_attr($schema->meta_title) . '">' . "\n";
            }
            
            if (!empty($schema->meta_description)) {
                echo '<meta name="description" content="' . esc_attr($schema->meta_description) . '">' . "\n";
                echo '<meta property="og:description" content="' . esc_attr($schema->meta_description) . '">' . "\n";
                echo '<meta name="twitter:description" content="' . esc_attr($schema->meta_description) . '">' . "\n";
            }
            
            echo '<!-- End Custom Meta Tags by ' . $this->brand_name . ' -->' . "\n";
        }
    }
    
    // Inject schema in head with variable replacement
    public function inject_schema() {
        $current_url = $this->get_current_url();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $schemas = $wpdb->get_results($wpdb->prepare(
            "SELECT schema_name, schema_data, meta_title, meta_description FROM $table_name 
             WHERE url = %s AND is_active = 1 
             ORDER BY created_at ASC",
            $current_url
        ));
        
        if ($schemas) {
            echo '<!-- Custom Schema by ' . $this->brand_name . ' -->' . "\n";
            
            foreach ($schemas as $schema) {
                // Replace variables in schema_data
                $schema_data = $schema->schema_data;
                
                // Replace {{meta_title}} with actual meta_title
                if (!empty($schema->meta_title)) {
                    $schema_data = str_replace('{{meta_title}}', $schema->meta_title, $schema_data);
                }
                
                // Replace {{meta_description}} with actual meta_description
                if (!empty($schema->meta_description)) {
                    $schema_data = str_replace('{{meta_description}}', $schema->meta_description, $schema_data);
                }
                
                // Validate and decode JSON
                $decoded = json_decode($schema_data);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue; // Skip invalid JSON
                }
                
                echo '<!-- Schema: ' . esc_html($schema->schema_name) . ' -->' . "\n";
                echo '<script type="application/ld+json">' . "\n";
                $json_output = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $json_output = str_ireplace('</script>', '<\/script>', $json_output);
                echo $json_output . "\n";
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
                </ol>';
    }
    
    private function get_help_sidebar() {
        return '<p><strong>🎯 ' . __('Premium Support', 'custom-schema-baseo') . '</strong></p>
                <p>' . __('Need personalized help?', 'custom-schema-baseo') . '</p>
                <p><a href="' . $this->support_url . '" target="_blank" class="button button-primary">' . sprintf(__('Contact %s', 'custom-schema-baseo'), $this->brand_name) . '</a></p>
                <hr>
                <p><strong>📚 ' . __('Documentation', 'custom-schema-baseo') . '</strong></p>
                <p><a href="' . $this->docs_url . '" target="_blank">' . __('View complete guides', 'custom-schema-baseo') . '</a></p>';
    }
    
    // Clean on deactivation
    public function cleanup() {
        // Keep data in case they reactivate the plugin
        delete_transient('baseo_schema_activation_notice');
    }
}

// 🚀 Initialize Custom Schema by BASEO
new CustomSchemaByBaseo();
?>