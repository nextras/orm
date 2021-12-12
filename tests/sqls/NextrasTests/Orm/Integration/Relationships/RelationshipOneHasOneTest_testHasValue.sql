SELECT "books".* FROM "books" AS "books" WHERE (("books"."id" = 1));
START TRANSACTION;
INSERT INTO "eans" ("code", "type") VALUES ('1234', 2);
SELECT CURRVAL('eans_id_seq');
UPDATE "books" SET "ean_id" = 1 WHERE "id" = 1;
COMMIT;
SELECT "eans".* FROM "eans" AS "eans" WHERE (("eans"."code" = '1234'));
SELECT "books".* FROM "books" AS "books" WHERE "books"."ean_id" IN (1);
