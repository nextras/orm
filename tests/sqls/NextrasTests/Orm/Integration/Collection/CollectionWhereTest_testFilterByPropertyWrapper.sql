SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
START TRANSACTION;
INSERT INTO "eans" ("code", "type") VALUES ('123', 2);
SELECT CURRVAL('eans_id_seq');
UPDATE "books" SET "ean_id" = 1 WHERE "id" = 1;
SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 2));
INSERT INTO "eans" ("code", "type") VALUES ('456', 1);
SELECT CURRVAL('eans_id_seq');
UPDATE "books" SET "ean_id" = 2 WHERE "id" = 2;
COMMIT;
SELECT "eans".* FROM "eans" AS "eans";
SELECT "eans".* FROM "eans" AS "eans" WHERE (("eans"."type" = 2));
SELECT "eans".* FROM "eans" AS "eans" WHERE (("eans"."type" = 1));