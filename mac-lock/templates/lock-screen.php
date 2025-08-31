<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全站已加密</title>
    <?php wp_head(); ?>
</head>
<body class="mac-lock-screen-body">
    <div class="lock-screen-container">
        <!-- 背景图片 -->
<div class="lock-screen-bg" <?php if (!empty($bg_image)) echo 'style="background-image: url(\'' . esc_url_for_base64($bg_image) . '\');"'; ?>></div>      
        <!-- 模糊遮罩 -->
        <div class="lock-screen-blur"></div>
        
        <!-- 内容区域 -->
        <div class="lock-screen-content">
            <!-- 登录表单 -->
            <div class="lock-screen-form-container">
                <!-- 头像 -->
                <?php if (!empty($avatar)): ?>
                <div class="lock-screen-avatar">
                    <img src="<?php echo esc_url($avatar); ?>" alt="用户头像">
                </div>
                <?php endif; ?>
                <!-- 表单 -->
                <form id="mac-lock-screen-form" class="lock-screen-form">
                    <div class="form-group">
                        <input type="password" id="password" name="password" placeholder="请输入密码解锁" required>
                    </div>
                    <div id="error-message" class="error-message"></div>
                    <button type="submit" class="unlock-button">解锁</button>
                </form>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
    