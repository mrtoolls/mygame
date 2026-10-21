@include('errors.partials.nocturne', [
    'code' => '500',
    'title' => $pageTitle ?? 'Something went wrong',
    'message' => "We hit a snag on our end. Our team has been notified - try reloading, or head back home.",
])
