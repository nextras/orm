CREATE TABLE authors (
	id int NOT NULL AUTO_INCREMENT,
	name varchar(50) NOT NULL,
	web varchar(100) NOT NULL,
	born date DEFAULT NULL,
	PRIMARY KEY(id)
) AUTO_INCREMENT=2;


CREATE TABLE publishers (
	id int NOT NULL AUTO_INCREMENT,
	name varchar(50) NOT NULL,
	PRIMARY KEY(id)
) AUTO_INCREMENT=1;


CREATE TABLE tags (
	id int NOT NULL AUTO_INCREMENT,
	name varchar(50) NOT NULL,
	PRIMARY KEY (id)
) AUTO_INCREMENT=4;

CREATE TABLE eans (
	id int NOT NULL AUTO_INCREMENT,
	code varchar(50) NOT NULL,
	PRIMARY KEY(id)
) AUTO_INCREMENT=1;

CREATE TABLE books (
	id int NOT NULL AUTO_INCREMENT,
	author_id int NOT NULL,
	translator_id int,
	title varchar(50) NOT NULL,
	next_part int,
	publisher_id int NOT NULL,
	ean_id int,
	PRIMARY KEY (id),
	CONSTRAINT books_authors FOREIGN KEY (author_id) REFERENCES authors (id),
	CONSTRAINT books_translator FOREIGN KEY (translator_id) REFERENCES authors (id),
	CONSTRAINT books_next_part FOREIGN KEY (next_part) REFERENCES books (id),
	CONSTRAINT books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id),
	CONSTRAINT books_ena FOREIGN KEY (ean_id) REFERENCES eans (id)
) AUTO_INCREMENT=4;

CREATE INDEX book_title ON books (title);


CREATE TABLE books_x_tags (
	book_id int NOT NULL,
	tag_id int NOT NULL,
	PRIMARY KEY (book_id, tag_id),
	CONSTRAINT books_x_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id),
	CONSTRAINT books_x_tags_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
);


CREATE TABLE tag_followers (
	tag_id int NOT NULL,
	author_id  int NOT NULL,
	created_at  datetime NOT NULL,
	PRIMARY KEY (tag_id, author_id),
	CONSTRAINT tag_followers_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT tag_followers_author FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE contents (
	id int NOT NULL,
	type varchar(10) NOT NULL,
	parent_id int,
	PRIMARY KEY (id),
	CONSTRAINT contents_parent_id FOREIGN KEY (parent_id) REFERENCES contents (id) ON DELETE CASCADE ON UPDATE CASCADE
);
