-- Creating table for demo purposes (julio@email.com)

CREATE TABLE IF NOT EXISTS dummy (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(25) NOT NULL
);

--@UNDO

DROP TABLE IF EXISTS dummy;