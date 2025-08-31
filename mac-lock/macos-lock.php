<?php
/*
Plugin Name: Mac风格锁屏插件
Plugin URI: https://lorcan.cn/
Description: 全站锁屏，输入密码解锁，支持头像等自定义
Version: 1.0.23
Author: Lorcan
Author URI: https://lorcan.cn/
License: GPL2
*/

// 默认密码常量
define('MAC_LOCK_DEFAULT_PASSWORD', '');
// 默认ICP备案号
define('MAC_LOCK_DEFAULT_ICP', '京ICP 备123456号');
// 默认头像路径
define('MAC_LOCK_DEFAULT_AVATAR', plugins_url('assets/images/default-avatar.png', __FILE__));

// 确保会话启动 - 修复会话管理问题
function mac_lock_screen_init_session() {
    if (!session_id() &&!headers_sent()) {
        $session_name = 'MAC_LOCK_SESSION';
        session_name($session_name);
        
        // 会话设置优化
        $session_params = session_get_cookie_params();
        session_set_cookie_params(
            86400, // 会话有效期1天
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
        
        session_start();
    }
}
// 提前启动会话，确保在输出前初始化
add_action('plugins_loaded','mac_lock_screen_init_session', 1);

// 定义资源目录
define('MY_PLUGIN_ASSETS', plugins_url('assets/', __FILE__));

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

// 获取版权默认值
function mac_lock_get_default_copyright() {
    $admin_user = get_user_by('ID', 1);
    $current_year = date('Y');
    
    if ($admin_user &&!empty($admin_user->display_name)) {
        return '© '. $current_year.''. esc_html($admin_user->display_name);
    }
    
    return '© '. $current_year.''. get_bloginfo('name');
}

// 注册设置
function mac_lock_screen_register_settings() {
    $default_copyright = mac_lock_get_default_copyright();
    $admin_user = get_user_by('ID', 1);
    $default_email = $admin_user? $admin_user->user_email : '';
    
    register_setting('mac_lock_screen_options', 'mac_lock_screen_enabled', [
        'sanitize_callback' => 'absint',
        'default' => 0
    ]);
    register_setting('mac_lock_screen_options', 'mac_lock_screen_title', [
        'sanitize_callback' =>'sanitize_text_field',
        'default' => '全站已加密'
    ]);
    register_setting('mac_lock_screen_options', 'mac_lock_screen_password', [
        'sanitize_callback' =>'sanitize_text_field'
    ]);
    register_setting('mac_lock_screen_options', 'mac_lock_screen_avatar', [
        'sanitize_callback' => function($input) {
            // 验证输入是URL或邮箱
            if (filter_var($input, FILTER_VALIDATE_URL)) {
                return esc_url_raw($input);
            } elseif (is_email($input)) {
                return sanitize_email($input);
            } elseif (empty($input)) {
                return '';
            } else {
                // 无效输入返回空，将使用默认值
                return '';
            }
        },
        'default' => $default_email
    ]);
    register_setting('mac_lock_screen_options', 'mac_lock_screen_bg', [
        'sanitize_callback' => 'esc_url_raw'
    ]);
    register_setting('mac_lock_screen_options', 'mac_lock_screen_show_footer', [
        'sanitize_callback' => function($input) {
            return $input === 'on'? 'on' : 'off';
        },
        'default' => 'off'
    ]);
    register_setting('mac_lock_screen_options', 'mac_lock_screen_copyright', [
        'sanitize_callback' =>'sanitize_text_field',
        'default' => $default_copyright
    ]);
    register_setting('mac_lock_screen_options', 'mac_lock_screen_icp', [
        'sanitize_callback' =>'sanitize_text_field',
        'default' => MAC_LOCK_DEFAULT_ICP
    ]);
}
add_action('admin_init', 'mac_lock_screen_register_settings');

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
        '1.0.23'
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
        '1.0.23',
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

// 设置页面回调函数
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

                <!-- 头像设置（整合Cravatar和自定义头像） -->
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
                        <div id="preview-type-indicator" class="preview-type-indicator"></div>
                    </div>
                    <strong class="mac-description">输入邮箱将使用对应的Cravatar头像，输入图片URL将使用自定义头像</strong>
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
                    <strong class="mac-description">支持本地图片和外链图片（http/https）</strong>
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
                    <strong class="mac-description">留空将自动显示：<?php echo esc_html($default_copyright);?></strong>
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
                    <strong class="mac-description">留空将自动显示默认备案号：<?php echo esc_html(MAC_LOCK_DEFAULT_ICP);?></strong>
                </div>

                <!-- 保存按钮 -->
                <div class="mac-form-actions">
                    <?php submit_button('保存设置', 'primary','submit', false, [
                        'class' => 'mac-submit'
                    ]);?>
                </div>
            </form>
        </div>
    </div>
    <?php
}
    
// 处理AJAX密码验证 - 修复密码验证问题
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
    
