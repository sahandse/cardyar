<?php
/**
 * Plugin Name: کارت‌یار
 * Plugin URI: https://github.com/sahandse/cardyar
 * Description: افزونه پرداخت کارت‌به‌کارت برای وردپرس و ووکامرس با ثبت رسید، شماره مرجع، مدیریت وضعیت و رابط کاربری فارسی.
 * Version: 1.1.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: cardyar
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class Cardyar_Plugin {
    const VERSION = '1.1.0';
    const OPTION  = 'cardyar_settings';
    const CPT     = 'cardyar_payment';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('cardyar_payment', [$this, 'shortcode']);
        add_action('admin_post_nopriv_cardyar_submit', [$this, 'submit_payment']);
        add_action('admin_post_cardyar_submit', [$this, 'submit_payment']);
        add_filter('manage_'.self::CPT.'_posts_columns', [$this, 'columns']);
        add_action('manage_'.self::CPT.'_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_action('admin_post_cardyar_status', [$this, 'change_status']);
    }

    public function defaults() {
        return [
            'card_holder' => '',
            'card_number' => '',
            'bank_name' => '',
            'accent' => '#111827',
            'success_message' => 'رسید شما ثبت شد و در انتظار بررسی است.',
            'max_upload_mb' => 5,
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
            'max_upload_mb' => min(10,max(1,absint($in['max_upload_mb'] ?? 5))),
        ];
    }

    public function admin_menu() {
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('cardyar', 'کارت‌یار', [$this, 'settings_page'], 'manage_options', 'کارت‌یار');
        add_submenu_page('s-store','پرداخت‌های کارت‌یار','↳ پرداخت‌های کارت‌یار','manage_options','edit.php?post_type=' . self::CPT);
            return;
        }
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
                        <h2>آپلود رسید</h2>
                        <label>حداکثر حجم فایل (MB)
                            <input type="number" min="1" max="10" name="<?php echo self::OPTION; ?>[max_upload_mb]" value="<?php echo esc_attr($s['max_upload_mb']); ?>">
                        </label>
                        <p>رسیدها در Media Library ذخیره می‌شوند؛ شماره مرجع تکراری پذیرفته نمی‌شود و مدیر می‌تواند پرداخت را تأیید یا رد کند.</p>
                    </section>
                </div>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    public function columns($cols) {
        return [
            'cb'=>$cols['cb']??'<input type="checkbox" />',
            'title'=>'پرداخت',
            'cardyar_ref'=>'شماره مرجع',
            'cardyar_amount'=>'مبلغ',
            'cardyar_status'=>'وضعیت',
            'cardyar_receipt'=>'رسید',
            'date'=>'تاریخ',
        ];
    }

    public function column_content($col,$post_id) {
        if('cardyar_ref'===$col) echo esc_html(get_post_meta($post_id,'_cardyar_ref',true));
        if('cardyar_amount'===$col) echo esc_html(number_format_i18n((float)get_post_meta($post_id,'_cardyar_amount',true)));
        if('cardyar_status'===$col){
            $st=get_post_meta($post_id,'_cardyar_status',true)?:'pending';
            echo esc_html(['pending'=>'در انتظار','approved'=>'تأیید شده','rejected'=>'رد شده'][$st]??$st);
            if(current_user_can('manage_options')){
                foreach(['approved'=>'تأیید','rejected'=>'رد'] as $key=>$label){
                    $url=wp_nonce_url(admin_url('admin-post.php?action=cardyar_status&payment='.$post_id.'&status='.$key),'cardyar_status_'.$post_id);
                    echo ' <a href="'.esc_url($url).'">'.esc_html($label).'</a>';
                }
            }
        }
        if('cardyar_receipt'===$col){
            $id=(int)get_post_meta($post_id,'_cardyar_receipt_id',true);
            if($id) echo wp_get_attachment_image($id,[60,60],false,['style'=>'border-radius:8px']);
        }
    }

    public function change_status() {
        if(!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
        $id=absint($_GET['payment']??0); check_admin_referer('cardyar_status_'.$id);
        $status=sanitize_key($_GET['status']??'');
        if(!in_array($status,['approved','rejected'],true)) wp_die('وضعیت نامعتبر');
        update_post_meta($id,'_cardyar_status',$status);
        wp_safe_redirect(admin_url('edit.php?post_type='.self::CPT)); exit;
    }

    public function submit_payment() {
        if(!isset($_POST['cardyar_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cardyar_nonce'])),'cardyar_submit')) wp_die('درخواست نامعتبر');
        $s=$this->settings();
        $name=sanitize_text_field(wp_unslash($_POST['name']??''));
        $phone=preg_replace('/[^0-9+]/','',wp_unslash($_POST['phone']??''));
        $ref=sanitize_text_field(wp_unslash($_POST['reference']??''));
        $amount=(float)wc_format_decimal(wp_unslash($_POST['amount']??''));
        if(!$name||!$phone||!$ref||$amount<=0) wp_die('اطلاعات ناقص است');

        $dup=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>1,'meta_key'=>'_cardyar_ref','meta_value'=>$ref]);
        if($dup) wp_die('این شماره مرجع قبلاً ثبت شده است.');

        $attachment_id=0;
        if(!empty($_FILES['receipt']['name'])){
            if((int)$_FILES['receipt']['size'] > ((int)$s['max_upload_mb']*1024*1024)) wp_die('حجم فایل بیش از حد مجاز است.');
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
            $attachment_id=media_handle_upload('receipt',0);
            if(is_wp_error($attachment_id)) wp_die(esc_html($attachment_id->get_error_message()));
            $mime=get_post_mime_type($attachment_id);
            if(0!==strpos((string)$mime,'image/')){ wp_delete_attachment($attachment_id,true); wp_die('فقط تصویر رسید قابل قبول است.'); }
        } else wp_die('تصویر رسید الزامی است.');

        $id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'publish','post_title'=>$name.' - '.$ref]);
        if(!$id) wp_die('ثبت پرداخت ناموفق بود.');
        update_post_meta($id,'_cardyar_name',$name); update_post_meta($id,'_cardyar_phone',$phone);
        update_post_meta($id,'_cardyar_ref',$ref); update_post_meta($id,'_cardyar_amount',$amount);
        update_post_meta($id,'_cardyar_receipt_id',$attachment_id); update_post_meta($id,'_cardyar_status','pending');

        wp_safe_redirect(add_query_arg('cardyar_success','1',wp_get_referer()?:home_url('/'))); exit;
    }

    public function shortcode() {
        $s=$this->settings();
        ob_start(); ?>
        <div class="cardyar-box" style="--cardyar-accent:<?php echo esc_attr($s['accent']); ?>">
            <h3>پرداخت کارت‌به‌کارت</h3>
            <?php if(isset($_GET['cardyar_success'])):?><div class="cardyar-success"><?php echo esc_html($s['success_message']); ?></div><?php endif; ?>
            <?php if($s['bank_name']): ?><p><strong>بانک:</strong> <?php echo esc_html($s['bank_name']); ?></p><?php endif; ?>
            <?php if($s['card_holder']): ?><p><strong>به نام:</strong> <?php echo esc_html($s['card_holder']); ?></p><?php endif; ?>
            <?php if($s['card_number']): ?><p class="cardyar-number"><?php echo esc_html(chunk_split($s['card_number'],4,' ')); ?></p><?php endif; ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="cardyar_submit"><?php wp_nonce_field('cardyar_submit','cardyar_nonce'); ?>
              <label>نام<input type="text" name="name" required></label>
              <label>موبایل<input type="tel" name="phone" required></label>
              <label>مبلغ<input type="number" min="1" name="amount" required></label>
              <label>شماره مرجع<input type="text" name="reference" required></label>
              <label>تصویر رسید<input type="file" name="receipt" accept="image/*" required></label>
              <button type="submit">ثبت رسید</button>
            </form>
        </div>
        <?php return ob_get_clean();
    }

}

new Cardyar_Plugin();
