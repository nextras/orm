/*!40102 SET storage_engine = InnoDB */;

DROP DATABASE IF EXISTS nextras_orm_test;
CREATE DATABASE IF NOT EXISTS nextras_orm_test;
USE nextras_orm_test;


CREATE TABLE authors (
	id int NOT NULL AUTO_INCREMENT,
	name varchar(30) NOT NULL,
	web varchar(100) NOT NULL,
	born date DEFAULT NULL,
	PRIMARY KEY(id)
) AUTO_INCREMENT=2;


CREATE TABLE tags (
	id int NOT NULL AUTO_INCREMENT,
	name varchar(20) NOT NULL,
	PRIMARY KEY (id)
) AUTO_INCREMENT=4;


CREATE TABLE books (
	id int NOT NULL AUTO_INCREMENT,
	author_id int NOT NULL,
	translator_id int,
	title varchar(50) NOT NULL,
	PRIMARY KEY (id),
	CONSTRAINT books_authors FOREIGN KEY (author_id) REFERENCES authors (id),
	CONSTRAINT books_translator FOREIGN KEY (translator_id) REFERENCES authors (id)
) AUTO_INCREMENT=4;

CREATE INDEX book_title ON books (title);


CREATE TABLE books_x_tags (
	book_id int NOT NULL,
	tag_id int NOT NULL,
	PRIMARY KEY (book_id, tag_id),
	CONSTRAINT books_x_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id),
	CONSTRAINT books_x_tags_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
);
