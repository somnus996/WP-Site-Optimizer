<?php
/**
 * Plugin Name: WP Site Optimizer
 * Plugin URI: https://example.com/wp-site-optimizer
 * Description: WordPress SEO优化插件 - 移除自动保存和修订版本、取消符号转义、文章标签关键词自动内链
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wp-site-optimizer
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('WSO_VERSION', '1.0.0');
define('WSO_PLUGIN_FILE', __FILE__);
define('WSO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * WP Site Optimizer 主类
 */
class WP_Site_Optimizer {
    
    /**
     * 单例实例
     */
    private static $instance = null;
    
    /**
     * 插件选项
     */
    private $options;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        $this->options = get_option('wso_options', array());
        $this->init_hooks();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 插件激活/停用钩子
        register_activation_hook(WSO_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WSO_PLUGIN_FILE, array($this, 'deactivate'));
        
        // 管理员菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // 根据设置启用功能
        $this->init_features();
    }
    
    /**
     * 初始化功能
     */
    private function init_features() {
        // 移除自动保存和修订版本
        if (!empty($this->options['disable_autosave_revisions'])) {
            $this->disable_autosave_and_revisions();
        }
        
        // 取消WordPress符号转义
        if (!empty($this->options['disable_symbol_conversion'])) {
            $this->disable_symbol_conversion();
        }
        
        // 文章标签关键词自动内链
        if (!empty($this->options['auto_internal_links'])) {
            $this->enable_auto_internal_links();
        }
    }
    
    /**
     * 移除自动保存和修订版本
     */
    private function disable_autosave_and_revisions() {
        // 禁用自动保存
        add_action('wp_print_scripts', function() {
            wp_deregister_script('autosave');
        });
        
        // 禁用修订版本
        add_filter('wp_revisions_to_keep', '__return_zero');
        
        // 移除现有修订版本（可选）
        add_action('admin_init', array($this, 'remove_existing_revisions'));
    }
    
    /**
     * 移除现有修订版本
     */
    public function remove_existing_revisions() {
        if (!empty($this->options['remove_existing_revisions']) && is_admin()) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
            
            // 只执行一次
            $options = $this->options;
            unset($options['remove_existing_revisions']);
            update_option('wso_options', $options);
        }
    }
    
    /**
     * 取消WordPress符号转义
     */
    private function disable_symbol_conversion() {
        // 移除wptexturize过滤器
        remove_filter('the_content', 'wptexturize');
        remove_filter('the_excerpt', 'wptexturize');
        remove_filter('the_title', 'wptexturize');
        remove_filter('comment_text', 'wptexturize');
        remove_filter('single_post_title', 'wptexturize');
        remove_filter('wp_title', 'wptexturize');
        remove_filter('widget_title', 'wptexturize');
        remove_filter('widget_text', 'wptexturize');
    }
    
    /**
     * 启用文章标签关键词自动内链
     */
    private function enable_auto_internal_links() {
        add_filter('the_content', array($this, 'add_auto_internal_links'), 99);
    }
    
    /**
     * 添加自动内链
     */
    public function add_auto_internal_links($content) {
        // 避免在管理后台和非单篇文章页面执行
        if (is_admin() || !is_single()) {
            return $content;
        }
        
        global $post;
        
        // 获取当前文章的标签
        $tags = wp_get_post_tags($post->ID);
        
        if (empty($tags)) {
            return $content;
        }
        
        // 为每个标签添加内链
        foreach ($tags as $tag) {
            $tag_link = get_tag_link($tag->term_id);
            $tag_name = $tag->name;
            
            // 创建正则表达式，避免重复链接
            $pattern = '/(?<!<a[^>]*>)(?<!<\/a>)\b' . preg_quote($tag_name, '/') . '\b(?![^<]*<\/a>)/i';
            
            // 替换第一个匹配的标签名为链接（避免过度链接）
            $replacement = '<a href="' . esc_url($tag_link) . '" title="' . esc_attr($tag_name) . '">' . $tag_name . '</a>';
            $content = preg_replace($pattern, $replacement, $content, 1);
        }
        
        return $content;
    }
    
    /**
     * 添加管理员菜单
     */
    public function add_admin_menu() {
        add_options_page(
            'WP Site Optimizer 设置',
            'WP Site Optimizer',
            'manage_options',
            'wp-site-optimizer',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('wso_settings', 'wso_options');
        
        add_settings_section(
            'wso_main_section',
            'SEO优化设置',
            array($this, 'settings_section_callback'),
            'wp-site-optimizer'
        );
        
        add_settings_field(
            'disable_autosave_revisions',
            '移除自动保存和修订版本',
            array($this, 'checkbox_field_callback'),
            'wp-site-optimizer',
            'wso_main_section',
            array('field' => 'disable_autosave_revisions')
        );
        
        add_settings_field(
            'disable_symbol_conversion',
            '取消WordPress符号转义',
            array($this, 'checkbox_field_callback'),
            'wp-site-optimizer',
            'wso_main_section',
            array('field' => 'disable_symbol_conversion')
        );
        
        add_settings_field(
            'auto_internal_links',
            '文章标签关键词自动内链',
            array($this, 'checkbox_field_callback'),
            'wp-site-optimizer',
            'wso_main_section',
            array('field' => 'auto_internal_links')
        );
    }
    
    /**
     * 设置部分回调
     */
    public function settings_section_callback() {
        echo '<p>配置您的WordPress SEO优化选项。</p>';
    }
    
    /**
     * 复选框字段回调
     */
    public function checkbox_field_callback($args) {
        $field = $args['field'];
        $value = isset($this->options[$field]) ? $this->options[$field] : 0;
        echo '<input type="checkbox" id="' . $field . '" name="wso_options[' . $field . ']" value="1" ' . checked(1, $value, false) . ' />';
        
        // 添加字段说明
        $descriptions = array(
            'disable_autosave_revisions' => '禁用WordPress自动保存和文章修订版本功能，减少数据库存储。',
            'disable_symbol_conversion' => '禁用WordPress自动将引号、省略号等符号转换为特殊字符。',
            'auto_internal_links' => '自动为文章内容中的标签关键词添加内部链接，提升SEO效果。'
        );
        
        if (isset($descriptions[$field])) {
            echo '<p class="description">' . $descriptions[$field] . '</p>';
        }
    }
    
    /**
     * 管理页面
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>WP Site Optimizer 设置</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wso_settings');
                do_settings_sections('wp-site-optimizer');
                ?>
                
                <h3>高级选项</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">清理现有修订版本</th>
                        <td>
                            <input type="checkbox" name="wso_options[remove_existing_revisions]" value="1" />
                            <p class="description">一次性删除数据库中所有现有的修订版本（不可恢复）。</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('保存设置'); ?>
            </form>
            
            <div class="card">
                <h3>插件信息</h3>
                <p><strong>版本:</strong> <?php echo WSO_VERSION; ?></p>
                <p><strong>功能说明:</strong></p>
                <ul>
                    <li><strong>移除自动保存和修订版本:</strong> 减少数据库存储，提升网站性能</li>
                    <li><strong>取消WordPress符号转义:</strong> 保持原始字符，避免不必要的转换</li>
                    <li><strong>文章标签关键词自动内链:</strong> 自动为标签添加内部链接，提升SEO</li>
                </ul>
            </div>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        .card h3 {
            margin-top: 0;
        }
        </style>
        <?php
    }
    
    /**
     * 插件激活
     */
    public function activate() {
        // 设置默认选项
        $default_options = array(
            'disable_autosave_revisions' => 0,
            'disable_symbol_conversion' => 0,
            'auto_internal_links' => 0
        );
        
        if (!get_option('wso_options')) {
            add_option('wso_options', $default_options);
        }
    }
    
    /**
     * 插件停用
     */
    public function deactivate() {
        // 清理操作（如果需要）
    }
}

// 初始化插件
function wp_site_optimizer_init() {
    return WP_Site_Optimizer::get_instance();
}

// 启动插件
add_action('plugins_loaded', 'wp_site_optimizer_init');

// 添加插件操作链接
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=wp-site-optimizer') . '">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
});
