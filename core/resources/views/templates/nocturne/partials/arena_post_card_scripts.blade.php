{{-- Shared post-card behavior (See more clamp, menu/share/edit/delete, lightbox, feed video
     autoplay+mute, vote, reactions, comments/replies, mini follow). Must be included inside an
     existing `(function($) { ... })(jQuery);` closure - it relies on $ and declares `setupSeeMore`
     plus a few url-template vars used only within this chunk. Shared by every page that renders
     the arena_post_cards partial (the Arena feed, a user's Arena profile, etc.) so they all stay
     interactive and in sync instead of drifting apart as hand-copied duplicates. --}}
// Clamp long post text to ~3 lines with a "See more" toggle - only added when the text
// actually overflows that height, never on short posts.
function setupSeeMore(scope) {
    (scope || document).querySelectorAll('[data-seemore]:not([data-seemore-ready])').forEach(function(body) {
        body.setAttribute('data-seemore-ready', '1');
        body.classList.add('is-clamped');
        var button = body.nextElementSibling;
        if (!button || !button.classList.contains('nocturne-arena-seemore')) return;
        if (body.scrollHeight > body.clientHeight + 2) {
            button.hidden = false;
            button.addEventListener('click', function() {
                var expanded = !body.classList.contains('is-clamped');
                body.classList.toggle('is-clamped', expanded);
                button.textContent = expanded ? "{{ __('See more') }}" : "{{ __('See less') }}";
            });
            button.textContent = "{{ __('See more') }}";
        } else {
            body.classList.remove('is-clamped');
        }
    });
}
setupSeeMore(document);

var csrfToken = "{{ csrf_token() }}";
var voteUrlTpl = "{{ route('user.arena.vote', ['post' => '__ID__']) }}";
var reactUrl = "{{ route('user.arena.react') }}";
var commentsIndexUrlTpl = "{{ route('arena.comments.index', ['post' => '__ID__']) }}";
var commentsStoreUrlTpl = "{{ route('user.arena.comments.store', ['post' => '__ID__']) }}";

// Post menu (Share / Edit / Delete) - event-delegated since posts load via AJAX.
$(document).on('click', '.nocturne-arena-post__menu-btn', function(event) {
    event.stopPropagation();
    var menu = $(this).siblings('.nocturne-arena-post__menu');
    var isHidden = menu.prop('hidden');
    $('.nocturne-arena-post__menu').prop('hidden', true);
    menu.prop('hidden', !isHidden);
});
$(document).on('click', function() { $('.nocturne-arena-post__menu').prop('hidden', true); });
$(document).on('click', '.nocturne-arena-post__menu', function(event) { event.stopPropagation(); });

$(document).on('click', '.nocturne-arena-post__share', function() {
    var url = $(this).data('url');
    if (navigator.share) {
        navigator.share({ url: url }).catch(function() {});
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            window.notify ? notify('success', "{{ __('Link copied to clipboard') }}") : alert("{{ __('Link copied to clipboard') }}");
        });
    } else {
        window.prompt("{{ __('Copy this link:') }}", url);
    }
});

$(document).on('click', '.nocturne-arena-post__edit', function() {
    var article = $(this).closest('.nocturne-arena-post');
    article.find('.nocturne-arena-post__edit-form').prop('hidden', false);
    article.find('.nocturne-arena-post__body-wrap, .nocturne-arena-post__colored').hide();
    article.find('.nocturne-arena-post__edit-form textarea').trigger('focus');
});
$(document).on('click', '.nocturne-arena-post__edit-cancel', function() {
    var article = $(this).closest('.nocturne-arena-post');
    article.find('.nocturne-arena-post__edit-form').prop('hidden', true);
    article.find('.nocturne-arena-post__body-wrap, .nocturne-arena-post__colored').show();
});
$(document).on('submit', '.nocturne-arena-post__edit-form', function(event) {
    event.preventDefault();
    var form = $(this);
    $.post(form.attr('action'), form.serialize()).done(function() { location.reload(); });
});

$(document).on('click', '.nocturne-arena-post__delete', function() {
    if (!confirm("{{ __('Delete this post? This cannot be undone.') }}")) return;
    var url = $(this).data('url');
    var article = $(this).closest('.nocturne-arena-post');
    $.ajax({ url: url, method: 'DELETE', data: { _token: csrfToken } })
        .done(function() { article.fadeOut(200, function() { article.remove(); }); });
});

// Fixed-size media frame - click a post image to open it full size.
var lightbox = $('<div class="nocturne-arena-lightbox" hidden><button type="button" class="nocturne-arena-lightbox__close"><i class="ph-bold ph-x"></i></button><img src="" alt=""></div>').appendTo('body');
$(document).on('click', '[data-lightbox] img', function() {
    lightbox.find('img').attr('src', $(this).attr('src'));
    lightbox.prop('hidden', false);
});
lightbox.on('click', function(event) {
    if (event.target === this || $(event.target).closest('.nocturne-arena-lightbox__close').length) {
        lightbox.prop('hidden', true).find('img').attr('src', '');
    }
});

