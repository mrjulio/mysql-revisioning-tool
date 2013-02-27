INSERT INTO dummy VALUES (1, 'one'), (2, 'two');
INSERT INTO dummy VALUES (3, 'three'), (4, 'four');

--@UNDO

TRUNCATE TABLE dummy;