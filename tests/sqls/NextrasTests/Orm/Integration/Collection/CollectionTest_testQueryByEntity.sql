SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE "books"."author_id" = 1) temp;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" = 1;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 2 LIMIT 1;
SELECT COUNT(*) AS count FROM (SELECT "books"."id" FROM "books" AS "books" WHERE "books"."author_id" IN (1, 2)) temp;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1, 2);
