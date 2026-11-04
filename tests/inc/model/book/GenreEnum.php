<?php declare(strict_types=1);

namespace inc\model\book;


enum GenreEnum: string
{
	case HORROR = 'horror';
	case FANTASY = 'fantasy';
	case SCIFI = 'sciFi';
	case ROMANCE = 'romance';
	case THRILLER = 'thriller';
}
