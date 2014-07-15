SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE books_x_tags;
TRUNCATE books;
TRUNCATE tags;
TRUNCATE authors;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO authors (id, name, web, born) VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO authors (id, name, web, born) VALUES (2, 'Writer 2', 'http://example.com/2', NULL);

INSERT INTO tags (id, name) VALUES (1, 'Tag 1');
INSERT INTO tags (id, name) VALUES (2, 'Tag 2');
INSERT INTO tags (id, name) VALUES (3, 'Tag 3');

INSERT INTO books (id, author_id, translator_id, title) VALUES (1, 1, 1, 'Book 1');
INSERT INTO books (id, author_id, translator_id, title) VALUES (2, 1, NULL, 'Book 2');
INSERT INTO books (id, author_id, translator_id, title) VALUES (3, 2, 2, 'Book 3');
INSERT INTO books (id, author_id, translator_id, title) VALUES (4, 2, 2, 'Book 4');

INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 1);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 3);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (3, 3);
