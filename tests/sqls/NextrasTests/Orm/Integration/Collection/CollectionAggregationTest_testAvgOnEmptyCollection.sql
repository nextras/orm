START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('Test 3', '2021-03-21'::date, 'http://www.example.com', NULL);
SELECT CURRVAL('public.authors_id_seq');
COMMIT;
SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books__AVG" ON (
    "authors"."id" = "books__AVG"."author_id"
  )
GROUP BY
  "authors"."id"
ORDER BY
  AVG("books__AVG"."price") ASC NULLS FIRST,
  "authors"."id" ASC;
