START TRANSACTION;
INSERT INTO "public"."authors" ("id", "name", "born_on", "web", "favorite_author_id") VALUES (444, 'Jon Snow', '2021-03-21 00:00:00.000000'::timestamp, 'http://nextras.cz', NULL);
COMMIT;
START TRANSACTION;
INSERT INTO "public"."authors" ("id", "name", "born_on", "web", "favorite_author_id") VALUES (444, 'The Imp', '2021-03-21 00:00:00.000000'::timestamp, 'http://nextras.cz/imp', NULL);
ROLLBACK;
START TRANSACTION;
INSERT INTO "public"."authors" ("id", "name", "born_on", "web", "favorite_author_id") VALUES (445, 'The Imp', '2021-03-21 00:00:00.000000'::timestamp, 'http://nextras.cz/imp', NULL);
COMMIT;
