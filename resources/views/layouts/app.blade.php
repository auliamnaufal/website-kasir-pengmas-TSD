<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Kasir UMKM TSD</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/ti-icons/css/themify-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" />
    <style>
        .scrollable-table-container {
            max-height: 400px;
            /* Adjust height as needed */
            overflow-y: auto;
        }

        .quantity-input {
            width: 100%;
            max-width: 100px;
            /* Set a maximum width to prevent it from being too wide */
            padding: 5px;
            text-align: center;
        }

        @media (max-width: 576px) {
            .quantity-input {
                max-width: 70px;
                /* Adjust the width for smaller screens */
            }
        }

        @media (max-width: 768px) {
            .quantity-input {
                max-width: 80px;
            }
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <!-- Navbar -->
        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
                <a class="navbar-brand brand-logo me-10" href="{{ auth()->user()->home_route ?? route('login') }}">
                    <img src="{{ asset('assets/images/logo.png') }}" class="me-10" alt="logo" />
                </a>
                <a class="navbar-brand brand-logo-mini" href="{{ auth()->user()->home_route ?? route('login') }}">
                    <img src="{{ asset('assets/images/logo-mini.png') }}" alt="logo" />
                </a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="icon-menu"></span>
                </button>
                <ul class="navbar-nav mr-lg-2">
                    <li class="nav-item nav-search d-none d-lg-block">
                    </li>
                </ul>
                <ul class="navbar-nav navbar-nav-right">
                    <!-- Notification dropdown -->
                    <!-- Profile dropdown -->
                    <li class="nav-item nav-profile dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown">
                            <i class="fa fa-user" style="font-size: 1.5rem;"></i>
                        </a>

                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="ti-power-off text-primary"></i> Logout
                                </button>
                            </form>
                        </div>
                    </li>
                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button"
                    data-toggle="offcanvas">
                    <span class="icon-menu"></span>
                </button>
            </div>
        </nav>
        <!-- Navbar end -->

        <div class="container-fluid page-body-wrapper">
            <!-- Sidebar -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    <!-- Sidebar for Admin -->
                    @if(Auth::user()->role === 'admin')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">
                            <i class="icon-grid menu-icon mdi mdi-home"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('kasir.index') }}">
                            <i class="icon-grid menu-icon mdi mdi-cash-multiple"></i>
                            <span class="menu-title">Kasir</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('products.index') }}">
                            <i class="icon-columns menu-icon"></i>
                            <span class="menu-title">Kelola Produk</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('karyawan.index') }}">
                            <i class="icon-head menu-icon mdi mdi-account-multiple"></i>
                            <span class="menu-title">Kelola Pegawai</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('jabatan-config.index') }}">
                            <i class="icon-columns menu-icon mdi mdi-cog"></i>
                            <span class="menu-title">Konfigurasi Jabatan</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('simpan-pinjam.index') }}">
                            <i class="icon-columns menu-icon mdi mdi-bank"></i>
                            <span class="menu-title">Simpan Pinjam</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('simpanan.index') }}">
                            <i class="icon-columns menu-icon mdi mdi-wallet"></i>
                            <span class="menu-title">Simpanan</span>
                        </a>
                    </li>
                    @endif

                    <!-- Sidebar for Karyawan -->
                    @if(Auth::user()->role === 'karyawan')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('kasir.index') }}">
                            <i class="icon-grid menu-icon mdi mdi-cash-multiple"></i>
                            <span class="menu-title">Kasir</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('products.index') }}">
                            <i class="icon-columns menu-icon"></i>
                            <span class="menu-title">Kelola Produk</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>
            <!-- Sidebar end -->

            <!-- Main content -->
            <div class="main-panel">
                <div class="content-wrapper">
                    <!-- Content placeholder -->
                    @yield('content')
                </div>
                <!-- Content ends -->

                <!-- Footer -->
                <footer class="footer">
                    <div class="d-sm-flex justify-content-center justify-content-sm-between">
                        <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">Copyright © 2024 </span>
                        <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Pengabdian Masyarakat Teknologi Sains Data UNAIR</span>
                    </div>
                </footer>
                <!-- Footer end -->
            </div>
        </div>
    </div>

    <!-- JS Scripts -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
    <script src="{{ asset('assets/chart.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ asset('assets/vendors/chart.js/chart.umd.js') }}"></script>
    <script src="{{ asset('assets/js/off-canvas.js') }}"></script>
    <script src="{{ asset('assets/js/hoverable-collapse.js') }}"></script>
    <script src="{{ asset('assets/js/template.js') }}"></script>
    <script src="{{ asset('assets/js/todolist.js') }}"></script>
    <script src="assets/vendors/datatables.net/jquery.dataTables.js"></script>
    <script src="assets/vendors/datatables.net-bs5/dataTables.bootstrap5.js"></script>
    <script src="assets/js/dataTables.select.min.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/jquery.cookie.js" type="text/javascript"></script>
    <script src="assets/js/dashboard.js"></script>
</body>

</html>