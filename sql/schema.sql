-- ---------------------------------------------------------------
-- bale-group-manager — ساختار پایگاه‌داده (نسخه ۱.۶.۰)
-- 58 جدول · MySQL 5.7+ / MariaDB 10.2+ · utf8mb4
--
-- این فایل فقط برای مرجع/بازیابی دستی است؛ نصب‌کننده (install.php) و
-- ارتقای خودکار (Schema::upgrade) خودشان جدول‌ها را می‌سازند.
-- اجرا:  mysql -u USER -p DBNAME < sql/schema.sql
-- ---------------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE `admins_cache` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `status` varchar(32) NOT NULL,
  `cached_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=421 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `bad_words` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) DEFAULT NULL,
  `word` varchar(120) NOT NULL,
  `is_regex` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chat` (`chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE `bot_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bale_user_id` bigint(20) NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `verified_at` int(10) unsigned DEFAULT NULL,
  `otp_ok_until` int(10) unsigned NOT NULL DEFAULT 0,
  `otp_fail_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `alert_opt` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen_at` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`bale_user_id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `bot_otps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bale_user_id` bigint(20) NOT NULL,
  `code_hash` char(64) NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `used_at` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`bale_user_id`,`used_at`),
  KEY `idx_exp` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `bot_pairs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(16) NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  `used_at` int(10) unsigned DEFAULT NULL,
  `used_by` bigint(20) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_exp` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `bot_updates` (
  `update_id` bigint(20) NOT NULL,
  `handled_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`update_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `captcha_pending` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `answer` int(11) NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`chat_id`,`user_id`),
  KEY `idx_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `channels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `title` varchar(160) NOT NULL,
  `username` varchar(80) DEFAULT NULL,
  `kind` enum('channel','group') NOT NULL DEFAULT 'channel',
  `signature` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `post_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat` (`chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE `coupon_uses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_coupon` (`coupon_id`),
  KEY `idx_user` (`user_id`,`coupon_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `coupons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` bigint(20) unsigned NOT NULL DEFAULT 0,
  `min_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `max_uses` int(10) unsigned NOT NULL DEFAULT 0,
  `used_count` int(10) unsigned NOT NULL DEFAULT 0,
  `per_user` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `seller_id` int(10) unsigned DEFAULT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `expires_at` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_active` (`is_active`,`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `event_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `etype` varchar(60) NOT NULL,
  `severity` enum('info','warn','critical') NOT NULL DEFAULT 'warn',
  `ip` varchar(45) DEFAULT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`etype`,`created_at`),
  KEY `idx_notified` (`notified`,`id`),
  KEY `idx_time` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `forcejoin_state` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `status` enum('pending','passed','failed') NOT NULL DEFAULT 'pending',
  `quiz_ok` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `verified_at` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE `news_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `keywords` varchar(600) NOT NULL,
  `target_chat` bigint(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort` smallint(6) NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `news_inbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_chat` bigint(20) NOT NULL,
  `source_msg` bigint(20) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `status` enum('new','sent','skipped','failed') NOT NULL DEFAULT 'new',
  `target_chat` bigint(20) DEFAULT NULL,
  `sent_msg` bigint(20) DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `news_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` bigint(20) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `body` text NOT NULL,
  `media_url` varchar(255) DEFAULT NULL,
  `status` enum('draft','scheduled','sent','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_at` int(10) unsigned DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_due` (`status`,`scheduled_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `news_sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `title` varchar(160) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_src_chat` (`chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `coupon_id` int(10) unsigned DEFAULT NULL,
  `coupon_code` varchar(32) DEFAULT NULL,
  `reviewed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_user` (`user_id`,`id`),
  KEY `idx_status` (`status`,`id`),
  KEY `idx_aff` (`affiliate_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `poll_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` int(10) unsigned NOT NULL,
  `idx` tinyint(3) unsigned NOT NULL,
  `label` varchar(120) NOT NULL,
  `votes` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_poll` (`poll_id`)
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `poll_votes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` int(10) unsigned NOT NULL,
  `option_idx` tinyint(3) unsigned NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vote` (`poll_id`,`user_id`,`option_idx`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `polls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(12) NOT NULL,
  `chat_id` bigint(20) DEFAULT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `question` varchar(300) NOT NULL,
  `description` varchar(600) DEFAULT NULL,
  `multi` tinyint(1) NOT NULL DEFAULT 0,
  `anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','active','closed') NOT NULL DEFAULT 'draft',
  `expires_at` int(10) unsigned DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `product_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `comment` varchar(600) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `admin_id` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_product_order` (`user_id`,`product_id`,`order_id`),
  KEY `idx_product` (`product_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `mode` enum('single','rotate') NOT NULL DEFAULT 'single',
  `last_product_id` int(10) unsigned DEFAULT NULL,
  `rotate_pool` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_due` (`status`,`next_run_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `raffle_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `raffle_id` int(10) unsigned NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entry` (`raffle_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `raffles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(12) NOT NULL,
  `title` varchar(200) NOT NULL,
  `prize` varchar(200) NOT NULL,
  `description` varchar(600) DEFAULT NULL,
  `chat_id` bigint(20) DEFAULT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `require_chat` bigint(20) DEFAULT NULL,
  `ends_at` int(10) unsigned NOT NULL DEFAULT 0,
  `winners_count` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `status` enum('draft','active','drawn','canceled') NOT NULL DEFAULT 'active',
  `winner_ids` varchar(255) DEFAULT NULL,
  `entrants` int(10) unsigned NOT NULL DEFAULT 0,
  `drawn_at` int(10) unsigned DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_due` (`status`,`ends_at`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE `settings` (
  `skey` varchar(64) NOT NULL,
  `svalue` text DEFAULT NULL,
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `stock_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `last_stock` int(11) NOT NULL DEFAULT 0,
  `last_alert_at` int(10) unsigned NOT NULL DEFAULT 0,
  `alert_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `wallets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `balance` bigint(20) NOT NULL DEFAULT 0,
  `updated_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `warns` (
  `chat_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `webhook_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(64) DEFAULT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `ts` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ts` (`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `welcome_buttons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `label` varchar(80) NOT NULL,
  `url` varchar(500) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chat` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
