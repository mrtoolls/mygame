@if (gs('multi_language'))
    @php
        $languages = App\Models\Language::get();
        $language = $languages->where('code', '!=', session('lang'));
        $activeLanguage = $languages->where('code', session('lang'))->first();
    @endphp
    <li class="{{ $class }}">
        <div class="language dropdown nocturne-lang">
            <button class="language-wrapper nocturne-lang__trigger" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Change language') }}" title="{{ __(@$activeLanguage->name ?: 'Language') }}">
                <span class="language_flag"><img src="{{ getImage(getFilePath('language') . '/' . @$activeLanguage->image, getFileSize('language')) }}" alt=""></span>
            </button>
            <div class="dropdown-menu langList_dropdow py-2">
                <ul class="langList">
                    @foreach ($language as $item)
                        <li class="language-list langSel" data-lang_code="{{ $item->code }}">
                            <div class="language_flag">
                                <img src="{{ getImage(getFilePath('language') . '/' . @$item->image, getFileSize('language')) }}" alt="flag">
                            </div>
                            <p class="language_text">{{ __(@$item->name) }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </li>
@endif

@push('script')
    <script>
        $(document).ready(function() {
            const $mainlangList = $(".langList");
            const $langBtn = $(".nocturne-lang__trigger");
            const $langListItem = $mainlangList.children();

            $langListItem.each(function() {
                const $innerItem = $(this);
                const $languageFlag = $innerItem.find(".language_flag");
                const $languageText = $innerItem.find(".language_text");

                $innerItem.on("click", function(e) {
                    $langBtn.find(".language_flag").html($languageFlag.html());
                    $langBtn.attr("title", $languageText.text());
                });
            });
        });
    </script>
@endpush

@push('style')
    <style>
        /* Compact icon-only trigger (flag only, no label/chevron) - matches the rest of the
           sidebar's icon-first nav items instead of a full-width text row. Full language names
           still show inside the dropdown list itself. */
        .nocturne-lang__trigger {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 1px solid var(--nocturne-line, rgba(145, 156, 205, .16));
            border-radius: 13px;
            background: var(--nocturne-raised, #232a47);
            cursor: pointer;
        }
        .nocturne-lang__trigger:hover { border-color: rgba(0, 239, 135, .4); }
        .nocturne-lang .language_flag { flex-shrink: 0; }
        .nocturne-lang__trigger .language_flag img {
            height: 22px;
            width: 22px;
            object-fit: cover;
            border-radius: 50%;
        }

        .language_text {
            color: #ffffff;
            padding: 0 !important;
            line-height: 1.75rem;
        }

        .language-list {
            display: flex;
            align-items: center;
            gap: 12px !important;
            padding: 6px 12px;
            cursor: pointer;
        }

        .language-list .language_flag img {
            height: 20px;
            width: 20px;
            border-radius: 50%;
            object-fit: cover;
        }

        .nocturne-lang .dropdown-menu {
            position: absolute;
            transition: ease-in-out 0.1s;
            opacity: 0;
            visibility: hidden;
            bottom: 100%;
            top: auto;
            margin-bottom: 8px;
            display: unset;
            background: #2a313b;
            width: max-content;
            padding: 7px 0 !important;
            border-radius: 12px;
            border: 1px solid rgb(255 255 255 / 10%);
            box-shadow: 0 14px 30px rgba(0, 0, 0, .35);
        }

        .nocturne-lang .dropdown-menu.show {
            visibility: visible;
            opacity: 1;
        }
    </style>
@endpush
