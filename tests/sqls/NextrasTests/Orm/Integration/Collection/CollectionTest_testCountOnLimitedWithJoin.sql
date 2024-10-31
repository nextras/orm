SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "books"."id"
    FROM
      "books" AS "books"
      LEFT JOIN "public"."authors" AS "author" ON (
        "books"."author_id" = "author"."id"
      )
    WHERE
      "author"."name" = 'Writer 1'
    ORDER BY
      "books"."id" ASC
    LIMIT
      5
  ) temp;

SELECT
  COUNT(*) AS count
FROM
  (
    SELECT
      "tag_followers"."tag_id",
      "tag_followers"."author_id"
    FROM
      "tag_followers" AS "tag_followers"
      LEFT JOIN "tags" AS "tag" ON (
        "tag_followers"."tag_id" = "tag"."id"
      )
    WHERE
      "tag"."name" = 'Tag 1'
    ORDER BY
      "tag_followers"."tag_id" ASC
    LIMIT
      3
  ) temp;
