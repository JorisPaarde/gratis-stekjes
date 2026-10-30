@php
  $feedbackPagePath = '/'.ltrim(request()->path(), '/');
  if ($feedbackPagePath === '//') {
      $feedbackPagePath = '/';
  }
  if (mb_strlen($feedbackPagePath) > 160) {
      $feedbackPagePath = mb_substr($feedbackPagePath, 0, 160);
  }
@endphp
<a
  class="feedback-fab"
  href="{{ route('feedback.create', ['page_path' => $feedbackPagePath]) }}"
  data-feedback-fab
>
  <x-icon name="bericht" :size="18" />
  <span>Feedback</span>
</a>
