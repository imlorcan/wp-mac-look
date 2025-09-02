// 等待DOM加载完成
document.addEventListener('DOMContentLoaded', function() {
    try {
        // 检查jQuery是否加载
        if (typeof jQuery === 'undefined') {
            console.error('jQuery未加载');
            // 尝试动态加载jQuery作为备选方案
            const script = document.createElement('script');
            script.src = 'https://cdn.bootcdn.net/ajax/libs/jquery/3.7.1/jquery.min.js';
            script.onload = initScript;
            document.head.appendChild(script);
            return;
        }
        
        initScript();
        
        function initScript() {
            const $ = jQuery;
            
            // 确保macLockScreen对象存在且有效
            if (typeof macLockScreen === 'undefined' ||!macLockScreen.ajaxUrl ||!macLockScreen.nonce) {
                console.error('macLockScreen配置不完整');
                return;
            }
            
            // 序列动画 - 添加底部信息动画
            function startSequenceAnimation() {
                // 包含底部信息的动画序列
                const delays = [300, 500, 700, 900, 1100];
                const elements = ['animate-avatar', 'animate-title', 'animate-password', 'animate-helper', 'animate-footer'];
                
                elements.forEach((elementId, index) => {
                    const $element = $('#' + elementId);
                    if ($element.length) {
                        setTimeout(() => {
                            $element.addClass('fade-in-up');
                        }, delays[index]);
                    }
                });
            }
            
            // 启动动画
            startSequenceAnimation();
            
            const passwordInput = $('#password');
            const errorMessage = $('#error-message');
            
            if (passwordInput.length) {
                // 密码框焦点事件处理 - 移除/恢复占位文字
                passwordInput.focus(function() {
                    $(this).data('original-placeholder', $(this).attr('placeholder'));
                    $(this).attr('placeholder', '');
                    errorMessage.hide();
                }).blur(function() {
                    if ($(this).val() === '') {
                        const originalPlaceholder = $(this).data('original-placeholder');
                        if (originalPlaceholder) {
                            $(this).attr('placeholder', originalPlaceholder);
                        }
                    }
                });
                
                // 自动聚焦密码框
                passwordInput.focus();
            }
            
            // 解锁功能
            function unlock() {
                if (!passwordInput.length) return;
                
                const password = passwordInput.val().trim();
                
                if (!password) {
                    showError('请输入密码');
                    triggerErrorFlash();
                    return;
                }
                
                // AJAX验证密码
                $.ajax({
                    url: macLockScreen.ajaxUrl,
                    type: 'POST',
                    dataType: 'json', // 明确指定预期响应格式
                    data: {
                        action:'mac_lock_screen_verify',
                        password: password,
                        nonce: macLockScreen.nonce
                    },
                    beforeSend: function() {
                        passwordInput.prop('disabled', true).addClass('loading');
                        errorMessage.hide();
                    },
                    success: function(response) {
                        // 验证响应是否有效
                        if (typeof response!== 'object' || response === null) {
                            showError('无效的响应格式');
                            return;
                        }
                        
                        if (response.success) {
                            // 显示成功提示并刷新
                            passwordInput.removeClass('error-flash').addClass('success');
                            setTimeout(() => window.location.reload()， 500);
                        } else {
                            showError(response.data || '密码不正确，请重试');
                            passwordInput。val('');
                            triggerErrorFlash();
                        }
                    }，
                    error: function(xhr， status， error) {
                        console.error('AJAX错误:'， status， error);
                        showError('网络错误，请检查连接后重试');
                        triggerErrorFlash();
                    },
                    complete: function() {
                        passwordInput.prop('disabled'， false).removeClass('loading').focus();
                    }
                });
            }
            
            // 显示错误信息
            function showError(message) {
                if (errorMessage.length) {
                    errorMessage。text(message)。show();
                    
                    // 5秒后自动隐藏错误信息
                    setTimeout(() => {
                        errorMessage。fadeOut(300);
                    }, 5000);
                } else {
                    alert(message);
                }
            }
            
            // 触发错误闪光效果
            function triggerErrorFlash() {
                if (!passwordInput.length) return;
                
                passwordInput.removeClass('error-flash');
                
                // 触发重绘
                void passwordInput[0].offsetWidth;
                
                passwordInput.addClass('error-flash');
                
                // 移除动画类
                setTimeout(() => {
                    passwordInput.removeClass('error-flash');
                }， 800);
            }
            
            // 键盘事件
            $(document).keydown(function(e) {
                if (!passwordInput.length) return;
                
                // 回车键提交密码
                if (e。key === 'Enter' || e。keyCode === 13) {
                    e。preventDefault(); // 防止默认行为
                    unlock();
                }
                // ESC键清除密码
                if (e。key === 'Escape' || e。keyCode === 27) {
                    passwordInput.val('').removeClass('error-flash');
                    errorMessage.hide();
                }
            });
        }
        
    } catch (error) {
        console。error('锁屏脚本错误:'， error);
        // 显示用户友好的错误信息
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv。style。cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 10px 20px; background: #ff3b30; color: white; border-radius: 5px; z-index: 9999;';
        errorDiv.textContent = '锁屏功能加载失败，请刷新页面重试';
        document.body.appendChild(errorDiv);
    }
});
    
