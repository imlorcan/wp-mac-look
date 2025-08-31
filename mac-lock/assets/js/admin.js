jQuery(document).ready(function($) {
    // 初始化头像预览
    updateAvatarPreview();
    
    // 监听头像输入框变化，实时更新预览
    $('#mac_lock_screen_avatar').on('input', function() {
        updateAvatarPreview();
    });
    
    // 更新头像预览图片 - 增强版
    function updateAvatarPreview() {
        var previewContainer = $('#avatar-preview');
        var previewImage = previewContainer.find('img');
        var typeIndicator = $('#preview-type-indicator');
        var inputValue = $('#mac_lock_screen_avatar').val().trim();
        var defaultAvatar = macLockInitialValues.defaultAvatarUrl;
        
        // 显示预览容器
        previewContainer.show();
        
        // 检查输入是否为有效的URL
        if (inputValue && filterVar(inputValue, 'url')) {
            // 是图片URL，显示自定义头像
            previewImage.attr('src', inputValue);
            previewImage.attr('onerror', "this.src='" + defaultAvatar + "'");
            typeIndicator.text('当前：自定义头像');
        } else {
            // 不是有效的URL，尝试作为邮箱处理
            var email = inputValue || macLockInitialValues.defaultEmail;
            var hash = md5(email.toLowerCase().trim());
            // 添加随机参数避免缓存
            var cacheBuster = Math.floor(Math.random() * 1000);
            var url = 'https://cravatar.cn/avatar/' + hash + '?s=100&d=' + encodeURIComponent(defaultAvatar) + '&cb=' + cacheBuster;
            
            // 设置头像URL并添加错误处理
            previewImage.attr('src', url);
            previewImage.attr('onerror', "this.src='" + defaultAvatar + "'");
            
            // 显示适当的提示文本
            if (inputValue &&!isEmail(inputValue)) {
                typeIndicator.text('当前：使用默认Cravatar头像（邮箱格式不正确）');
            } else if (inputValue) {
                typeIndicator.text('当前：Cravatar头像（若未显示，可能该邮箱未注册Cravatar）');
            } else {
                typeIndicator.text('当前：默认Cravatar头像');
            }
        }
    }
    
    // 辅助函数：验证URL
    function filterVar(value, type) {
        if (type === 'url') {
            var pattern = new RegExp('^(https?:\\/\\/)?'+ // 协议可选
                '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // 域名
                '((\\d{1,3}\\.){3}\\d{1,3}))'+ // IP地址
                '(\\:\\d+)?(\\/.*)?$','i'); // 端口和路径
            return!!pattern.test(value);
        }
        return false;
    }
    
    // 辅助函数：验证邮箱
    function isEmail(email) {
        var re = /^[^@]+@[^@]+\.[^@]+$/; // 简单验证：包含@和.
        return re.test(email);
    }
    
    // 媒体库上传功能
    $('.upload-image').click(function(e) {
        e.preventDefault();
        
        var targetInput = $(this).data('target');
        var previewId = $(this).data('preview');
        var customUploader = wp.media({
            title: '选择图片',
            button: {
                text: '选择'
            },
            multiple: false
        })
       .on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $('#' + targetInput).val(attachment.url);
            updateAvatarPreview();
        })
       .open();
    });
});
    