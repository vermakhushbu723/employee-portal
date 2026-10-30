<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ready e-mail (admin manages them under Email Templates). Placeholders filled when used:
 * {Name} {Title} {Date} {Time} {Link} {Purpose} {Employee} {Company}
 */
class EmailTemplate extends Model
{
    public const CATEGORIES = [
        'meeting' => ['Meeting', 'camera-video-fill', 'purple'],
        'interview' => ['Interview & Hiring', 'people-fill', 'info'],
        'sales' => ['Sales & Customer', 'graph-up-arrow', 'success'],
        'work' => ['Work & Attendance', 'briefcase-fill', 'warning'],
        'general' => ['Others', 'three-dots', 'secondary'],
    ];

    public const PLACEHOLDERS = ['{Name}', '{Title}', '{Date}', '{Time}', '{Link}', '{Purpose}', '{Employee}', '{Company}'];

    protected $fillable = ['name', 'category', 'subject', 'body', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function categoryMeta(): array
    {
        return self::CATEGORIES[$this->category] ?? self::CATEGORIES['general'];
    }

    /** For the compose popups: [{id, name, category, subject, body}] */
    public static function forCompose(): array
    {
        return self::where('active', true)->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'category', 'subject', 'body'])->toArray();
    }
}