// 输出锁屏界面
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
    
    // 检查是否已解锁 - 添加会话有效期检查
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
    
    // 获取头像设置
    $avatar_input = get_option('mac_lock_screen_avatar', '');
    $avatar = mac_lock_get_avatar_url($avatar_input, 200);
    
    $title = get_option('mac_lock_screen_title');
    $bg_image = get_option('mac_lock_screen_bg');
    // 获取底部信息设置
    $show_footer = get_option('mac_lock_screen_show_footer', 'off');
    
    // 处理版权信息（为空时显示默认值）
    $copyright_value = get_option('mac_lock_screen_copyright', '');
    $copyright = empty($copyright_value)? mac_lock_get_default_copyright() : $copyright_value;
    
    // 处理备案信息（为空时显示默认值）
    $icp_value = get_option('mac_lock_screen_icp', '');
    $icp = empty($icp_value)? MAC_LOCK_DEFAULT_ICP : $icp_value;
    
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
            /* 动画定义 */
           .fade-in {
                opacity: 0;
                animation: fadeIn 0.6s ease forwards;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            /* 延迟动画 */
           .delay-100 { animation-delay: 0.1s; }
           .delay-200 { animation-delay: 0.2s; }
           .delay-300 { animation-delay: 0.3s; }
           .delay-400 { animation-delay: 0.4s; }
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
                    <input type="password" id="password" placeholder="输入密码解锁">
                </div>
                <div class="helper-text fade-in delay-300" id="animate-helper">
                    <span>按 <strong>Enter</strong> 解锁</span>
                    <span>清除密码按 <strong>ESC</strong></span>
                </div>
                <div class="error-message fade-in delay-300" id="error-message" style="display: none; color: #ff3b30;"></div>
            </div>
            
            <!-- 底部网站信息 -->
            <?php if ($show_footer === 'on'):?>
            <div class="footer-info fade-in delay-400" id="animate-footer">
                <?php if (!empty($copyright)):?>
                <div class="copyright"><?php echo esc_html($copyright);?></div>
                <?php endif;?>
                <?php if (!empty($icp)):?>
                <div class="icp-info"><a href="https://beian.miit.gov.cn/" target="_blank"><?php echo esc_html($icp);?></a></div>
                <?php endif;?>
            </div>
            <?php endif;?>
        </div>
        
        <script>
            // 头像加载失败时重试机制
            document.addEventListener('DOMContentLoaded', function() {
                var avatar = document.querySelector('.lazy-avatar');
                var retryCount = 0;
                
                avatar.addEventListener('error', function() {
                    // 最多重试3次
                    if (retryCount < 3) {
                        retryCount++;
                        // 稍微延迟后重试，避免立即重试
                        setTimeout(function() {
                            var src = avatar.getAttribute('data-src');
                            // 添加随机参数避免缓存
                            var newSrc = src + (src.indexOf('?') >= 0? '&' : '?') + 'retry=' + retryCount;
                            avatar.setAttribute('src', newSrc);
                        }, 500 * retryCount); // 每次重试延迟增加
                    } else {
                        // 多次重试失败，使用默认头像
                        avatar.setAttribute('src', '<?php echo esc_url(MAC_LOCK_DEFAULT_AVATAR);?>');
                    }
                });
                
                // 手动触发一次加载
                if (avatar.getAttribute('src') === '') {
                    avatar.setAttribute('src', avatar.getAttribute('data-src'));
                }
            });
        </script>
        <script>
            // 密码验证JS逻辑 - 确保正确处理验证结果
            document.addEventListener('DOMContentLoaded', function() {
                var passwordInput = document.getElementById('password');
                var errorMessage = document.getElementById('error-message');
                
                // 处理密码提交
                function submitPassword() {
                    var password = passwordInput.value.trim();
                    
                    if (!password) {
                        showError('请输入密码');
                        return;
                    }
                    
                    // 发送AJAX请求验证密码
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
                            errorMessage.style.display = 'none';
                        },
                        success: function(response) {
                            if (response.success) {
                                // 密码正确，刷新页面进入网站
                                window.location.reload();
                            } else {
                                showError(response.data || '验证失败，请重试');
                                passwordInput.value = '';
                            }
                        },
                        error: function() {
                            showError('网络错误，请重试');
                        },
                        complete: function() {
                            passwordInput.disabled = false;
                            passwordInput.focus();
                        }
                    });
                }
                
                // 显示错误信息
                function showError(message) {
                    errorMessage.textContent = message;
                    errorMessage.style.display = 'block';
                    
                    // 3秒后自动隐藏错误信息
                    setTimeout(function() {
                        errorMessage.style.display = 'none';
                    }, 3000);
                }
                
                // 回车键提交密码
                passwordInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        submitPassword();
                    }
                    // ESC键清除密码
                    if (e.key === 'Escape') {
                        passwordInput.value = '';
                        errorMessage.style.display = 'none';
                    }
                });
                
                // 自动聚焦密码框
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
