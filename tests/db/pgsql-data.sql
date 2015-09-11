TRUNCATE books_x_tags CASCADE;
TRUNCATE books CASCADE;
TRUNCATE tags CASCADE;
TRUNCATE authors CASCADE;
TRUNCATE publishers CASCADE;
TRUNCATE tag_followers CASCADE;
TRUNCATE contents CASCADE;


INSERT INTO "authors" ("id", "name", "web", "born") VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO "authors" ("id", "name", "web", "born") VALUES (2, 'Writer 2', 'http://example.com/2', NULL);

SELECT setval('authors_id_seq', 2, TRUE);


INSERT INTO "publishers" ("id", "name") VALUES (1, 'Nextras publisher');

SELECT setval('publishers_id_seq', 1, TRUE);


INSERT INTO "tags" ("id", "name") VALUES (1, 'Tag 1');
INSERT INTO "tags" ("id", "name") VALUES (2, 'Tag 2');
INSERT INTO "tags" ("id", "name") VALUES (3, 'Tag 3');

SELECT setval('tags_id_seq', 3, TRUE);


INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (1, 1, 1, 'Book 1', NULL, 1, NOW());
INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (2, 1, NULL, 'Book 2', NULL, 1, NOW());
INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (3, 2, 2, 'Book 3', NULL, 1, NOW());
INSERT INTO "books" ("id", "author_id", "translator_id", "title", "next_part", "publisher_id", "published_at") VALUES (4, 2, 2, 'Book 4', 3, 1, NOW());

SELECT setval('books_id_seq', 4, TRUE);


INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (1, 1);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (1, 2);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (2, 2);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (2, 3);
INSERT INTO "books_x_tags" ("book_id", "tag_id") VALUES (3, 3);

INSERT INTO "tag_followers" ("tag_id", "author_id", "created_at") VALUES (1, 1, '2014-01-01 00:10:00');
INSERT INTO "tag_followers" ("tag_id", "author_id", "created_at") VALUES (3, 1, '2014-01-01 00:10:00');
INSERT INTO "tag_followers" ("tag_id", "author_id", "created_at") VALUES (2, 2, '2014-01-01 00:10:00');

INSERT INTO "contents" ("id", "type", "parent_id") VALUES (1, 'thread', NULL);
INSERT INTO "contents" ("id", "type", "parent_id") VALUES (2, 'comment', 1);
