/**
 * Plugin Name:     Custom WordPress Roles For Public Users
 * Plugin URI:      https://github.com/codewithhamza1/custom-wordpress-roles-for-public-users
 * Description:     A plugin to create a restricted role for viewing Ninja Forms submissions with a custom, responsive dashboard. Limits access to a clean submissions page, removing other admin menus and redirecting users appropriately.
 * Author:          Muhammad Hamza Yousaf
 * Author URI:      https://github.com/codewithhamza1/
 * Text Domain:     custom-wordpress-ninja-forms-access
 * Domain Path:     /languages
 * Version:         1.0.3
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.0
 * Requires PHP:    7.0
 * Tested up to:    6.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SimpleNinjaFormsAccess {
    
    private $role_name = 'nf_viewer';
    private $role_display_name = 'Forms Viewer Final';
    
    public function __construct() {
        add_action('init', array($this, 'create_role'));
        add_action('admin_menu', array($this, 'remove_menus'), 999);
        add_action('admin_menu', array($this, 'add_custom_menu'), 1000);
        add_action('admin_init', array($this, 'redirect_non_submissions'));
        add_action('admin_head', array($this, 'hide_elements'));
        add_action('wp_before_admin_bar_render', array($this, 'clean_admin_bar'));
        add_filter('login_redirect', array($this, 'login_redirect'), 10, 3);
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    /**
     * Create restricted role
     */
    public function create_role() {
        remove_role($this->role_name);
        $subscriber = get_role('subscriber');
        if (!$subscriber) {
            return;
        }
        $caps = $subscriber->capabilities;
        $caps['view_nf_submissions'] = true;
        $caps['read'] = true;
        add_role($this->role_name, $this->role_display_name, $caps);
    }
    
    /**
     * Check if user is our custom viewer
     */
    private function is_viewer() {
        $user = wp_get_current_user();
        return in_array($this->role_name, (array) $user->roles);
    }
    
    /**
     * Remove ALL admin menus
     */
    public function remove_menus() {
        if (!$this->is_viewer()) {
            return;
        }
        remove_menu_page('index.php');
        remove_menu_page('edit.php');
        remove_menu_page('upload.php');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('edit-comments.php');
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('users.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('ninja-forms');
    }
    
    /**
     * Add custom menu for submissions
     */
    public function add_custom_menu() {
        if (!$this->is_viewer()) {
            return;
        }
        add_menu_page(
            'Form Submissions',
            'Form Submissions',
            'view_nf_submissions',
            'nf-custom-submissions',
            array($this, 'display_submissions_page'),
            'dashicons-forms',
            10
        );
        add_submenu_page(
            'nf-custom-submissions',
            'My Profile',
            'My Profile',
            'read',
            'profile.php'
        );
    }
    
    /**
     * Display custom submissions page
     */
    public function display_submissions_page() {
        global $wpdb;
        
        // Get all forms using Ninja Forms API
        $forms = Ninja_Forms()->form()->get_forms();
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        
        ?>
        <div class="wrap">
            <h1>Form Submissions</h1>
            
            <?php if (current_user_can('manage_options')): ?>
                <div class="notice notice-info">
                    <p><strong>Debug Info:</strong> Forms found: <?php echo count($forms); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="nf-custom-submissions">
                <select name="form_id" style="padding: 8px; min-width: 200px;">
                    <option value="">Select a Form</option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?php echo esc_attr($form->get_id()); ?>" <?php selected($form_id, $form->get_id()); ?>>
                            <?php echo esc_html($form->get_setting('title')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button('View Submissions', 'primary', 'submit', false); ?>
            </form>
            
            <?php if ($form_id): ?>
                <?php
                // Get fields using Ninja Forms API
                $fields = Ninja_Forms()->form($form_id)->get_fields();
                
                if (empty($fields)) {
                    // Fallback to database query
                    $fields = $wpdb->get_results($wpdb->prepare(
                        "SELECT id, label, `key` FROM {$wpdb->prefix}nf3_fields WHERE form_id = %d ORDER BY `order`",
                        $form_id
                    ));
                    
                    if (empty($fields)) {
                        error_log('No fields found for form_id: ' . $form_id);
                        echo '<p>No fields found for this form. Check if the form has fields configured or contact the administrator.</p>';
                        echo '</div>';
                        return;
                    }
                }
                
                // Get submissions using database query
                $submissions = $wpdb->get_results($wpdb->prepare(
                    "SELECT p.ID, p.post_date 
                     FROM {$wpdb->posts} p 
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                     WHERE p.post_type = 'nf_sub' 
                     AND p.post_status = 'publish' 
                     AND pm.meta_key = '_form_id' 
                     AND pm.meta_value = %d 
                     ORDER BY p.post_date DESC",
                    $form_id
                ));
                
                if (empty($submissions)) {
                    echo '<p>No submissions found for this form.</p>';
                    echo '</div>';
                    return;
                }
                ?>
                
                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped" style="border-collapse: collapse; width: 100%;">
                        <thead>
                            <tr style="background: #f9f9f9; border-bottom: 2px solid #ddd;">
                                <th style="padding: 12px; text-align: left; min-width: 150px;">Date</th>
                                <?php foreach ($fields as $field): ?>
                                    <th style="padding: 12px; text-align: left; min-width: 120px;">
                                        <?php echo esc_html(is_object($field) ? $field->get_setting('label') : $field->label); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $sub): ?>
                                <tr style="background: #fff;">
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <?php echo esc_html($sub->post_date); ?>
                                    </td>
                                    <?php foreach ($fields as $field): ?>
                                        <?php
                                        $field_id = is_object($field) ? $field->get_id() : $field->id;
                                        $meta_key = '_field_' . $field_id;
                                        $value = $wpdb->get_var($wpdb->prepare(
                                            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
                                            $sub->ID,
                                            $meta_key
                                        ));
                                        // Check if value is serialized
                                        if (is_serialized($value)) {
                                            $unserialized = maybe_unserialize($value);
                                            if (is_array($unserialized)) {
                                                $value = '<ul style="margin: 0; padding-left: 20px;">';
                                                foreach ($unserialized as $item) {
                                                    $value .= '<li>' . esc_html($item) . '</li>';
                                                }
                                                $value .= '</ul>';
                                            }
                                        } else {
                                            $value = esc_html($value ?: '-');
                                        }
                                        ?>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <?php echo $value; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <style>
                    /* Responsive table styling */
                    .wp-list-table {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        font-size: 14px;
                        color: #333;
                    }
                    .wp-list-table th, .wp-list-table td {
                        word-wrap: break-word;
                        max-width: 300px;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    .wp-list-table tr:hover {
                        background: #f5f5f5;
                    }
                    @media screen and (max-width: 600px) {
                        .wp-list-table {
                            display: block;
                            overflow-x: auto;
                        }
                        .wp-list-table thead, .wp-list-table tbody, .wp-list-table tr {
                            display: block;
                        }
                        .wp-list-table th, .wp-list-table td {
                            display: block;
                            text-align: left;
                            padding: 10px;
                            border-bottom: 1px solid #eee;
                            position: relative;
                            min-width: unset;
                            max-width: unset;
                        }
                        .wp-list-table th:before, .wp-list-table td:before {
                            content: attr(data-label);
                            font-weight: bold;
                            display: inline-block;
                            width: 40%;
                            padding-right: 10px;
                        }
                    }
                </style>
                
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Redirect users away from non-submissions pages
     */
    public function redirect_non_submissions() {
        if (!$this->is_viewer() || wp_doing_ajax()) {
            return;
        }
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $allowed_pages = array('nf-custom-submissions');
        $allowed_files = array('profile.php', 'user-edit.php');
        if ($pagenow === 'index.php' || ($pagenow === 'admin.php' && empty($page))) {
            wp_redirect(admin_url('admin.php?page=nf-custom-submissions'));
            exit;
        }
        if (!empty($page) && !in_array($page, $allowed_pages)) {
            wp_redirect(admin_url('admin.php?page=nf-custom-submissions'));
            exit;
        }
        $blocked_files = array(
            'edit.php', 'post-new.php', 'post.php',
            'upload.php', 'media-new.php',
            'themes.php', 'theme-editor.php',
            'plugins.php', 'plugin-editor.php',
            'users.php', 'user-new.php',
            'tools.php', 'options-general.php',
            'edit-comments.php', 'ninja-forms.php'
        );
        if (in_array($pagenow, $blocked_files)) {
            wp_redirect(admin_url('admin.php?page=nf-custom-submissions'));
            exit;
        }
    }
    
    /**
     * Login redirect to submissions
     */
    public function login_redirect($redirect_to, $request, $user) {
        if (!is_wp_error($user) && in_array($this->role_name, (array) $user->roles)) {
            return admin_url('admin.php?page=nf-custom-submissions');
        }
        return $redirect_to;
    }
    
    /**
     * Clean admin bar
     */
    public function clean_admin_bar() {
        if (!$this->is_viewer()) {
            return;
        }
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('wp-logo');
        $wp_admin_bar->remove_menu('new-content');
        $wp_admin_bar->remove_menu('comments');
        $wp_admin_bar->remove_menu('customize');
    }
    
    /**
     * Hide admin elements with CSS
     */
    public function hide_elements() {
        if (!$this->is_viewer()) {
            return;
        }
        ?>
        <style>
        /* Hide unnecessary admin elements */
        .welcome-panel, .page-title-action, #screen-meta-links,
        .notice:not(.nf-admin-notice), .updated, .error, .update-nag,
        #wp-admin-bar-wp-logo, #wp-admin-bar-new-content,
        #wp-admin-bar-comments, #wp-admin-bar-customize,
        #footer-thankyou, #footer-upgrade {
            display: none !important;
        }
        /* Hide actions if any */
        .bulk-actions, .row-actions {
            display: none !important;
        }
        </style>
        <?php
    }
    
    /**
     * Create user with viewer role
     */
    public function create_viewer_user($username, $email, $password = '') {
        if (empty($password)) {
            $password = wp_generate_password(12, true);
        }
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            return false;
        }
        $user = new WP_User($user_id);
        $user->set_role($this->role_name);
        return array(
            'user_id' => $user_id,
            'username' => $username,
            'password' => $password,
            'email' => $email
        );
    }
    
    /**
     * Assign existing user to viewer role
     */
    public function assign_viewer_role($user_id) {
        $user = new WP_User($user_id);
        if (!$user->exists()) {
            return false;
        }
        $user->set_role($this->role_name);
        return true;
    }
    
    /**
     * Activate plugin
     */
    public function activate() {
        $this->create_role();
        flush_rewrite_rules();
    }
}

