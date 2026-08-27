<?php

namespace Spaseossr\UnifiedApi\Tests\Stubs\App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'email', 'password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
