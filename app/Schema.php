<?php
declare(strict_types=1);

/**
 * ساختار دیتابیس — در زمان نصب اجرا می‌شود و در آپدیت‌ها هم به صورت idempotent
 * (IF NOT EXISTS) مجدد اجرا می‌شود تا هیچ‌وقت خراب نشود.
 */
final class Schema
{
    public const VERSION = '1.6.1';

    public static function statements(): array
    {
        return [
            // ---------- ادمین‌های پنل ----------
            "CREATE TABLE IF NOT EXISTS panel_admins (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                bale_user_id BIGINT UNSIGNED NULL,
                full_name VARCHAR(120) NULL,
                role ENUM('super','admin') NOT NULL DEFAULT 'admin',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                must_change_password TINYINT(1) NOT NULL DEFAULT 0,
                last_login_at INT UNSIGNED NULL,
                last_login_ip VARCHAR(64) NULL,
                failed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                locked_until INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- رمز یکبار مصرف / لاگین ----------
            "CREATE TABLE IF NOT EXISTS login_otps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id INT UNSIGNED NOT NULL,
                code_hash CHAR(64) NOT NULL,
                token_hash CHAR(64) NULL,
                channel ENUM('group','private') NOT NULL DEFAULT 'group',
                chat_id BIGINT NULL,
                message_id BIGINT NULL,
                expires_at INT UNSIGNED NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                used_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                ip VARCHAR(64) NULL,
                KEY idx_admin (admin_id, expires_at),
                CONSTRAINT fk_otp_admin FOREIGN KEY (admin_id) REFERENCES panel_admins(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS panel_sessions (
                id CHAR(64) PRIMARY KEY,
                admin_id INT UNSIGNED NOT NULL,
                ip VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                last_seen_at INT UNSIGNED NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                KEY idx_admin (admin_id),
                CONSTRAINT fk_sess_admin FOREIGN KEY (admin_id) REFERENCES panel_admins(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS login_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NULL,
                ip VARCHAR(64) NULL,
                kind VARCHAR(24) NOT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_ip (ip, created_at),
                KEY idx_kind (kind, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- تنظیمات گروه ----------
            "CREATE TABLE IF NOT EXISTS chat_settings (
                chat_id BIGINT PRIMARY KEY,
                title VARCHAR(255) NULL,
                chat_type VARCHAR(32) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                anti_link TINYINT(1) NOT NULL DEFAULT 1,
                anti_spam TINYINT(1) NOT NULL DEFAULT 1,
                spam_window_sec INT UNSIGNED NOT NULL DEFAULT 8,
                spam_max_msgs INT UNSIGNED NOT NULL DEFAULT 6,
                bad_words TINYINT(1) NOT NULL DEFAULT 1,
                anti_bot TINYINT(1) NOT NULL DEFAULT 0,
                anti_forward TINYINT(1) NOT NULL DEFAULT 0,
                anti_username TINYINT(1) NOT NULL DEFAULT 1,
                anti_flood_action ENUM('warn','delete','mute') NOT NULL DEFAULT 'mute',
                flood_mute_sec INT UNSIGNED NOT NULL DEFAULT 300,
                warn_limit TINYINT UNSIGNED NOT NULL DEFAULT 3,
                warn_action ENUM('mute','kick','ban') NOT NULL DEFAULT 'mute',
                warn_mute_sec INT UNSIGNED NOT NULL DEFAULT 3600,
                welcome_on TINYINT(1) NOT NULL DEFAULT 1,
                welcome_text TEXT NULL,
                welcome_buttons VARCHAR(500) NULL,
                goodbye_on TINYINT(1) NOT NULL DEFAULT 0,
                goodbye_text TEXT NULL,
                rules TEXT NULL,
                lock_link TINYINT(1) NOT NULL DEFAULT 0,
                lock_photo TINYINT(1) NOT NULL DEFAULT 0,
                lock_video TINYINT(1) NOT NULL DEFAULT 0,
                lock_gif TINYINT(1) NOT NULL DEFAULT 0,
                lock_sticker TINYINT(1) NOT NULL DEFAULT 0,
                lock_voice TINYINT(1) NOT NULL DEFAULT 0,
                lock_document TINYINT(1) NOT NULL DEFAULT 0,
                lock_forward TINYINT(1) NOT NULL DEFAULT 0,
                lock_contact TINYINT(1) NOT NULL DEFAULT 0,
                lock_poll TINYINT(1) NOT NULL DEFAULT 0,
                lock_location TINYINT(1) NOT NULL DEFAULT 0,
                lock_bot TINYINT(1) NOT NULL DEFAULT 0,
                captcha_on TINYINT(1) NOT NULL DEFAULT 0,
                captcha_timeout_sec INT UNSIGNED NOT NULL DEFAULT 90,
                log_channel_id BIGINT NULL,
                report_channel_id BIGINT NULL,
                lang VARCHAR(8) NOT NULL DEFAULT 'fa',
                created_at INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS warns (
                chat_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                updated_at INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (chat_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS mutes (
                chat_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                until_ts INT UNSIGNED NOT NULL DEFAULT 0,
                reason VARCHAR(255) NULL,
                by_admin_id BIGINT NULL,
                created_at INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (chat_id, user_id),
                KEY idx_until (until_ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS bans (
                chat_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                by_admin_id BIGINT NULL,
                reason VARCHAR(255) NULL,
                ts INT UNSIGNED NOT NULL DEFAULT 0,
                until_ts INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (chat_id, user_id),
                KEY idx_until (until_ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                target_user_id BIGINT NOT NULL,
                reporter_user_id BIGINT NULL,
                admin_user_id BIGINT NULL,
                message_id BIGINT NULL,
                reason VARCHAR(500) NULL,
                status ENUM('open','seen','closed') NOT NULL DEFAULT 'open',
                ts INT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_chat (chat_id, ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS users (
                user_id BIGINT PRIMARY KEY,
                username VARCHAR(64) NULL,
                first_name VARCHAR(120) NULL,
                last_name VARCHAR(120) NULL,
                messages_count INT UNSIGNED NOT NULL DEFAULT 0,
                violations_count INT UNSIGNED NOT NULL DEFAULT 0,
                first_seen_at INT UNSIGNED NOT NULL DEFAULT 0,
                last_seen_at INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS admins_cache (
                chat_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                status VARCHAR(32) NOT NULL,
                cached_at INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (chat_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS bad_words (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NULL,
                word VARCHAR(120) NOT NULL,
                is_regex TINYINT(1) NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_chat (chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS welcome_buttons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                label VARCHAR(80) NOT NULL,
                url VARCHAR(500) NOT NULL,
                sort INT NOT NULL DEFAULT 0,
                KEY idx_chat (chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS captcha_pending (
                chat_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                message_id BIGINT NULL,
                answer INT NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                PRIMARY KEY (chat_id, user_id),
                KEY idx_exp (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NULL,
                actor_id BIGINT NULL,
                target_id BIGINT NULL,
                action VARCHAR(48) NOT NULL,
                detail VARCHAR(500) NULL,
                ts INT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_chat (chat_id, ts),
                KEY idx_action (action, ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id INT UNSIGNED NULL,
                username VARCHAR(64) NULL,
                action VARCHAR(64) NOT NULL,
                target VARCHAR(120) NULL,
                detail TEXT NULL,
                ip VARCHAR(64) NULL,
                ts INT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_admin (admin_id, ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS scheduled_actions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                user_id BIGINT NULL,
                action ENUM('unmute','unban','unwarn') NOT NULL,
                amount SMALLINT NOT NULL DEFAULT 0,
                run_at INT UNSIGNED NOT NULL,
                done TINYINT(1) NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_run (done, run_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS bot_updates (
                update_id BIGINT PRIMARY KEY,
                handled_at INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS webhook_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(64) NULL,
                ok TINYINT(1) NOT NULL DEFAULT 0,
                note VARCHAR(255) NULL,
                ts INT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_ts (ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- آمار روزانه (نمودارها) ----------
            "CREATE TABLE IF NOT EXISTS metrics_daily (
                day DATE NOT NULL,
                chat_id BIGINT NOT NULL,
                messages INT UNSIGNED NOT NULL DEFAULT 0,
                violations INT UNSIGNED NOT NULL DEFAULT 0,
                joins INT UNSIGNED NOT NULL DEFAULT 0,
                lefts INT UNSIGNED NOT NULL DEFAULT 0,
                commands INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (day, chat_id),
                KEY idx_day (day)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- بکاپ ----------
            "CREATE TABLE IF NOT EXISTS backup_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(190) NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                tables_count INT UNSIGNED NOT NULL DEFAULT 0,
                rows_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                kind ENUM('manual','cron') NOT NULL DEFAULT 'manual',
                admin_id INT UNSIGNED NULL,
                note VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- صف ارسال همگانی ----------
            "CREATE TABLE IF NOT EXISTS broadcast_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id INT UNSIGNED NOT NULL DEFAULT 0,
                chat_id BIGINT NOT NULL,
                text TEXT NOT NULL,
                status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
                error VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                sent_at INT UNSIGNED NULL,
                KEY idx_status (status, id),
                KEY idx_batch (batch_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- کدهای بازیابی پنل ----------
            "CREATE TABLE IF NOT EXISTS recovery_codes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id INT UNSIGNED NOT NULL,
                code_hash CHAR(64) NOT NULL,
                hint VARCHAR(32) NULL,
                used_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_admin (admin_id, used_at),
                CONSTRAINT fk_recovery_admin FOREIGN KEY (admin_id) REFERENCES panel_admins(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- لاگ OTP ----------
            "CREATE TABLE IF NOT EXISTS otp_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id INT UNSIGNED NULL,
                purpose VARCHAR(32) NOT NULL,
                channel VARCHAR(24) NOT NULL,
                destination VARCHAR(64) NULL,
                status VARCHAR(24) NOT NULL,
                ip VARCHAR(64) NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_created (created_at),
                KEY idx_admin (admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- زیرمجموعه‌گیری / دعوت ----------
            "CREATE TABLE IF NOT EXISTS referrals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inviter_id BIGINT NOT NULL,
                invitee_id BIGINT NOT NULL,
                chat_id BIGINT NULL,
                status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
                note VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                verified_at INT UNSIGNED NULL,
                UNIQUE KEY uq_invitee (invitee_id),
                KEY idx_inviter (inviter_id, status),
                KEY idx_chat (chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- صف ارسال همگانی (۱.۲) ----------
            "CREATE TABLE IF NOT EXISTS broadcast_batches (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id INT UNSIGNED NULL,
                title VARCHAR(120) NULL,
                text TEXT NOT NULL,
                parse_mode VARCHAR(16) NOT NULL DEFAULT 'HTML',
                audience VARCHAR(24) NOT NULL DEFAULT 'groups',
                target_count INT UNSIGNED NOT NULL DEFAULT 0,
                sent_count INT UNSIGNED NOT NULL DEFAULT 0,
                failed_count INT UNSIGNED NOT NULL DEFAULT 0,
                rate_per_sec SMALLINT UNSIGNED NOT NULL DEFAULT 15,
                status ENUM('queued','sending','done','canceled','paused') NOT NULL DEFAULT 'queued',
                scheduled_at INT UNSIGNED NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                started_at INT UNSIGNED NULL,
                finished_at INT UNSIGNED NULL,
                KEY idx_status (status, scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- فروشگاه: فروشنده و دسته (۱.۳) ----------
            "CREATE TABLE IF NOT EXISTS sellers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                shop_name VARCHAR(120) NOT NULL,
                phone VARCHAR(32) NULL,
                status ENUM('pending','approved','suspended') NOT NULL DEFAULT 'pending',
                note VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                approved_at INT UNSIGNED NULL,
                approved_by INT UNSIGNED NULL,
                UNIQUE KEY uq_user (user_id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id INT UNSIGNED NULL,
                name VARCHAR(80) NOT NULL,
                slug VARCHAR(80) NOT NULL,
                sort INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY uq_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- فروشگاه: کالا و انبار ----------
            "CREATE TABLE IF NOT EXISTS products (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                seller_id INT UNSIGNED NULL,
                category_id INT UNSIGNED NULL,
                sku VARCHAR(40) NULL,
                title VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                description TEXT NULL,
                price BIGINT UNSIGNED NOT NULL DEFAULT 0,
                discount_price BIGINT UNSIGNED NULL,
                stock INT NOT NULL DEFAULT 0,
                reserved INT NOT NULL DEFAULT 0,
                min_stock INT NOT NULL DEFAULT 3,
                unit VARCHAR(20) NOT NULL DEFAULT 'عدد',
                image VARCHAR(255) NULL,
                code CHAR(8) NOT NULL,
                status ENUM('draft','pending','active','rejected','archived') NOT NULL DEFAULT 'draft',
                views INT UNSIGNED NOT NULL DEFAULT 0,
                sold_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                updated_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_code (code),
                KEY idx_status (status, id),
                KEY idx_seller (seller_id),
                KEY idx_cat (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS stock_moves (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                delta INT NOT NULL,
                reason VARCHAR(24) NOT NULL,
                ref_type VARCHAR(16) NULL,
                ref_id BIGINT NULL,
                admin_id INT UNSIGNED NULL,
                note VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_product (product_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- فروشگاه: سفارش و پرداخت ----------
            "CREATE TABLE IF NOT EXISTS orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(16) NOT NULL,
                user_id BIGINT NOT NULL,
                seller_id INT UNSIGNED NULL,
                ref_code VARCHAR(24) NULL,
                affiliate_user_id BIGINT NULL,
                status ENUM('awaiting_payment','paid','preparing','shipped','delivered','canceled','returned') NOT NULL DEFAULT 'awaiting_payment',
                payment_status ENUM('unpaid','pending_review','verified','rejected') NOT NULL DEFAULT 'unpaid',
                subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0,
                discount BIGINT UNSIGNED NOT NULL DEFAULT 0,
                shipping BIGINT UNSIGNED NOT NULL DEFAULT 0,
                total BIGINT UNSIGNED NOT NULL DEFAULT 0,
                commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                commission_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
                commission_released TINYINT(1) NOT NULL DEFAULT 0,
                buyer_name VARCHAR(120) NULL,
                buyer_phone VARCHAR(32) NULL,
                address TEXT NULL,
                note VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                updated_at INT UNSIGNED NOT NULL,
                verified_by INT UNSIGNED NULL,
                verified_at INT UNSIGNED NULL,
                UNIQUE KEY uq_code (code),
                KEY idx_user (user_id, id),
                KEY idx_status (status, id),
                KEY idx_aff (affiliate_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS order_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                title_snapshot VARCHAR(190) NOT NULL,
                price_snapshot BIGINT UNSIGNED NOT NULL,
                qty INT UNSIGNED NOT NULL DEFAULT 1,
                total BIGINT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                method ENUM('card','cash','wallet') NOT NULL DEFAULT 'card',
                amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
                receipt_file VARCHAR(255) NULL,
                status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
                note VARCHAR(255) NULL,
                verified_by INT UNSIGNED NULL,
                verified_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_order (order_id),
                KEY idx_status (status, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- فروشگاه: کیف پول و کمیسیون ----------
            "CREATE TABLE IF NOT EXISTS wallets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                balance BIGINT NOT NULL DEFAULT 0,
                updated_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS wallet_tx (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                amount BIGINT NOT NULL,
                kind ENUM('commission','withdraw','purchase','refund','topup','adjust') NOT NULL,
                ref VARCHAR(40) NULL,
                note VARCHAR(255) NULL,
                admin_id INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_user (user_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS withdraw_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                amount BIGINT UNSIGNED NOT NULL,
                dest_card VARCHAR(40) NOT NULL,
                status ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
                note VARCHAR(255) NULL,
                admin_id INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                paid_at INT UNSIGNED NULL,
                KEY idx_status (status, id),
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- فروشگاه: کمپین تبلیغ کالا ----------
            "CREATE TABLE IF NOT EXISTS promo_campaigns (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                title VARCHAR(120) NULL,
                template TEXT NOT NULL,
                audience VARCHAR(24) NOT NULL DEFAULT 'groups',
                interval_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
                next_run_at INT UNSIGNED NOT NULL DEFAULT 0,
                status ENUM('active','paused','done') NOT NULL DEFAULT 'active',
                last_batch_id INT UNSIGNED NULL,
                sent_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_due (status, next_run_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS shop_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                purpose VARCHAR(24) NOT NULL DEFAULT 'login',
                expires_at INT UNSIGNED NOT NULL,
                used_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                ip VARCHAR(64) NULL,
                KEY idx_token (token_hash),
                KEY idx_user (user_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- کد تخفیف (۱.۴) ----------
            "CREATE TABLE IF NOT EXISTS coupons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(32) NOT NULL,
                type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
                value BIGINT UNSIGNED NOT NULL DEFAULT 0,
                min_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
                max_uses INT UNSIGNED NOT NULL DEFAULT 0,
                used_count INT UNSIGNED NOT NULL DEFAULT 0,
                per_user TINYINT UNSIGNED NOT NULL DEFAULT 1,
                seller_id INT UNSIGNED NULL,
                product_id INT UNSIGNED NULL,
                expires_at INT UNSIGNED NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_code (code),
                KEY idx_active (is_active, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS coupon_uses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                coupon_id INT UNSIGNED NOT NULL,
                user_id BIGINT NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_coupon (coupon_id),
                KEY idx_user (user_id, coupon_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- نظرات و امتیاز خریدار (۱.۴) ----------
            "CREATE TABLE IF NOT EXISTS product_reviews (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                user_id BIGINT NOT NULL,
                order_id BIGINT UNSIGNED NULL,
                rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
                comment VARCHAR(600) NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
                admin_id INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_user_product_order (user_id, product_id, order_id),
                KEY idx_product (product_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- ماژول: کانال‌ها ----------
            "CREATE TABLE IF NOT EXISTS channels (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                title VARCHAR(160) NOT NULL,
                username VARCHAR(80) NULL,
                kind ENUM('channel','group') NOT NULL DEFAULT 'channel',
                signature VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                post_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_chat (chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- ماژول: اخبار ----------
            "CREATE TABLE IF NOT EXISTS news_posts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                channel_id BIGINT NULL,
                title VARCHAR(200) NULL,
                body TEXT NOT NULL,
                media_url VARCHAR(255) NULL,
                status ENUM('draft','scheduled','sent','failed') NOT NULL DEFAULT 'draft',
                scheduled_at INT UNSIGNED NOT NULL DEFAULT 0,
                sent_at INT UNSIGNED NULL,
                error VARCHAR(255) NULL,
                admin_id INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_due (status, scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- ماژول: اجبار عضویت / بازی ----------
            "CREATE TABLE IF NOT EXISTS forcejoin_state (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                status ENUM('pending','passed','failed') NOT NULL DEFAULT 'pending',
                quiz_ok TINYINT(1) NOT NULL DEFAULT 0,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                verified_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- ماژول: نظرسنجی (۱.۵) ----------
            "CREATE TABLE IF NOT EXISTS polls (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(12) NOT NULL,
                chat_id BIGINT NULL,
                message_id BIGINT NULL,
                question VARCHAR(300) NOT NULL,
                description VARCHAR(600) NULL,
                multi TINYINT(1) NOT NULL DEFAULT 0,
                anonymous TINYINT(1) NOT NULL DEFAULT 0,
                status ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
                expires_at INT UNSIGNED NULL,
                admin_id INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_code (code),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS poll_options (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                poll_id INT UNSIGNED NOT NULL,
                idx TINYINT UNSIGNED NOT NULL,
                label VARCHAR(120) NOT NULL,
                votes INT UNSIGNED NOT NULL DEFAULT 0,
                KEY idx_poll (poll_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS poll_votes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                poll_id INT UNSIGNED NOT NULL,
                option_idx TINYINT UNSIGNED NOT NULL,
                user_id BIGINT NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_vote (poll_id, user_id, option_idx),
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- ماژول: قرعه‌کشی (۱.۵) ----------
            "CREATE TABLE IF NOT EXISTS raffles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(12) NOT NULL,
                title VARCHAR(200) NOT NULL,
                prize VARCHAR(200) NOT NULL,
                description VARCHAR(600) NULL,
                chat_id BIGINT NULL,
                message_id BIGINT NULL,
                require_chat BIGINT NULL,
                ends_at INT UNSIGNED NOT NULL DEFAULT 0,
                winners_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
                status ENUM('draft','active','drawn','canceled') NOT NULL DEFAULT 'active',
                winner_ids VARCHAR(255) NULL,
                entrants INT UNSIGNED NOT NULL DEFAULT 0,
                drawn_at INT UNSIGNED NULL,
                admin_id INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_code (code),
                KEY idx_due (status, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS raffle_entries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                raffle_id INT UNSIGNED NOT NULL,
                user_id BIGINT NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_entry (raffle_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- ماژول: کانال خبر (۱.۵) ----------
            "CREATE TABLE IF NOT EXISTS news_sources (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                title VARCHAR(160) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_src_chat (chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS news_categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(80) NOT NULL,
                keywords VARCHAR(600) NOT NULL,
                target_chat BIGINT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort SMALLINT NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_sort (sort)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS news_inbox (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_chat BIGINT NOT NULL,
                source_msg BIGINT NULL,
                category_id INT UNSIGNED NULL,
                body TEXT NOT NULL,
                status ENUM('new','sent','skipped','failed') NOT NULL DEFAULT 'new',
                target_chat BIGINT NULL,
                sent_msg BIGINT NULL,
                error VARCHAR(255) NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- امنیت و مدیریت از ربات (۱.۶) ----------
            "CREATE TABLE IF NOT EXISTS event_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                etype VARCHAR(60) NOT NULL,
                severity ENUM('info','warn','critical') NOT NULL DEFAULT 'warn',
                ip VARCHAR(45) NULL,
                actor_id BIGINT NULL,
                detail VARCHAR(255) NULL,
                meta TEXT NULL,
                notified TINYINT(1) NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_type (etype, created_at),
                KEY idx_notified (notified, id),
                KEY idx_time (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS bot_links (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bale_user_id BIGINT NOT NULL,
                admin_id INT UNSIGNED NOT NULL,
                verified_at INT UNSIGNED NULL,
                otp_ok_until INT UNSIGNED NOT NULL DEFAULT 0,
                otp_fail_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
                alert_opt TINYINT(1) NOT NULL DEFAULT 1,
                last_seen_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_user (bale_user_id),
                KEY idx_admin (admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS bot_pairs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(16) NOT NULL,
                admin_id INT UNSIGNED NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                used_at INT UNSIGNED NULL,
                used_by BIGINT NULL,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_code (code),
                KEY idx_admin (admin_id),
                KEY idx_exp (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS bot_otps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bale_user_id BIGINT NOT NULL,
                code_hash CHAR(64) NOT NULL,
                expires_at INT UNSIGNED NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                used_at INT UNSIGNED NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_user (bale_user_id, used_at),
                KEY idx_exp (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS stock_alerts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                last_stock INT NOT NULL DEFAULT 0,
                last_alert_at INT UNSIGNED NOT NULL DEFAULT 0,
                alert_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // ---------- تنظیمات کلید/مقدار ----------
            "CREATE TABLE IF NOT EXISTS settings (
                skey VARCHAR(64) PRIMARY KEY,
                svalue TEXT NULL,
                updated_at INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }


    /** آپدیت‌های تدریجی: ستون‌های جدید جدول‌های موجود را اضافه می‌کند (idempotent) */
    public static function upgrade(): array
    {
        $log = [];
        // ستون‌های جدید جدول‌های موجود (idempotent)
        $tableCols = [
            'users' => [
                'referral_code' => "VARCHAR(16) NULL",
                'referred_by'   => "BIGINT NULL",
                'invites_count' => "INT UNSIGNED NOT NULL DEFAULT 0",
            ],
            'broadcast_queue' => [
                'attempts'    => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
                'next_try_at' => "INT UNSIGNED NOT NULL DEFAULT 0",
                'chat_type'   => "VARCHAR(12) NULL",
            ],
            'promo_campaigns' => [
                'mode'            => "ENUM('single','rotate') NOT NULL DEFAULT 'single'",
                'last_product_id' => "INT UNSIGNED NULL",
                'rotate_pool'     => "VARCHAR(500) NULL",
            ],
            'orders' => [
                'coupon_id'   => "INT UNSIGNED NULL",
                'coupon_code' => "VARCHAR(32) NULL",
                'reviewed'    => "TINYINT(1) NOT NULL DEFAULT 0",
            ],
        ];
        foreach ($tableCols as $table => $cols) {
            foreach ($cols as $col => $def) {
                $exists = DB::val(
                    "SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name=? AND column_name=?",
                    [$table, $col], 0
                );
                if ((int)$exists === 0) {
                    try {
                        DB::pdo()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                        $log[] = "$table.$col added";
                    } catch (Throwable $e) {
                        $log[] = "$table.$col failed: " . $e->getMessage();
                    }
                }
            }
        }
        try {
            $idx = (int)DB::val(
                "SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name='users' AND index_name='uq_referral_code'",
                [], 0
            );
            if ($idx === 0) {
                DB::pdo()->exec("ALTER TABLE users ADD UNIQUE KEY uq_referral_code (referral_code)");
                $log[] = 'index uq_referral_code added';
            }
        } catch (Throwable $e) {
            $log[] = 'index failed: ' . $e->getMessage();
        }
        $all = self::statements();
        // جدول‌های اعلام‌شده توسط ماژول‌ها (پلاگین‌ها)
        try {
            if (class_exists('ModuleManager')) {
                $all = array_merge($all, ModuleManager::migrations());
            }
        } catch (Throwable $e) {
            $log[] = 'modules migrations: ' . $e->getMessage();
        }
        foreach ($all as $sql) {
            try {
                DB::pdo()->exec($sql);
            } catch (Throwable $e) {
                $log[] = 'table: ' . $e->getMessage();
            }
        }
        self::setSetting('schema_version', self::VERSION);
        return $log;
    }

    public static function install(): void
    {
        $all = self::statements();
        try {
            if (class_exists('ModuleManager')) {
                $all = array_merge($all, ModuleManager::migrations());
            }
        } catch (Throwable $e) {
            // ماژول‌ها در نصب اولیه حیاتی نیستند
        }
        foreach ($all as $sql) {
            DB::pdo()->exec($sql);
        }
        // ستون‌های نسخه‌های بعدی (referral_code و …) + جدول‌های ماژول‌ها — idempotent؛
        // بدون این، نصب اولیه جدول users را بدون ستون‌های ارجاع می‌ساخت و صفحهٔ زیرمجموعه‌گیری خطا می‌داد.
        self::upgrade();
    }

    public static function seedDefaults(): void
    {
        // کلمات پیش‌فرض (قابل ویرایش در پنل)
        $cnt = (int)DB::val("SELECT COUNT(*) FROM bad_words WHERE chat_id IS NULL", [], 0);
        if ($cnt === 0) {
            $words = ['کیر','کص','کون','جنده','حرومزاده','بی‌شرف','مادرجنده','fuck','shit','bitch'];
            foreach ($words as $w) {
                DB::q("INSERT INTO bad_words (chat_id, word, is_regex, created_at) VALUES (NULL, ?, 0, ?)", [$w, time()]);
            }
        }
        self::setSetting('schema_version', self::VERSION);
        self::setSetting('bot_started_at', (string)time());
    }

    public static function setSetting(string $key, string $value): void
    {
        DB::q("INSERT INTO settings (skey, svalue, updated_at) VALUES (?,?,?)
               ON DUPLICATE KEY UPDATE svalue=VALUES(svalue), updated_at=VALUES(updated_at)", [$key, $value, time()]);
    }

    public static function getSetting(string $key, ?string $default = null): ?string
    {
        $v = DB::val("SELECT svalue FROM settings WHERE skey=?", [$key]);
        return $v === null ? $default : (string)$v;
    }
}
