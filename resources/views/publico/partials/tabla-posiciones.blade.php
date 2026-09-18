<div class="table-responsive">
    <table class="table public-table align-middle">
        <thead><tr><th>#</th><th>Equipo</th><th>PJ</th><th>PG</th><th>PE</th><th>PP</th><th>GF</th><th>GC</th><th>DG</th><th>PTS</th></tr></thead>
        <tbody>
            @foreach($filas as $posicion => $fila)
                <tr>
                    <td><span class="position-number">{{ $posicion + 1 }}</span></td>
                    <td class="fw-bold"><span class="table-club"><x-team-crest :equipo="$fila['equipo']" /><span>{{ $fila['equipo']->nombre }}</span></span></td>
                    <td>{{ $fila['pj'] }}</td><td>{{ $fila['pg'] }}</td><td>{{ $fila['pe'] }}</td><td>{{ $fila['pp'] }}</td>
                    <td>{{ $fila['gf'] }}</td><td>{{ $fila['gc'] }}</td><td>{{ $fila['dif'] }}</td><td><strong class="points">{{ $fila['pts'] }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
