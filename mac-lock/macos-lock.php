<?php
/*
Plugin Name: Mac风格锁屏
Description: 为网站添加Mac风格的锁屏功能，保护网站内容
Version: 1.0.24
Author: Lorcan
*/

// 定义常量
define('MY_PLUGIN_ASSETS', plugin_dir_url(__FILE__). 'assets/');
define('MAC_LOCK_DEFAULT_AVATAR', MY_PLUGIN_ASSETS. 'images/default-avatar.png');
define('MAC_LOCK_DEFAULT_COPYRIGHT', '© '. date('Y').get_bloginfo('name'));
define('MAC_LOCK_DEFAULT_ICP', '');
define('MAC_LOCK_DEFAULT_HELPER_HINT', '按 Enter 解锁 | 清除密码按 ESC');
define('MAC_LOCK_DEFAULT_NOTICE_TITLE', '欢迎访问');
define('MAC_LOCK_DEFAULT_NOTICE_CONTENT', '请输入密码解锁网站内容');

// 获取版权默认值
function mac_lock_get_default_copyright() {
    $admin_user = get_user_by('ID', 1);
    $current_year = date('Y');
    
    if ($admin_user &&!empty($admin_user->display_name)) {
        return '© '. $current_year.''. esc_html($admin_user->display_name);
    }
    
    return '© '. $current_year.''. get_bloginfo('name');
}

// 获取头像URL（整合Cravatar和自定义头像，增加错误处理）
function mac_lock_get_avatar_url($input = '', $size = 200) {
    // 检查是否为有效的URL
    if (!empty($input) && filter_var($input, FILTER_VALIDATE_URL)) {
        return esc_url($input);
    }
    
    // 处理邮箱情况
    $email = $input;
    
    // 如果没有提供邮箱，使用管理员邮箱
    if (empty($email)) {
        $admin_user = get_user_by('ID', 1);
        if ($admin_user &&!empty($admin_user->user_email)) {
            $email = $admin_user->user_email;
        } else {
            $email = 'default@example.com';
        }
    }
    
    // 验证邮箱格式，如果无效则使用默认
    if (!is_email($email)) {
        return MAC_LOCK_DEFAULT_AVATAR;
    }
    
    // 生成Cravatar URL（增加缓存参数避免浏览器缓存旧头像）
    $hash = md5(strtolower(trim($email)));
    $cache_buster = time() % 24; // 每24小时更新一次缓存
    return "https://cravatar.cn/avatar/{$hash}?s={$size}&d=". urlencode(MAC_LOCK_DEFAULT_AVATAR). "&cb={$cache_buster}";
}

// 初始化会话
function mac_lock_screen_init_session() {
    if (!session_id()) {
        session_start();
    }
}