// Feed videos: muted + play only while actually visible (Facebook/Instagram pattern),
// tap the speaker to unmute. Never plays with sound unless the viewer asks for it.
if ('IntersectionObserver' in window) {
    var videoObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            var video = entry.target;
            if (entry.isIntersecting) { video.play().catch(function() {}); }
            else { video.pause(); }
        });
    }, { threshold: 0.6 });
    function observeFeedVideos(scope) {
        (scope || document).querySelectorAll('[data-feed-video]:not([data-observed])').forEach(function(video) {
            video.setAttribute('data-observed', '1');
            videoObserver.observe(video);
        });
    }
    observeFeedVideos(document);
    window.arenaObserveFeedVideos = observeFeedVideos;
}
$(document).on('click', '.nocturne-arena-post__mute-btn', function() {
    var video = $(this).siblings('[data-feed-video]')[0];
    if (!video) return;
    video.muted = !video.muted;
    $(this).find('i').attr('class', video.muted ? 'ph-fill ph-speaker-x' : 'ph-fill ph-speaker-high');
});

// Upvote / downvote
$(document).on('click', '.nocturne-arena-vote__btn:not(:disabled)', function() {
    var btn = $(this);
    var article = btn.closest('.nocturne-arena-post');
    var postId = article.data('post-id');
    var value = btn.data('vote');
    $.post(voteUrlTpl.replace('__ID__', postId), { value: value, _token: csrfToken })
        .done(function(res) {
            article.find('.nocturne-arena-vote__score').text(res.upvotes - res.downvotes);
            article.find('.nocturne-arena-vote__btn').removeClass('is-active');
            if (res.mine === 1) article.find('.nocturne-arena-vote__btn--up').addClass('is-active');
            if (res.mine === -1) article.find('.nocturne-arena-vote__btn--down').addClass('is-active');
        });
});

// Emoji reactions
$(document).on('click', '.nocturne-arena-reaction:not(:disabled)', function() {
    var btn = $(this);
    var wrap = btn.closest('.nocturne-arena-reactions');
    var emoji = btn.data('emoji');
    $.post(reactUrl, { reactable_type: wrap.data('reactable-type'), reactable_id: wrap.data('reactable-id'), emoji: emoji, _token: csrfToken })
        .done(function(res) {
            wrap.find('.nocturne-arena-reaction').each(function() {
                var e = $(this).data('emoji');
                var count = (res.counts && res.counts[e]) || 0;
                $(this).toggleClass('is-active', res.mine === e);
                $(this).find('small').text(count).toggleClass('is-zero', count === 0);
            });
        });
});

// Comment toggle: lazy-load on first open, then just show/hide
$(document).on('click', '.nocturne-arena-comment-toggle', function() {
    var btn = $(this);
    var article = btn.closest('.nocturne-arena-post');
    var wrap = article.find('.nocturne-arena-comments-wrap');
    if (!wrap.data('loaded')) {
        $.ajax({ url: commentsIndexUrlTpl.replace('__ID__', btn.data('post-id')), headers: { 'Accept': 'application/json' } })
            .done(function(res) {
                wrap.html(res.html).data('loaded', true).prop('hidden', false);
            });
    } else {
        wrap.prop('hidden', !wrap.prop('hidden'));
    }
});

// Top-level comment submit
$(document).on('submit', '.nocturne-arena-comment-form', function(e) {
    e.preventDefault();
    var form = $(this);
    var postId = form.data('post-id');
    var input = form.find('input[name=body]');
    var body = $.trim(input.val());
    if (!body) return;
    $.post(commentsStoreUrlTpl.replace('__ID__', postId), { body: body, _token: csrfToken })
        .done(function(res) {
            form.siblings('.nocturne-arena-comment-list').find('.nocturne-arena-comment-empty').remove();
            form.siblings('.nocturne-arena-comment-list').append(res.html);
            input.val('');
            form.closest('.nocturne-arena-post').find('.nocturne-arena-comment-count').text(res.comments_count);
        });
});

// Reply toggle
$(document).on('click', '.nocturne-arena-comment__reply-btn', function() {
    var btn = $(this);
    var existing = btn.closest('.nocturne-arena-comment__body').children('.nocturne-arena-reply-form');
    if (existing.length) { existing.remove(); return; }
    var form = $('<form class="nocturne-arena-reply-form"><input type="text" name="body" maxlength="1000" placeholder="{{ __('Write a reply...') }}"><button type="submit"><i class="ph-bold ph-paper-plane-tilt"></i></button></form>');
    form.data('parent-id', btn.data('reply-to'));
    btn.closest('.nocturne-arena-comment__meta').after(form);
    form.find('input').trigger('focus');
});

// Reply submit
$(document).on('submit', '.nocturne-arena-reply-form', function(e) {
    e.preventDefault();
    var form = $(this);
    var input = form.find('input[name=body]');
    var body = $.trim(input.val());
    if (!body) return;
    var postId = form.closest('.nocturne-arena-post').data('post-id');
    var parentId = form.data('parent-id');
    $.post(commentsStoreUrlTpl.replace('__ID__', postId), { body: body, parent_id: parentId, _token: csrfToken })
        .done(function(res) {
            $('[data-replies-for="' + parentId + '"]').append(res.html);
            form.remove();
            $('.nocturne-arena-post[data-post-id="' + postId + '"]').find('.nocturne-arena-comment-count').text(res.comments_count);
        });
});

// Mini follow button on each post's author
$(document).on('click', '.nocturne-arena-mini-follow', function() {
    var btn = $(this);
    var userId = btn.data('user-id');
    var following = btn.data('following') == '1';
    var url = "{{ route('user.arena.follow.store', ['user' => '__ID__']) }}".replace('__ID__', userId);
    $.ajax({ url: url, method: following ? 'DELETE' : 'POST', data: { _token: csrfToken } })
        .done(function(res) {
            $('.nocturne-arena-mini-follow[data-user-id="' + userId + '"]').toggleClass('is-following', res.following).data('following', res.following ? '1' : '0');
        });
});
