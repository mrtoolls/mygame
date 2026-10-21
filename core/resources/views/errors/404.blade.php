@include('errors.partials.nocturne', [
    'code' => '404',
    'title' => $pageTitle ?? 'Page not found',
    'message' => "The page you're looking for doesn't exist, may have moved, or is temporarily unavailable.",
])
