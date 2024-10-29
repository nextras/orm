SELECT "authors".* FROM "public"."authors" AS "authors" WHERE (("authors"."id" = 1));
SELECT "publishers".* FROM "publishers" AS "publishers" WHERE (("publishers"."publisher_id" = 1));
START TRANSACTION;
INSERT INTO "eans" ("code", "type") VALUES ('1234', 2);
SELECT CURRVAL('public.eans_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('Games of Thrones I', 1, NULL, NULL, 1, 1, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "books"."id"
    FROM
      "books" AS "books"
      LEFT JOIN "eans" AS "ean" ON ("books"."ean_id" = "ean"."id")
    WHERE
      (
        ("ean"."code" = '1234')
      )
  ) temp;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "eans" AS "ean" ON ("books"."ean_id" = "ean"."id")
WHERE
  (
    ("ean"."code" = '1234')
  );

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "eans"."id"
    FROM
      "eans" AS "eans"
      LEFT JOIN "books" AS "book" ON ("eans"."id" = "book"."ean_id")
    WHERE
      (
        (
          "book"."title" = 'Games of Thrones I'
        )
      )
  ) temp;

SELECT
  "eans".*
FROM
  "eans" AS "eans"
  LEFT JOIN "books" AS "book" ON ("eans"."id" = "book"."ean_id")
WHERE
  (
    (
      "book"."title" = 'Games of Thrones I'
    )
  );
