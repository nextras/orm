CREATE TABLE "authors"
(
    "id"                 SERIAL4      NOT NULL,
    "name"               varchar(50)  NOT NULL,
    "web"                varchar(100) NOT NULL,
    "born"               date DEFAULT NULL,
    "favorite_author_id" int,
    PRIMARY KEY ("id"),
    CONSTRAINT "authors_favorite_author" FOREIGN KEY ("favorite_author_id") REFERENCES authors ("id")
);


CREATE TABLE "publishers"
(
    "publisher_id" SERIAL4     NOT NULL,
    "name"         varchar(50) NOT NULL,
    PRIMARY KEY ("publisher_id")
);


CREATE TABLE "tags"
(
    "id"        SERIAL4     NOT NULL,
    "name"      varchar(50) NOT NULL,
    "is_global" char(1)     NOT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "eans"
(
    "id"   SERIAL4     NOT NULL,
    "code" varchar(50) NOT NULL,
    "type" int         NOT NULL,
    PRIMARY KEY ("id")
);

CREATE TABLE "currencies"
(
    "code" CHAR(3)     NOT NULL,
    "name" VARCHAR(50) NOT NULL,
    PRIMARY KEY ("code")
);

CREATE TABLE "books"
(
    "id"                  SERIAL4     NOT NULL,
    "author_id"           int         NOT NULL,
    "translator_id"       int,
    "title"               varchar(50) NOT NULL,
    "next_part"           int,
    "publisher_id"        int         NOT NULL,
    "published_at"        TIMESTAMP   NOT NULL,
    "printed_at"          TIMESTAMP,
    "ean_id"              int,
    "price"               int,
    "price_currency"      char(3),
    "orig_price_cents"    int,
    "orig_price_currency" char(3),
    PRIMARY KEY ("id"),
    CONSTRAINT "books_authors" FOREIGN KEY ("author_id") REFERENCES authors ("id"),
    CONSTRAINT "books_translator" FOREIGN KEY ("translator_id") REFERENCES authors ("id"),
    CONSTRAINT "books_next_part" FOREIGN KEY ("next_part") REFERENCES books ("id"),
    CONSTRAINT "books_publisher" FOREIGN KEY ("publisher_id") REFERENCES publishers ("publisher_id"),
    CONSTRAINT "books_ean" FOREIGN KEY ("ean_id") REFERENCES eans ("id"),
    CONSTRAINT "books_price_currency" FOREIGN KEY ("price_currency") REFERENCES currencies ("code"),
    CONSTRAINT "books_orig_price_currency" FOREIGN KEY ("orig_price_currency") REFERENCES currencies ("code")
);

CREATE INDEX "book_title" ON "books" ("title");


CREATE TABLE "books_x_tags"
(
    "book_id" int NOT NULL,
    "tag_id"  int NOT NULL,
    PRIMARY KEY ("book_id", "tag_id"),
    CONSTRAINT "books_x_tags_tag" FOREIGN KEY ("tag_id") REFERENCES "tags" ("id"),
    CONSTRAINT "books_x_tags_book" FOREIGN KEY ("book_id") REFERENCES "books" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE "tag_followers"
(
    "tag_id"     int         NOT NULL,
    "author_id"  int         NOT NULL,
    "created_at" timestamptz NOT NULL,
    PRIMARY KEY ("tag_id", "author_id"),
    CONSTRAINT "tag_followers_tag" FOREIGN KEY ("tag_id") REFERENCES "tags" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "tag_followers_author" FOREIGN KEY ("author_id") REFERENCES "authors" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE "contents"
(
    "id"         SERIAL4     NOT NULL,
    "type"       varchar(10) NOT NULL,
    "thread_id"  int,
    "replied_at" timestamptz,
    PRIMARY KEY ("id"),
    CONSTRAINT "contents_thread_id" FOREIGN KEY ("thread_id") REFERENCES "contents" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE "book_collections"
(
    "id"         int4         NOT NULL,
    "name"       varchar(255) NOT NULL,
    "updated_at" timestamptz,
    PRIMARY KEY ("id")
);


CREATE TABLE "photo_albums"
(
    "id"         serial4      NOT NULL,
    "title"      varchar(255) NOT NULL,
    "preview_id" int          NULL,
    PRIMARY KEY ("id")
);


CREATE TABLE "photos"
(
    "id"       serial4      NOT NULL,
    "title"    varchar(255) NOT NULL,
    "album_id" int          NOT NULL,
    PRIMARY KEY ("id"),
    CONSTRAINT "photos_album_id" FOREIGN KEY ("album_id") REFERENCES "photo_albums" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);


ALTER TABLE "photo_albums"
    ADD CONSTRAINT "photo_albums_preview_id" FOREIGN KEY ("preview_id") REFERENCES "photos" ("id") ON DELETE CASCADE ON UPDATE CASCADE;


CREATE TABLE "users"
(
    id serial4 NOT NULL,
    PRIMARY KEY ("id")
);


CREATE TABLE "user_stats"
(
    "user_id" int         NOT NULL,
    "date"    TIMESTAMPTZ NOT NULL,
    "value"   int         NOT NULL,
    PRIMARY KEY ("user_id", "date"),
    CONSTRAINT "user_stats_user_id" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE "users_x_users"
(
    "my_friends_id"      int NOT NULL,
    "friends_with_me_id" int NOT NULL,
    PRIMARY KEY ("my_friends_id", "friends_with_me_id"),
    CONSTRAINT "my_friends_key" FOREIGN KEY ("my_friends_id") REFERENCES "users" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT "friends_with_me_key" FOREIGN KEY ("friends_with_me_id") REFERENCES "users" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE FUNCTION "book_collections_before"() RETURNS TRIGGER AS
$BODY$
BEGIN
    NEW."updated_at" = NOW();
    return NEW;
END;
$BODY$
    LANGUAGE 'plpgsql' VOLATILE;

CREATE TRIGGER "book_collections_before_insert_trigger"
    BEFORE INSERT
    ON "book_collections"
    FOR EACH ROW
EXECUTE PROCEDURE "book_collections_before"();

CREATE TRIGGER "book_collections_before_update_trigger"
    BEFORE UPDATE
    ON "book_collections"
    FOR EACH ROW
EXECUTE PROCEDURE "book_collections_before"();


CREATE TABLE "logs"
(
    "date"  TIMESTAMPTZ NOT NULL,
    "count" int         NOT NULL,
    PRIMARY KEY ("date")
);
