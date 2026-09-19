<?php
/**
 * Plugin Name: کارت‌یار
 * Plugin URI: https://github.com/sahandse/cardyar
 * Description: افزونه پرداخت کارت‌به‌کارت برای وردپرس و ووکامرس با ثبت رسید، شماره مرجع، مدیریت وضعیت و رابط کاربری فارسی.
 * Version: 1.0.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: cardyar
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class Cardyar_Plugin {
    const VERSION = '1.0.0';
    const OPTION  = 'cardyar_settings';
    const CPT     = 'cardyar_payment';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('cardyar_payment', [$this, 'shortcode']);
    }

    public function defaults() {
        return [
            'card_holder' => '',
            'card_number' => '',
            'bank_name' => '',
            'accent' => '#111827',
            'success_message' => 'رسید شما ثبت شد و در انتظار بررسی است.',
        ];
    }

    public function settings() {
        return wp_parse_args((array)get_option(self::OPTION, []), $this->defaults());
    }

    public function register_cpt() {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'پرداخت‌های کارت‌یار',
                'singular_name' => 'پرداخت کارت‌یار',
                'add_new_item' => 'افزودن پرداخت',
                'edit_item' => 'ویرایش پرداخت',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }

    public function register_settings() {
        register_setting('cardyar_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();
        return [
            'card_holder' => sanitize_text_field($in['card_holder'] ?? ''),
            'card_number' => preg_replace('/\D+/', '', $in['card_number'] ?? ''),
            'bank_name' => sanitize_text_field($in['bank_name'] ?? ''),
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'success_message' => sanitize_text_field($in['success_message'] ?? $d['success_message']),
        ];
    }

    public function admin_menu() {
        add_menu_page(
            'کارت‌یار',
            'کارت‌یار',
            'manage_options',
            'cardyar',
            [$this, 'settings_page'],
            'dashicons-money-alt',
            57
        );

        add_submenu_page(
            'cardyar',
            'پرداخت‌ها',
            'پرداخت‌ها',
            'manage_options',
            'edit.php?post_type=' . self::CPT
        );
    }

    public function admin_assets($hook) {
        if (false === strpos($hook, 'cardyar')) return;
        wp_enqueue_style('cardyar-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = $this->settings();
        ?>
        <div class="wrap cardyar-admin">
            <div class="cardyar-hero">
                <div>
                    <h1>کارت‌یار</h1>
                    <p>مدیریت پرداخت کارت‌به‌کارت، اطلاعات کارت و بررسی رسیدها.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('cardyar_group'); ?>
                <div class="cardyar-grid">
                    <section class="cardyar-card">
                        <h2>اطلاعات کارت</h2>
                        <label>نام صاحب کارت
                            <input type="text" name="<?php echo self::OPTION; ?>[card_holder]" value="<?php echo esc_attr($s['card_holder']); ?>">
                        </label>
                        <label>شماره کارت
                            <input type="text" inputmode="numeric" maxlength="16" name="<?php echo self::OPTION; ?>[card_number]" value="<?php echo esc_attr($s['card_number']); ?>">
                        </label>
                        <label>نام بانک
                            <input type="text" name="<?php echo self::OPTION; ?>[bank_name]" value="<?php echo esc_attr($s['bank_name']); ?>">
                        </label>
                    </section>

                    <section class="cardyar-card">
                        <h2>ظاهر</h2>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                        <label>پیام ثبت موفق
                            <input type="text" name="<?php echo self::OPTION; ?>[success_message]" value="<?php echo esc_attr($s['success_message']); ?>">
                        </label>
                    </section>

                    <section class="cardyar-card">
                        <h2>شورت‌کد</h2>
                        <code>[cardyar_payment]</code>
                        <p>فرم پایه پرداخت کارت‌به‌کارت با این شورت‌کد در برگه‌ها نمایش داده می‌شود.</p>
                    </section>

                    <section class="cardyar-card">
                        <h2>وضعیت توسعه</h2>
                        <p>ساختار Repo، مدیریت پرداخت‌ها و تنظیمات اصلی آماده است. اتصال رسید، تأیید/رد، SMS و WooCommerce در نسخه‌های بعدی همین افزونه توسعه داده می‌شود.</p>
                    </section>
                </div>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    public function shortcode() {
        $s = $this->settings();
        ob_start();
        ?>
        <div class="cardyar-box" style="--cardyar-accent:<?php echo esc_attr($s['accent']); ?>">
            <h3>پرداخت کارت‌به‌کارت</h3>
            <?php if ($s['bank_name']) : ?>
                <p><strong>بانک:</strong> <?php echo esc_html($s['bank_name']); ?></p>
            <?php endif; ?>
            <?php if ($s['card_holder']) : ?>
                <p><strong>به نام:</strong> <?php echo esc_html($s['card_holder']); ?></p>
            <?php endif; ?>
            <?php if ($s['card_number']) : ?>
                <p class="cardyar-number"><?php echo esc_html(chunk_split($s['card_number'], 4, ' ')); ?></p>
            <?php endif; ?>
            <p>فرم بارگذاری رسید و ثبت شماره مرجع در نسخه کامل همین افزونه فعال می‌شود.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Cardyar_Plugin();