// 注册设置
function mac_lock_screen_register_settings() {
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_enabled',
        array(
            'type' => 'boolean',
            'default' => 0
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_title',
        array(
            'type' =>'string',
            'default' => '全站已加密',
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_password',
        array(
            'type' =>'string',
            'default' => '',
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_avatar',
        array(
            'type' =>'string',
            'default' => '',
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_bg',
        array(
            'type' =>'string',
            'default' => '',
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_show_footer',
        array(
            'type' =>'string',
            'default' => 'off',
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_copyright',
        array(
            'type' =>'string',
            'default' => mac_lock_get_default_copyright(),
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_icp',
        array(
            'type' =>'string',
            'default' => MAC_LOCK_DEFAULT_ICP,
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_screen_helper_hint',
        array(
            'type' =>'string',
            'default' => MAC_LOCK_DEFAULT_HELPER_HINT,
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    // 公告弹窗相关设置
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_show_notice',
        array(
            'type' =>'string',
            'default' => 'off',
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_notice_title',
        array(
            'type' =>'string',
            'default' => MAC_LOCK_DEFAULT_NOTICE_TITLE,
           'sanitize_callback' =>'sanitize_text_field'
        )
    );
    
    register_setting(
       'mac_lock_screen_options',
       'mac_lock_notice_content',
        array(
            'type' =>'string',
            'default' => MAC_LOCK_DEFAULT_NOTICE_CONTENT,
           'sanitize_callback' => 'wp_kses_post'
        )
    );
}
add_action('admin_init','mac_lock_screen_register_settings');

// 添加设置页面
function mac_lock_screen_add_settings_page() {
    add_options_page(
        'Mac风格锁屏设置',
        'Mac风格锁屏',
       'manage_options',
       'mac-lock-screen-settings',
       'mac_lock_screen_settings_page'
    );
}
add_action('admin_menu','mac_lock_screen_add_settings_page');

// 加载后台脚本和样式
function mac_lock_screen_enqueue_admin_scripts($hook) {
    if ($hook!== 'settings_page_mac-lock-screen-settings') {
        return;
    }
    
    // 获取默认值
    $default_copyright = mac_lock_get_default_copyright();
    $admin_user = get_user_by('ID', 1);
    $default_email = $admin_user? $admin_user->user_email : '';
    
    // 加载后台样式
    wp_enqueue_style(
       'mac-lock-screen-admin-css',
        MY_PLUGIN_ASSETS. 'css/admin-style.css',
        array(),
        '1.0.32'
    );
    
    wp_enqueue_script('jquery');
    wp_enqueue_media();
    
    // 添加MD5库用于前端实时预览
    wp_enqueue_script(
       'md5-js',
        'https://cdn.bootcdn.net/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js',
        array(),
        '2.19.0',
        true
    );
    
    // 加载后台脚本
    wp_enqueue_script(
       'mac-lock-screen-admin',
        MY_PLUGIN_ASSETS. 'js/admin.js',
        array('jquery','md5-js'),
        '1.0.32',
        true
    );
    
    // 本地化脚本，传递初始值
    $initial_values = array(
        'enabled' => get_option('mac_lock_screen_enabled', 0),
        'title' => get_option('mac_lock_screen_title', '全站已加密'),
        'password' => get_option('mac_lock_screen_password', ''),
        'avatar' => get_option('mac_lock_screen_avatar', $default_email),
        'bg' => get_option('mac_lock_screen_bg', ''),
       'show_footer' => get_option('mac_lock_screen_show_footer', 'off'),
        'copyright' => get_option('mac_lock_screen_copyright', $default_copyright),
        'icp' => get_option('mac_lock_screen_icp', MAC_LOCK_DEFAULT_ICP),
        'helperHint' => get_option('mac_lock_screen_helper_hint', MAC_LOCK_DEFAULT_HELPER_HINT),
        // 公告弹窗相关初始值
       'showNotice' => get_option('mac_lock_show_notice', 'off'),
        'noticeTitle' => get_option('mac_lock_notice_title', MAC_LOCK_DEFAULT_NOTICE_TITLE),
        'noticeContent' => get_option('mac_lock_notice_content', MAC_LOCK_DEFAULT_NOTICE_CONTENT),
        'defaultCopyright' => $default_copyright,
        'defaultIcp' => MAC_LOCK_DEFAULT_ICP,
        'defaultEmail' => $default_email,
        'defaultAvatarUrl' => MAC_LOCK_DEFAULT_AVATAR
    );
    
    wp_localize_script(
       'mac-lock-screen-admin',
       'macLockInitialValues',
        $initial_values
    );
}
add_action('admin_enqueue_scripts','mac_lock_screen_enqueue_admin_scripts');

// 设置页面回调函数 - 添加公告弹窗设置项
function mac_lock_screen_settings_page() {
    // 权限检查
    if (!current_user_can('manage_options')) {
        wp_die(__('您没有权限访问此页面。', 'mac-lock-screen'));
    }

    // 处理通知
    if (isset($_GET['mac_lock_error']) && $_GET['mac_lock_error'] === 'empty_password') {
        add_settings_error(
            'mac_lock_screen_messages',
            'mac_lock_empty_password',
            __('启用锁屏功能时，解锁密码不能为空！', 'mac-lock-screen'),
            'error'
        );
    }

    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        add_settings_error(
            'mac_lock_screen_messages',
            'mac_lock_save_success',
            __('设置已成功保存！', 'mac-lock-screen'),
           'success'
        );
    }

    settings_errors('mac_lock_screen_messages');

    // 获取设置值
    $is_enabled     = get_option('mac_lock_screen_enabled', 0);
    $title          = get_option('mac_lock_screen_title', '全站已加密');
    $password       = get_option('mac_lock_screen_password', '');
    $default_copyright = mac_lock_get_default_copyright();
    $admin_user = get_user_by('ID', 1);
    $default_email = $admin_user? $admin_user->user_email : '';
    $avatar_input   = get_option('mac_lock_screen_avatar', $default_email);
    $bg_url         = get_option('mac_lock_screen_bg', MY_PLUGIN_ASSETS. 'images/default-bg.png');
    $show_footer    = get_option('mac_lock_screen_show_footer', 'off');
    $copyright      = get_option('mac_lock_screen_copyright', $default_copyright);
    $icp            = get_option('mac_lock_screen_icp', MAC_LOCK_DEFAULT_ICP);
    $helper_hint    = get_option('mac_lock_screen_helper_hint', MAC_LOCK_DEFAULT_HELPER_HINT);
    
    // 公告弹窗相关设置值
    $show_notice = get_option('mac_lock_show_notice', 'off');
    $notice_title = get_option('mac_lock_notice_title', MAC_LOCK_DEFAULT_NOTICE_TITLE);
    $notice_content = get_option('mac_lock_notice_content', MAC_LOCK_DEFAULT_NOTICE_CONTENT);

    // 生成头像预览URL
    $avatar_preview_url = mac_lock_get_avatar_url($avatar_input, 100);
   ?>

    <div class="mac-lock-wrap">
        <h1>Mac风格锁屏设置</h1>
        <div class="mac-card">
            <form action="options.php" method="post" id="mac-lock-settings-form">
                <?php
                settings_fields('mac_lock_screen_options');
                do_settings_sections('mac_lock_screen_options');
               ?>

                <!-- 启用锁屏功能 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_enabled">启用锁屏功能</label>
                    <div class="mac-toggle-switch">
                        <input 
                            type="checkbox" 
                            name="mac_lock_screen_enabled" 
                            id="mac_lock_screen_enabled" 
                            value="1" 
                            <?php checked(1, $is_enabled);?>
                        >
                        <label for="mac_lock_screen_enabled" class="toggle-slider"></label>
                    </div>
                    <strong class="mac-description">勾选后启用全站锁屏</strong>
                </div>

                <!-- 锁屏标题 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_title">锁屏标题</label>
                    <input 
                        type="text" 
                        name="mac_lock_screen_title" 
                        id="mac_lock_screen_title"
                        value="<?php echo esc_attr($title);?>" 
                        placeholder="请输入锁屏标题" 
                        class="mac-input"
                    />
                </div>

                <!-- 解锁密码 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_password">解锁密码 <span class="required">*</span></label>
                    <input 
                        type="text" 
                        name="mac_lock_screen_password" 
                        id="mac_lock_screen_password"
                        value="<?php echo esc_attr($password);?>" 
                        placeholder="请设置解锁密码" 
                        class="mac-input"
                    />
                </div>

                <!-- 头像设置 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_avatar">用户头像</label>
                    <div class="mac-upload-group">
                        <input 
                            type="text" 
                            id="mac_lock_screen_avatar" 
                            name="mac_lock_screen_avatar" 
                            value="<?php echo esc_attr($avatar_input);?>" 
                            class="mac-input"
                            placeholder="输入邮箱获取Cravatar头像，或输入图片URL使用自定义头像"
                        />
                        <button 
                            type="button" 
                            class="mac-button upload-image" 
                            data-target="mac_lock_screen_avatar" 
                            data-preview="avatar-preview"
                        >
                            从媒体库选择
                        </button>
                    </div>
                    <div id="avatar-preview" class="mac-preview-container">
                        <img 
                            src="<?php echo esc_url($avatar_preview_url);?>" 
                            class="mac-preview-image" 
                            alt="头像预览" 
                            width="100" 
                            height="100"
                            onerror="this.src='<?php echo esc_url(MAC_LOCK_DEFAULT_AVATAR);?>'"
                        />
                    </div>
                </div>

                <!-- 背景图设置 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_bg">背景图</label>
                    <div class="mac-upload-group">
                        <input 
                            type="text" 
                            id="mac_lock_screen_bg" 
                            name="mac_lock_screen_bg" 
                            value="<?php echo esc_attr($bg_url);?>" 
                            class="mac-input image-url-input"
                            placeholder="输入图片URL或从媒体库选择"
                        />
                        <button 
                            type="button" 
                            class="mac-button upload-image" 
                            data-target="mac_lock_screen_bg" 
                            data-preview="bg-preview"
                        >
                            从媒体库选择
                        </button>
                    </div>
                    <div id="bg-preview" class="mac-preview-container">
                        <img 
                            src="<?php echo esc_url($bg_url);?>" 
                            class="mac-preview-image" 
                            alt="背景预览" 
                            width="300" 
                            height="150"
                        />
                    </div>
                </div>

                <!-- 公告弹窗设置 -->
                <div class="mac-form-section">
                    <label class="mac-label" for="mac_lock_screen_show_notice">公告弹窗设置</label>
                    <div class="mac-form-group">
                        <div class="mac-toggle-switch">
                            <input 
                                type="checkbox" 
                                name="mac_lock_show_notice" 
                                id="mac_lock_show_notice" 
                                value="on" 
                                <?php checked('on', $show_notice);?>
                            >
                            <label for="mac_lock_show_notice" class="toggle-slider"></label>
                        </div>
                        <strong class="mac-description">勾选后在锁屏界面显示公告弹窗</strong>
                    </div>

                    <div class="mac-form-group">
                        <label class="mac-label" for="mac_lock_notice_title">公告标题</label>
                        <input 
                            type="text" 
                            name="mac_lock_notice_title" 
                            id="mac_lock_notice_title"
                            value="<?php echo esc_attr($notice_title);?>" 
                            placeholder="请输入公告标题" 
                            class="mac-input"
                        />
                    </div>

                    <div class="mac-form-group">
                        <label class="mac-label" for="mac_lock_notice_content">公告内容</label>
                        <textarea 
                            name="mac_lock_notice_content" 
                            id="mac_lock_notice_content"
                            rows="4"
                            placeholder="请输入公告内容" 
                            class="mac-textarea"
                        ><?php echo esc_textarea($notice_content);?></textarea>
                        <strong class="mac-description">支持简单的HTML标签，如&lt;br&gt;换行</strong>
                    </div>
                </div>

                <!-- 显示底部信息 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_show_footer">显示底部信息</label>
                    <div class="mac-toggle-switch">
                        <input 
                            type="checkbox" 
                            name="mac_lock_screen_show_footer" 
                            id="mac_lock_screen_show_footer" 
                            value="on" 
                            <?php checked('on', $show_footer);?>
                        >
                        <label for="mac_lock_screen_show_footer" class="toggle-slider"></label>
                    </div>
                </div>

                <!-- 版权信息 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_copyright">版权信息</label>
                    <input 
                        type="text" 
                        name="mac_lock_screen_copyright" 
                        id="mac_lock_screen_copyright"
                        value="<?php echo esc_attr($copyright);?>" 
                        placeholder="例如：© 2024 网站用户名" 
                        class="mac-input"
                    />
                </div>

                <!-- ICP备案信息 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_icp">ICP备案信息</label>
                    <input 
                        type="text" 
                        name="mac_lock_screen_icp" 
                        id="mac_lock_screen_icp"
                        value="<?php echo esc_attr($icp);?>" 
                        placeholder="例如：京ICP 备123456号" 
                        class="mac-input"
                    />
                </div>

                <!-- 提示文本设置 -->
                <div class="mac-form-group">
                    <label class="mac-label" for="mac_lock_screen_helper_hint">操作提示文本</label>
                    <input 
                        type="text" 
                        name="mac_lock_screen_helper_hint" 
                        id="mac_lock_screen_helper_hint"
                        value="<?php echo esc_attr($helper_hint);?>" 
                        placeholder="例如：按 Enter 解锁 | 清除密码按 ESC（留空则不显示）" 
                        class="mac-input"
                    />
                </div>

                <!-- 保存按钮 -->
                <div class="mac-form-actions">
                    <?php submit_button('保存设置', 'primary','submit', false, [
                        'class' =>'mac-submit'
                    ]);?>
                </div>
            </form>
        </div>
    </div>
    <?php
}
    
// 处理AJAX密码验证
function mac_lock_screen_verify_password() {
    // 检查会话是否已启动
    if (!session_id()) {
        mac_lock_screen_init_session();
    }
    
    // 添加CSRF验证
    if (!isset($_POST['nonce']) ||!wp_verify_nonce($_POST['nonce'],'mac_lock_nonce')) {
        wp_send_json_error('验证失败，请刷新页面重试');
        wp_die();
    }
    
    $saved_password = get_option('mac_lock_screen_password');
    
    if (empty($saved_password)) {
        wp_send_json_error('请先在后台设置解锁密码');
        wp_die();
    }
    
    $user_password = sanitize_text_field($_POST['password']);
    
    // 密码验证逻辑
    if ($user_password === $saved_password) {
        // 存储解锁状态，设置过期时间
        $_SESSION['mac_lock_screen_unlocked'] = true;
        $_SESSION['mac_lock_screen_unlocked_time'] = time();
        wp_send_json_success();
    } else {
        wp_send_json_error('密码不正确');
    }
    wp_die();
}
    
// 注册AJAX动作
add_action('wp_ajax_mac_lock_screen_verify', 'mac_lock_screen_verify_password');
add_action('wp_ajax_nopriv_mac_lock_screen_verify', 'mac_lock_screen_verify_password');
    
// 输出锁屏界面 - 添加公告弹窗
function mac_lock_screen_output() {
    // 检查是否启用了锁屏功能
    $is_enabled = get_option('mac_lock_screen_enabled', 0);
    if (!$is_enabled) {
        return;
    }
    
    // 检查会话是否已启动
    if (!session_id()) {
        mac_lock_screen_init_session();
    }
    
    // 检查是否已解锁
    $is_unlocked = false;
    if (isset($_SESSION['mac_lock_screen_unlocked']) && $_SESSION['mac_lock_screen_unlocked']) {
        // 检查会话是否过期（8小时有效期）
        if (isset($_SESSION['mac_lock_screen_unlocked_time']) && 
            (time() - $_SESSION['mac_lock_screen_unlocked_time'] < 28800)) {
            $is_unlocked = true;
            // 更新会话时间
            $_SESSION['mac_lock_screen_unlocked_time'] = time();
        } else {
            // 会话过期，重置解锁状态
            unset($_SESSION['mac_lock_screen_unlocked']);
            unset($_SESSION['mac_lock_screen_unlocked_time']);
        }
    }
    
    if ($is_unlocked) {
        return;
    }
    
    // 获取设置值
    $avatar_input = get_option('mac_lock_screen_avatar', '');
    $avatar = mac_lock_get_avatar_url($avatar_input, 200);
    $title = get_option('mac_lock_screen_title');
    $bg_image = get_option('mac_lock_screen_bg');
    $show_footer = get_option('mac_lock_screen_show_footer', 'off');
    
    // 处理版权信息
    $copyright_value = get_option('mac_lock_screen_copyright', '');
    $copyright = empty($copyright_value)? mac_lock_get_default_copyright() : $copyright_value;
    
    // 处理备案信息
    $icp_value = get_option('mac_lock_screen_icp', '');
    $icp = empty($icp_value)? MAC_LOCK_DEFAULT_ICP : $icp_value;
    
    // 处理提示文本
    $helper_hint_value = get_option('mac_lock_screen_helper_hint', '');
    $helper_hint = empty($helper_hint_value)? MAC_LOCK_DEFAULT_HELPER_HINT : $helper_hint_value;
    $display_helper_hint =!empty($helper_hint_value) || $helper_hint_value === MAC_LOCK_DEFAULT_HELPER_HINT;
    
    // 处理公告弹窗相关设置
    $show_notice = get_option('mac_lock_show_notice', 'off');
    $notice_title = get_option('mac_lock_notice_title', MAC_LOCK_DEFAULT_NOTICE_TITLE);
    $notice_content = get_option('mac_lock_notice_content', MAC_LOCK_DEFAULT_NOTICE_CONTENT);
    
    // 处理背景图默认值
    if (empty($bg_image)) {
        $bg_image = MY_PLUGIN_ASSETS. 'images/default-bg.png';
    }
   ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>
        <?php 
            $site_name = get_bloginfo('name');
            $plugin_title = empty($title)? '全站已加密' : $title;
            echo esc_html("{$site_name} - {$plugin_title}");
       ?>
        </title>
        <link rel="stylesheet" href="<?php echo MY_PLUGIN_ASSETS;?>css/lock-screen.css">
        <?php if (!wp_script_is('jquery', 'enqueued')):?>
        <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <?php endif;?>
        <style>
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.6s ease forwards;
        }
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(10px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        </style>
    </head>
    <body>
        <div class="background-image" style="background-image: url('<?php echo esc_url($bg_image);?>')"></div>
        <div class="frosted-glass"></div>
        <div class="lock-screen-container">
            <div class="lock-screen-content">
                <div class="avatar fade-in" id="animate-avatar">
                    <img 
                        src="<?php echo esc_url($avatar);?>" 
                        alt="用户头像"
                        onerror="this.src='<?php echo esc_url(MAC_LOCK_DEFAULT_AVATAR);?>'"
                        data-src="<?php echo esc_url($avatar);?>"
                        class="lazy-avatar"
                    >
                </div>
                <div class="title-tip fade-in delay-100" id="animate-title">
                    <div class="title"><?php echo esc_html(get_bloginfo('name'));?></div>
                </div>
                <div class="password-container fade-in delay-200" id="animate-password">
                    <div class="password-wrapper">
                        <input type="password" id="password" placeholder="输入密码解锁">
                        <button type="button" class="unlock-btn" id="unlock-btn">
                            <svg t="1756756285411" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="20021" width="15" height="15"><path d="M283.648 174.081l57.225-59.008 399.479 396.929-399.476 396.924-57.228-59.004 335.872-337.92z" fill="#ffffff" p-id="20022"></path></svg>
                        </button>
                    </div>
                </div>
                <?php if ($display_helper_hint): // 只有当需要显示时才输出提示文本的HTML结构?>
                <div class="helper-text fade-in delay-300" id="animate-helper">
                    <span><?php echo esc_html($helper_hint);?></span>
                </div>
                <?php endif;?>
            </div>
            
            <!-- 底部网站信息 -->
            <?php if ($show_footer === 'on'):?>
            <div class="footer-info fade-in delay-400" id="animate-footer">
                <?php if (!empty($copyright)):?>
                <div class="copyright"><?php echo esc_html($copyright);?></div>
                <?php endif;?>
                <?php if (!empty($icp)):?>
                <div class="icp-info">
                    <svg t="1756756971574" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="21890" width="15" height="15"><path d="M512 967.111111c170.666667-68.266667 284.444444-164.977778 341.333333-284.444444 73.955556-142.222222 91.022222-307.2 56.888889-500.622223L512 56.888889 113.777778 187.733333c-34.133333 187.733333-17.066667 352.711111 51.2 494.933334C227.555556 802.133333 341.333333 898.844444 512 967.111111z m-295.822222-307.2C153.6 534.755556 136.533333 392.533333 164.977778 227.555556L512 113.777778l347.022222 113.777778c22.755556 164.977778 5.688889 307.2-56.888889 426.666666-51.2 102.4-147.911111 182.044444-290.133333 244.622222-147.911111-56.888889-244.622222-142.222222-295.822222-238.933333z" fill="#ffffff" p-id="21891"></path><path d="M512 631.466667L318.577778 438.044444l45.511111-39.822222L512 546.133333 716.8 341.333333l45.511111 39.822223z" fill="#ffffff" p-id="21892"></path></svg> 
                    <a href="https://beian.miit.gov.cn/" target="_blank"><?php echo esc_html($icp);?></a></div>
                <?php endif;?>
            </div>
            <?php endif;?>
        </div>
        
        <!-- Mac风格公告弹窗 -->
        <?php if ($show_notice === 'on'):?>
        <div class="mac-notice-overlay" id="macNoticeOverlay">
            <div class="mac-notice-dialog">
                <div class="mac-notice-header">
                    <svg t="1756763437559" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="26440" width="30" height="30"><path d="M905.325116 737.104021l-66.421423-51.649863c-11.460711-8.904232-18.032323-22.345933-18.032323-36.882391l0-238.154226c0-41.675405-8.178483-82.124191-24.304435-120.226046-15.54433-36.77404-37.797244-69.791559-66.140323-98.141792-28.310368-28.335923-61.332998-50.593948-98.152014-66.156677-12.883589-5.448233-26.320179-10.072587-39.960183-13.749378l0-31.901295c-0.031688-44.246194-36.046246-80.243375-80.278129-80.243375-44.262549 0-80.271996 35.997181-80.271996 80.24542l0 31.87983c-13.524497 3.645103-26.962109 8.269457-39.966316 13.751422-36.74133 15.527975-69.76396 37.787023-98.146903 66.152589-28.324679 28.298102-50.586793 61.316643-66.168944 98.141792-16.126974 38.155008-24.305457 78.60175-24.305457 120.217869l0 238.130716c0 14.526236-6.570589 27.969981-18.030278 36.881369l-66.426534 51.659062c-13.141179 10.22387-22.312201 24.010046-26.523593 39.86512-3.85465 14.487393-3.307783 29.659649 1.577228 43.87412 4.878877 14.252291 13.757555 26.570613 25.680292 35.618973 13.102336 9.932548 28.811238 15.1835 45.425793 15.1835l694.261283 0c16.620688 0 32.327546-5.24073 45.426815-15.154879 11.906382-9.035072 20.789149-21.352371 25.684381-35.616929 4.893188-14.222648 5.446189-29.395926 1.598694-43.884342C927.632206 761.100779 918.460162 747.323802 905.325116 737.104021zM802.720475 731.94711l66.413245 51.642707c6.767871 5.28775 7.228875 12.82328 5.40019 18.117164-1.816418 5.30615-6.806714 10.976196-15.364426 10.976196l-694.288882 0c-8.56589 0-13.546985-5.668002-15.353182-10.976196-1.814374-5.303083-1.351325-12.848835 5.384858-18.113075l66.413245-51.646796c25.925616-20.156418 40.795306-50.552038 40.795306-83.395786l0-238.164448c0-137.798386 112.111961-249.903192 249.914436-249.903192 137.782032 0 249.872527 112.105828 249.872527 249.903192l0 238.164448C761.928236 681.39916 776.806103 711.795803 802.720475 731.94711zM512.035265 58.934958c11.754077 0 21.315573 9.559451 21.315573 21.30944l0 18.147829-42.650567 0 0-18.147829C490.700271 68.494409 500.271988 58.934958 512.035265 58.934958z" fill="#ffffffcc" p-id="26441"></path><path d="M406.366121 918.343122c0 58.259295 47.403716 105.656878 105.669144 105.656878 58.255206 0 105.648701-47.397583 105.648701-105.656878l0-4.075442L406.366121 914.26768 406.366121 918.343122z"fill="#ffffffcc" p-id="26442"></path><path d="M243.198186 128.5599c6.112652-4.951452 9.938681-11.992246 10.772782-19.827275 0.83819-7.842185-1.432078-15.523887-6.394774-21.635516-5.63427-6.92222-13.992657-10.892377-22.933688-10.892377-6.710629 0-13.285307 2.322399-18.514793 6.545035-48.965611 39.62184-89.28458 87.589801-119.837617 142.576068-31.335006 56.470475-50.820872 117.649124-57.917886 181.835033-0.8709 7.808453 1.361547 15.495265 6.286423 21.645738 4.923853 6.147406 11.940114 10.012278 19.74039 10.881133 1.051826 0.123684 2.153739 0.187059 3.272006 0.187059 15.023017 0 27.597907-11.271607 29.24771-26.21796C99.386299 301.277065 154.886723 200.028861 243.198186 128.5599z" fill="#ffffffcc" p-id="26443"></path><path d="M995.631879 407.137358c-7.074525-64.164444-26.551192-125.333892-57.894376-181.810501-30.523393-54.966845-70.844407-102.947072-119.836595-142.608778-5.218242-4.218547-11.797009-6.540946-18.520926-6.540946-8.922632 0-17.281018 3.980379-22.936754 10.925087-4.943275 6.11163-7.213542 13.790265-6.388641 21.625294 0.823879 7.836052 4.641731 14.86969 10.750294 19.802743 88.346216 71.50167 143.838463 172.748853 156.256959 285.094893 1.66718 14.952486 14.243092 26.226138 29.249754 26.226138 1.131556 0 2.235513-0.061331 3.263829-0.182971 7.811519-0.856589 14.835958-4.722483 19.777188-10.8842C994.258065 422.667377 996.48949 414.980565 995.631879 407.137358z" fill="#ffffffcc" p-id="26444"></path></svg>
                    <h3 class="mac-notice-title" style="flex: 1 1;"><?php echo esc_html($notice_title);?></h3>
                    <button class="mac-notice-close" id="macNoticeClose">
                         <svg t="1756761782765" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="22942" width="25" height="25"><path d="M286.165333 798.165333L512 572.330667l225.834667 225.834666 60.330666-60.330666L572.330667 512l225.834666-225.834667-60.330666-60.330666L512 451.669333 286.165333 225.834667 225.834667 286.165333 451.669333 512l-225.834666 225.834667z" fill="#ffffffcc" p-id="22943"></path></svg>
                    </button>
                </div>
                <div class="mac-notice-content">
                    <?php echo wp_kses_post($notice_content);?>
                </div>
                <div class="mac-notice-footer">
                    <button class="mac-notice-button" id="macNoticeConfirm">确定</button>
                </div>
            </div>
        </div>
        <?php endif;?>
        
        <!-- 密码错误弹窗 -->
        <div class="mac-notice-overlay" id="passwordErrorOverlay">
            <div class="mac-notice-dialog">
                <div class="mac-notice-contents">
                    <svg t="1756764033309" class="icon" viewBox="0 0 1293 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="30123" width="30" height="30"><path d="M743.316211 54.649263l487.154526 797.157053A113.178947 113.178947 0 0 1 1133.891368 1024H159.528421A113.178947 113.178947 0 0 1 63.056842 851.806316l487.208421-797.103158A113.178947 113.178947 0 0 1 737.28 45.810526l6.036211 8.838737z m395.15621 853.369263l-487.154526-797.103158-0.754527-1.077894-1.024-0.754527a5.389474 5.389474 0 0 0-7.383579 1.778527l-487.154526 797.103158a5.389474 5.389474 0 0 0 4.581053 8.245894H1133.945263a5.389474 5.389474 0 0 0 4.581053-8.192z" fill="#E01313" p-id="30124"></path><path d="M646.736842 323.368421a75.290947 75.290947 0 0 1 75.075369 80.626526l-17.354106 242.903579a57.882947 57.882947 0 0 1-115.442526 0l-17.354105-242.903579A75.290947 75.290947 0 0 1 646.736842 323.368421z" fill="#E01313" p-id="30125"></path><path d="M646.736842 808.421053m-53.894737 0a53.894737 53.894737 0 1 0 107.789474 0 53.894737 53.894737 0 1 0-107.789474 0Z" fill="#E01313" p-id="30126"></path></svg>
                    <span>密码不正确，请重新输入</span>
                </div>
            </div>
        </div>
        
        <!-- 密码正确弹窗 -->
        <div class="mac-notice-overlay" id="passwordSuccessOverlay">
            <div class="mac-notice-dialog">
                <div class="mac-notice-contents">
                    <svg t="1756762612720" class="icon password-success-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="23051" width="24" height="24"><path d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64z m0 820c-205.4 0-372-166.6-372-372s166.6-372 372-372 372 166.6 372 372-166.6 372-372 372z" fill="#34c759" p-id="23052"></path><path d="M719.4 347.9L548.3 519l-81.5-81.5c-6.2-6.2-16.4-6.2-22.6 0-6.2 6.2-6.2 16.4 0 22.6l92.8 92.8c6.2 6.2 16.4 6.2 22.6 0l189.1-189.1c6.2-6.2 6.2-16.4 0-22.6-6.3-6.2-16.5-6.2-22.7 0z" fill="#34c759" p-id="23053"></path></svg>
                    <span>密码正确，正在进入...</span>
                </div>
            </div>
        </div>
        
        <script>
            // 头像加载失败时重试机制
            document.addEventListener('DOMContentLoaded', function() {
                var avatar = document.querySelector('.lazy-avatar');
                var retryCount = 0;
                
                avatar.addEventListener('error', function() {
                    if (retryCount < 3) {
                        retryCount++;
                        setTimeout(function() {
                            var src = avatar.getAttribute('data-src');
                            var newSrc = src + (src.indexOf('?') >= 0? '&' : '?') + 'retry=' + retryCount;
                            avatar.setAttribute('src', newSrc);
                        }, 500 * retryCount);
                    } else {
                        avatar.setAttribute('src', '<?php echo esc_url(MAC_LOCK_DEFAULT_AVATAR);?>');
                    }
                });
                
                if (avatar.getAttribute('src') === '') {
                    avatar.setAttribute('src', avatar.getAttribute('data-src'));
                }
                
                // 公告弹窗控制 - 增加本地存储记录关闭状态，且仅允许通过按钮关闭
                <?php if ($show_notice === 'on'):?>
                // 检查是否已经关闭过弹窗
                var noticeClosed = localStorage.getItem('macNoticeClosed') === 'true';
                var noticeOverlay = document.getElementById('macNoticeOverlay');
                var noticeClose = document.getElementById('macNoticeClose');
                var noticeConfirm = document.getElementById('macNoticeConfirm');
                
                // 如果没有关闭过，才显示弹窗
                if (!noticeClosed) {
                    // 显示弹窗（延迟1秒显示，增加动画效果）
                    setTimeout(function() {
                        noticeOverlay.classList.add('active');
                    }, 1000);
                }
                
                // 关闭弹窗并记录状态
                function closeNotice() {
                    noticeOverlay.classList.remove('active');
                    // 存储到本地存储，标记为已关闭
                    localStorage.setItem('macNoticeClosed', 'true');
                }
                
                // 仅绑定按钮关闭事件，移除点击外部关闭的逻辑
                noticeClose.addEventListener('click', closeNotice);
                noticeConfirm.addEventListener('click', closeNotice);
                <?php endif;?>
                
                // 密码错误弹窗控制 - 修复关闭功能
                var passwordErrorOverlay = document.getElementById('passwordErrorOverlay');
                var passwordErrorClose = document.getElementById('passwordErrorClose');
                var passwordErrorConfirm = document.getElementById('passwordErrorConfirm');
                
                // 确保关闭函数能正确执行
                function closePasswordError() {
                    if (passwordErrorOverlay && passwordErrorOverlay.classList) {
                        passwordErrorOverlay.classList.remove('active');
                    }
                }
                
                // 为关闭按钮添加点击事件监听，确保绑定正确
                if (passwordErrorClose) {
                    passwordErrorClose.addEventListener('click', closePasswordError);
                }
                if (passwordErrorConfirm) {
                    passwordErrorConfirm.addEventListener('click', closePasswordError);
                }
                
                // 增加点击弹窗外部关闭的功能
                if (passwordErrorOverlay) {
                    passwordErrorOverlay.addEventListener('click', function(e) {
                        // 点击弹窗背景关闭
                        if (e.target === passwordErrorOverlay) {
                            closePasswordError();
                        }
                    });
                }
            });
        </script>
        <script>
            // 密码验证JS逻辑
            document.addEventListener('DOMContentLoaded', function() {
                var passwordInput = document.getElementById('password');
                var unlockBtn = document.getElementById('unlock-btn');
                var passwordErrorOverlay = document.getElementById('passwordErrorOverlay');
                var passwordSuccessOverlay = document.getElementById('passwordSuccessOverlay');
                
                function submitPassword() {
                    var password = passwordInput.value.trim();
                    
                    if (!password) {
                        return;
                    }
                    
                    unlockBtn.classList.add('loading');
                    
                    jQuery.ajax({
                        url: macLockScreen.ajaxUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action:'mac_lock_screen_verify',
                            nonce: macLockScreen.nonce,
                            password: password
                        },
                        beforeSend: function() {
                            passwordInput.disabled = true;
                        },
                        success: function(response) {
                            if (response.success) {
                                // 显示密码正确弹窗，1秒后关闭并刷新页面进入网站
                                passwordSuccessOverlay.classList.add('active');
                                setTimeout(function() {
                                    passwordSuccessOverlay.classList.remove('active');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 300); // 给关闭动画一点时间
                                }, 1000);
                            } else {
                                // 显示密码错误弹窗，1秒后自动关闭
                                passwordErrorOverlay.classList.add('active');
                                setTimeout(function() {
                                    passwordErrorOverlay.classList.remove('active');
                                }, 1000);
                                passwordInput.value = '';
                            }
                        },
                        error: function() {
                            // 错误时也1秒后自动关闭
                            passwordErrorOverlay.classList.add('active');
                            setTimeout(function() {
                                passwordErrorOverlay.classList.remove('active');
                            }, 1000);
                            passwordInput.value = '';
                        },
                        complete: function() {
                            unlockBtn.classList.remove('loading');
                            passwordInput.disabled = false;
                            passwordInput.focus();
                        }
                    });
                }
                
                passwordInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        submitPassword();
                    }
                    if (e.key === 'Escape') {
                        passwordInput.value = '';
                    }
                });
                
                unlockBtn.addEventListener('click', submitPassword);
                passwordInput.focus();
            });
        </script>
        <script>
            var macLockScreen = {
                ajaxUrl: '<?php echo admin_url('admin-ajax.php');?>',
                nonce: '<?php echo wp_create_nonce('mac_lock_nonce');?>'
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}
// 修改优先级，确保在其他输出前执行
add_action('template_redirect','mac_lock_screen_output', 1);
