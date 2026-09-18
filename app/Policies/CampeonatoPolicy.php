<?php

namespace App\Policies;

use App\Models\Campeonato;
use App\Models\User;

class CampeonatoPolicy
{
    public function before(User $user): ?bool { return $user->esDesarrollador() ? true : null; }
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Campeonato $campeonato): bool { return true; }
    public function create(User $user): bool { return $user->rol === User::ROLE_ORGANIZADOR; }
    public function update(User $user, Campeonato $campeonato): bool { return $this->esCreador($user, $campeonato); }
    public function delete(User $user, Campeonato $campeonato): bool { return $this->esCreador($user, $campeonato); }
    public function changeFormat(User $user, Campeonato $campeonato): bool { return $this->esCreador($user, $campeonato) && ! $campeonato->partidos()->exists(); }
    public function generateFixture(User $user, Campeonato $campeonato): bool { return $this->esCreador($user, $campeonato); }
    public function generateEliminatoria(User $user, Campeonato $campeonato): bool { return $this->esCreador($user, $campeonato); }

    private function esCreador(User $user, Campeonato $campeonato): bool
    {
        return $user->rol === User::ROLE_ORGANIZADOR && (int) $user->id === (int) $campeonato->usuario_creador;
    }
}
