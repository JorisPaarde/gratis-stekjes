  <h1 class="page-title" id="verenigingen">Verenigingen van leden</h1>
  @if(count($verenigingen ?? []) === 0)
    <p class="muted">Nog geen verenigingen.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead>
          <tr><th>Naam</th><th>Plaats</th><th>Soort</th><th>Leden</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @foreach($verenigingen as $v)
            <tr>
              <td data-label="Naam">@if($v->isApproved())<a href="{{ $v->url() }}">{{ $v->naam }}</a>@else{{ $v->naam }}@endif</td>
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
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
