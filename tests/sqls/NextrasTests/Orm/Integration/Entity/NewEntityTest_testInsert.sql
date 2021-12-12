START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born", "web", "favorite_author_id") VALUES ('Jon Snow', '2021-03-21 08:23:00.000000'::timestamp, 'http://nextras.cz', NULL);
SELECT CURRVAL('authors_id_seq');
COMMIT;
