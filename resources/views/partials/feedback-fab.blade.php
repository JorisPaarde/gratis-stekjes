@php
  $feedbackPagePath = '/'.ltrim(request()->path(), '/');
  if ($feedbackPagePath === '//') {
      $feedbackPagePath = '/';
  }
  if (mb_strlen($feedbackPagePath) > 160) {
      $feedbackPagePath = mb_substr($feedbackPagePath, 0, 160);
  }
  $feedbackTipId = 'feedback-fab-tip';
@endphp
<div class="feedback-fab-wrap" data-feedback-fab-wrap>
  <div
    id="{{ $feedbackTipId }}"
    class="feedback-fab-tip"
    role="tooltip"
    data-feedback-fab-tip
  >
    <p class="feedback-fab-tip-text">Gratis planten is nog in opbouw. Mis je iets of gaat er iets mis? Laat het me weten.</p>
    <button
      type="button"
      class="feedback-fab-tip-dismiss"
      data-feedback-tip-dismiss
      aria-label="Tip sluiten"
      hidden
    >×</button>
  </div>
  <a
    class="feedback-fab"
    href="{{ route('feedback.create', ['page_path' => $feedbackPagePath]) }}"
    data-feedback-fab
    aria-describedby="{{ $feedbackTipId }}"
  >
    <x-icon name="bericht" :size="18" />
    <span>Feedback</span>
  </a>
</div>
