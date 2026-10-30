            <div class="section-title">
                <div><span>Fase eliminatoria</span><h2>Llaves del campeonato</h2></div>
                <strong>El ganador avanza</strong>
            </div>
            <p class="bracket-help"><i class="bi bi-arrow-left-right"></i> Desliza horizontalmente para recorrer las rondas. Los ganadores avanzan hacia la derecha.</p>
            <div class="bracket-scroll" tabindex="0" aria-label="Cuadro de eliminación">
                {{-- Cada columna es una etapa. En pantallas pequeñas se muestran
                     una debajo de otra para mantener un orden de lectura claro. --}}
                @php
                    $serieFinal = $llaves->last()['series']->first();
                    $campeon = null;

                    if ($serieFinal && $serieFinal['completa']) {
                        $campeon = ($serieFinal['ganador_id'] ?? null) ? ((int) $serieFinal['ganador_id'] === (int) $serieFinal['equipo_a']?->id ? $serieFinal['equipo_a'] : $serieFinal['equipo_b']) : null;
                    }
                @endphp
                <div class="stage-bracket">
                    <header class="stage-title"><i class="bi bi-trophy-fill"></i><strong>CAMINO A LA COPA</strong></header>

                    <div class="stage-flow" data-bracket-flow data-bracket-width="{{ max(900, ($llaves->count() * 317) + 265) }}" data-bracket-height="{{ max(390, $llaves->first()['series']->count() * 145) }}">
                        <svg class="stage-connectors" aria-hidden="true"></svg>
                        @foreach($llaves as $indice => $ronda)
                            <section class="stage-column {{ $loop->last ? 'is-final' : '' }}" aria-label="{{ $ronda['nombre'] }}">
                                <header>
                                    <span>{{ $indice + 1 }}</span>
                                    <div>
                                        <small>Etapa {{ $indice + 1 }}</small>
                                        <h3>{{ $loop->last ? 'Gran final' : $ronda['nombre'] }}</h3>
                                    </div>
                                </header>
                                <div class="stage-matches">
                                    @foreach($ronda['series'] as $serie)
                                        @include('publico.partials.bracket-node', ['serie' => $serie])
                                    @endforeach
                                </div>
                            </section>

                            <div class="stage-arrow" aria-hidden="true">
                                <i class="bi bi-arrow-right"></i>
                                <span>{{ $loop->last ? 'Resultado final' : 'Ganadores avanzan' }}</span>
                            </div>
                        @endforeach

                        <div class="stage-outcomes">
                            <aside class="stage-champion {{ $campeon ? 'has-champion' : '' }}" aria-label="Campeón del campeonato">
                                <i class="bi bi-trophy-fill"></i>
                                @if($campeon)<x-team-crest :equipo="$campeon" />@endif
                                <span><small>Campeón</small><strong>{{ $campeon?->nombre ?? 'Por definir' }}</strong></span>
                            </aside>

                            @if($tercerLugar)
                                <section class="third-place-card" aria-label="Partido por el tercer lugar">
                                    <header><i class="bi bi-award-fill"></i><span><small>Partido de bronce</small><strong>Tercer lugar</strong></span></header>
                                    @include('publico.partials.bracket-node', ['serie' => $tercerLugar])
                                    <p>Lo disputan los perdedores de las semifinales.</p>
                                </section>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
