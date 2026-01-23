<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'public_key',
        'private_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(Follow::class, 'followed_user_id');
    }

    public function createKeys(): void
    {
        if ($this->private_key && $this->public_key) {
            return;
        }

        // Generate key pair
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $keyPair = openssl_pkey_new($config);
        openssl_pkey_export($keyPair, $privateKey);
        $publicKey = openssl_pkey_get_details($keyPair)['key'];

        $this->private_key = $privateKey;
        $this->public_key = $publicKey;
        $this->save();
    }
}
