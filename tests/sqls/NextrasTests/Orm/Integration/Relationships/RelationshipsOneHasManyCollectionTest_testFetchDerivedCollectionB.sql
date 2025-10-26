SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" = 1 LIMIT 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC LIMIT 1;
