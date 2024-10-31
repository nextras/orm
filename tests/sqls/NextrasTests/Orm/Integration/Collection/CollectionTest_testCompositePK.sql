SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE ("tag_followers"."author_id", "tag_followers"."tag_id") IN ((2, 2));
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (2);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (2);
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE ("tag_followers"."author_id", "tag_followers"."tag_id") IN ((2, 2), (1, 3)) ORDER BY "tag_followers"."author_id" ASC;
SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" IN (3, 2);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1, 2);
SELECT "tag_followers".* FROM "tag_followers" AS "tag_followers" WHERE NOT (("tag_followers"."author_id", "tag_followers"."tag_id") IN ((2, 2), (1, 3)));
