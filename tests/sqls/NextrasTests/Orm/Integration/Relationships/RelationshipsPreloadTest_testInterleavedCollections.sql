SELECT "books".* FROM "books" AS "books" WHERE "books"."id" <= 2 ORDER BY "books"."id" ASC;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" > 2 ORDER BY "books"."id" ASC;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (1);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (2);
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" IN (2);
