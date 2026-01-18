<?php
/**
 * Plugin Name: N8N Chat Assistant
 * Description: Chat assistant plugin that connects WordPress to n8n with Q&A RAG and conversation logging.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: n8n-chat-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class N8N_Chat_Assistant {
    private const OPTION_NAME = 'n8n_chat_assistant_settings';
    private const QA_CPT = 'n8n_qa';
    private const CONVO_CPT = 'n8n_conversation';

    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode('n8n_chat', [$this, 'render_shortcode']);
    }

    public function register_post_types(): void {
        register_post_type(self::QA_CPT, [
            'labels' => [
                'name' => __('Q&A', 'n8n-chat-assistant'),
                'singular_name' => __('Q&A', 'n8n-chat-assistant'),
                'add_new_item' => __('Add Q&A', 'n8n-chat-assistant'),
                'edit_item' => __('Edit Q&A', 'n8n-chat-assistant'),
            ],
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-editor-help',
            'supports' => ['title'],
        ]);

        register_post_type(self::CONVO_CPT, [
            'labels' => [
                'name' => __('Conversaciones', 'n8n-chat-assistant'),
                'singular_name' => __('Conversación', 'n8n-chat-assistant'),
                'add_new_item' => __('Add Conversation', 'n8n-chat-assistant'),
                'edit_item' => __('Edit Conversation', 'n8n-chat-assistant'),
            ],
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-format-chat',
            'supports' => ['title'],
        ]);
    }

    public function register_meta_boxes(): void {
        add_meta_box('n8n_qa_fields', __('Q&A Fields', 'n8n-chat-assistant'), [$this, 'render_qa_meta_box'], self::QA_CPT, 'normal', 'default');
        add_meta_box('n8n_convo_fields', __('Conversation Details', 'n8n-chat-assistant'), [$this, 'render_convo_meta_box'], self::CONVO_CPT, 'normal', 'default');
    }

    public function render_qa_meta_box($post): void {
        wp_nonce_field('n8n_qa_meta_box', 'n8n_qa_meta_box_nonce');
        $question = get_post_meta($post->ID, '_n8n_question', true);
        $answer = get_post_meta($post->ID, '_n8n_answer', true);
        $tags = get_post_meta($post->ID, '_n8n_tags', true);
        ?>
        <p>
            <label for="n8n_question"><strong><?php esc_html_e('Question', 'n8n-chat-assistant'); ?></strong></label>
            <textarea name="n8n_question" id="n8n_question" class="widefat" rows="3"><?php echo esc_textarea($question); ?></textarea>
        </p>
        <p>
            <label for="n8n_answer"><strong><?php esc_html_e('Answer', 'n8n-chat-assistant'); ?></strong></label>
            <textarea name="n8n_answer" id="n8n_answer" class="widefat" rows="5"><?php echo esc_textarea($answer); ?></textarea>
        </p>
        <p>
            <label for="n8n_tags"><strong><?php esc_html_e('Tags (comma separated)', 'n8n-chat-assistant'); ?></strong></label>
            <input type="text" name="n8n_tags" id="n8n_tags" class="widefat" value="<?php echo esc_attr($tags); ?>" />
        </p>
        <?php
    }

    public function render_convo_meta_box($post): void {
        $meta = get_post_meta($post->ID);
        $fields = [
            'user_name' => __('User Name', 'n8n-chat-assistant'),
            'user_email' => __('User Email', 'n8n-chat-assistant'),
            'user_phone' => __('User Phone', 'n8n-chat-assistant'),
            'ip' => __('IP Address', 'n8n-chat-assistant'),
            'country' => __('Country', 'n8n-chat-assistant'),
            'user_agent' => __('User Agent', 'n8n-chat-assistant'),
            'browser' => __('Browser', 'n8n-chat-assistant'),
            'platform' => __('Platform', 'n8n-chat-assistant'),
            'language' => __('Language', 'n8n-chat-assistant'),
            'created_at' => __('Created At', 'n8n-chat-assistant'),
        ];
        echo '<table class="widefat striped">';
        foreach ($fields as $key => $label) {
            $value = isset($meta["_n8n_{$key}"][0]) ? $meta["_n8n_{$key}"][0] : '';
            echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        $messages = get_post_meta($post->ID, '_n8n_messages', true);
        echo '</table>';
        echo '<h4>' . esc_html__('Messages', 'n8n-chat-assistant') . '</h4>';
        echo '<pre style="white-space: pre-wrap;">' . esc_html($messages ? wp_json_encode($messages, JSON_PRETTY_PRINT) : '') . '</pre>';
    }

    public function save_meta_boxes(int $post_id): void {
        if (self::QA_CPT !== get_post_type($post_id)) {
            return;
        }
        if (!isset($_POST['n8n_qa_meta_box_nonce']) || !wp_verify_nonce($_POST['n8n_qa_meta_box_nonce'], 'n8n_qa_meta_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $question = isset($_POST['n8n_question']) ? sanitize_textarea_field(wp_unslash($_POST['n8n_question'])) : '';
        $answer = isset($_POST['n8n_answer']) ? sanitize_textarea_field(wp_unslash($_POST['n8n_answer'])) : '';
        $tags = isset($_POST['n8n_tags']) ? sanitize_text_field(wp_unslash($_POST['n8n_tags'])) : '';

        update_post_meta($post_id, '_n8n_question', $question);
        update_post_meta($post_id, '_n8n_answer', $answer);
        update_post_meta($post_id, '_n8n_tags', $tags);
    }

    public function register_settings_page(): void {
        add_options_page(
            __('N8N Chat Assistant', 'n8n-chat-assistant'),
            __('N8N Chat Assistant', 'n8n-chat-assistant'),
            'manage_options',
            'n8n-chat-assistant',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting('n8n_chat_assistant', self::OPTION_NAME, [$this, 'sanitize_settings']);

        add_settings_section('n8n_chat_assistant_main', __('Connection Settings', 'n8n-chat-assistant'), null, 'n8n-chat-assistant');

        add_settings_field('webhook_url', __('n8n Webhook URL', 'n8n-chat-assistant'), [$this, 'render_text_field'], 'n8n-chat-assistant', 'n8n_chat_assistant_main', [
            'label_for' => 'webhook_url',
            'option_key' => 'webhook_url',
            'placeholder' => 'https://your-n8n-domain/webhook/...'
        ]);

        add_settings_field('webhook_token', __('Webhook Token', 'n8n-chat-assistant'), [$this, 'render_text_field'], 'n8n-chat-assistant', 'n8n_chat_assistant_main', [
            'label_for' => 'webhook_token',
            'option_key' => 'webhook_token',
            'placeholder' => __('Optional shared secret', 'n8n-chat-assistant')
        ]);

        add_settings_field('qa_token', __('Q&A API Token', 'n8n-chat-assistant'), [$this, 'render_text_field'], 'n8n-chat-assistant', 'n8n_chat_assistant_main', [
            'label_for' => 'qa_token',
            'option_key' => 'qa_token',
            'placeholder' => __('Token for n8n to fetch Q&A', 'n8n-chat-assistant')
        ]);
    }

    public function sanitize_settings(array $input): array {
        return [
            'webhook_url' => isset($input['webhook_url']) ? esc_url_raw($input['webhook_url']) : '',
            'webhook_token' => isset($input['webhook_token']) ? sanitize_text_field($input['webhook_token']) : '',
            'qa_token' => isset($input['qa_token']) ? sanitize_text_field($input['qa_token']) : '',
        ];
    }

    public function render_text_field(array $args): void {
        $options = get_option(self::OPTION_NAME, []);
        $key = $args['option_key'];
        $value = isset($options[$key]) ? $options[$key] : '';
        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" placeholder="%4$s" />',
            esc_attr($key),
            esc_attr(self::OPTION_NAME),
            esc_attr($value),
            esc_attr($args['placeholder'])
        );
    }

    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('N8N Chat Assistant', 'n8n-chat-assistant'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('n8n_chat_assistant');
                do_settings_sections('n8n-chat-assistant');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_rest_routes(): void {
        register_rest_route('n8n-chat/v1', '/message', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('n8n-chat/v1', '/qa', [
            'methods' => 'GET',
            'callback' => [$this, 'get_qa_data'],
            'permission_callback' => [$this, 'validate_qa_token'],
        ]);
    }

    public function register_assets(): void {
        wp_register_style('n8n-chat-assistant', plugin_dir_url(__FILE__) . 'assets/dist/chat.css', [], '1.0.0');
        wp_register_script('n8n-chat-gsap', plugin_dir_url(__FILE__) . 'assets/vendor/gsap.min.js', [], '3.12.2', true);
        wp_register_script('n8n-chat-assistant', plugin_dir_url(__FILE__) . 'assets/dist/chat.js', ['n8n-chat-gsap'], '1.0.0', true);
    }

    public function render_shortcode(array $atts): string {
        $atts = shortcode_atts([
            'lang' => '',
        ], $atts, 'n8n_chat');

        wp_enqueue_style('n8n-chat-assistant');
        wp_enqueue_script('n8n-chat-gsap');
        wp_enqueue_script('n8n-chat-assistant');

        $settings = get_option(self::OPTION_NAME, []);
        $data = [
            'endpoint' => rest_url('n8n-chat/v1/message'),
            'lang' => $atts['lang'],
            'nonce' => wp_create_nonce('wp_rest'),
        ];
        wp_localize_script('n8n-chat-assistant', 'N8NChatSettings', $data);

        ob_start();
        ?>
        <div class="n8n-chat-widget" data-lang="<?php echo esc_attr($atts['lang']); ?>">
            <button class="n8n-chat-toggle" type="button" aria-expanded="false">
                <span class="n8n-chat-toggle__icon">💬</span>
                <span class="n8n-chat-toggle__text"><?php esc_html_e('Chat', 'n8n-chat-assistant'); ?></span>
            </button>
            <div class="n8n-chat-panel" aria-hidden="true">
                <div class="n8n-chat-header">
                    <div>
                        <strong class="n8n-chat-title"></strong>
                        <p class="n8n-chat-subtitle"></p>
                    </div>
                    <button class="n8n-chat-close" type="button">×</button>
                </div>
                <div class="n8n-chat-messages"></div>
                <form class="n8n-chat-form">
                    <input type="text" class="n8n-chat-input" placeholder="" required />
                    <button type="submit" class="n8n-chat-send"></button>
                </form>
                <div class="n8n-chat-meta">
                    <input type="text" class="n8n-chat-name" placeholder="" />
                    <input type="email" class="n8n-chat-email" placeholder="" />
                    <input type="tel" class="n8n-chat-phone" placeholder="" />
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_message(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $message = isset($params['message']) ? sanitize_text_field($params['message']) : '';
        $conversation_id = isset($params['conversation_id']) ? absint($params['conversation_id']) : 0;
        $user = isset($params['user']) ? (array) $params['user'] : [];
        $client = isset($params['client']) ? (array) $params['client'] : [];

        if ('' === $message) {
            return new WP_REST_Response(['error' => __('Message is required.', 'n8n-chat-assistant')], 400);
        }

        $conversation_id = $this->upsert_conversation($conversation_id, $user, $client);
        $messages = get_post_meta($conversation_id, '_n8n_messages', true);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = [
            'role' => 'user',
            'content' => $message,
            'timestamp' => current_time('mysql'),
        ];

        update_post_meta($conversation_id, '_n8n_messages', $messages);

        $response_text = $this->send_to_n8n($message, $conversation_id, $user, $client, $messages);
        $messages[] = [
            'role' => 'assistant',
            'content' => $response_text,
            'timestamp' => current_time('mysql'),
        ];
        update_post_meta($conversation_id, '_n8n_messages', $messages);

        return new WP_REST_Response([
            'conversation_id' => $conversation_id,
            'message' => $response_text,
        ], 200);
    }

    private function upsert_conversation(int $conversation_id, array $user, array $client): int {
        $post_id = $conversation_id;
        if (!$post_id || get_post_type($post_id) !== self::CONVO_CPT) {
            $post_id = wp_insert_post([
                'post_type' => self::CONVO_CPT,
                'post_status' => 'publish',
                'post_title' => sprintf(__('Conversation %s', 'n8n-chat-assistant'), current_time('Y-m-d H:i')),
            ]);
            update_post_meta($post_id, '_n8n_created_at', current_time('mysql'));
        }

        $meta_map = [
            'user_name' => $user['name'] ?? '',
            'user_email' => $user['email'] ?? '',
            'user_phone' => $user['phone'] ?? '',
            'ip' => $client['ip'] ?? $this->get_client_ip(),
            'country' => $client['country'] ?? $this->get_country_from_ip($client['ip'] ?? ''),
            'user_agent' => $client['user_agent'] ?? '',
            'browser' => $client['browser'] ?? '',
            'platform' => $client['platform'] ?? '',
            'language' => $client['language'] ?? '',
        ];

        foreach ($meta_map as $key => $value) {
            if ($value !== '') {
                update_post_meta($post_id, "_n8n_{$key}", sanitize_text_field($value));
            }
        }

        return (int) $post_id;
    }

    private function send_to_n8n(string $message, int $conversation_id, array $user, array $client, array $messages): string {
        $settings = get_option(self::OPTION_NAME, []);
        $webhook_url = isset($settings['webhook_url']) ? $settings['webhook_url'] : '';
        if (!$webhook_url) {
            return __('Webhook URL not configured.', 'n8n-chat-assistant');
        }

        $payload = [
            'message' => $message,
            'conversation_id' => $conversation_id,
            'user' => $user,
            'client' => $client,
            'messages' => $messages,
            'site' => [
                'url' => home_url(),
                'name' => get_bloginfo('name'),
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];
        if (!empty($settings['webhook_token'])) {
            $headers['X-N8N-Token'] = $settings['webhook_token'];
        }

        $response = wp_remote_post($webhook_url, [
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return __('Unable to reach assistant. Please try again.', 'n8n-chat-assistant');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['reply'])) {
            return sanitize_text_field($data['reply']);
        }

        return __('Thanks! We will get back to you shortly.', 'n8n-chat-assistant');
    }

    public function get_qa_data(): WP_REST_Response {
        $items = get_posts([
            'post_type' => self::QA_CPT,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'id' => $item->ID,
                'title' => $item->post_title,
                'question' => get_post_meta($item->ID, '_n8n_question', true),
                'answer' => get_post_meta($item->ID, '_n8n_answer', true),
                'tags' => get_post_meta($item->ID, '_n8n_tags', true),
            ];
        }

        return new WP_REST_Response(['items' => $data], 200);
    }

    public function validate_qa_token(WP_REST_Request $request): bool {
        $settings = get_option(self::OPTION_NAME, []);
        $expected = $settings['qa_token'] ?? '';
        if (!$expected) {
            return false;
        }
        $token = $request->get_header('x-n8n-token');
        return hash_equals($expected, (string) $token);
    }

    private function get_client_ip(): string {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                return sanitize_text_field(wp_unslash($_SERVER[$key]));
            }
        }
        return '';
    }

    private function get_country_from_ip(string $ip): string {
        if (!$ip) {
            return '';
        }
        $response = wp_remote_get('https://ipapi.co/' . rawurlencode($ip) . '/country_name/');
        if (is_wp_error($response)) {
            return '';
        }
        $body = trim(wp_remote_retrieve_body($response));
        return sanitize_text_field($body);
    }
}

new N8N_Chat_Assistant();
