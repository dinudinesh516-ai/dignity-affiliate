-- Dignity Jewels Affiliate Program — database schema (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role             ENUM('admin','agent') NOT NULL DEFAULT 'agent',
  full_name        VARCHAR(120) NOT NULL,
  email            VARCHAR(190) NOT NULL,
  phone            VARCHAR(40)  NULL,
  username         VARCHAR(60)  NULL,
  password_hash    VARCHAR(255) NULL,
  referral_code    VARCHAR(16)  NULL,
  commission_rate  DECIMAL(5,2) NULL COMMENT 'NULL = use default from settings',
  status           ENUM('active','disabled') NOT NULL DEFAULT 'active',
  source           ENUM('admin','self','system') NOT NULL DEFAULT 'admin',
  last_login_at    DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_code (referral_code),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_tokens (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  purpose     ENUM('reset','setup') NOT NULL DEFAULT 'reset',
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token_hash),
  KEY idx_token_user (user_id),
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referred clients (one client can only ever belong to one agent)
CREATE TABLE IF NOT EXISTS clients (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id          INT UNSIGNED NULL,
  full_name         VARCHAR(120) NOT NULL,
  email             VARCHAR(190) NULL,
  phone             VARCHAR(40)  NOT NULL,
  phone_key         VARCHAR(20)  NOT NULL COMMENT 'last 10 digits, used for duplicate detection',
  email_key         VARCHAR(190) NULL COMMENT 'lower-cased email, used for duplicate detection',
  interests         VARCHAR(255) NULL,
  looking_for       TEXT NULL,
  budget            VARCHAR(60)  NULL,
  preferred_contact ENUM('whatsapp','call','email') NOT NULL DEFAULT 'whatsapp',
  source            ENUM('qr','agent','admin','self') NOT NULL DEFAULT 'qr',
  status            ENUM('new','contacted','negotiating','won','lost') NOT NULL DEFAULT 'new',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clients_phone (phone_key),
  UNIQUE KEY uq_clients_email (email_key),
  KEY idx_clients_agent (agent_id),
  KEY idx_clients_status (status),
  CONSTRAINT fk_clients_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_activity (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  type        ENUM('note','email','whatsapp','call','status','invoice','payment') NOT NULL DEFAULT 'note',
  body        TEXT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_client (client_id),
  CONSTRAINT fk_activity_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_no    VARCHAR(30) NOT NULL,
  client_id     INT UNSIGNED NOT NULL,
  agent_id      INT UNSIGNED NULL,
  invoice_date  DATE NOT NULL,
  subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_rate      DECIMAL(5,2)  NOT NULL DEFAULT 0,
  tax_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
  total         DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0,
  status        ENUM('pending','partial','paid','cancelled') NOT NULL DEFAULT 'pending',
  notes         TEXT NULL,
  paid_at       DATETIME NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoice_no (invoice_no),
  KEY idx_invoice_client (client_id),
  KEY idx_invoice_agent (agent_id),
  KEY idx_invoice_status (status),
  CONSTRAINT fk_invoice_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoice_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id   INT UNSIGNED NOT NULL,
  description  VARCHAR(255) NOT NULL,
  qty          DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
  sort_order   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_items_invoice (invoice_id),
  CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id    INT UNSIGNED NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  payment_date  DATE NOT NULL,
  method        VARCHAR(40) NOT NULL,
  reference     VARCHAR(120) NULL,
  notes         VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_invoice (invoice_id),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referral bonus earned by an agent when an invoice is fully paid
CREATE TABLE IF NOT EXISTS commissions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id      INT UNSIGNED NOT NULL,
  invoice_id    INT UNSIGNED NOT NULL,
  client_id     INT UNSIGNED NOT NULL,
  sale_amount   DECIMAL(12,2) NOT NULL,
  rate          DECIMAL(5,2)  NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0,
  status        ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  earned_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commission_invoice (invoice_id),
  KEY idx_commission_agent (agent_id),
  CONSTRAINT fk_commission_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_commission_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bonus payouts to agents (partial or full) against a commission
CREATE TABLE IF NOT EXISTS payouts (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  commission_id  INT UNSIGNED NOT NULL,
  agent_id       INT UNSIGNED NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  payout_date    DATE NOT NULL,
  method         VARCHAR(40) NOT NULL,
  reference      VARCHAR(120) NULL,
  notes          VARCHAR(255) NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payouts_agent (agent_id),
  KEY idx_payouts_commission (commission_id),
  CONSTRAINT fk_payouts_commission FOREIGN KEY (commission_id) REFERENCES commissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  name   VARCHAR(60) NOT NULL,
  value  TEXT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  to_email    VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  ok          TINYINT(1) NOT NULL DEFAULT 0,
  response    TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket      VARCHAR(190) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_bucket (bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (name, value) VALUES
  ('commission_rate', '5'),
  ('commission_basis', 'total'),
  ('currency_symbol', '$'),
  ('currency_code', 'USD'),
  ('default_country_code', '1'),
  ('notify_emails', ''),
  ('company_name', 'Dignity Jewels Inc'),
  ('company_address', '650 S Hill Street, Suite 705, Los Angeles CA 90014'),
  ('company_phone', ''),
  ('company_website', 'https://dignityjewelry.com'),
  ('logo_url', 'https://dignityjewelry.com/wp-content/uploads/2026/06/logo_black.png'),
  ('invoice_prefix', 'DJ-'),
  ('default_tax_rate', '0'),
  ('client_confirmation_email', '1'),
  ('phone_key_version', '2'),
  ('schema_version', '2'),
  ('whatsapp_template', 'Hi {client}, this is Dignity Jewels. {agent} let us know you are looking for {interest}. When is a good time to chat?');
