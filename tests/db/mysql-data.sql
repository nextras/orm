SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE books_x_tags;
TRUNCATE publishers_x_tags;
TRUNCATE books;
TRUNCATE eans;
TRUNCATE tags;
TRUNCATE authors;
TRUNCATE publishers;
TRUNCATE tag_followers;
TRUNCATE contents;
TRUNCATE users_x_users;
TRUNCATE user_stats;
TRUNCATE users;
TRUNCATE logs;
TRUNCATE personal_data;
TRUNCATE admins;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO authors (id, name, web, born) VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO authors (id, name, web, born) VALUES (2, 'Writer 2', 'http://example.com/2', NULL);

INSERT INTO publishers (publisher_id, name) VALUES (1, 'Nextras publisher A');
INSERT INTO publishers (publisher_id, name) VALUES (2, 'Nextras publisher B');
INSERT INTO publishers (publisher_id, name) VALUES (3, 'Nextras publisher C');

INSERT INTO tags (id, name, is_global) VALUES (1, 'Tag 1', 'y');
INSERT INTO tags (id, name, is_global) VALUES (2, 'Tag 2', 'y');
INSERT INTO tags (id, name, is_global) VALUES (3, 'Tag 3', 'n');

INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, genre, published_at, price, price_currency) VALUES (1, 1, 1, 'Book 1', NULL, 1, 'sciFi', '2021-12-14 21:10:04', 50, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, genre, published_at, price, price_currency) VALUES (2, 1, NULL, 'Book 2', NULL, 2, 'horror', '2021-12-14 21:10:02', 150, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, genre, published_at, price, price_currency) VALUES (3, 2, 2, 'Book 3', NULL, 3, 'thriller', '2021-12-14 21:10:03', 20, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, genre, published_at, price, price_currency) VALUES (4, 2, 2, 'Book 4', 3, 1, 'romance', '2021-12-14 21:10:01', 220, 'CZK');

INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 1);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 3);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (3, 3);

INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (1, 1, '2014-01-01 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (3, 1, '2014-01-02 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (2, 2, '2014-01-03 00:10:00');

INSERT INTO contents (id, type, thread_id, replied_at) VALUES (1, 'thread', NULL, NULL);
INSERT INTO contents (id, type, thread_id, replied_at) VALUES (2, 'comment', 1, '2020-01-01 12:00:00');
INSERT INTO contents (id, type, thread_id, replied_at) VALUES (3, 'comment', 1, '2020-01-02 12:00:00');

INSERT INTO admins (id) VALUE (1);

INSERT INTO personal_data (id, admin_id, first_name, last_name) VALUES (1, 1, 'John', 'Doe');
