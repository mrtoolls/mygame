@include('errors.partials.nocturne', [
    'code' => '419',
    'title' => $pageTitle ?? 'Session expired',
    'message' => 'Your session timed out for security. Reload the page to get a fresh one, then try again.',
])
