-- Rode isso uma vez no phpMyAdmin do cPanel da Locaweb, dentro do banco
-- que você criar em "Bancos de Dados MySQL".

CREATE TABLE IF NOT EXISTS parceiros (
  id BIGINT UNSIGNED PRIMARY KEY,
  created_at VARCHAR(40) NOT NULL,
  name VARCHAR(255) NOT NULL,
  document VARCHAR(32) NOT NULL,
  commercial_phone VARCHAR(32) NOT NULL,
  whatsapp_phone VARCHAR(32) DEFAULT '',
  commercial_email VARCHAR(255) NOT NULL,
  financial_email VARCHAR(255) DEFAULT '',
  city VARCHAR(255) DEFAULT '',
  contact_time VARCHAR(64) DEFAULT '',
  works_commercial VARCHAR(16) DEFAULT '',
  has_address VARCHAR(16) DEFAULT '',
  credit_experience VARCHAR(16) DEFAULT '',
  message TEXT,
  status VARCHAR(16) NOT NULL DEFAULT 'pendente',
  status_updated_at VARCHAR(40) DEFAULT NULL,
  fontedata_result TEXT DEFAULT NULL,
  fontedata_consultado_em VARCHAR(40) DEFAULT NULL,
  soublu_user_id VARCHAR(64) DEFAULT NULL,
  soublu_temp_password VARCHAR(64) DEFAULT NULL,
  soublu_synced_at VARCHAR(40) DEFAULT NULL,
  soublu_error TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
