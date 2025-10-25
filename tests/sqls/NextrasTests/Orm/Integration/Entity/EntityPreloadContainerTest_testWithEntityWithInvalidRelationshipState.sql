SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (1);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (1);
START TRANSACTION;
DELETE FROM "tag_followers" WHERE "author_id" = 1 AND "tag_id" = 1;
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
