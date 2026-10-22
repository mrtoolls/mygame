{{-- Shared post-card styling (card shell, menu/edit/delete, media, lightbox, vote/reaction/
     comment bar, mini follow button). Included wherever the arena_post_cards partial is used -
     the Arena feed, a user's Arena profile, etc. - so every page that renders posts gets the
     same look and the cards never ship unstyled on a page this wasn't copy-pasted into. --}}
.nocturne-arena-post__identity a{color:inherit;text-decoration:none}
.nocturne-arena-post__identity strong:hover{color:var(--arena-accent)}
.nocturne-arena-mini-follow{flex:0 0 auto;padding:5px 11px;border:1px solid rgba(0,231,138,.4);border-radius:999px;background:rgba(0,231,138,.1);color:var(--arena-accent);font-size:10.5px;font-weight:800;cursor:pointer}
.nocturne-arena-mini-follow .label-following{display:none}
.nocturne-arena-mini-follow.is-following{border-color:var(--arena-line);background:transparent;color:var(--arena-muted)}
.nocturne-arena-mini-follow.is-following .label-follow{display:none}
.nocturne-arena-mini-follow.is-following .label-following{display:inline}

.nocturne-arena-post{padding:18px;border:1px solid var(--arena-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.14);transition:border-color .18s ease,transform .18s ease,box-shadow .18s ease}
@media(hover:hover) and (pointer:fine){
    .nocturne-arena-post:hover{border-color:rgba(0,231,138,.22);transform:translateY(-2px);box-shadow:0 20px 44px rgba(0,0,0,.22)}
}
.nocturne-arena-post__head{display:flex;align-items:center;gap:11px;margin-bottom:12px}
.nocturne-arena-post__avatar{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:50%;background:rgba(0,231,138,.12);color:var(--arena-accent);font-size:15px;font-weight:800;overflow:hidden;box-shadow:0 0 0 2px var(--arena-panel),0 0 0 3px rgba(0,231,138,.18)}
.nocturne-arena-post__avatar img{width:100%;height:100%;object-fit:cover}
.nocturne-arena-post__identity{flex:1;min-width:0;display:grid;gap:1px}
.nocturne-arena-post__identity strong{color:var(--arena-text);font-size:13.5px;font-weight:800}
.nocturne-arena-post__identity small{color:var(--arena-muted);font-size:11px}
.nocturne-arena-post__type{display:inline-flex;align-items:center;gap:5px;flex:none;padding:5px 11px;border-radius:999px;background:rgba(133,142,175,.12);color:var(--arena-muted);font-size:10.5px;font-weight:800}
.nocturne-arena-post__type--win{background:rgba(0,231,138,.14);color:var(--arena-accent)}
.nocturne-arena-post__type--challenge{background:rgba(249,115,22,.14);color:#ffa958}
.nocturne-arena-post__type--question{background:rgba(14,165,233,.14);color:#5fc5f5}
.nocturne-arena-post__menu-wrap{position:relative;flex:0 0 auto}
.nocturne-arena-post__menu-btn{display:grid;place-items:center;width:30px;height:30px;border:0;border-radius:50%;background:transparent;color:var(--arena-muted);font-size:17px;cursor:pointer}
.nocturne-arena-post__menu-btn:hover{background:rgba(255,255,255,.06);color:var(--arena-text)}
.nocturne-arena-post__menu{position:absolute;top:34px;right:0;z-index:5;display:grid;min-width:150px;padding:6px;border:1px solid var(--arena-line);border-radius:13px;background:var(--arena-panel);box-shadow:0 14px 30px rgba(0,0,0,.35)}
.nocturne-arena-post__menu[hidden]{display:none}
.nocturne-arena-post__menu button{display:flex;align-items:center;gap:9px;padding:9px 10px;border:0;border-radius:9px;background:transparent;color:var(--arena-text);font-size:12.5px;font-weight:700;text-align:left;cursor:pointer}
.nocturne-arena-post__menu button:hover{background:rgba(255,255,255,.06)}
.nocturne-arena-post__menu .nocturne-arena-post__delete{color:#ff6b86}
.nocturne-arena-post__edit-form{display:grid;gap:8px;margin:0 0 10px}
.nocturne-arena-post__edit-form textarea{width:100%;padding:10px;border:1px solid var(--arena-line);border-radius:12px;background:var(--arena-bg);color:var(--arena-text);font:inherit;font-size:13.5px;resize:vertical}
.nocturne-arena-post__edit-actions{display:flex;justify-content:flex-end;gap:8px}
.nocturne-arena-post__edit-actions button{min-height:34px;padding:0 16px;border:0;border-radius:10px;font-size:12px;font-weight:800;cursor:pointer}
.nocturne-arena-post__edit-cancel{background:rgba(255,255,255,.06);color:var(--arena-muted)}
.nocturne-arena-post__edit-actions button[type=submit]{background:var(--arena-accent);color:#062a1d}
.nocturne-arena-post__body-wrap{margin:0 0 4px}
.nocturne-arena-post__body{margin:0;color:#d7dafa;font-size:14px;line-height:1.65;white-space:pre-wrap;overflow-wrap:anywhere}
.nocturne-arena-post__body.is-clamped{max-height:4.95em;overflow:hidden}
.nocturne-arena-seemore{display:inline-block;margin-top:4px;padding:0;border:0;background:none;color:var(--arena-accent);font-size:12.5px;font-weight:800;cursor:pointer}
.nocturne-arena-post__image{position:relative;margin-top:10px;aspect-ratio:16/10;border-radius:14px;overflow:hidden;cursor:zoom-in}
.nocturne-arena-post__image img{display:block;width:100%;height:100%;object-fit:cover}
.nocturne-arena-post__video{position:relative;margin-top:10px;aspect-ratio:16/10;border-radius:14px;overflow:hidden;background:#000}
.nocturne-arena-post__video video{display:block;width:100%;height:100%;object-fit:cover}
.nocturne-arena-post__mute-btn{position:absolute;right:10px;bottom:10px;z-index:1;display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:50%;background:rgba(6,8,18,.65);color:#fff;font-size:16px;cursor:pointer}
.nocturne-arena-lightbox{position:fixed;inset:0;z-index:1400;display:grid;place-items:center;padding:24px;background:rgba(4,6,16,.92);backdrop-filter:blur(3px)}
.nocturne-arena-lightbox[hidden]{display:none}
.nocturne-arena-lightbox img{max-width:100%;max-height:92vh;border-radius:10px;object-fit:contain}
.nocturne-arena-lightbox__close{position:absolute;top:18px;right:18px;display:grid;place-items:center;width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,.1);color:#fff;font-size:18px;cursor:pointer}
.nocturne-arena-post__colored{display:grid;place-items:center;min-height:140px;padding:24px;border-radius:16px;text-align:center}
.nocturne-arena-post__colored p{margin:0;color:#fff;font-size:17px;font-weight:800;line-height:1.5;overflow-wrap:anywhere}
.nocturne-arena-post__colored--violet{background:linear-gradient(135deg,#7c3aed,#4c1d95)}
.nocturne-arena-post__colored--sunset{background:linear-gradient(135deg,#f97316,#be123c)}
.nocturne-arena-post__colored--forest{background:linear-gradient(135deg,#00e78a,#065f46)}
.nocturne-arena-post__colored--ocean{background:linear-gradient(135deg,#0ea5e9,#1e3a8a)}
.nocturne-arena-post__colored--rose{background:linear-gradient(135deg,#f472b6,#9d174d)}
.nocturne-arena-post__win{display:flex;align-items:center;gap:12px;margin-top:12px;padding:13px 15px;border:1px solid rgba(0,231,138,.22);border-radius:14px;background:rgba(0,231,138,.07)}
.nocturne-arena-post__win i{color:var(--arena-accent);font-size:22px}
.nocturne-arena-post__win strong{display:block;color:var(--arena-text);font-size:13.5px}
.nocturne-arena-post__win small{color:var(--arena-muted);font-size:11.5px}

.nocturne-arena-post__bar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--arena-line)}
.nocturne-arena-vote{display:flex;align-items:center;gap:8px}
.nocturne-arena-vote.is-disabled{opacity:.5}
.nocturne-arena-vote__btn{display:grid;place-items:center;width:30px;height:30px;border:1px solid var(--arena-line);border-radius:9px;background:var(--arena-bg);color:var(--arena-muted);font-size:13px;cursor:pointer}
.nocturne-arena-vote__btn:hover:not(:disabled){border-color:rgba(0,231,138,.4);color:var(--arena-accent)}
.nocturne-arena-vote__btn--up.is-active{background:rgba(0,231,138,.14);border-color:rgba(0,231,138,.5);color:var(--arena-accent)}
.nocturne-arena-vote__btn--down.is-active{background:rgba(255,92,122,.14);border-color:rgba(255,92,122,.5);color:#ff5c7a}
.nocturne-arena-vote__score{min-width:22px;text-align:center;color:var(--arena-text);font-size:13px;font-weight:800}

.nocturne-arena-reactions{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.nocturne-arena-reactions.is-disabled{opacity:.5}
.nocturne-arena-reaction{display:inline-flex;align-items:center;gap:4px;padding:5px 9px;border:1px solid var(--arena-line);border-radius:999px;background:var(--arena-bg);font-size:14px;cursor:pointer;transition:border-color .15s ease,transform .15s ease}
.nocturne-arena-reaction:hover:not(:disabled){border-color:rgba(0,231,138,.35);transform:translateY(-1px)}
.nocturne-arena-reaction.is-active{border-color:rgba(0,231,138,.4);background:rgba(0,231,138,.1)}
.nocturne-arena-reaction small{color:var(--arena-muted);font-size:10.5px;font-weight:800}
.nocturne-arena-reaction small.is-zero{display:none}

.nocturne-arena-comment-toggle{display:inline-flex;align-items:center;gap:6px;margin-left:auto;padding:6px 12px;border:1px solid var(--arena-line);border-radius:999px;background:transparent;color:var(--arena-muted);font-size:12.5px;font-weight:750;cursor:pointer}
.nocturne-arena-comment-toggle:hover{border-color:rgba(0,231,138,.4);color:var(--arena-accent)}

.nocturne-arena-comments-wrap{margin-top:14px;padding-top:14px;border-top:1px solid var(--arena-line)}
.nocturne-arena-comment-form{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.nocturne-arena-comment-form input,.nocturne-arena-reply-form input{flex:1;min-width:0;padding:9px 13px;border:1px solid var(--arena-line);border-radius:999px;background:var(--arena-bg);color:var(--arena-text);font:inherit;font-size:12.5px;outline:0}
.nocturne-arena-comment-form button,.nocturne-arena-reply-form button{flex:0 0 auto;display:grid;place-items:center;width:32px;height:32px;border:0;border-radius:50%;background:var(--arena-accent);color:#062a23;cursor:pointer}
.nocturne-arena-reply-form{display:flex;align-items:center;gap:8px;margin:8px 0 0 44px}

.nocturne-arena-comment-list{display:grid;gap:12px}
.nocturne-arena-comment-empty{margin:0;color:var(--arena-muted);font-size:12.5px;text-align:center;padding:10px 0}
.nocturne-arena-comment{display:flex;gap:10px}
.nocturne-arena-comment--reply{margin-top:8px}
.nocturne-arena-comment__avatar{display:grid;place-items:center;flex:0 0 32px;width:32px;height:32px;border-radius:50%;background:rgba(0,231,138,.12);color:var(--arena-accent);font-size:12px;font-weight:800;overflow:hidden}
.nocturne-arena-comment__avatar img{width:100%;height:100%;object-fit:cover}
.nocturne-arena-comment__body{flex:1;min-width:0}
.nocturne-arena-comment__bubble{display:inline-block;padding:9px 13px;border-radius:14px;background:var(--arena-bg)}
.nocturne-arena-comment__bubble strong{display:block;margin-bottom:2px;color:var(--arena-text);font-size:12.5px;font-weight:800}
.nocturne-arena-comment__bubble p{margin:0;color:#d7dafa;font-size:13px;line-height:1.5;overflow-wrap:anywhere}
.nocturne-arena-comment__meta{display:flex;align-items:center;gap:10px;margin:5px 0 0 13px}
.nocturne-arena-comment__meta span{color:var(--arena-muted);font-size:11px}
.nocturne-arena-comment__reply-btn{border:0;background:transparent;color:var(--arena-muted);font-size:11px;font-weight:800;cursor:pointer;padding:0}
.nocturne-arena-comment__reply-btn:hover{color:var(--arena-accent)}
.nocturne-arena-comment__replies{display:grid;gap:8px;margin-left:30px}
