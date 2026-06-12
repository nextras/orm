SELECT "books".* FROM "books" AS "books" WHERE ("books"."id" IN (1, 2, 3, 4)) AND ("books"."id" = 1) ORDER BY "books"."id" ASC;
SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (1, 2, 3, 4) ORDER BY "books"."id" ASC;
