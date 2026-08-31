<?php
/**
 * ====================================================================
 * 安全密码生成器 - 单文件全栈应用 (PHP + JS)
 *
 * 描述:
 * 这是一个功能完整的安全密码生成工具。它整合了后端PHP逻辑
 * 用于安全地生成密码，以及一个现代、响应式的前端界面
 * 用于用户交互。
 *
 * @version    1.1.8
 * @author     编码助手
 * @lastupdate 2026-08-31
 * ====================================================================
 */

$raw_request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$http_request_method = is_string($raw_request_method) ? strtoupper($raw_request_method) : 'GET';

/**
 * 将外部长度参数限制在界面支持的范围内，并拒绝数组等异常输入。
 *
 * @param mixed $value
 * @return int
 */
function normalize_password_length(mixed $value): int {
    if (is_int($value)) {
        $raw_length = $value;
    } elseif (is_string($value) && preg_match('/^[+-]?\d+$/', trim($value))) {
        $raw_length = (int)trim($value);
    } else {
        return 16;
    }

    return max(8, min(50, $raw_length));
}

/**
 * 以兼容复选框表单和接口调用的方式读取字符类型选项。
 *
 * @param string $name
 * @return bool
 */
function post_checkbox_enabled(string $name): bool {
    $value = $_POST[$name] ?? null;
    if (!is_scalar($value)) {
        return false;
    }

    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'on', 'yes'], true);
}

/**
 * 判断当前请求是否为页面发起的 AJAX 密码生成请求。
 *
 * @param string $request_method
 * @return bool
 */
function is_ajax_generation_request(string $request_method): bool {
    if ($request_method !== 'POST') {
        return false;
    }

    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;
    if (!is_string($requested_with)) {
        return false;
    }

    return strcasecmp(trim($requested_with), 'XMLHttpRequest') === 0;
}

/**
 * 对页面文本进行 UTF-8 HTML 转义。
 *
 * @param string $value
 * @return string
 */
function escape_html(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
}

// --- 核心功能：安全地生成密码 ---

/**
 * 使用加密安全随机数重排字符串，并避免相邻字符重复
 *
 * @param string $string
 * @return string
 */
function secure_str_shuffle(string $string): string {
    $counts = array_count_values(str_split($string));
    $remaining = strlen($string);
    $previous = null;
    $result = '';

    while ($remaining > 0) {
        $max_count = 0;
        $candidates = [];

        foreach ($counts as $raw_char => $count) {
            $char = (string)$raw_char;
            if ($count <= 0 || $char === $previous) {
                continue;
            }

            if ($count > $max_count) {
                $max_count = $count;
                $candidates = [$raw_char];
            } elseif ($count === $max_count) {
                $candidates[] = $raw_char;
            }
        }

        if (empty($candidates)) {
            throw new RuntimeException('无法生成不含相邻重复字符的密码。');
        }

        $chosen = $candidates[random_int(0, count($candidates) - 1)];
        $result .= (string)$chosen;
        $counts[$chosen]--;
        $previous = (string)$chosen;
        $remaining--;
    }

    return $result;
}

/**
 * 根据指定的标准生成一个加密安全的随机密码。
 *
 * @param int    $length            密码的目标长度。
 * @param bool   $include_uppercase 是否包含大写字母。
 * @param bool   $include_lowercase 是否包含小写字母。
 * @param bool   $include_numbers   是否包含数字。
 * @param bool   $include_symbols   是否包含特殊符号。
 * @return string                   生成的安全密码或错误信息。
 */
