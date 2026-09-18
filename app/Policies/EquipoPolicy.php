<?php

namespace App\Policies;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\User;

class EquipoPolicy
{
    public function before(User $user): ?bool { return $user->esDesarrollador() ? true : null; }
    public function viewAny(User $user): bool { return true; }
    /**
     * Las fichas pueden consultarse dentro de la plataforma.
     * Las acciones de edición y eliminación siguen limitadas al propietario,
     * al organizador responsable o al desarrollador mediante los métodos siguientes.
     */
    public function view(User $user, Equipo $equipo): bool
    {
        return true;
    }

    public function create(User $user): bool { return $user->rol === User::ROLE_DELEGADO; }

    public function update(User $user, Equipo $equipo): bool
    {
        // El organizador sólo puede corregir equipos inscritos en sus propios
        // campeonatos; no basta con que el equipo exista en la plataforma.
        return $this->puedeDelegado($user, $equipo)
            || $this->perteneceACampeonatoDelOrganizador($user, $equipo);
    }

    public function delete(User $user, Equipo $equipo): bool
    {
        return $this->puedeDelegado($user, $equipo)
            || $this->perteneceACampeonatoDelOrganizador($user, $equipo);
    }

    private function puedeDelegado(User $user, Equipo $equipo): bool
    {
        return $user->rol === User::ROLE_DELEGADO
            && (int) $user->id === (int) $equipo->delegado_id;
    }

    private function perteneceACampeonatoDelOrganizador(User $user, Equipo $equipo): bool
    {
        if ($user->rol !== User::ROLE_ORGANIZADOR) {
            return false;
        }

        // La tabla pivote es la fuente actual. El fallback permite autorizar
        // registros antiguos que aún sólo tienen campeonato_id.
        return $equipo->campeonatos()
                ->where('campeonatos.usuario_creador', $user->id)
                ->exists()
            || ($equipo->campeonato_id !== null && Campeonato::query()
                ->whereKey($equipo->campeonato_id)
                ->where('usuario_creador', $user->id)
                ->exists());
    }
}
