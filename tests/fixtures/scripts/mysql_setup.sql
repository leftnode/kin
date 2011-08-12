DROP TABLE IF EXISTS fixture1;
CREATE TABLE fixture1 (
	id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	created DATETIME NOT NULL,
	updated DATETIME NULL DEFAULT NULL,
	name VARCHAR(64) NOT NULL,
	identifier VARCHAR(64) NOT NULL,
	status TINYINT(1) NOT NULL DEFAULT '0',
	INDEX (status)
) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;