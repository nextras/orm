TRUNCATE books_x_tags CASCADE;
TRUNCATE books CASCADE;
TRUNCATE tags CASCADE;
TRUNCATE eans CASCADE;
TRUNCATE authors CASCADE;
TRUNCATE publishers CASCADE;
TRUNCATE tag_followers CASCADE;
TRUNCATE contents CASCADE;
TRUNCATE users CASCADE;


INSERT INTO "authors" ("id", "name", "web", "born") VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO "authors" ("id", "name", "web", "born") VALUES (2, 'Writer 2', 'http://example.com/2', NULL);

SELECT setval('authors_id_seq', 2, true);


INSERT INTO "publishers" ("publisher_id", "name") VALUES (1, 'Nextras publisher A');
INSERT INTO "publishers" ("publisher_id", "name") VALUES (2, 'Nextras publisher B');
INSERT INTO "publishers" ("publisher_id", "name") VALUES (3, 'Nextras publisher C');

SELECT setval('publishers_publisher_id_seq', 3, true);


INSERT INTO "tags" ("id", "name", "is_global") VALUES (1, 'Tag 1', 'y');
INSERT INTO "tags" ("id", "name", "is_global") VALUES (2, 'Tag 2', 'y');
INSERT INTO "tags" ("id", "name", "is_global") VALUES (3, 'Tag 3', 'n');

SELECT setval('tags_id_seq', 3, true);


INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (1, 1, 1, 'Book 1', NULL, 1, NOW() + interval '4 seconds');
INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (2, 1, NULL, 'Book 2', NULL, 2, NOW() + interval '2 seconds');
INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (3, 2, 2, 'Book 3', NULL, 3, NOW() + interval '3 seconds');
INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (4, 2, 2, 'Book 4', 3, 1, NOW() + interval '1 seconds');

SELECT setval('books_id_seq', 4, true);


INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (1, 1);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (1, 2);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (2, 2);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (2, 3);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (3, 3);

INSERT INTO "tag_followers" ("tag_id", "author_id", "created_at") VALUES (1, 1, '2014-01-01 00:10:00');
INSERT INTO "tag_followers" ("tag_id", "author_id", "created_at") VALUES (3, 1, '2014-01-01 00:10:00');
INSERT INTO "tag_followers" ("tag_id", "author_id", "created_at") VALUES (2, 2, '2014-01-01 00:10:00');

INSERT INTO "contents" ("id", "type", "thread_id") VALUES (1, 'thread', NULL);
INSERT INTO "contents" ("id", "type", "thread_id") VALUES (2, 'comment', 1);
