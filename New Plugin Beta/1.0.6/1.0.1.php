<?php
/**
 * Plugin Name: Custom Schema by BASEO
 * Plugin URI: http://thebaseo.com/plugins
 * Description: 🚀 Professional plugin to add custom schema to each URL of your website. Developed by BASEO to maximize your SEO.
 * Version: 1.1.21
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
define('BASEO_SCHEMA_VERSION', '1.1.76');
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
        
        // Bulk apply schemas
        register_rest_route($namespace, '/schemas/bulk-apply', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_bulk_apply_schemas'),
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
        
        // Search parameter
        $search_filter = $request->get_param('search');
        if ($search_filter !== null) {
            $search_filter = sanitize_text_field($search_filter);
        }
        
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
        
        // Add search filter
        if ($search_filter) {
            $where_clauses[] = '(url LIKE %s OR schema_name LIKE %s OR schema_data LIKE %s)';
            $search_param = '%' . $wpdb->esc_like($search_filter) . '%';
            $where_params[] = $search_param;
            $where_params[] = $search_param;
            $where_params[] = $search_param;
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
            'url' => $url_filter,
            'search' => $search_filter
        ));
        $self_url = untrailingslashit(rest_url('baseo/v1/schemas')) . '?' . http_build_query($self_params, '', '&', PHP_QUERY_RFC3986);
        $links[] = '<' . $self_url . '>; rel="self"';
        
        // Link a primera página
        if ($page > 1) {
            $first_params = array_filter(array(
                'page' => 1,
                'per_page' => $per_page,
                'schema_type' => $type_filter,
                'url' => $url_filter,
                'search' => $search_filter
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
                'url' => $url_filter,
                'search' => $search_filter
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
                'url' => $url_filter,
                'search' => $search_filter
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
                'url' => $url_filter,
                'search' => $search_filter
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
    
    // REST API: Bulk apply schemas to multiple URLs
    public function rest_bulk_apply_schemas($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        if (empty($params['urls']) || !is_array($params['urls'])) {
            return new WP_Error('no_urls', __('No URLs provided', 'custom-schema-baseo'), array('status' => 400));
        }
        
        $schema_name = sanitize_text_field($params['schema_name'] ?? __('Bulk Schema', 'custom-schema-baseo'));
        $schema_type = sanitize_text_field($params['schema_type'] ?? 'WebPage');
        $schema_data = wp_unslash($params['schema_data'] ?? '');
        $meta_title = isset($params['meta_title']) ? sanitize_text_field($params['meta_title']) : '';
        $meta_description = isset($params['meta_description']) ? sanitize_textarea_field($params['meta_description']) : '';
        
        // Validate schema_type
        $allowed_types = array('WebPage', 'Article', 'Product', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Recipe', 'Review', 'FAQ');
        if (!in_array($schema_type, $allowed_types, true)) {
            return new WP_Error('invalid_type', __('server_invalid_schema_type', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Validate JSON
        $decoded = json_decode($schema_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('server_invalid_json', 'custom-schema-baseo'), array('status' => 400));
        }
        
        // Check for script tags
        if (preg_match('/<\s*\/?\s*script\b/i', $schema_data)) {
            return new WP_Error('script_tags', __('server_contains_script_tags', 'custom-schema-baseo'), array('status' => 400));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $success_count = 0;
        $failed_count = 0;
        $errors = array();
        
        foreach ($params['urls'] as $url) {
            $url = esc_url_raw($url);
            
            // Validate URL
            if (!$this->validate_url($url)) {
                $failed_count++;
                $errors[] = array('url' => $url, 'error' => 'Invalid URL');
                continue;
            }
            
            // Canonize URL
            $url = $this->canonize_url_https($url);
            
            // Insert schema
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
                $success_count++;
            } else {
                $failed_count++;
                $errors[] = array('url' => $url, 'error' => 'Database error');
            }
        }
        
        return rest_ensure_response(array(
            'success' => $success_count,
            'failed' => $failed_count,
            'errors' => $errors
        ));
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
        add_action('wp_ajax_baseo_get_bulk_urls', array($this, 'get_bulk_urls'));
        
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
        
    // ==========================================
    // ENQUEUE CSS con cache busting agresivo
    // ==========================================
    wp_enqueue_style(
        'baseo-schema-admin', 
        BASEO_SCHEMA_PLUGIN_URL . 'assets/css/admin.css', 
        array(), 
        time() . '-' . BASEO_SCHEMA_VERSION, // Forzar recarga siempre
        'all'
    );
    
    // ==========================================
    // INLINE CSS - Respaldo para asegurar tabs
    // ==========================================
    $custom_css = "
    /* Force new tab styles */
    .baseo-tabs {
        display: flex !important;
        gap: 0 !important;
        border-bottom: 3px solid #e1e5e9 !important;
        margin: 30px 0 40px 0 !important;
        background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%) !important;
        border-radius: 12px 12px 0 0 !important;
        overflow: hidden !important;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08) !important;
        padding: 0 !important;
    }
    
    .baseo-tab-btn {
        flex: 1 !important;
        padding: 20px 40px !important;
        border: none !important;
        background: transparent !important;
        cursor: pointer !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        color: #6c757d !important;
        border-bottom: 4px solid transparent !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative !important;
    }
    
    .baseo-tab-btn:hover {
        color: var(--baseo-primary) !important;
        background: linear-gradient(180deg, rgba(255, 107, 53, 0.05) 0%, rgba(255, 107, 53, 0.1) 100%) !important;
    }
    
    .baseo-tab-btn.active {
        color: var(--baseo-primary) !important;
        border-bottom-color: var(--baseo-primary) !important;
        background: linear-gradient(180deg, rgba(255, 107, 53, 0.08) 0%, rgba(255, 107, 53, 0.12) 100%) !important;
        font-weight: 700 !important;
    }
    
    .baseo-tab-content {
        display: none !important;
    }
    
    .baseo-tab-content.active {
        display: block !important;
    }
    ";
    wp_add_inline_style('baseo-schema-admin', $custom_css);

        // Enqueue JavaScript files in correct dependency order
        
        // 1. Utils (base - no dependencies)
        wp_enqueue_script(
            'baseo-schema-utils',
            BASEO_SCHEMA_PLUGIN_URL . 'assets/js/admin-utils.js',
            array('jquery'),
            BASEO_SCHEMA_VERSION,
            true
        );
        
        // 2. Editor (depends on utils)
        wp_enqueue_script(
            'baseo-schema-editor',
            BASEO_SCHEMA_PLUGIN_URL . 'assets/js/admin-editor.js',
            array('jquery', 'baseo-schema-utils'),
            BASEO_SCHEMA_VERSION,
            true
        );
        
        // 3. Forms (depends on utils and editor)
        wp_enqueue_script(
            'baseo-schema-forms',
            BASEO_SCHEMA_PLUGIN_URL . 'assets/js/admin-forms.js',
            array('jquery', 'baseo-schema-utils', 'baseo-schema-editor'),
            BASEO_SCHEMA_VERSION,
            true
        );
        
        // 4. Schemas (depends on utils and editor)
        wp_enqueue_script(
            'baseo-schema-schemas',
            BASEO_SCHEMA_PLUGIN_URL . 'assets/js/admin-schemas.js',
            array('jquery', 'baseo-schema-utils', 'baseo-schema-editor'),
            BASEO_SCHEMA_VERSION,
            true
        );
        
        // 5. Main (depends on ALL previous files)
        wp_enqueue_script(
            'baseo-schema-main',
            BASEO_SCHEMA_PLUGIN_URL . 'assets/js/admin-main.js',
            array('jquery', 'baseo-schema-utils', 'baseo-schema-editor', 'baseo-schema-forms', 'baseo-schema-schemas'),
            BASEO_SCHEMA_VERSION,
            true
        );
        
        // Localize script (only once, on utils)
        wp_localize_script('baseo-schema-utils', 'baseo_ajax', array(
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
                // Existing translations
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
                'script_tags_detected' => __('🚨 Script tags detected! Please remove <script> tags from your JSON-LD code.', 'custom-schema-baseo'),
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
                'url_prefilled' => __('📝 URL pre-filled', 'custom-schema-baseo'),
                'confirm_delete' => __('Are you sure you want to delete this schema?', 'custom-schema-baseo'),
                'validate' => __('✓ Validate', 'custom-schema-baseo'),
                'validate_json' => __('✓ Validate JSON', 'custom-schema-baseo'),
                'valid' => __('✅ Valid', 'custom-schema-baseo'),
                'error' => __('❌ Error', 'custom-schema-baseo'),
                'invalid_json_prefix' => __('❌ Invalid JSON: ', 'custom-schema-baseo'),
                'frontend_fill_required' => __('❌ Please fill all required fields', 'custom-schema-baseo'),
                'frontend_same_domain' => __('🚨 URL must be from the same domain', 'custom-schema-baseo'),
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
                'search_schemas' => __('Search schemas...', 'custom-schema-baseo'),
                'searching' => __('🔍 Searching...', 'custom-schema-baseo'),
                'no_results' => __('No schemas found', 'custom-schema-baseo'),
                'meta_title' => __('Meta Title', 'custom-schema-baseo'),
                'meta_description' => __('Meta Description', 'custom-schema-baseo'),
                'chars_left' => __('characters left', 'custom-schema-baseo'),
                'optimal' => __('Optimal', 'custom-schema-baseo'),
                'too_long' => __('Too long', 'custom-schema-baseo'),
                'insert_variables' => __('📖 Insert Variables', 'custom-schema-baseo'),
                'variable_copied' => __('✅ Variable copied!', 'custom-schema-baseo'),
                'click_to_copy' => __('Click to copy', 'custom-schema-baseo'),
                
                // NEW: Bulk Upload translations
                'add_single_schema' => __('Add Single Schema', 'custom-schema-baseo'),
                'bulk_upload' => __('Bulk Upload', 'custom-schema-baseo'),
                'bulk_schema' => __('Apply Schema to Multiple URLs', 'custom-schema-baseo'),
                'bulk_description' => __('Select multiple pages and apply the same schema to all', 'custom-schema-baseo'),
                'select_urls' => __('Select URLs', 'custom-schema-baseo'),
                'urls_selected' => __('selected', 'custom-schema-baseo'),
                'pages' => __('Pages', 'custom-schema-baseo'),
                'posts' => __('Posts', 'custom-schema-baseo'),
                'products' => __('Products', 'custom-schema-baseo'),
                'select_all' => __('Select All', 'custom-schema-baseo'),
                'unselect_all' => __('Unselect All', 'custom-schema-baseo'),
                'loading_urls' => __('⏱ Loading URLs...', 'custom-schema-baseo'),
                'all_urls_loaded' => __('All URLs loaded', 'custom-schema-baseo'),
                'apply_bulk_schema' => __('Apply Bulk Schema', 'custom-schema-baseo'),
                'no_urls_selected' => __('⚠️ Please select at least one URL', 'custom-schema-baseo'),
                'bulk_confirm' => __('Apply schema to %d selected URLs?', 'custom-schema-baseo'),
                'bulk_success' => __('✅ %d schemas created successfully', 'custom-schema-baseo'),
                'bulk_partial' => __('⚠️ %d created, %d failed', 'custom-schema-baseo'),
                'bulk_applying' => __('Applying to', 'custom-schema-baseo'),
                'has_schemas' => __('Has %d schema(s)', 'custom-schema-baseo'),
            )
        ));
    }
    
    // Admin page - Minimal container
    public function admin_page() {
        echo '<div id="baseo-schema-app"></div>';
    }
    
    // AJAX: Get bulk URLs for selector
    public function get_bulk_urls() {
        if (!wp_verify_nonce($_POST['nonce'], 'baseo_schema_nonce')) {
            wp_send_json_error(__('server_nonce_invalid', 'custom-schema-baseo'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('server_permission_denied', 'custom-schema-baseo'));
            return;
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'all-pages');
        $urls = array();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        if ($type === 'all-pages') {
            // Get all published pages
            $pages = get_pages(array(
                'post_status' => 'publish',
                'number' => 500 // Limit to 500 to avoid performance issues
            ));
            
            foreach ($pages as $page) {
                $url = get_permalink($page->ID);
                $schema_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE url = %s",
                    $url
                ));
                
                $urls[] = array(
                    'title' => $page->post_title,
                    'url' => $url,
                    'path' => wp_make_link_relative($url),
                    'type' => 'page',
                    'schema_count' => intval($schema_count)
                );
            }
        } elseif ($type === 'all-posts') {
            // Get all published posts
            $posts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'numberposts' => 500
            ));
            
            foreach ($posts as $post) {
                $url = get_permalink($post->ID);
                $schema_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE url = %s",
                    $url
                ));
                
                $urls[] = array(
                    'title' => $post->post_title,
                    'url' => $url,
                    'path' => wp_make_link_relative($url),
                    'type' => 'post',
                    'schema_count' => intval($schema_count)
                );
            }
        } elseif ($type === 'all-products' && class_exists('WooCommerce')) {
            // Get all published products (WooCommerce)
            $products = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'numberposts' => 500
            ));
            
            foreach ($products as $product) {
                $url = get_permalink($product->ID);
                $schema_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE url = %s",
                    $url
                ));
                
                $urls[] = array(
                    'title' => $product->post_title,
                    'url' => $url,
                    'path' => wp_make_link_relative($url),
                    'type' => 'product',
                    'schema_count' => intval($schema_count)
                );
            }
        }
        
        wp_send_json_success(array('urls' => $urls));
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
    // ==========================================
    // Sincronizar con post_meta
    // ==========================================
    $post_id = url_to_postid($url);
    
    if ($post_id > 0) {
        // Actualizar post meta
        if (!empty($meta_title)) {
            update_post_meta($post_id, '_baseo_meta_title', $meta_title);
        }
        
        if (!empty($meta_description)) {
            update_post_meta($post_id, '_baseo_meta_description', $meta_description);
        }
    }
    
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
    // ==========================================
    // Sincronizar con post_meta
    // ==========================================
    $post_id = url_to_postid($url);
    
    if ($post_id > 0) {
        // Actualizar post meta
        if (!empty($meta_title)) {
            update_post_meta($post_id, '_baseo_meta_title', $meta_title);
        } else {
            delete_post_meta($post_id, '_baseo_meta_title');
        }
        
        if (!empty($meta_description)) {
            update_post_meta($post_id, '_baseo_meta_description', $meta_description);
        } else {
            delete_post_meta($post_id, '_baseo_meta_description');
        }
    }
    
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
        
        // Get current post meta values
        $meta_title = get_post_meta($post->ID, '_baseo_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_baseo_meta_description', true);
        
        wp_nonce_field('baseo_schema_meta_box', 'baseo_schema_meta_box_nonce');
        ?>
        <div class="baseo-meta-box">
            <style>
                .baseo-meta-box { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                .baseo-meta-field { margin-bottom: 20px; }
                .baseo-meta-field label { 
                    display: block; 
                    font-weight: 600; 
                    margin-bottom: 8px; 
                    color: #1d2327;
                    font-size: 13px;
                }
                .baseo-meta-field input[type="text"],
                .baseo-meta-field textarea { 
                    width: 100%; 
                    padding: 8px 12px;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                    font-size: 13px;
                    box-sizing: border-box;
                }
                .baseo-meta-field textarea {
                    resize: vertical;
                    min-height: 80px;
                    font-family: inherit;
                }
                .baseo-meta-field input[type="text"]:focus,
                .baseo-meta-field textarea:focus {
                    border-color: #2271b1;
                    outline: none;
                    box-shadow: 0 0 0 1px #2271b1;
                }
                .baseo-char-counter {
                    font-size: 12px;
                    margin-top: 6px;
                    font-weight: 500;
                    padding: 4px 8px;
                    border-radius: 3px;
                    display: inline-block;
                }
                .baseo-char-counter.optimal {
                    color: #00a32a;
                    background: #f0f6f0;
                }
                .baseo-char-counter.warning {
                    color: #dba617;
                    background: #fcf9e8;
                }
                .baseo-char-counter.danger {
                    color: #d63638;
                    background: #fcf0f1;
                }
                .baseo-google-preview {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f6f7f7;
                    border: 1px solid #dcdcde;
                    border-radius: 4px;
                }
                .baseo-google-preview h4 {
                    margin: 0 0 12px 0;
                    font-size: 13px;
                    color: #1d2327;
                    font-weight: 600;
                }
                .baseo-preview-title {
                    color: #1a0dab;
                    font-size: 20px;
                    font-weight: 400;
                    margin-bottom: 4px;
                    line-height: 1.3;
                    text-decoration: none;
                    cursor: pointer;
                }
                .baseo-preview-url {
                    color: #006621;
                    font-size: 14px;
                    margin-bottom: 4px;
                }
                .baseo-preview-description {
                    color: #545454;
                    font-size: 14px;
                    line-height: 1.58;
                }
                .baseo-preview-placeholder {
                    color: #999;
                    font-style: italic;
                }
                .baseo-existing-schemas {
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #dcdcde;
                }
                .baseo-existing-schemas h4 {
                    margin: 0 0 12px 0;
                    font-size: 13px;
                    color: #1d2327;
                }
                .baseo-schema-badge {
                    background: #f0f0f1;
                    padding: 8px 12px;
                    margin: 6px 0;
                    border-radius: 4px;
                    font-size: 12px;
                }
                .baseo-schema-badge.active {
                    background: #f0f6f0;
                    border-left: 3px solid #00a32a;
                }
                .baseo-schema-badge.inactive {
                    background: #fcf0f1;
                    border-left: 3px solid #d63638;
                    opacity: 0.7;
                }
                .baseo-manage-link {
                    margin-top: 15px;
                    text-align: center;
                }
            </style>

            <!-- Meta Title Field -->
            <div class="baseo-meta-field">
                <label for="baseo_meta_title">
                    📝 <?php _e('Meta Title', 'custom-schema-baseo'); ?>
                </label>
                <input 
                    type="text" 
                    id="baseo_meta_title" 
                    name="baseo_meta_title" 
                    value="<?php echo esc_attr($meta_title); ?>"
                    maxlength="70"
                    placeholder="<?php echo esc_attr(get_the_title($post->ID)); ?>"
                />
                <div class="baseo-char-counter optimal" id="baseo-title-counter">
                    <span class="count">0</span>/70 - 
                    <span class="remaining">70 <?php _e('characters left', 'custom-schema-baseo'); ?></span>
                </div>
            </div>

            <!-- Meta Description Field -->
            <div class="baseo-meta-field">
                <label for="baseo_meta_description">
                    📄 <?php _e('Meta Description', 'custom-schema-baseo'); ?>
                </label>
                <textarea 
                    id="baseo_meta_description" 
                    name="baseo_meta_description"
                    maxlength="160"
                    rows="3"
                    placeholder="<?php _e('Enter a compelling description for search engines...', 'custom-schema-baseo'); ?>"
                ><?php echo esc_textarea($meta_description); ?></textarea>
                <div class="baseo-char-counter optimal" id="baseo-description-counter">
                    <span class="count">0</span>/160 - 
                    <span class="remaining">160 <?php _e('characters left', 'custom-schema-baseo'); ?></span>
                </div>
            </div>

            <!-- Google Preview -->
            <div class="baseo-google-preview">
                <h4>🔍 <?php _e('Google Search Preview', 'custom-schema-baseo'); ?></h4>
                <div class="baseo-preview-title" id="baseo-preview-title">
                    <?php echo $meta_title ? esc_html($meta_title) : '<span class="baseo-preview-placeholder">' . esc_html(get_the_title($post->ID)) . '</span>'; ?>
                </div>
                <div class="baseo-preview-url">
                    <?php 
                    $parsed_url = wp_parse_url($current_url);
                    echo esc_html($parsed_url['host'] . ($parsed_url['path'] ?? ''));
                    ?>
                </div>
                <div class="baseo-preview-description" id="baseo-preview-description">
                    <?php 
                    if ($meta_description) {
                        echo esc_html($meta_description);
                    } else {
                        echo '<span class="baseo-preview-placeholder">' . __('Your meta description will appear here...', 'custom-schema-baseo') . '</span>';
                    }
                    ?>
                </div>
            </div>

            <!-- Existing Schemas Section -->
            <?php if ($existing_schemas): ?>
            <div class="baseo-existing-schemas">
                <h4>📋 <?php printf(__('Schemas on this page (%d):', 'custom-schema-baseo'), count($existing_schemas)); ?></h4>
                <?php foreach ($existing_schemas as $schema): ?>
                <div class="baseo-schema-badge <?php echo $schema['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php echo $schema['is_active'] ? '✅' : '⏸'; ?> 
                    <strong><?php echo esc_html($schema['schema_name']); ?></strong>
                    <span style="color: #646970; margin-left: 8px;">
                        (<?php echo esc_html($schema['schema_type']); ?>)
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Manage Link -->
            <div class="baseo-manage-link">
                <a href="<?php echo admin_url('tools.php?page=baseo-custom-schema&url=' . urlencode($current_url)); ?>" 
                   class="button button-primary" 
                   target="_blank">
                    ⚙️ <?php _e('Manage All Schemas', 'custom-schema-baseo'); ?>
                </a>
            </div>

            <!-- Inline JavaScript for Character Counter -->
            <script>
            (function() {
                function updateCounter(input, counterId, max) {
                    var counter = document.getElementById(counterId);
                    var length = input.value.length;
                    var remaining = max - length;
                    
                    counter.querySelector('.count').textContent = length;
                    counter.querySelector('.remaining').textContent = remaining + ' <?php _e('characters left', 'custom-schema-baseo'); ?>';
                    
                    // Update colors
                    counter.classList.remove('optimal', 'warning', 'danger');
                    
                    if (max === 70) {
                        // Title: green <60, yellow 60-70, red >70
                        if (length > 70) {
                            counter.classList.add('danger');
                        } else if (length >= 60) {
                            counter.classList.add('warning');
                        } else {
                            counter.classList.add('optimal');
                        }
                    } else {
                        // Description: green <150, yellow 150-160, red >160
                        if (length > 160) {
                            counter.classList.add('danger');
                        } else if (length >= 150) {
                            counter.classList.add('warning');
                        } else {
                            counter.classList.add('optimal');
                        }
                    }
                }
                
                function updatePreview() {
                    var titleInput = document.getElementById('baseo_meta_title');
                    var descInput = document.getElementById('baseo_meta_description');
                    var previewTitle = document.getElementById('baseo-preview-title');
                    var previewDesc = document.getElementById('baseo-preview-description');
                    
                    if (titleInput && previewTitle) {
                        if (titleInput.value.trim()) {
                            previewTitle.innerHTML = escapeHtml(titleInput.value);
                        } else {
                            previewTitle.innerHTML = '<span class="baseo-preview-placeholder"><?php echo esc_js(get_the_title($post->ID)); ?></span>';
                        }
                    }
                    
                    if (descInput && previewDesc) {
                        if (descInput.value.trim()) {
                            previewDesc.innerHTML = escapeHtml(descInput.value);
                        } else {
                            previewDesc.innerHTML = '<span class="baseo-preview-placeholder"><?php _e('Your meta description will appear here...', 'custom-schema-baseo'); ?></span>';
                        }
                    }
                }
                
                function escapeHtml(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                // Initialize counters
                var titleInput = document.getElementById('baseo_meta_title');
                var descInput = document.getElementById('baseo_meta_description');
                
                if (titleInput) {
                    updateCounter(titleInput, 'baseo-title-counter', 70);
                    updatePreview();
                    
                    titleInput.addEventListener('input', function() {
                        updateCounter(this, 'baseo-title-counter', 70);
                        updatePreview();
                    });
                }
                
                if (descInput) {
                    updateCounter(descInput, 'baseo-description-counter', 160);
                    updatePreview();
                    
                    descInput.addEventListener('input', function() {
                        updateCounter(this, 'baseo-description-counter', 160);
                        updatePreview();
                    });
                }
            })();
            </script>
        </div>
        <?php
    }
    
    // Save Meta Box
public function save_schema_meta_box($post_id) {
    // Check if nonce is set
    if (!isset($_POST['baseo_schema_meta_box_nonce'])) {
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['baseo_schema_meta_box_nonce'], 'baseo_schema_meta_box')) {
        return;
    }
    
    // Check if autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Get meta values
    $meta_title = isset($_POST['baseo_meta_title']) ? sanitize_text_field($_POST['baseo_meta_title']) : '';
    $meta_description = isset($_POST['baseo_meta_description']) ? sanitize_textarea_field($_POST['baseo_meta_description']) : '';
    
    // Save Meta Title
    if (!empty($meta_title)) {
        update_post_meta($post_id, '_baseo_meta_title', $meta_title);
    } else {
        delete_post_meta($post_id, '_baseo_meta_title');
    }
    
    // Save Meta Description
    if (!empty($meta_description)) {
        update_post_meta($post_id, '_baseo_meta_description', $meta_description);
    } else {
        delete_post_meta($post_id, '_baseo_meta_description');
    }
    
    // ==========================================
    // NUEVO: Sincronizar con tabla baseo_custom_schemas
    // ==========================================
    $post_url = get_permalink($post_id);
    
    if ($post_url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        // Buscar schemas de esta URL
        $schemas = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table_name WHERE url = %s",
            $post_url
        ));
        
        // Actualizar todos los schemas de esta URL
        if ($schemas) {
            foreach ($schemas as $schema) {
                $wpdb->update(
                    $table_name,
                    array(
                        'meta_title' => $meta_title,
                        'meta_description' => $meta_description,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $schema->id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }
        }
    }
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
        // Try to get post meta first (priority)
        $meta_title = '';
        $meta_description = '';
        
        if (is_singular()) {
            $post_id = get_the_ID();
            $meta_title = get_post_meta($post_id, '_baseo_meta_title', true);
            $meta_description = get_post_meta($post_id, '_baseo_meta_description', true);
        }
        
        // If no post meta, fallback to table lookup
        if (empty($meta_title) && empty($meta_description)) {
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
            
            if ($schema) {
                $meta_title = $schema->meta_title;
                $meta_description = $schema->meta_description;
            }
        }
        
        // Inject meta tags if we have any
        if (!empty($meta_title) || !empty($meta_description)) {
            echo '<!-- Custom Meta Tags by ' . $this->brand_name . ' -->' . "\n";
            
            if (!empty($meta_title)) {
                // Remove WordPress default title tag to replace it
                remove_action('wp_head', '_wp_render_title_tag', 1);
                
                // Inject custom title tag (this actually changes the page title)
                echo '<title>' . esc_html($meta_title) . '</title>' . "\n";
                
                // Social media meta tags
                echo '<meta property="og:title" content="' . esc_attr($meta_title) . '">' . "\n";
                echo '<meta name="twitter:title" content="' . esc_attr($meta_title) . '">' . "\n";
            }
            
            if (!empty($meta_description)) {
                echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
                echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
                echo '<meta name="twitter:description" content="' . esc_attr($meta_description) . '">' . "\n";
            }
            
            echo '<!-- End Custom Meta Tags by ' . $this->brand_name . ' -->' . "\n";
        }
    }
    
