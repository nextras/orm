SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC;
