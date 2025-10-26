SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE ("tag_followers"."tag_id" = 3) AND ("tag_followers"."author_id" = 1) LIMIT 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
START TRANSACTION;
DELETE FROM "tag_followers" WHERE "author_id" = 1 AND "tag_id" = 3;
COMMIT;
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (1);
SELECT "author_id", COUNT(DISTINCT "tag_followers"."tag_id") as "count" FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (1) GROUP BY "author_id";