// Inject schema in head with variable replacement
public function inject_schema() {
    $current_url = $this->get_current_url();
    
    // ==========================================
    // PASO 1: Get meta values (PRIMERO - ANTES de schemas)
    // ==========================================
    $meta_title_value = '';
    $meta_description_value = '';
    
    if (is_singular()) {
        $post_id = get_the_ID();
        
        // 1A: Try BASEO custom meta first
        $meta_title_value = get_post_meta($post_id, '_baseo_meta_title', true);
        $meta_description_value = get_post_meta($post_id, '_baseo_meta_description', true);
        
        // 1B: If empty, try Yoast SEO
        if (empty($meta_title_value) && defined('WPSEO_VERSION')) {
            $meta_title_value = get_post_meta($post_id, '_yoast_wpseo_title', true);
        }
        if (empty($meta_description_value) && defined('WPSEO_VERSION')) {
            $meta_description_value = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        }
        
        // 1C: If empty, try Rank Math
        if (empty($meta_title_value) && class_exists('RankMath')) {
            $meta_title_value = get_post_meta($post_id, 'rank_math_title', true);
        }
        if (empty($meta_description_value) && class_exists('RankMath')) {
            $meta_description_value = get_post_meta($post_id, 'rank_math_description', true);
        }
        
        // 1D: If empty, try All in One SEO
        if (empty($meta_title_value) && function_exists('aioseo')) {
            $aioseo_post = get_post_meta($post_id, '_aioseo_title', true);
            if (!empty($aioseo_post)) {
                $meta_title_value = $aioseo_post;
            }
        }
        if (empty($meta_description_value) && function_exists('aioseo')) {
            $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);
            if (!empty($aioseo_desc)) {
                $meta_description_value = $aioseo_desc;
            }
        }
        
        // 1E: If empty, try SEOPress
        if (empty($meta_title_value) && function_exists('seopress_titles_the_title')) {
            $meta_title_value = get_post_meta($post_id, '_seopress_titles_title', true);
        }
        if (empty($meta_description_value) && function_exists('seopress_titles_the_title')) {
            $meta_description_value = get_post_meta($post_id, '_seopress_titles_desc', true);
        }
        
        // 1F: If empty, try The SEO Framework
        if (empty($meta_title_value) && function_exists('the_seo_framework')) {
            $tsf = the_seo_framework();
            if (method_exists($tsf, 'get_custom_field')) {
                $meta_title_value = $tsf->get_custom_field('_genesis_title', $post_id);
            }
        }
        if (empty($meta_description_value) && function_exists('the_seo_framework')) {
            $tsf = the_seo_framework();
            if (method_exists($tsf, 'get_custom_field')) {
                $meta_description_value = $tsf->get_custom_field('_genesis_description', $post_id);
            }
        }
        
        // 1G: Fallback to WordPress defaults
        if (empty($meta_title_value)) {
            $meta_title_value = get_the_title($post_id);
        }
        
        if (empty($meta_description_value)) {
            $meta_description_value = get_the_excerpt($post_id);
            if (empty($meta_description_value)) {
                $post_content = get_post_field('post_content', $post_id);
                $meta_description_value = wp_trim_words(strip_tags($post_content), 30, '...');
            }
        }
    }
    
    // 1H: If still empty, fallback to BASEO schema table
    if (empty($meta_title_value) && empty($meta_description_value)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'baseo_custom_schemas';
        
        $meta_schema = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_title, meta_description FROM $table_name 
             WHERE url = %s AND is_active = 1 
             ORDER BY updated_at DESC LIMIT 1",
            $current_url
        ));
        
        if ($meta_schema) {
            if (empty($meta_title_value)) {
                $meta_title_value = $meta_schema->meta_title;
            }
            if (empty($meta_description_value)) {
                $meta_description_value = $meta_schema->meta_description;
            }
        }
    }
    
    // 1I: Process SEO plugin variables (%%title%%, %title%, etc.)
     if (!empty($meta_title_value)) {
        $meta_title_value = $this->process_seo_variables($meta_title_value, $post_id ?? 0);
    }
    if (!empty($meta_description_value)) {
        $meta_description_value = $this->process_seo_variables($meta_description_value, $post_id ?? 0);
    }
    
    // ==========================================
    // PASO 2: Get schemas for current URL (DESPUÉS de obtener metas)
    // ==========================================
    global $wpdb;
    $table_name = $wpdb->prefix . 'baseo_custom_schemas';
    
    $schemas = $wpdb->get_results($wpdb->prepare(
        "SELECT schema_name, schema_data FROM $table_name 
         WHERE url = %s AND is_active = 1 
         ORDER BY created_at ASC",
        $current_url
    ));
    
    // ==========================================
    // PASO 3: Process and output schemas
    // ==========================================
    if ($schemas) {
        echo '<!-- Custom Schema by ' . $this->brand_name . ' -->' . "\n";
        
        foreach ($schemas as $schema) {
            $schema_data = $schema->schema_data;
            
            // Replace {{meta_title}}
            if (!empty($meta_title_value)) {
                $schema_data = str_replace('{{meta_title}}', addslashes($meta_title_value), $schema_data);
            } else {
                $schema_data = str_replace('{{meta_title}}', '', $schema_data);
            }
            
            // Replace {{meta_description}}
            if (!empty($meta_description_value)) {
                $schema_data = str_replace('{{meta_description}}', addslashes($meta_description_value), $schema_data);
            } else {
                $schema_data = str_replace('{{meta_description}}', '', $schema_data);
            }
            
            // Validate JSON
            $decoded = json_decode($schema_data);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[BASEO Schema] Invalid JSON after variable replacement for schema: ' . $schema->schema_name);
                continue;
            }
            
            // Output schema
            echo '<!-- Schema: ' . esc_html($schema->schema_name) . ' -->' . "\n";
            echo '<script type="application/ld+json">' . "\n";
            
            $json_output = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $json_output = str_ireplace('</script>', '<\/script>', $json_output);
            
            echo $json_output . "\n";
            echo '</script>' . "\n";
        }
        
        echo '<!-- End Custom Schema by ' . $this->brand_name . ' -->' . "\n";
    }
}

/**
 * Process SEO plugin variables (Yoast, Rank Math, etc.)
 */
private function process_seo_variables($text, $post_id = 0) {
    if (empty($text)) {
        return $text;
    }
    
    $post = null;
    if ($post_id > 0) {
        $post = get_post($post_id);
    }
    
    // Common SEO variables
    $replacements = array(
        // Yoast style (double %%)
        '%%title%%' => $post ? get_the_title($post_id) : '',
        '%%sitename%%' => get_bloginfo('name'),
        '%%sitedesc%%' => get_bloginfo('description'),
        '%%sep%%' => '-',
        '%%excerpt%%' => $post ? wp_trim_words(strip_tags($post->post_content), 30) : '',
        
        // Rank Math style (single %)
        '%title%' => $post ? get_the_title($post_id) : '',
        '%sitename%' => get_bloginfo('name'),
        '%sitedesc%' => get_bloginfo('description'),
        '%sep%' => '-',
        '%excerpt%' => $post ? wp_trim_words(strip_tags($post->post_content), 30) : '',
    );
    
    // Replace all variables
    foreach ($replacements as $var => $value) {
        $text = str_replace($var, $value, $text);
    }
    
    return $text;
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