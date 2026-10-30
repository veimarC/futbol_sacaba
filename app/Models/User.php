<?php

namespace App\Models;

use App\Models\Concerns\TieneWhatsappBoliviano;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TieneWhatsappBoliviano;

    // Constantes de roles para usar en todo el proyecto y evitar 'magic strings'.
    // El valor histórico en la base es "desarrollador"; en la interfaz se llama Administrador general.
    public const ROLE_DESARROLLADOR = 'desarrollador';

    public const ROLE_ORGANIZADOR = 'organizador';     // Crea campeonatos, valida equipos, etc.

    public const ROLE_DELEGADO = 'delegado';           // Registra jugadores de su equipo


    public const ROLE_PUBLICO = 'publico';             // Usuario público por defecto

    /**
     * Atributos que pueden ser asignados masivamente.
     */
    protected $fillable = [
        'name',
        'email',
        'whatsapp',
        'whatsapp_opt_in_at',
        'password',
        'rol', // se mantiene 'rol' en español para compatibilidad con la base actual
    ];

    /**
     * Atributos ocultos al serializar el modelo.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'whatsapp',
    ];

    /**
     * Casts de atributos.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'whatsapp_opt_in_at' => 'datetime',
    ];

    /**
     * Comprueba si el usuario tiene exactamente el rol indicado.
     */
    public function isRol(string $rol): bool
    {
        return $this->rol === $rol;
    }

    /**
     * Comprueba si el usuario tiene cualquiera de los roles indicados.
     * Acepta string (un solo rol) o array de roles.
     *
     * @param  array|string  $roles
     */
    public function hasAnyRol($roles): bool
    {
        if (is_string($roles)) {
            return $this->rol === $roles;
        }

        return in_array($this->rol, (array) $roles, true);
    }

    public function esDesarrollador(): bool
    {
        return $this->rol === self::ROLE_DESARROLLADOR;
    }

    public function hasRole(array|string $roles): bool
    {
        return $this->hasAnyRol($roles);
    }

    /**
     * Asigna un rol al usuario y guarda el cambio.
     *
     * @return $this
     */
    public function assignRol(string $rol): self
    {
        $this->rol = $rol;
        $this->save();

        return $this;
    }
}