function generate_secure_password(int $length, bool $include_uppercase, bool $include_lowercase, bool $include_numbers, bool $include_symbols): string {
    // 定义各类字符集
    $lowercase_chars = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $number_chars = '0123456789';
    $symbol_chars = '!@#$%^&*()-_=+[]{}|;:,.<>?';

    // 默认开启：过滤常见混淆字符（0 O 1 l I 等）
    $ambiguous = '0O1lI';
    $lowercase_chars = str_replace(str_split($ambiguous), '', $lowercase_chars);
    $uppercase_chars = str_replace(str_split($ambiguous), '', $uppercase_chars);
    $number_chars    = str_replace(str_split($ambiguous), '', $number_chars);

    $char_pool = ''; // 用于填充密码剩余长度的字符池
    $password = '';  // 最终的密码

    // 步骤1: 确保每种选定的字符类型都至少在密码中出现一次
    if ($include_lowercase && $lowercase_chars !== '') {
        $char_pool .= $lowercase_chars;
        $password .= $lowercase_chars[random_int(0, strlen($lowercase_chars) - 1)];
    }
    if ($include_uppercase && $uppercase_chars !== '') {
        $char_pool .= $uppercase_chars;
        $password .= $uppercase_chars[random_int(0, strlen($uppercase_chars) - 1)];
    }
    if ($include_numbers && $number_chars !== '') {
        $char_pool .= $number_chars;
        $password .= $number_chars[random_int(0, strlen($number_chars) - 1)];
    }
    if ($include_symbols && $symbol_chars !== '') {
        $char_pool .= $symbol_chars;
        $password .= $symbol_chars[random_int(0, strlen($symbol_chars) - 1)];
    }

    // 如果用户未选择任何字符类型，则返回错误
    if (empty($char_pool)) {
        return '错误：请至少选择一种字符类型。';
    }

    // 检查密码长度是否足够容纳所有选定的字符类型
    $selected_types_count = (int)$include_uppercase + (int)$include_lowercase + (int)$include_numbers + (int)$include_symbols;
    if ($length < $selected_types_count) {
        return '错误：密码长度不能小于所选字符类型的数量。';
    }

    // 步骤2: 使用完整的字符池随机填充密码的剩余部分（默认避免连续重复字符）
    $remaining_length = $length - strlen($password);
    if ($remaining_length > 0) {
        $pool_length = strlen($char_pool) - 1;
        for ($i = 0; $i < $remaining_length; $i++) {
            // 默认开启：避免连续相同字符
            do {
                $new_char = $char_pool[random_int(0, $pool_length)];
            } while (strlen($password) > 0 && $new_char === $password[-1]);
            $password .= $new_char;
        }
    }
    
    // 步骤3: 打乱密码字符串
    return secure_str_shuffle($password);
}

/**
 * 将密码生成结果转换为适合页面和 AJAX 响应使用的安全结构。
 *
 * @return array{password: ?string, error: ?string, status: int}
 */
function generate_password_result(int $length, bool $include_uppercase, bool $include_lowercase, bool $include_numbers, bool $include_symbols): array {
    try {
        $password = generate_secure_password(
            $length,
            $include_uppercase,
            $include_lowercase,
            $include_numbers,
            $include_symbols
        );
    } catch (Throwable) {
        return [
            'password' => null,
            'error' => '错误：密码生成失败，请稍后重试。',
            'status' => 500,
        ];
    }

    if (str_starts_with($password, '错误：')) {
        return [
            'password' => null,
            'error' => $password,
            'status' => 422,
        ];
    }

    return [
        'password' => $password,
        'error' => null,
        'status' => 200,
    ];
}

// --- 页面逻辑：处理用户输入和显示 ---

// 初始化密码选项的默认值
$generated_password = '';
$length = normalize_password_length($_POST['length'] ?? 16);
$form_submitted = isset($_POST['form_submitted']) && is_scalar($_POST['form_submitted']);

$options = [
    'length' => $length,
    'lowercase' => $form_submitted ? post_checkbox_enabled('lowercase') : true,
    'uppercase' => $form_submitted ? post_checkbox_enabled('uppercase') : true,
    'numbers' => $form_submitted ? post_checkbox_enabled('numbers') : true,
    'symbols' => $form_submitted ? post_checkbox_enabled('symbols') : true,
];

