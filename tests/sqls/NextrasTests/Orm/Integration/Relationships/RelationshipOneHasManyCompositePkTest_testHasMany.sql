SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (1);
SELECT "author_id", COUNT(DISTINCT "__nextras_fix_tag_id_count") as "count" FROM (SELECT "tag_followers"."author_id", "tag_followers"."tag_id" AS "__nextras_fix_tag_id_count" FROM "tag_followers" AS "tag_followers" WHERE "tag_followers"."author_id" IN (1)) AS "temp" GROUP BY "author_id";
