-- ============================================================
-- Bale Group Manager — ساختار دیتابیس (نسخه 1.3.0)
-- ۳۹ جدول: مدیریت گروه، آمار، بکاپ، دعوت، صف ارسال، کد بازیابی، فروشگاه
-- نصب دستی:  mysql -u USER -p DBNAME < sql/schema.sql
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `admins_cache`;
CREATE TABLE `admins_cache` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `status` varchar(32) NOT NULL,
  `cached_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `username` varchar(64) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `target` varchar(120) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `ts` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`,`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=320 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `backup_log`;
CREATE TABLE `backup_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(190) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `tables_count` int(10) unsigned NOT NULL DEFAULT 0,
  `rows_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `kind` enum('manual','cron') NOT NULL DEFAULT 'manual',
  `admin_id` int(10) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `bad_words`;
CREATE TABLE `bad_words` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) DEFAULT NULL,
  `word` varchar(120) NOT NULL,
  `is_regex` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chat` (`chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `bans`;
CREATE TABLE `bans` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `by_admin_id` bigint(20) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ts` int(10) unsigned NOT NULL DEFAULT 0,
  `until_ts` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`,`user_id`),
  KEY `idx_until` (`until_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `bot_updates`;
CREATE TABLE `bot_updates` (
  `update_id` bigint(20) NOT NULL,
  `handled_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`update_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `broadcast_batches`;
CREATE TABLE `broadcast_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(120) DEFAULT NULL,
  `text` text NOT NULL,
  `parse_mode` varchar(16) NOT NULL DEFAULT 'HTML',
  `audience` varchar(24) NOT NULL DEFAULT 'groups',
  `target_count` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `rate_per_sec` smallint(5) unsigned NOT NULL DEFAULT 15,
  `status` enum('queued','sending','done','canceled','paused') NOT NULL DEFAULT 'queued',
  `scheduled_at` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  `started_at` int(10) unsigned DEFAULT NULL,
  `finished_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`scheduled_at`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `broadcast_queue`;
CREATE TABLE `broadcast_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int(10) unsigned NOT NULL DEFAULT 0,
  `chat_id` bigint(20) NOT NULL,
  `text` text NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `sent_at` int(10) unsigned DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `next_try_at` int(10) unsigned NOT NULL DEFAULT 0,
  `chat_type` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`id`),
  KEY `idx_batch` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `captcha_pending`;
CREATE TABLE `captcha_pending` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `answer` int(11) NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`chat_id`,`user_id`),
  KEY `idx_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `chat_settings`;
CREATE TABLE `chat_settings` (
  `chat_id` bigint(20) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `chat_type` varchar(32) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `anti_link` tinyint(1) NOT NULL DEFAULT 1,
  `anti_spam` tinyint(1) NOT NULL DEFAULT 1,
  `spam_window_sec` int(10) unsigned NOT NULL DEFAULT 8,
  `spam_max_msgs` int(10) unsigned NOT NULL DEFAULT 6,
  `bad_words` tinyint(1) NOT NULL DEFAULT 1,
  `anti_bot` tinyint(1) NOT NULL DEFAULT 0,
  `anti_forward` tinyint(1) NOT NULL DEFAULT 0,
  `anti_username` tinyint(1) NOT NULL DEFAULT 1,
  `anti_flood_action` enum('warn','delete','mute') NOT NULL DEFAULT 'mute',
  `flood_mute_sec` int(10) unsigned NOT NULL DEFAULT 300,
  `warn_limit` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `warn_action` enum('mute','kick','ban') NOT NULL DEFAULT 'mute',
  `warn_mute_sec` int(10) unsigned NOT NULL DEFAULT 3600,
  `welcome_on` tinyint(1) NOT NULL DEFAULT 1,
  `welcome_text` text DEFAULT NULL,
  `welcome_buttons` varchar(500) DEFAULT NULL,
  `goodbye_on` tinyint(1) NOT NULL DEFAULT 0,
  `goodbye_text` text DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `lock_link` tinyint(1) NOT NULL DEFAULT 0,
  `lock_photo` tinyint(1) NOT NULL DEFAULT 0,
  `lock_video` tinyint(1) NOT NULL DEFAULT 0,
  `lock_gif` tinyint(1) NOT NULL DEFAULT 0,
  `lock_sticker` tinyint(1) NOT NULL DEFAULT 0,
  `lock_voice` tinyint(1) NOT NULL DEFAULT 0,
  `lock_document` tinyint(1) NOT NULL DEFAULT 0,
  `lock_forward` tinyint(1) NOT NULL DEFAULT 0,
  `lock_contact` tinyint(1) NOT NULL DEFAULT 0,
  `lock_poll` tinyint(1) NOT NULL DEFAULT 0,
  `lock_location` tinyint(1) NOT NULL DEFAULT 0,
  `lock_bot` tinyint(1) NOT NULL DEFAULT 0,
  `captcha_on` tinyint(1) NOT NULL DEFAULT 0,
  `captcha_timeout_sec` int(10) unsigned NOT NULL DEFAULT 90,
  `log_channel_id` bigint(20) DEFAULT NULL,
  `report_channel_id` bigint(20) DEFAULT NULL,
  `lang` varchar(8) NOT NULL DEFAULT 'fa',
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `kind` varchar(24) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip`,`created_at`),
  KEY `idx_kind` (`kind`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `login_otps`;
CREATE TABLE `login_otps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `code_hash` char(64) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `channel` enum('group','private') NOT NULL DEFAULT 'group',
  `chat_id` bigint(20) DEFAULT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `used_at` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`,`expires_at`),
  CONSTRAINT `fk_otp_admin` FOREIGN KEY (`admin_id`) REFERENCES `panel_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) DEFAULT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `target_id` bigint(20) DEFAULT NULL,
  `action` varchar(48) NOT NULL,
  `detail` varchar(500) DEFAULT NULL,
  `ts` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chat` (`chat_id`,`ts`),
  KEY `idx_action` (`action`,`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=293 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `metrics_daily`;
CREATE TABLE `metrics_daily` (
  `day` date NOT NULL,
  `chat_id` bigint(20) NOT NULL,
  `messages` int(10) unsigned NOT NULL DEFAULT 0,
  `violations` int(10) unsigned NOT NULL DEFAULT 0,
  `joins` int(10) unsigned NOT NULL DEFAULT 0,
  `lefts` int(10) unsigned NOT NULL DEFAULT 0,
  `commands` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`,`chat_id`),
  KEY `idx_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `mutes`;
CREATE TABLE `mutes` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `until_ts` int(10) unsigned NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `by_admin_id` bigint(20) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`,`user_id`),
  KEY `idx_until` (`until_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `title_snapshot` varchar(190) NOT NULL,
  `price_snapshot` bigint(20) unsigned NOT NULL,
  `qty` int(10) unsigned NOT NULL DEFAULT 1,
  `total` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(16) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `seller_id` int(10) unsigned DEFAULT NULL,
  `ref_code` varchar(24) DEFAULT NULL,
  `affiliate_user_id` bigint(20) DEFAULT NULL,
  `status` enum('awaiting_payment','paid','preparing','shipped','delivered','canceled','returned') NOT NULL DEFAULT 'awaiting_payment',
  `payment_status` enum('unpaid','pending_review','verified','rejected') NOT NULL DEFAULT 'unpaid',
  `subtotal` bigint(20) unsigned NOT NULL DEFAULT 0,
  `discount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `shipping` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `commission_released` tinyint(1) NOT NULL DEFAULT 0,
  `buyer_name` varchar(120) DEFAULT NULL,
  `buyer_phone` varchar(32) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `updated_at` int(10) unsigned NOT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_user` (`user_id`,`id`),
  KEY `idx_status` (`status`,`id`),
  KEY `idx_aff` (`affiliate_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `otp_logs`;
CREATE TABLE `otp_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `purpose` varchar(32) NOT NULL,
  `channel` varchar(24) NOT NULL,
  `destination` varchar(64) DEFAULT NULL,
  `status` varchar(24) NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `panel_admins`;
CREATE TABLE `panel_admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `bale_user_id` bigint(20) unsigned DEFAULT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `role` enum('super','admin') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` int(10) unsigned DEFAULT NULL,
  `last_login_ip` varchar(64) DEFAULT NULL,
  `failed_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `locked_until` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `panel_sessions`;
CREATE TABLE `panel_sessions` (
  `id` char(64) NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `last_seen_at` int(10) unsigned NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  CONSTRAINT `fk_sess_admin` FOREIGN KEY (`admin_id`) REFERENCES `panel_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `method` enum('card','cash','wallet') NOT NULL DEFAULT 'card',
  `amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `receipt_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `sku` varchar(40) DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `price` bigint(20) unsigned NOT NULL DEFAULT 0,
  `discount_price` bigint(20) unsigned DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `reserved` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 3,
  `unit` varchar(20) NOT NULL DEFAULT 'عدد',
  `image` varchar(255) DEFAULT NULL,
  `code` char(8) NOT NULL,
  `status` enum('draft','pending','active','rejected','archived') NOT NULL DEFAULT 'draft',
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  `sold_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  `updated_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_status` (`status`,`id`),
  KEY `idx_seller` (`seller_id`),
  KEY `idx_cat` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `promo_campaigns`;
CREATE TABLE `promo_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `template` text NOT NULL,
  `audience` varchar(24) NOT NULL DEFAULT 'groups',
  `interval_hours` smallint(5) unsigned NOT NULL DEFAULT 24,
  `next_run_at` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','paused','done') NOT NULL DEFAULT 'active',
  `last_batch_id` int(10) unsigned DEFAULT NULL,
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_due` (`status`,`next_run_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `recovery_codes`;
CREATE TABLE `recovery_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `code_hash` char(64) NOT NULL,
  `hint` varchar(32) DEFAULT NULL,
  `used_at` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`,`used_at`),
  CONSTRAINT `fk_recovery_admin` FOREIGN KEY (`admin_id`) REFERENCES `panel_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `referrals`;
CREATE TABLE `referrals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inviter_id` bigint(20) NOT NULL,
  `invitee_id` bigint(20) NOT NULL,
  `chat_id` bigint(20) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `verified_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invitee` (`invitee_id`),
  KEY `idx_inviter` (`inviter_id`,`status`),
  KEY `idx_chat` (`chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `target_user_id` bigint(20) NOT NULL,
  `reporter_user_id` bigint(20) DEFAULT NULL,
  `admin_user_id` bigint(20) DEFAULT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `status` enum('open','seen','closed') NOT NULL DEFAULT 'open',
  `ts` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chat` (`chat_id`,`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `scheduled_actions`;
CREATE TABLE `scheduled_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `action` enum('unmute','unban','unwarn') NOT NULL,
  `amount` smallint(6) NOT NULL DEFAULT 0,
  `run_at` int(10) unsigned NOT NULL,
  `done` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_run` (`done`,`run_at`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `sellers`;
CREATE TABLE `sellers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `shop_name` varchar(120) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `status` enum('pending','approved','suspended') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `approved_at` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `skey` varchar(64) NOT NULL,
  `svalue` text DEFAULT NULL,
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `shop_tokens`;
CREATE TABLE `shop_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `purpose` varchar(24) NOT NULL DEFAULT 'login',
  `expires_at` int(10) unsigned NOT NULL,
  `used_at` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_hash`),
  KEY `idx_user` (`user_id`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `stock_moves`;
CREATE TABLE `stock_moves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `delta` int(11) NOT NULL,
  `reason` varchar(24) NOT NULL,
  `ref_type` varchar(16) DEFAULT NULL,
  `ref_id` bigint(20) DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL,
  `username` varchar(64) DEFAULT NULL,
  `first_name` varchar(120) DEFAULT NULL,
  `last_name` varchar(120) DEFAULT NULL,
  `messages_count` int(10) unsigned NOT NULL DEFAULT 0,
  `violations_count` int(10) unsigned NOT NULL DEFAULT 0,
  `first_seen_at` int(10) unsigned NOT NULL DEFAULT 0,
  `last_seen_at` int(10) unsigned NOT NULL DEFAULT 0,
  `referral_code` varchar(16) DEFAULT NULL,
  `referred_by` bigint(20) DEFAULT NULL,
  `invites_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_referral_code` (`referral_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `wallet_tx`;
CREATE TABLE `wallet_tx` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `kind` enum('commission','withdraw','purchase','refund','topup','adjust') NOT NULL,
  `ref` varchar(40) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `wallets`;
CREATE TABLE `wallets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `balance` bigint(20) NOT NULL DEFAULT 0,
  `updated_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `warns`;
CREATE TABLE `warns` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `webhook_log`;
CREATE TABLE `webhook_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(64) DEFAULT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `ts` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ts` (`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `welcome_buttons`;
CREATE TABLE `welcome_buttons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `label` varchar(80) NOT NULL,
  `url` varchar(500) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chat` (`chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `withdraw_requests`;
CREATE TABLE `withdraw_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `amount` bigint(20) unsigned NOT NULL,
  `dest_card` varchar(40) NOT NULL,
  `status` enum('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `paid_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

SET FOREIGN_KEY_CHECKS = 1;
