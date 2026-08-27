<?php

namespace Spaseossr\UnifiedApi\Tests\Stubs\App;

use Illuminate\Foundation\Auth\User as Authenticatable;

class UserWithoutApiTokens extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
