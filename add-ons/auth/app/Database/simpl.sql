START TRANSACTION;

DROP SCHEMA IF EXISTS simpl;
CREATE SCHEMA simpl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- users
--

CREATE TABLE simpl.users
(
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NULL     DEFAULT NULL,
    email       VARCHAR(100)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    profile_img VARCHAR(50)     NULL     DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_update TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active   TINYINT         NOT NULL DEFAULT 1,
    deleted_at  TIMESTAMP                DEFAULT NULL
) ENGINE = InnoDB;

INSERT INTO simpl.users (email, password)
VALUES ('admin@example.com', '$2y$12$WEOZKzM9JmXBLDcBMbRJfunNuu9OKYbkWaXOp34noadW2IWH36x0a'),
       ('user@example.com', '$2y$12$KiJxiUKSmlp1OTohZmNqIOs3tGF7W1XLUh42zE8qpBnFuK7hggTH2');

-- passwords are 'admin' and 'user'

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- login_attempts
--

CREATE TABLE simpl.login_attempts
(
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NULL,
    ip_address    VARCHAR(45)     NOT NULL,
    user_agent    VARCHAR(255)    NOT NULL,
    attempt_time  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success       TINYINT         NOT NULL DEFAULT 0,
    failed_reason VARCHAR(50)     NULL     DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES simpl.users (id) ON DELETE CASCADE,
    INDEX idx_user_success_time (user_id, success, attempt_time),
    INDEX idx_ip_success_time (ip_address, success, attempt_time)
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- tokens
--

CREATE TABLE simpl.tokens
(
    id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token   VARCHAR(32)     NOT NULL,
    type    VARCHAR(50)     NOT NULL,
    created TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires TIMESTAMP       NULL     DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES simpl.users (id) ON DELETE CASCADE,
    INDEX idx_user_type_expires (user_id, type, expires)
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- roles
--

CREATE TABLE simpl.roles
(
    id   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50)       NOT NULL UNIQUE
) ENGINE = InnoDB,
  AUTO_INCREMENT = 3;

INSERT INTO simpl.roles (id, name)
VALUES (1, 'admin'),
       (2, 'user');

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- user_roles
--

CREATE TABLE simpl.user_roles
(
    user_id BIGINT UNSIGNED   NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES simpl.users (id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES simpl.roles (id) ON DELETE CASCADE,
    INDEX idx_role_user (role_id, user_id)
) ENGINE = InnoDB;

INSERT INTO simpl.user_roles (user_id, role_id)
VALUES (1, 1),
       (2, 2);

-- --------------------------------------------------------------------------------------------------------------------------------

COMMIT;
