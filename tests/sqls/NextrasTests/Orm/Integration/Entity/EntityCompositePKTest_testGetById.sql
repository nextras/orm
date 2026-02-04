SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE ("tag_followers"."author_id", "tag_followers"."tag_id") IN ((1, 3)) LIMIT 1;
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE ("tag_followers"."author_id", "tag_followers"."tag_id") IN ((3, 1)) LIMIT 1;