// Initialize
$ninja_forms_access = new SimpleNinjaFormsAccess();

/**
 * Helper functions
 */
function create_ninja_viewer($username, $email, $password = '') {
    global $ninja_forms_access;
    return $ninja_forms_access->create_viewer_user($username, $email, $password);
}

function assign_ninja_viewer_role($user_id) {
    global $ninja_forms_access;
    return $ninja_forms_access->assign_viewer_role($user_id);
}

/**
 * Admin page for managing viewers
 */
add_action('admin_menu', function() {
    if (current_user_can('manage_options')) {
        add_users_page(
            'Ninja Forms Viewers',
            'Forms Viewers',
            'manage_options',
            'ninja-viewers',
            'ninja_viewers_page'
        );
    }
});

function ninja_viewers_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle form submissions
    if (isset($_POST['create_viewer']) && wp_verify_nonce($_POST['_wpnonce'], 'create_ninja_viewer')) {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        
        $result = create_ninja_viewer($username, $email, $password);
        if ($result) {
            echo '<div class="notice notice-success"><p><strong>SUCCESS!</strong><br>';
            echo 'Username: <strong>' . esc_html($result['username']) . '</strong><br>';
            echo 'Password: <strong>' . esc_html($result['password']) . '</strong><br>';
            echo 'Email: ' . esc_html($result['email']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to create user.</p></div>';
        }
    }
    
    if (isset($_POST['assign_viewer']) && wp_verify_nonce($_POST['_wpnonce'], 'assign_ninja_viewer')) {
        $user_id = intval($_POST['user_id']);
        if (assign_ninja_viewer_role($user_id)) {
            echo '<div class="notice notice-success"><p>User role updated!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to update role.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Ninja Forms Viewers Management</h1>
        
        <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">
            <h3>✅ Custom Dashboard Solution</h3>
            <p>This version uses a custom dashboard with a responsive, minimal table to display form submissions, queried via Ninja Forms API and database.</p>
        </div>
        
        <div class="card" style="max-width: 600px;">
            <h2>Create New Forms Viewer</h2>
            <form method="post">
                <?php wp_nonce_field('create_ninja_viewer'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="username">Username</label></th>
                        <td><input type="text" id="username" name="username" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" id="email" name="email" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="password">Password</label></th>
                        <td>
                            <input type="password" id="password" name="password" class="regular-text" />
                            <p class="description">Leave empty for auto-generated password</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Create Forms Viewer', 'primary', 'create_viewer'); ?>
            </form>
        </div>
        
        <div class="card" style="max-width: 600px;">
            <h2>Convert Existing User</h2>
            <form method="post">
                <?php wp_nonce_field('assign_ninja_viewer'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="user_id">Select User</label></th>
                        <td>
                            <?php wp_dropdown_users(array(
                                'name' => 'user_id',
                                'show_option_none' => 'Choose user...',
                                'option_none_value' => ''
                            )); ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Convert to Forms Viewer', 'secondary', 'assign_viewer'); ?>
            </form>
        </div>
        
        <div class="card">
            <h2>Current Forms Viewers</h2>
            <?php
            $viewers = get_users(array('role' => 'nf_viewer'));
            if ($viewers) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Username</th><th>Email</th><th>Created</th><th>Action</th></tr></thead><tbody>';
                foreach ($viewers as $user) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($user->user_login) . '</strong></td>';
                    echo '<td>' . esc_html($user->user_email) . '</td>';
                    echo '<td>' . date('M j, Y', strtotime($user->user_registered)) . '</td>';
                    echo '<td><a href="' . get_edit_user_link($user->ID) . '" class="button">Edit</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No forms viewers found.</p>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>How This Works</h2>
            <ul>
                <li><strong>Base Role:</strong> Subscriber-level permissions plus custom view_nf_submissions capability</li>
                <li><strong>Custom Dashboard:</strong> Responsive table displays submissions queried via Ninja Forms API and database</li>
                <li><strong>View Only:</strong> Clean, minimal table without edit or delete options</li>
                <li><strong>Menu Restriction:</strong> All admin menus removed except the custom submissions page</li>
                <li><strong>Page Blocking:</strong> Direct access to other admin pages redirects to custom submissions</li>
                <li><strong>Clean Interface:</strong> Unnecessary UI elements hidden with CSS</li>
                <li><strong>Auto Login:</strong> Users redirected to custom submissions page after login</li>
            </ul>
            <p>Note: Uses Ninja Forms API for reliable form and field retrieval, with database fallback for submissions.</p>
        </div>
    </div>
    <?php
}