// 判断请求类型：是AJAX请求还是常规的表单提交
// AJAX请求用于无刷新更新密码，提升用户体验
if (is_ajax_generation_request($http_request_method)) {
    
    $generation_result = generate_password_result(
        $length,
        post_checkbox_enabled('uppercase'),
        post_checkbox_enabled('lowercase'),
        post_checkbox_enabled('numbers'),
        post_checkbox_enabled('symbols')
    );
    
    // 以JSON格式返回结果给前端JavaScript
    header('Content-Type: application/json; charset=UTF-8');
    if ($generation_result['error'] !== null) {
        http_response_code($generation_result['status']);
        echo json_encode(['error' => $generation_result['error']], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['password' => $generation_result['password']], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
// 常规表单提交用于在JavaScript被禁用的情况下也能正常工作
elseif ($http_request_method === 'POST') {
    $generation_result = generate_password_result(
        $length,
        (bool)$options['uppercase'],
        (bool)$options['lowercase'],
        (bool)$options['numbers'],
        (bool)$options['symbols']
    );
    $generated_password = $generation_result['password'] ?? $generation_result['error'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安全密码生成器</title>
    <style>
        /* --- 引入外部字体 --- */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        /* --- CSS变量定义 (浅色与深色主题) --- */
        :root {
            --bg-color: #f0f2f5; --card-bg-color: rgba(255, 255, 255, 0.65); --text-color: #1f2937; --text-color-light: #6b7280; --border-color: rgba(0, 0, 0, 0.08); --primary-color: #3b82f6; --primary-color-hover: #2563eb; --success-color: #10b981; --success-color-hover: #059669; --input-bg-color: rgba(255, 255, 255, 0.8); --switch-bg-color: #e5e7eb; --switch-handle-color: #ffffff; --font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; --border-radius: 16px; --shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 4px 6px -1px rgba(0,0,0,0.05); --backdrop-blur: blur(12px); --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.dark-mode {
            --bg-color: #111827; --card-bg-color: rgba(31, 41, 55, 0.65); --text-color: #f9fafb; --text-color-light: #9ca3af; --border-color: rgba(255, 255, 255, 0.1); --primary-color: #60a5fa; --primary-color-hover: #3b82f6; --success-color: #34d399; --success-color-hover: #10b981; --input-bg-color: rgba(55, 65, 81, 0.8); --switch-bg-color: #4b5563; --switch-handle-color: #e5e7eb; --shadow: 0 10px 25px -5px rgba(0,0,0,0.2), 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        /* --- 全局与基础样式 --- */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background-color: var(--bg-color); background-image: radial-gradient(circle at 1% 1%, var(--primary-color), transparent 25%), radial-gradient(circle at 99% 99%, var(--success-color), transparent 25%); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; font-family: var(--font-family); color: var(--text-color); transition: var(--transition); }
        .container { width: 100%; max-width: 480px; background: var(--card-bg-color); border-radius: var(--border-radius); box-shadow: var(--shadow); border: 1px solid var(--border-color); backdrop-filter: var(--backdrop-blur); -webkit-backdrop-filter: var(--backdrop-blur); padding: 32px; transition: var(--transition); }
        
        /* --- 头部与主题切换器 --- */
        .header { text-align: center; margin-bottom: 25px; }
        .header h1 { font-size: 28px; color: var(--text-color); font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .header p { color: var(--text-color-light); font-size: 16px; margin-top: 5px; margin-bottom: 20px; }
        .theme-toggle { display: inline-flex; background-color: var(--input-bg-color); border-radius: 99px; padding: 4px; border: 1px solid var(--border-color); }
        .theme-toggle button { background: none; border: none; padding: 6px 16px; cursor: pointer; font-weight: 600; border-radius: 99px; color: var(--text-color-light); transition: var(--transition); }
        .theme-toggle button.active { background: var(--primary-color); color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .theme-toggle button:focus-visible, .btn:focus-visible, .btn-decrement:focus-visible, .btn-increment:focus-visible { outline: 2px solid var(--primary-color); outline-offset: 2px; }
        
        /* --- 密码显示与操作按钮 --- */
        #result { width: 100%; padding: 14px 16px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 20px; background: var(--input-bg-color); word-break: break-all; height: 80px; resize: none; overflow-y: auto; color: var(--text-color); transition: var(--transition); font-family: 'Courier New', Courier, monospace; display: flex; align-items: center; justify-content: center; text-align: center; }
        #result:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        body.dark-mode #result:focus { box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.3); }
        .password-actions { display: flex; gap: 12px; margin-top: 15px; }
        .btn { flex: 1; padding: 12px 15px; border-radius: 12px; font-weight: 600; font-size: 16px; cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn:active { transform: scale(0.97); }
        .btn-copy { background-color: var(--success-color); color: white; }
        .btn-copy:hover { background-color: var(--success-color-hover); transform: translateY(-2px); }
        .btn-generate { background-color: var(--primary-color); color: white; }
        .btn-generate:hover { background-color: var(--primary-color-hover); transform: translateY(-2px); }
        
        /* --- 自定义选项区域 --- */
        .options-section { margin-top: 30px; }
        .options-title { font-size: 18px; font-weight: 600; margin-bottom: 18px; color: var(--text-color); text-align: center; }
        .switch-container { display: flex; align-items: center; justify-content: space-between; background: var(--input-bg-color); border: 1px solid var(--border-color); border-radius: 12px; padding: 14px; margin-bottom: 12px; transition: var(--transition); }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch input:focus-visible + .slider { outline: 2px solid var(--primary-color); outline-offset: 2px; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--switch-bg-color); transition: .4s cubic-bezier(0.25, 0.8, 0.25, 1); border-radius: 26px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: var(--switch-handle-color); transition: .4s cubic-bezier(0.25, 0.8, 0.25, 1); border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: var(--primary-color); }
        input:checked + .slider:before { transform: translateX(24px); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 10px; font-size: 16px; text-align: center; }
        .length-control { display: flex; align-items: center; background: var(--input-bg-color); border: 1px solid var(--border-color); border-radius: 12px; padding: 4px; }
        .btn-decrement, .btn-increment { width: 44px; height: 44px; background: transparent; border: none; border-radius: 8px; font-size: 24px; font-weight: 400; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-color-light); transition: var(--transition); }
        .btn-decrement:hover, .btn-increment:hover { background: var(--switch-bg-color); color: var(--text-color); }
        .length-input { flex-grow: 1; text-align: center; font-weight: 700; font-size: 20px; border: none; background: transparent; color: var(--text-color); padding: 0 8px; }
        .length-input:focus { outline: none; }
        .length-input:focus-visible { outline: 2px solid var(--primary-color); outline-offset: 2px; }
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        
        /* --- 密码强度指示器 --- */
        .strength-meter { margin-top: 20px; border-radius: 12px; padding: 12px 15px; display: flex; align-items: center; background: var(--input-bg-color); border: 1px solid var(--border-color); }
        .strength-label { font-weight: 500; font-size: 14px; }
        .indicator { flex-grow: 1; height: 8px; background: var(--switch-bg-color); border-radius: 8px; overflow: hidden; margin: 0 12px; }
        .progress { height: 100%; border-radius: 8px; transition: width 0.4s ease, background-color 0.4s ease, box-shadow 0.4s ease; --strength-color: #e0e0e0; box-shadow: 0 0 8px 1px var(--strength-color); }
        
        /* --- 响应式设计 --- */
        @media (max-width: 500px) { .container { padding: 20px; } .header h1 { font-size: 24px; } .password-actions { flex-direction: column; } }
        
        /* --- 动画效果 --- */
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <!-- 主容器 -->
    <div class="container">
        
        <!-- 头部区域 -->
        <header class="header">
            <h1>安全密码生成器</h1>
            <p>快速创建高强度、可自定义的随机密码</p>
            <div class="theme-toggle">
                <button id="light-theme" type="button" aria-pressed="false">☀️ 浅色</button>
                <button id="dark-theme" type="button" aria-pressed="false">🌙 深色</button>
            </div>
        </header>
        
        <form id="passwordForm" method="post">
            <input type="hidden" name="form_submitted" value="1">
            
            <!-- 密码显示与操作区域 -->
            <div class="password-display">
                <textarea id="result" rows="1" readonly aria-label="生成的密码" placeholder="点击“生成”按钮创建密码"><?php echo escape_html($generated_password); ?></textarea>
                <div class="password-actions">
                    <button type="button" class="btn btn-copy" id="copyBtn" aria-live="polite">
                        <span class="state-default" style="display: inline-flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M19,21H8V7H19M19,5H8A2,2 0 0,0 6,7V21A2,2 0 0,0 8,23H19A2,2 0 0,0 21,21V7A2,2 0 0,0 19,5M16,1H4A2,2 0 0,0 2,3V17H4V3H16V1Z"/></svg>
                            <span>复制密码</span>
                        </span>
                        <span class="state-success" style="display: none; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/></svg>
                            <span>已复制!</span>
                        </span>
                    </button>
                    <button type="submit" class="btn btn-generate" id="generateBtn">
                        <span class="state-default" style="display: inline-flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z"/></svg>
                            <span>生成</span>
                        </span>
                        <span class="state-loading" style="display: none; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;"><path fill="currentColor" d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z"/></svg>
                            <span>生成中...</span>
                        </span>
                    </button>
                </div>
            </div>
            
            <!-- 选项表单区域 -->
            <div class="options-section">
                <h2 class="options-title">自定义选项</h2>
                <div class="form-group">
                    <label for="length">密码长度</label>
                    <div class="length-control">
                        <button class="btn-decrement" type="button" id="decrement" aria-label="减少密码长度">-</button>
                        <input type="number" id="length" name="length" class="length-input" min="8" max="50" value="<?php echo (int)$options['length']; ?>">
                        <button class="btn-increment" type="button" id="increment" aria-label="增加密码长度">+</button>
                    </div>
                </div>
                <div class="switch-container">
                    <label for="lowercase">小写字母 (a-z)</label>
                    <label class="switch">
                        <input type="checkbox" name="lowercase" id="lowercase" <?php if ($options['lowercase']) echo 'checked'; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-container">
                    <label for="uppercase">大写字母 (A-Z)</label>
                    <label class="switch">
                        <input type="checkbox" name="uppercase" id="uppercase" <?php if ($options['uppercase']) echo 'checked'; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-container">
                    <label for="numbers">数字 (0-9)</label>
                    <label class="switch">
                        <input type="checkbox" name="numbers" id="numbers" <?php if ($options['numbers']) echo 'checked'; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="switch-container">
                    <label for="symbols">特殊符号 (!@#...)</label>
                    <label class="switch">
                        <input type="checkbox" name="symbols" id="symbols" <?php if ($options['symbols']) echo 'checked'; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="strength-meter">
                    <span class="strength-label">密码强度:</span>
                    <div class="indicator"><div class="progress" id="strengthIndicator"></div></div>
                    <span class="strength-text" id="strengthText">-</span>
                </div>
            </div>
        </form>
    </div>

    <script>
        /**
         * 根据生成密码实际使用的字符池估算组合空间，并返回强度展示信息。
         *
         * 该估算仅用于界面提示，不参与密码生成或传输。字符池大小需与
         * generate_secure_password() 中过滤易混淆字符后的字符集保持一致。
         *
         * @param {string} password - 需要评估的密码
         * @returns {{percentage: number, color: string, text: string}}
         */
        function calculatePasswordStrength(password) {
            const emptyStrength = { percentage: 0, color: '#e0e0e0', text: '-' };
            if (typeof password !== 'string' || password === '' || password.startsWith('错误：')) {
                return emptyStrength;
            }

            // 小写字母移除 l（25），大写字母移除 O/I（24），数字移除 0/1（8）。
            const characterSets = [
                { pattern: /[a-z]/, size: 25 },
                { pattern: /[A-Z]/, size: 24 },
                { pattern: /[0-9]/, size: 8 },
                { pattern: /[^a-zA-Z0-9]/, size: 26 },
            ];
            const characterPoolSize = characterSets.reduce(
                (size, characterSet) => size + (characterSet.pattern.test(password) ? characterSet.size : 0),
                0
            );

            if (characterPoolSize === 0) {
                return emptyStrength;
            }

            const estimatedEntropy = password.length * Math.log2(characterPoolSize);
            let levelIndex;
            if (estimatedEntropy < 40) {
                levelIndex = 0;
            } else if (estimatedEntropy < 60) {
                levelIndex = 1;
            } else if (estimatedEntropy < 80) {
                levelIndex = 2;
            } else if (estimatedEntropy < 100) {
                levelIndex = 3;
            } else {
                levelIndex = 4;
            }

            // 短密码即使字符类型较多，也不应显示为过高等级。
            if (password.length < 10) {
                levelIndex = Math.min(levelIndex, 1);
            } else if (password.length < 12) {
                levelIndex = Math.min(levelIndex, 2);
            }

            const levels = [
                { percentage: 20, color: '#e74c3c', text: '很弱' },
                { percentage: 40, color: '#f39c12', text: '中等' },
                { percentage: 60, color: '#3498db', text: '强' },
                { percentage: 80, color: '#27ae60', text: '很强' },
                { percentage: 100, color: '#27ae60', text: '极强' },
            ];

            return levels[levelIndex];
        }

        /**
         * ====================================================================
         * 前端交互逻辑
         * ====================================================================
         */
        document.addEventListener('DOMContentLoaded', () => {
            // --- DOM元素获取 ---
            const getEl = (id) => document.getElementById(id);
            const passwordForm = getEl('passwordForm');
            const resultTextarea = getEl('result');
            const copyBtn = getEl('copyBtn');
            const generateBtn = getEl('generateBtn');
            const lengthInput = getEl('length');
            const decrementBtn = getEl('decrement');
            const incrementBtn = getEl('increment');
            const strengthIndicator = getEl('strengthIndicator');
            const strengthText = getEl('strengthText');
            const body = document.body;
            const lightThemeBtn = getEl('light-theme');
            const darkThemeBtn = getEl('dark-theme');
            const generateDefaultState = generateBtn.querySelector('.state-default');
            const generateLoadingState = generateBtn.querySelector('.state-loading');
            const copyDefaultState = copyBtn.querySelector('.state-default');
            const copySuccessState = copyBtn.querySelector('.state-success');
            const supportsAjaxGeneration = typeof fetch === 'function'
                && typeof FormData === 'function'
                && typeof URLSearchParams === 'function';
            let generationRequestId = 0;
            let generationController = null;
            let copyFeedbackTimer = null;

            // --- 主题管理 ---
            const getStoredTheme = () => {
                try {
                    return localStorage.getItem('theme');
                } catch (error) {
                    return null;
                }
            };
            const saveTheme = (theme) => {
                try {
                    localStorage.setItem('theme', theme);
                } catch (error) {
                    // 存储不可用时仍保持本次页面会话的主题设置。
                }
            };
            const setTheme = (theme, persist = true) => {
                const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
                const isDark = normalizedTheme === 'dark';
                body.classList.toggle('dark-mode', isDark);
                if (persist) {
                    saveTheme(normalizedTheme);
                }
                lightThemeBtn.classList.toggle('active', !isDark);
                darkThemeBtn.classList.toggle('active', isDark);
                lightThemeBtn.setAttribute('aria-pressed', String(!isDark));
                darkThemeBtn.setAttribute('aria-pressed', String(isDark));
            };
            lightThemeBtn.addEventListener('click', () => setTheme('light'));
            darkThemeBtn.addEventListener('click', () => setTheme('dark'));
            const storedTheme = getStoredTheme();
            setTheme(storedTheme === 'dark' ? 'dark' : 'light', false);

            // --- 核心功能函数 ---

            /**
             * 异步生成密码并更新UI
             */
            async function generatePassword() {
                const requestId = ++generationRequestId;

                if (generationController) {
                    generationController.abort();
                }
                const requestController = typeof AbortController === 'function'
                    ? new AbortController()
                    : null;
                generationController = requestController;
                
                // 切换到加载状态
                generateDefaultState.style.display = 'none';
                generateLoadingState.style.display = 'inline-flex';
                generateBtn.disabled = true;

                try {
                    // 发送 AJAX 请求到后端。请求体构造和 fetch 的同步异常也由本流程处理。
                    const requestBody = new URLSearchParams(new FormData(passwordForm));
                    const requestOptions = {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: requestBody,
                    };
                    if (requestController) {
                        requestOptions.signal = requestController.signal;
                    }
                    const response = await fetch('', requestOptions);

                    let data = null;
                    try {
                        data = await response.json();
                    } catch (error) {
                        data = null;
                    }

                    if (!response.ok) {
                        throw new Error(data && typeof data.error === 'string' ? data.error : `HTTP ${response.status}`);
                    }

                    if (requestId !== generationRequestId) {
                        return;
                    }

                    const hasValidPassword = data
                        && typeof data === 'object'
                        && !Array.isArray(data)
                        && typeof data.password === 'string'
                        && data.password.length >= 8
                        && data.password.length <= 50
                        && !data.password.startsWith('错误：');
                    if (!hasValidPassword) {
                        throw new Error('服务器返回了无效的密码数据');
                    }

                    renderGeneratedPassword(data.password);
                } catch (error) {
                    const isAbortError = error && typeof error === 'object' && error.name === 'AbortError';
                    if (isAbortError || requestId !== generationRequestId) {
                        return;
                    }
                    const errorMessage = error && typeof error.message === 'string' ? error.message : '';
                    renderGeneratedPassword(errorMessage.startsWith('错误：') ? errorMessage : '');
                    strengthText.textContent = errorMessage.startsWith('错误：') ? errorMessage : '生成失败';
                    console.error('密码生成失败:', error);
                } finally {
                    if (requestId !== generationRequestId) {
                        return;
                    }

                    if (generationController === requestController) {
                        generationController = null;
                    }

                    // 无论成功或失败，都恢复按钮的默认状态
                    generateDefaultState.style.display = 'inline-flex';
                    generateLoadingState.style.display = 'none';
                    generateBtn.disabled = false;
                }
            }

            /**
             * 根据密码评估其强度并更新UI指示器
             * @param {string} password - 需要评估的密码
             */
            function updateStrengthIndicator(password) {
                const strength = calculatePasswordStrength(password);
                strengthIndicator.style.width = `${strength.percentage}%`;
                strengthIndicator.style.backgroundColor = strength.color;
                strengthText.textContent = strength.text;
                strengthText.style.color = strength.color;
                strengthIndicator.style.setProperty('--strength-color', strength.color);
            }

            function renderGeneratedPassword(password) {
                resultTextarea.value = password;
                updateStrengthIndicator(password);
            }

            // --- 事件监听器绑定 ---

            function showCopySuccess() {
                if (copyFeedbackTimer !== null) {
                    window.clearTimeout(copyFeedbackTimer);
                }

                copyDefaultState.style.display = 'none';
                copySuccessState.style.display = 'inline-flex';
                copyFeedbackTimer = window.setTimeout(() => {
                    copyDefaultState.style.display = 'inline-flex';
                    copySuccessState.style.display = 'none';
                    copyFeedbackTimer = null;
                }, 2000);
            }

            function copyUsingSelection() {
                try {
                    resultTextarea.focus();
                    resultTextarea.select();
                    resultTextarea.setSelectionRange(0, resultTextarea.value.length);
                    return document.execCommand('copy');
                } catch (error) {
                    return false;
                }
            }

            async function copyPassword() {
                if (!resultTextarea.value || resultTextarea.value.startsWith('错误：')) {
                    return;
                }

                let copied = false;
                try {
                    const clipboard = typeof navigator === 'object' ? navigator.clipboard : null;
                    if (clipboard && typeof clipboard.writeText === 'function') {
                        await clipboard.writeText(resultTextarea.value);
                        copied = true;
                    }
                } catch (error) {
                    copied = false;
                }

                if (!copied) {
                    copied = copyUsingSelection();
                }

                if (copied) {
                    showCopySuccess();
                } else {
                    console.error('无法复制密码，请手动选择复制。');
                }
            }

            // 复制按钮
            copyBtn.addEventListener('click', () => {
                void copyPassword();
            });
            
            // 表单提交（有 JS 时使用 AJAX）
            passwordForm.addEventListener('submit', (e) => {
                if (!supportsAjaxGeneration) {
                    return;
                }

                e.preventDefault();
                void generatePassword();
            });

            function requestPasswordGeneration() {
                if (supportsAjaxGeneration) {
                    void generatePassword();
                }
            }

            function normalizeLengthInput() {
                let min = Number.parseInt(lengthInput.min, 10);
                let max = Number.parseInt(lengthInput.max, 10);
                if (Number.isNaN(min) || Number.isNaN(max) || max < min) {
                    min = 8;
                    max = 50;
                }

                const parsedLength = Number.parseInt(lengthInput.value, 10);
                const requestedLength = Number.isNaN(parsedLength) ? min : parsedLength;
                const normalizedLength = Math.min(max, Math.max(min, requestedLength));
                lengthInput.value = String(normalizedLength);

                return { value: normalizedLength, min, max };
            }

            // 长度控制器
            decrementBtn.addEventListener('click', () => {
                const length = normalizeLengthInput();
                if (length.value > length.min) {
                    lengthInput.value = String(length.value - 1);
                    requestPasswordGeneration();
                }
            });
            incrementBtn.addEventListener('click', () => {
                const length = normalizeLengthInput();
                if (length.value < length.max) {
                    lengthInput.value = String(length.value + 1);
                    requestPasswordGeneration();
                }
            });
            lengthInput.addEventListener('change', () => {
                normalizeLengthInput();
                requestPasswordGeneration();
            });
            
            // 字符类型选项
            ['lowercase', 'uppercase', 'numbers', 'symbols'].forEach(option => {
                getEl(option).addEventListener('change', requestPasswordGeneration);
            });
            
            // --- 初始化 ---
            // 如果页面加载时已有密码（例如，在非JS环境下提交表单后），则更新强度指示器
            if (resultTextarea.value && resultTextarea.value !== '') {
                updateStrengthIndicator(resultTextarea.value);
            }
        });
    </script>
</body>
</html>
