<!-- meta tags and other links -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ gs()->siteName($pageTitle ?? '') }}</title>

    <link rel="shortcut icon" type="image/png" href="{{ siteFavicon() }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/admin/css/vendor/bootstrap-toggle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}">

    @stack('style-lib')

    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/app.css') }}">
    @stack('style')
    <style>
        /* Winxpot theme: recolor the persistent admin chrome (sidebar/topnav) from the
           default navy/indigo to the dark-green wallet palette used across the site. */
        .sidebar.bg--dark,
        .navbar-wrapper.bg--dark { --color: #0d1225; }
        .sidebar { border-right-color: rgba(133, 142, 175, .18); }
        .navbar-wrapper { border-bottom-color: rgba(133, 142, 175, .18); }

        .sidebar__menu .sidebar-menu-item .side-menu--open,
        .sidebar__menu .sidebar-menu-item.active > a {
            background-color: #00e78a !important;
        }
        .sidebar__menu .sidebar-menu-item.active > a .menu-icon,
        .sidebar__menu .sidebar-menu-item.active > a .menu-title {
            color: #062a23 !important;
        }
        .sidebar__menu .sidebar-menu-item > a:hover .menu-title,
        .sidebar__menu .sidebar-menu-item > a:hover .menu-icon {
            color: #00e78a !important;
        }
        .sidebar__menu .sidebar-submenu .sidebar-menu-item.active > a {
            background-color: rgba(0, 231, 138, .16) !important;
        }
        .sidebar__menu .sidebar-submenu .sidebar-menu-item.active a .menu-icon,
        .sidebar__menu .sidebar-submenu .sidebar-menu-item.active a .menu-title {
            color: #00e78a !important;
        }
        .sidebar__menu .sidebar-submenu .sidebar-menu-item a:hover .menu-icon,
        .sidebar__menu .sidebar-submenu .sidebar-menu-item a:hover .menu-title {
            color: #00e78a !important;
        }
        .navbar-search .navbar-search-field:focus {
            border-color: #00e78a !important;
            box-shadow: 0 0 5px rgba(0, 231, 138, .5) !important;
        }
    </style>
</head>

<body>
    @yield('content')




    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/bootstrap-toggle.min.js') }}"></script>


    @include('partials.notify')
    @stack('script-lib')

    <script src="{{ asset('assets/global/js/nicEdit.js') }}"></script>

    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/app.js') }}"></script>

    {{-- LOAD NIC EDIT --}}
    <script>
        "use strict";
        bkLib.onDomLoaded(function() {
            $(".nicEdit").each(function(index) {
                $(this).attr("id", "nicEditor" + index);
                new nicEditor({
                    fullPanel: true
                }).panelInstance('nicEditor' + index, {
                    hasPanel: true
                });
            });
        });
        (function($) {
            $(document).on('mouseover ', '.nicEdit-main,.nicEdit-panelContain', function() {
                $('.nicEdit-main').focus();
            });

            $('.breadcrumb-nav-open').on('click', function() {
                $(this).toggleClass('active');
                $('.breadcrumb-nav').toggleClass('active');
            });

            $('.breadcrumb-nav-close').on('click', function() {
                $('.breadcrumb-nav').removeClass('active');
            });

            if ($('.topTap').length) {
                $('.breadcrumb-nav-open').removeClass('d-none');
            }
        })(jQuery);
    </script>

    @stack('script')


</body>

</html>
