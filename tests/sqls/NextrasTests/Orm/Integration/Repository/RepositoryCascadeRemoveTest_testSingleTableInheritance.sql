SELECT "contents".* FROM "contents" AS "contents" WHERE "contents"."id" = 2;
SELECT "contents".* FROM "contents" AS "contents" WHERE "contents"."id" IN (1);
SELECT "contents".* FROM "contents" AS "contents" WHERE "contents"."thread_id" IN (1);
START TRANSACTION;
DELETE FROM "contents" WHERE "id" = 2;
DELETE FROM "contents" WHERE "id" = 3;
DELETE FROM "contents" WHERE "id" = 1;
COMMIT;
