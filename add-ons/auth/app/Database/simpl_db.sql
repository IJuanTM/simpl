START TRANSACTION;

DROP SCHEMA if EXISTS simpl_db;
CREATE SCHEMA simpl_db DEFAULT CHARACTER SET utf8mb4;

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- users
--

CREATE TABLE simpl_db.users
(
  id          bigint UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200) NULL DEFAULT NULL,
  email       VARCHAR(100) NOT NULL UNIQUE,
  password    blob         NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_update TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active   tinyint      NOT NULL DEFAULT 1,
  deleted_at  TIMESTAMP             DEFAULT NULL
) ENGINE = InnoDB;

INSERT INTO simpl_db.users (email, password)
VALUES ('admin@example.com', '$2y$12$WEOZKzM9JmXBLDcBMbRJfunNuu9OKYbkWaXOp34noadW2IWH36x0a'),
       ('user@example.com', '$2y$12$KiJxiUKSmlp1OTohZmNqIOs3tGF7W1XLUh42zE8qpBnFuK7hggTH2');

-- passwords are 'admin' and 'user'

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- tokens
--

CREATE TABLE simpl_db.tokens
(
  user_id bigint UNSIGNED NOT NULL REFERENCES simpl_db.users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  token   blob        NOT NULL,
  type    VARCHAR(50) NOT NULL,
  created TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- roles
--

CREATE TABLE simpl_db.roles
(
  id   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE = InnoDB,
  AUTO_INCREMENT = 3;

INSERT INTO simpl_db.roles (id, name)
VALUES (1, 'admin'),
       (2, 'user');

-- --------------------------------------------------------------------------------------------------------------------------------

--
-- user_roles
--

CREATE TABLE simpl_db.user_roles
(
  user_id bigint UNSIGNED NOT NULL REFERENCES simpl_db.users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  role_id SMALLINT UNSIGNED NOT NULL DEFAULT 2 REFERENCES simpl_db.roles (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

INSERT INTO simpl_db.user_roles (user_id, role_id)
VALUES (1, 1),
       (2, 2);

-- --------------------------------------------------------------------------------------------------------------------------------

COMMIT;
