<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Docs Model
 *
 * @property-read   int    $id         Document ID
 *
 * @property        string $date       Date document posted
 * @property        string $title      Document title
 * @property        string $content    Document in HTML
 * @property        string $markdown   Document in MarkDown
 */
final class Docs extends Model
{
    protected $connection = 'default';
    protected $table = 'docs';
}
