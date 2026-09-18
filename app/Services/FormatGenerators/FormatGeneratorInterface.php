<?php

namespace App\Services\FormatGenerators;

use App\Models\Campeonato;

interface FormatGeneratorInterface
{
    public function generate(Campeonato $campeonato, array $options = []);
}
