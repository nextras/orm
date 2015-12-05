SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE books_x_tags;
TRUNCATE books;
TRUNCATE eans;
TRUNCATE tags;
TRUNCATE authors;
TRUNCATE publishers;
TRUNCATE tag_followers;
TRUNCATE contents;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO authors (id, name, web, born) VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO authors (id, name, web, born) VALUES (2, 'Writer 2', 'http://example.com/2', NULL);

INSERT INTO publishers (id, name) VALUES (1, 'Nextras publisher A');
INSERT INTO publishers (id, name) VALUES (2, 'Nextras publisher B');
INSERT INTO publishers (id, name) VALUES (3, 'Nextras publisher C');

INSERT INTO tags (id, name, is_global) VALUES (1, 'Tag 1', 'y');
INSERT INTO tags (id, name, is_global) VALUES (2, 'Tag 2', 'y');
INSERT INTO tags (id, name, is_global) VALUES (3, 'Tag 3', 'n');

INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at) VALUES (1, 1, 1, 'Book 1', NULL, 1, NOW());
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at) VALUES (2, 1, NULL, 'Book 2', NULL, 2, NOW());
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at) VALUES (3, 2, 2, 'Book 3', NULL, 3, NOW());
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at) VALUES (4, 2, 2, 'Book 4', 3, 1, NOW());

INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 1);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 3);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (3, 3);

INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (1, 1, '2014-01-01 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (3, 1, '2014-01-01 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (2, 2, '2014-01-01 00:10:00');

INSERT INTO contents (id, type, parent_id) VALUES (1, 'thread', NULL);
INSERT INTO contents (id, type, parent_id) VALUES (2, 'comment', 1);
