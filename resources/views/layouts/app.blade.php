<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    <link href='https://fonts.googleapis.com/css?family=Raleway:300,400,600' rel='stylesheet' type='text/css'>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('fa/css/font-awesome.min.css') }}" rel="stylesheet">

    <style type="text/css">
        .pagination {
            margin: 8px 0;
        }
    </style>

    <!-- Scripts -->
    <script>
        window.Laravel = {!! json_encode([
            'csrfToken' => csrf_token(),
        ]) !!};
    </script>
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-default navbar-static-top">
            <div class="container">
                <div class="navbar-header">

                    <!-- Collapsed Hamburger -->
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse">
                        <span class="sr-only">Toggle Navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>

                    <!-- Branding Image -->
                    <a class="navbar-brand" href="{{ url('/') }}">
                        {{ config('app.name', 'Laravel') }}
                    </a>
                </div>

                <div class="collapse navbar-collapse" id="app-navbar-collapse">
                    <!-- Left Side Of Navbar -->
                    <ul class="nav navbar-nav">
                        &nbsp;
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="nav navbar-nav navbar-right">
                        <!-- Authentication Links -->
                        @if (Auth::guest())
                            <li><a href="{{ route('login') }}">Login</a></li>
                            <li><a href="{{ route('register') }}">Register</a></li>
                        @else
                            <li class="dropdown">
                                <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                                    {{ Auth::user()->name }} <span class="caret"></span>
                                </a>

                                <ul class="dropdown-menu" role="menu">
                                    <li>
                                        <a href="{{ route('logout') }}"
                                            onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                            Logout
                                        </a>

                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                            {{ csrf_field() }}
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-lg-offset-2">
                    @if(Session::has('success'))
                    <div class="alert alert-success alert-dismissable hidden-print">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        {!! Session::get('success') !!}
                    </div>
                    @endif
                    @if(Session::has('info'))
                    <div class="alert alert-info alert-dismissable hidden-print">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        {!! Session::get('info') !!}
                    </div>
                    @endif
                    @if(Session::has('warning'))
                    <div class="alert alert-warning alert-dismissable hidden-print">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        {!! Session::get('warning') !!}
                    </div>
                    @endif
                    @if(Session::has('error'))
                    <div class="alert alert-danger alert-dismissable hidden-print">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        {!! Session::get('error') !!}
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @yield('content')
    </div>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}"></script>
    <script type="text/javascript">
        jQuery('#deauthorize').click( function( e ) {
            e.preventDefault()
            var ref = $(this)

            if ( window.confirm( 'Continue with the DeAuthorization Request?' ) ) {
                window.location = ref.attr( 'href' )
            }
        })
    </script>
</body>
</html>
