SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 2));
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (2) ORDER BY "books"."id" DESC LIMIT 1;
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1);
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (2) ORDER BY "books"."id" DESC;
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1, 3);
