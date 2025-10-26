SELECT "tags".* FROM "tags" AS "tags" WHERE "tags"."id" = 1 LIMIT 1;
SELECT
  "books_x_tags"."book_id",
  "books_x_tags"."tag_id"
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."book_id" = "books"."id"
  )
WHERE
  ("author"."id" = 1)
  AND (
    "books_x_tags"."tag_id" IN (1)
  );

SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (1);
SELECT
  "books_x_tags"."tag_id",
  COUNT(
    DISTINCT "books_x_tags"."book_id"
  ) AS "count"
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."book_id" = "books"."id"
  )
WHERE
  ("author"."id" = 1)
  AND (
    "books_x_tags"."tag_id" IN (1)
  )
GROUP BY
  "books_x_tags"."tag_id";

SELECT
  DISTINCT *
FROM
  (
    SELECT
      "books_x_tags"."book_id",
      "books_x_tags"."tag_id"
    FROM
      "books" AS "books"
      LEFT JOIN "public"."authors" AS "author" ON (
        "books"."author_id" = "author"."id"
      )
      LEFT JOIN "tag_followers" AS "author_tagFollowers_any" ON (
        "author"."id" = "author_tagFollowers_any"."author_id"
      )
      LEFT JOIN "public"."authors" AS "author_tagFollowers_author_any" ON (
        "author_tagFollowers_any"."author_id" = "author_tagFollowers_author_any"."id"
      )
      LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
        "books_x_tags"."book_id" = "books"."id"
      )
    WHERE
      (
        "author_tagFollowers_author_any"."id" = 1
      )
      AND (
        "books_x_tags"."tag_id" IN (1)
      )
    GROUP BY
      "books"."id",
      "books"."title",
      "books_x_tags"."book_id",
      "books_x_tags"."tag_id"
    ORDER BY
      "books"."title" ASC
  ) AS "__tmp";

SELECT "books".* FROM "books" AS "books" WHERE "books"."id" IN (1);
SELECT
  "books_x_tags"."tag_id",
  COUNT(
    DISTINCT "books_x_tags"."book_id"
  ) AS "count"
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
  LEFT JOIN "tag_followers" AS "author_tagFollowers_any" ON (
    "author"."id" = "author_tagFollowers_any"."author_id"
  )
  LEFT JOIN "public"."authors" AS "author_tagFollowers_author_any" ON (
    "author_tagFollowers_any"."author_id" = "author_tagFollowers_author_any"."id"
  )
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."book_id" = "books"."id"
  )
WHERE
  (
    "author_tagFollowers_author_any"."id" = 1
  )
  AND (
    "books_x_tags"."tag_id" IN (1)
  )
GROUP BY
  "books"."id",
  "books"."title",
  "books_x_tags"."tag_id";
