<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * User-centric permission check wrapper.
     *
     * Authorization rule: permissions are user-centric; roles are templates only.
     *
     * This intentionally checks DIRECT permissions only (not role-derived permissions)
     * so a user can be customized without role side-effects.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getDirectPermissions()->contains('name', $permission);
    }

    /**
     * Direct-only variant of Spatie's hasAnyPermission.
     *
     * Supports both:
     * - $user->hasAnyPermission('a', 'b')
     * - $user->hasAnyPermission(['a', 'b'])
     */
    public function hasAnyPermission(...$permissions): bool
    {
        if (count($permissions) === 1 && is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission((string) $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * User-centric permission assignment wrapper.
     */
    public function givePermission(string $permission): self
    {
        $this->givePermissionTo($permission);

        return $this;
    }

    /**
     * Copy direct permissions from another user.
     *
     * Note: this syncs direct permissions only; it does not change roles.
     */
    public function copyPermissionsFrom(User $source): self
    {
        $permissions = $source->getDirectPermissions()->pluck('name')->all();
        $this->syncPermissions($permissions);

        return $this;
    }
}
