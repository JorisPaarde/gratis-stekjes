  <h1 class="page-title" id="verenigingen">Verenigingen van leden</h1>
  @if(count($verenigingen ?? []) === 0)
    <p class="muted">Nog geen verenigingen.</p>
  @else
    @php
      $verwijderbaar = fn ($v) => \App\Services\VerenigingVerwijdering::verwijderbaar($v);
      $heeftVerwijderbaar = collect($verenigingen)->contains($verwijderbaar);
    @endphp
    @if($heeftVerwijderbaar)
      {{-- Eigen formulier buiten de tabel: rijen bevatten al formulieren (geen geneste forms). --}}
      <form id="vereniging-bulk" method="post" action="{{ route('beheer.verenigingen.bulk-destroy.confirm') }}" class="beheer-bulk-balk">
        @csrf
        <button type="submit" class="btn btn-gevaar btn-klein">Selectie verwijderen</button>
        <span class="muted beheer-klein">Alleen verborgen verenigingen; daarna volgt een bevestiging.</span>
      </form>
    @endif
    <div class="beheer-table-wrap">
      <table class="beheer-table beheer-deelplekken-table">
        <thead>
          <tr>@if($heeftVerwijderbaar)<th class="beheer-check-col"><span class="visually-hidden">Selecteer</span></th>@endif<th>Naam</th><th>Plaats</th><th>Soort</th><th>Leden</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @foreach($verenigingen as $v)
            <tr>
              @if($heeftVerwijderbaar)
                <td class="beheer-check-col" data-label="Selecteer">
                  @if($verwijderbaar($v))
                    <label class="beheer-check">
                      <input type="checkbox" name="ids[]" value="{{ $v->id }}" form="vereniging-bulk">
                      <span class="visually-hidden">{{ $v->naam }}</span>
                    </label>
                  @endif
                </td>
              @endif
              <td data-label="Naam">
                @if($v->isApproved())
                  <a href="{{ $v->url() }}">{{ $v->naam }}</a>
                @else
                  {{ $v->naam }}
                @endif
                <form method="post" action="{{ route('beheer.verenigingen.naam', $v) }}" class="beheer-naam-form">
                  @csrf
                  <label for="vereniging-naam-{{ $v->id }}">Naam wijzigen</label>
                  <input id="vereniging-naam-{{ $v->id }}" type="text" name="naam" value="{{ (string) old('renamed_id') === (string) $v->id ? old('naam') : $v->naam }}" required maxlength="160" autocomplete="off">
                  <button type="submit" class="linkish">Opslaan</button>
                </form>
                @if($errors->has('naam') && (string) old('renamed_id') === (string) $v->id)
                  <p class="error" role="alert">{{ $errors->first('naam') }}</p>
                @endif
              </td>
              <td data-label="Plaats">{{ $v->plaats }}</td>
              <td data-label="Soort">{{ $v->typeLabel() }}</td>
              <td data-label="Leden">{{ $v->leden_count }}</td>
              <td data-label="Status"><span class="status {{ ['pending' => 'status--wacht', 'approved' => 'status--ok', 'hidden' => 'status--uit'][$v->status] ?? '' }}">{{ ['pending' => 'Wacht op controle', 'approved' => 'Zichtbaar', 'hidden' => 'Verborgen'][$v->status] ?? $v->status }}</span></td>
              <td class="beheer-actions">
                @foreach(['approved' => 'Goedkeuren', 'hidden' => 'Verbergen'] as $status => $label)
                  @if($v->status !== $status && ! ($status === 'approved' && $v->json_slug))
                    <form method="post" action="{{ route('beheer.verenigingen.status', $v) }}">
                      @csrf
                      <input type="hidden" name="status" value="{{ $status }}">
                      <button type="submit" class="linkish">{{ $label }}</button>
                    </form>
                  @endif
                @endforeach
                @if($verwijderbaar($v))
                  <a class="beheer-link-gevaar" href="{{ route('beheer.verenigingen.destroy.confirm', $v) }}">Verwijderen</a>
                @elseif($v->status === 'hidden' && $v->json_slug)
                  <span class="muted beheer-klein">Vaste lijst, blijft verborgen</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
