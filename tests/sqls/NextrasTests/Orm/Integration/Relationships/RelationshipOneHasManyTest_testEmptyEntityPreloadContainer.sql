SELECT "authors".* FROM "public"."authors" AS "authors" ORDER BY "authors"."id" ASC;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (2) ORDER BY "books"."id" DESC;
