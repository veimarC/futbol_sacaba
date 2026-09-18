<div class="table-responsive">
    <table class="table table-dark table-striped table-hover mb-0">
        <thead>
            <tr class="text-center">
                <th>#</th>
                <th>Equipo</th>
                <th>PJ</th>
                <th>PG</th>
                <th>PE</th>
                <th>PP</th>
                <th>GF</th>
                <th>GC</th>
                <th>DIF</th>
                <th>PTS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $posicion => $fila)
                <tr class="text-center align-middle">
                    <td class="fw-bold">{{ $posicion + 1 }}</td>
                    <td class="text-start">
                        <div class="d-flex align-items-center gap-2">
                            @if($escudoUrl = $fila['equipo']->escudo_url)
                                <img src="{{ $escudoUrl }}" alt="Escudo de {{ $fila['equipo']->nombre }}" class="table-shield">
                            @else
                                <i class="bi bi-shield"></i>
                            @endif
                            <span>{{ $fila['equipo']->nombre }}</span>
                        </div>
                    </td>
                    <td>{{ $fila['pj'] }}</td>
                    <td>{{ $fila['pg'] }}</td>
                    <td>{{ $fila['pe'] }}</td>
                    <td>{{ $fila['pp'] }}</td>
                    <td>{{ $fila['gf'] }}</td>
                    <td>{{ $fila['gc'] }}</td>
                    <td class="{{ $fila['dif'] > 0 ? 'text-success' : ($fila['dif'] < 0 ? 'text-danger' : '') }}">{{ $fila['dif'] }}</td>
                    <td class="fw-bold fs-6">{{ $fila['pts'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
